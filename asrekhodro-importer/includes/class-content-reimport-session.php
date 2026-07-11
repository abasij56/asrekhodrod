<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Batched re-import of post_content only (preserves ak-gallery blocks).
 */
final class AsreKhodro_Content_Reimport_Session {

	private const NONCE_ACTION   = 'asrekhodro_import_progress';
	private const STATE_FILENAME = '.content-reimport-session.json';
	private const CRON_HOOK      = 'asrekhodro_content_reimport_background_tick';

	public static function init(): void {
		add_action( 'wp_ajax_asrekhodro_content_reimport_start', array( self::class, 'ajax_start' ) );
		add_action( 'wp_ajax_asrekhodro_content_reimport_status', array( self::class, 'ajax_status' ) );
		add_action( 'wp_ajax_asrekhodro_content_reimport_cancel', array( self::class, 'ajax_cancel' ) );
		add_action( self::CRON_HOOK, array( self::class, 'background_tick' ), 10, 1 );
	}

	public static function has_post_export( string $import_dir ): bool {
		return AsreKhodro_Import_Chunks::has_chunks( $import_dir, 'posts' )
			|| is_file( trailingslashit( $import_dir ) . 'posts.json' );
	}

	public static function ajax_start(): void {
		self::authorize_ajax();

		try {
			$import_dir = ASREKHODRO_IMPORTER_DEFAULT_IMPORT_DIR;
			if ( ! self::has_post_export( $import_dir ) ) {
				throw new RuntimeException( 'No post export found in: ' . $import_dir );
			}

			$batch = AsreKhodro_Import_Session::sanitize_post_batch_size(
				isset( $_POST['post_batch_size'] ) ? (int) wp_unslash( $_POST['post_batch_size'] ) : AsreKhodro_Import_Session::default_post_batch_size()
			);

			update_user_meta( get_current_user_id(), 'asrekhodro_import_post_batch_size', $batch );

			self::delete_state();

			$state = self::create_state( $import_dir, $batch );
			self::save_state( $state );
			self::schedule_background_tick( (string) $state['token'] );

			wp_send_json_success( self::build_progress_response( $state, false, __( 'Content re-import started.', 'asrekhodro' ) ) );
		} catch ( Throwable $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ), 500 );
		}
	}

	public static function ajax_status(): void {
		self::authorize_ajax();

		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['token'] ) ) : '';
		$state = self::load_state();

		if ( ! is_array( $state ) || (string) ( $state['token'] ?? '' ) !== $token ) {
			wp_send_json_success(
				array(
					'done'  => true,
					'label' => __( 'Content re-import session finished or expired.', 'asrekhodro' ),
				)
			);
		}

		if ( ! empty( $state['background']['error'] ) ) {
			wp_send_json_error( array( 'message' => (string) $state['background']['error'] ), 500 );
		}

		if ( empty( $state['complete'] ) && empty( $state['background']['running'] ) ) {
			self::background_tick( $token );
			$state = self::load_state();
			if ( ! is_array( $state ) || (string) ( $state['token'] ?? '' ) !== $token ) {
				wp_send_json_success(
					array(
						'done'  => true,
						'label' => __( 'Content re-import session finished or expired.', 'asrekhodro' ),
					)
				);
			}
		}

		$done = ! empty( $state['complete'] );
		wp_send_json_success(
			self::build_progress_response(
				$state,
				$done,
				$done
					? self::finished_label( $state )
					: __( 'Content re-import running in background…', 'asrekhodro' )
			)
		);
	}

	public static function ajax_cancel(): void {
		self::authorize_ajax();

		$state = self::load_state();
		if ( is_array( $state ) && ! empty( $state['token'] ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK, array( (string) $state['token'] ) );
		}

		self::delete_state();

		wp_send_json_success(
			array(
				'message' => __( 'Content re-import cancelled. Posts already updated remain unchanged.', 'asrekhodro' ),
			)
		);
	}

	public static function background_tick( string $token ): void {
		@set_time_limit( 0 );

		$state = self::load_state();
		if ( ! is_array( $state ) || (string) ( $state['token'] ?? '' ) !== $token ) {
			return;
		}

		if ( ! empty( $state['background']['running'] ) && time() - (int) ( $state['background']['lock_time'] ?? 0 ) < 180 ) {
			return;
		}

		$state['background']['running']   = true;
		$state['background']['lock_time'] = time();
		self::save_state( $state );

		$deadline = microtime( true ) + (float) apply_filters( 'asrekhodro_content_reimport_time_budget', 25 );

		try {
			while ( microtime( true ) < $deadline ) {
				$done = self::run_batch( $state );
				self::save_state( $state );

				if ( $done ) {
					$state['background']['running'] = false;
					self::save_state( $state );
					return;
				}
			}

			$state['background']['running']      = false;
			$state['background']['last_tick_at'] = gmdate( 'c' );
			self::save_state( $state );
			self::schedule_background_tick( $token );
		} catch ( Throwable $e ) {
			$state['background']['running'] = false;
			$state['background']['error']   = $e->getMessage();
			self::save_state( $state );
		}
	}

	/**
	 * @param array<string, mixed> $state
	 */
	public static function run_batch( array &$state ): bool {
		$import_dir  = (string) ( $state['import_dir'] ?? '' );
		$batch       = max( 1, (int) ( $state['batch_size'] ?? 200 ) );
		$chunk_index = (int) ( $state['chunk_index'] ?? 0 );
		$offset      = (int) ( $state['offset'] ?? 0 );
		$cache       = is_array( $state['chunk_cache'] ?? null ) ? $state['chunk_cache'] : null;

		$result = AsreKhodro_Import_Chunks::slice_items( $import_dir, 'posts', $chunk_index, $offset, $batch, $cache );
		$slice  = $result['slice'];

		if ( $slice === array() ) {
			$total_chunks = max( 1, (int) ( $state['total_chunk_files'] ?? $result['chunk_file_count'] ) );
			$state['complete']          = true;
			$state['current_file']      = '';
			$state['current_chunk_num'] = $total_chunks;
			$state['updated_at']        = gmdate( 'c' );
			$state['log'][]             = sprintf(
				'Finished all chunk files (%1$d/%2$d), %3$d posts scanned.',
				$total_chunks,
				$total_chunks,
				(int) ( $state['processed'] ?? 0 )
			);
			if ( count( $state['log'] ) > 40 ) {
				$state['log'] = array_slice( $state['log'], -40 );
			}
			return true;
		}

		$current_file = self::resolve_chunk_filename( $import_dir, $chunk_index );

		$state['current_file']      = $current_file;
		$state['current_chunk_num'] = $chunk_index + 1;

		if ( $offset === 0 && $current_file !== '' ) {
			$state['log'][] = 'Reading ' . $current_file;
		}

		$importer = new AsreKhodro_Importer( $import_dir );
		$stats    = $importer->reimport_post_content_batch( $slice );

		$advance = AsreKhodro_Import_Chunks::advance_position(
			$chunk_index,
			$offset,
			count( $slice ),
			(int) $result['chunk_item_count'],
			(int) $result['chunk_file_count']
		);

		$state['chunk_index']       = (int) $advance['chunk_index'];
		$state['offset']            = (int) $advance['offset'];
		$state['processed']         = (int) ( $state['processed'] ?? 0 ) + count( $slice );
		$state['updated']           = (int) ( $state['updated'] ?? 0 ) + (int) $stats['updated'];
		$state['skipped_not_found'] = (int) ( $state['skipped_not_found'] ?? 0 ) + (int) $stats['skipped_not_found'];
		$state['skipped_no_embed']  = (int) ( $state['skipped_no_embed'] ?? 0 ) + (int) $stats['skipped_no_embed'];
		$state['skipped_other']     = (int) ( $state['skipped_other'] ?? 0 ) + (int) $stats['skipped_other'];
		$state['errors']            = (int) ( $state['errors'] ?? 0 ) + (int) $stats['errors'];
		$state['updated_at']        = gmdate( 'c' );

		if ( ! empty( $advance['clear_cache'] ) ) {
			unset( $state['chunk_cache'] );
		} else {
			$state['chunk_cache'] = $cache;
		}

		foreach ( array_slice( $stats['log'] ?? array(), -5 ) as $line ) {
			$state['log'][] = (string) $line;
		}
		if ( count( $state['log'] ) > 40 ) {
			$state['log'] = array_slice( $state['log'], -40 );
		}

		return false;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function create_state( string $import_dir, int $batch ): array {
		$total_posts       = AsreKhodro_Import_Chunks::count_collection_rows( $import_dir, 'posts' );
		$total_chunk_files = AsreKhodro_Import_Chunks::count_chunk_files( $import_dir, 'posts' );

		return array(
			'token'              => wp_generate_password( 24, false, false ),
			'import_dir'         => $import_dir,
			'batch_size'         => $batch,
			'chunk_index'        => 0,
			'offset'             => 0,
			'processed'          => 0,
			'updated'            => 0,
			'skipped_not_found'  => 0,
			'skipped_no_embed'   => 0,
			'skipped_other'      => 0,
			'errors'             => 0,
			'total_posts'        => max( 1, $total_posts ),
			'total_chunk_files'  => max( 1, $total_chunk_files ),
			'complete'           => false,
			'started_at'         => gmdate( 'c' ),
			'updated_at'         => gmdate( 'c' ),
			'log'                => array(),
			'current_file'       => '',
			'current_chunk_num'  => 0,
			'background'         => array(
				'running'      => false,
				'lock_time'    => 0,
				'last_tick_at' => '',
				'error'        => '',
			),
		);
	}

	/**
	 * @param array<string, mixed> $state
	 * @return array<string, mixed>
	 */
	private static function build_progress_response( array $state, bool $done, string $label ): array {
		$total   = max( 1, (int) ( $state['total_posts'] ?? 1 ) );
		$done_n  = min( $total, (int) ( $state['processed'] ?? 0 ) );
		$percent = $done ? 100 : (int) round( ( $done_n / $total ) * 100 );

		return array(
			'done'            => $done,
			'label'           => $label,
			'token'           => (string) ( $state['token'] ?? '' ),
			'phase_label'     => __( 'Post content', 'asrekhodro' ),
			'phase_done'      => $done_n,
			'phase_total'     => $total,
			'overall_done'    => $done_n,
			'overall_total'   => $total,
			'phase_percent'   => $percent,
			'overall_percent' => $percent,
			'counts'          => array(
				'updated'           => (int) ( $state['updated'] ?? 0 ),
				'skipped_not_found' => (int) ( $state['skipped_not_found'] ?? 0 ),
				'skipped_no_embed'  => (int) ( $state['skipped_no_embed'] ?? 0 ),
				'skipped_other'     => (int) ( $state['skipped_other'] ?? 0 ),
				'errors'            => (int) ( $state['errors'] ?? 0 ),
			),
			'log_tail'        => array_slice( $state['log'] ?? array(), -8 ),
			'current_file'    => $done ? '' : (string) ( $state['current_file'] ?? '' ),
			'current_chunk'   => $done ? 0 : (int) ( $state['current_chunk_num'] ?? 0 ),
			'total_chunks'    => max( 1, (int) ( $state['total_chunk_files'] ?? 0 ) ),
			'result'          => $done
				? array(
					'updated'           => (int) ( $state['updated'] ?? 0 ),
					'skipped_not_found' => (int) ( $state['skipped_not_found'] ?? 0 ),
					'skipped_no_embed'  => (int) ( $state['skipped_no_embed'] ?? 0 ),
					'skipped_other'     => (int) ( $state['skipped_other'] ?? 0 ),
					'errors'            => (int) ( $state['errors'] ?? 0 ),
					'processed'         => (int) ( $state['processed'] ?? 0 ),
				)
				: null,
		);
	}

	/**
	 * @param array<string, mixed> $state
	 */
	private static function finished_label( array $state ): string {
		$chunks = max( 1, (int) ( $state['total_chunk_files'] ?? 0 ) );

		return sprintf(
			/* translators: 1: number of posts scanned, 2: number of JSON chunk files on disk */
			__( 'Finished — scanned %1$d posts across %2$d chunk file(s).', 'asrekhodro' ),
			(int) ( $state['processed'] ?? 0 ),
			$chunks
		);
	}

	private static function resolve_chunk_filename( string $import_dir, int $chunk_index ): string {
		$files = AsreKhodro_Import_Chunks::list_chunk_files( $import_dir, 'posts' );
		if ( $files === array() ) {
			return 'posts.json';
		}

		if ( $chunk_index < 0 || $chunk_index >= count( $files ) ) {
			return '';
		}

		return 'posts/' . basename( $files[ $chunk_index ] );
	}

	private static function schedule_background_tick( string $token ): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK, array( $token ) ) ) {
			wp_schedule_single_event( time() + 1, self::CRON_HOOK, array( $token ) );
		}

		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}

	private static function authorize_ajax(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
	}

	private static function state_path(): string {
		return ASREKHODRO_IMPORTER_DEFAULT_IMPORT_DIR . '/' . self::STATE_FILENAME;
	}

	/**
	 * @param array<string, mixed> $state
	 */
	private static function save_state( array $state ): void {
		$json = wp_json_encode( $state, JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) {
			throw new RuntimeException( 'Failed to encode content re-import session.' );
		}

		if ( false === file_put_contents( self::state_path(), $json, LOCK_EX ) ) {
			throw new RuntimeException( 'Failed to save content re-import session.' );
		}
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function load_state(): ?array {
		$path = self::state_path();
		if ( ! is_file( $path ) ) {
			return null;
		}

		$data = json_decode( (string) file_get_contents( $path ), true );

		return is_array( $data ) ? $data : null;
	}

	private static function delete_state(): void {
		$path = self::state_path();
		if ( is_file( $path ) ) {
			wp_delete_file( $path );
		}
	}
}
