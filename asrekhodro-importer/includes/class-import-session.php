<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Batched import with persisted session state for AJAX progress UI.
 */
final class AsreKhodro_Import_Session {

	private const STATE_FILENAME     = '.import-session.json';
	public const CLI_STATE_FILENAME  = '.wpcli-import.json';
	private const NONCE_ACTION       = 'asrekhodro_import_progress';
	private const CATEGORY_BATCH_SIZE = 20;
	private const POST_BATCH_SIZE     = 500;
	private const TAXONOMY_BATCH_SIZE = 25;
	private const AD_BATCH_SIZE       = 25;
	private const VIDEO_BATCH_SIZE    = 10;
	private const REVIEW_BATCH_SIZE   = 10;
	private const MAGAZINE_BATCH_SIZE = 10;
	private const COMMENT_BATCH_SIZE  = 100;

	public static function init(): void {
		add_action( 'wp_ajax_asrekhodro_import_start', array( self::class, 'ajax_start' ) );
		add_action( 'wp_ajax_asrekhodro_import_step', array( self::class, 'ajax_step' ) );
		add_action( 'wp_ajax_asrekhodro_import_background_start', array( self::class, 'ajax_background_start' ) );
		add_action( 'wp_ajax_asrekhodro_import_background_status', array( self::class, 'ajax_background_status' ) );
		add_action( 'wp_ajax_asrekhodro_import_cancel', array( self::class, 'ajax_cancel' ) );
		add_action( 'asrekhodro_import_background_tick', array( self::class, 'background_tick' ), 10, 1 );
	}

	/**
	 * Build a new import session state for WP-CLI (all phases, batched).
	 *
	 * @param array<string, bool> $reset_flags
	 * @return array<string, mixed>
	 */
	public static function create_state( string $import_dir, int $post_batch, array $reset_flags = array() ): array {
		if ( ! is_dir( $import_dir ) ) {
			throw new RuntimeException( 'Import directory not found: ' . $import_dir );
		}

		$totals   = AsreKhodro_Importer::get_file_totals( $import_dir );
		$manifest = self::read_json_file( $import_dir . '/manifest.json' );

		return self::initial_state( $import_dir, $manifest, $totals, $post_batch, $reset_flags );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function load_cli_state( string $import_dir ): ?array {
		return self::load_state( self::cli_state_path( $import_dir ) );
	}

	/**
	 * @param array<string, mixed> $state
	 */
	public static function save_cli_state( array $state ): void {
		$import_dir = (string) ( $state['import_dir'] ?? '' );
		if ( $import_dir === '' ) {
			throw new RuntimeException( 'Import session is missing import_dir.' );
		}

		self::save_state( $state, self::cli_state_path( $import_dir ) );
	}

	public static function delete_cli_state( string $import_dir ): void {
		$path = self::cli_state_path( $import_dir );
		if ( file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	public static function cli_state_path( string $import_dir ): string {
		return trailingslashit( $import_dir ) . self::CLI_STATE_FILENAME;
	}

	public static function ajax_start(): void {
		self::authorize_ajax();

		try {
			$import_dir = ASREKHODRO_IMPORTER_DEFAULT_IMPORT_DIR;
			$totals     = AsreKhodro_Importer::get_file_totals( $import_dir );
			$manifest   = self::read_json_file( $import_dir . '/manifest.json' );
			$post_batch = self::sanitize_post_batch_size(
				isset( $_POST['post_batch_size'] ) ? (int) wp_unslash( $_POST['post_batch_size'] ) : self::POST_BATCH_SIZE
			);

			update_user_meta( get_current_user_id(), 'asrekhodro_import_post_batch_size', $post_batch );

			$reset_flags = self::parse_reset_flags();
			$state       = self::initial_state( $import_dir, $manifest, $totals, $post_batch, $reset_flags );

			self::save_state( $state );

			wp_send_json_success(
				array(
					'token'         => $state['token'],
					'totals'        => $totals,
					'overall_total' => $state['overall_total'],
					'phase'         => (string) $state['phase'],
					'phase_label'   => self::phase_label( (string) $state['phase'], $state ),
				)
			);
		} catch ( Throwable $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ), 500 );
		}
	}

	public static function ajax_cancel(): void {
		self::authorize_ajax();

		$state = self::load_state();
		$counts = array(
			'categories'     => 0,
			'posts'          => 0,
			'videos'         => 0,
			'reviews'        => 0,
			'magazines'      => 0,
			'ads'            => 0,
			'comments'       => 0,
			'external_media' => 0,
		);

		if ( is_array( $state ) ) {
			$counts = array(
				'categories'     => count( $state['category_map'] ?? array() ),
				'posts'          => (int) ( $state['imported_counts']['posts'] ?? count( $state['post_map'] ?? array() ) ),
				'videos'         => count( $state['video_map'] ?? array() ),
				'reviews'        => count( $state['review_map'] ?? array() ),
				'magazines'      => count( $state['magazine_map'] ?? array() ),
				'ads'            => count( $state['ad_map'] ?? array() ),
				'comments'       => count( $state['comment_map'] ?? array() ),
				'external_media' => (int) ( $state['external_media_count'] ?? 0 ),
			);

			self::save_partial_mapping( $state );
		}

		self::delete_state();

		wp_send_json_success(
			array(
				'cancelled' => true,
				'counts'    => $counts,
				'message'   => __( 'Import cancelled. Items already imported remain in WordPress.', 'asrekhodro' ),
			)
		);
	}

	public static function ajax_step(): void {
		self::authorize_ajax();

		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['token'] ) ) : '';
		if ( $token === '' ) {
			wp_send_json_error( array( 'message' => 'Missing import session token.' ), 400 );
		}

		$state = self::load_state();
		if ( ! is_array( $state ) || ( $state['token'] ?? '' ) !== $token ) {
			wp_send_json_error( array( 'message' => 'Import session expired. Please start again.' ), 400 );
		}

		@set_time_limit( max( 600, self::post_batch_size( $state ) * 2 ) );

		try {
			$result = self::run_step( $state );
			self::save_state( $state );

			if ( ! empty( $result['done'] ) ) {
				self::delete_state();
			}

			wp_send_json_success( $result );
		} catch ( Throwable $e ) {
			self::delete_state();
			wp_send_json_error( array( 'message' => $e->getMessage() ), 500 );
		}
	}

	public static function ajax_background_start(): void {
		self::authorize_ajax();

		try {
			$import_dir = ASREKHODRO_IMPORTER_DEFAULT_IMPORT_DIR;
			$totals     = AsreKhodro_Importer::get_file_totals( $import_dir );
			$manifest   = self::read_json_file( $import_dir . '/manifest.json' );
			$post_batch = self::sanitize_post_batch_size(
				isset( $_POST['post_batch_size'] ) ? (int) wp_unslash( $_POST['post_batch_size'] ) : self::POST_BATCH_SIZE
			);

			update_user_meta( get_current_user_id(), 'asrekhodro_import_post_batch_size', $post_batch );

			$reset_flags = self::parse_reset_flags();
			$state       = self::initial_state( $import_dir, $manifest, $totals, $post_batch, $reset_flags );
			$state['background'] = array(
				'enabled'      => true,
				'running'      => false,
				'last_tick_at' => '',
				'error'        => '',
			);

			self::save_state( $state );
			self::schedule_background_tick( (string) $state['token'] );

			wp_send_json_success(
				array(
					'token'         => $state['token'],
					'totals'        => $totals,
					'overall_total' => self::compute_import_work_total( $state ),
					'reset_total'   => is_array( $state['reset']['queue'] ?? null ) ? count( $state['reset']['queue'] ) : 0,
					'phase'         => (string) $state['phase'],
					'phase_label'   => self::phase_label( (string) $state['phase'], $state ),
					'background'    => true,
				)
			);
		} catch ( Throwable $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ), 500 );
		}
	}

	public static function ajax_background_status(): void {
		self::authorize_ajax();

		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['token'] ) ) : '';
		$state = self::load_state();

		if ( ! is_array( $state ) || ( $state['token'] ?? '' ) !== $token ) {
			wp_send_json_success(
				array(
					'done'  => true,
					'label' => __( 'Import session finished or expired.', 'asrekhodro' ),
				)
			);
		}

		if ( ! empty( $state['background']['error'] ) ) {
			wp_send_json_error( array( 'message' => (string) $state['background']['error'] ), 500 );
		}

		if ( empty( $state['background']['running'] ) ) {
			self::schedule_background_tick( $token );
		}

		$done = (string) ( $state['phase'] ?? '' ) === 'done';
		wp_send_json_success(
			self::build_progress_response(
				$state,
				$done,
				$done ? __( 'Import finished.', 'asrekhodro' ) : __( 'Import running in background…', 'asrekhodro' ),
				array( 'background' => true )
			)
		);
	}

	public static function background_tick( string $token ): void {
		@set_time_limit( 0 );

		$state = self::load_state();
		if ( ! is_array( $state ) || ( $state['token'] ?? '' ) !== $token ) {
			return;
		}

		if ( ! empty( $state['background']['running'] ) && time() - (int) ( $state['background']['lock_time'] ?? 0 ) < 180 ) {
			return;
		}

		$state['background']['running']   = true;
		$state['background']['lock_time'] = time();
		self::save_state( $state );

		$deadline = microtime( true ) + (float) apply_filters( 'asrekhodro_import_background_time_budget', 25 );

		try {
			while ( microtime( true ) < $deadline ) {
				$result = self::run_step( $state );
				self::save_state( $state );

				if ( ! empty( $result['done'] ) || (string) ( $state['phase'] ?? '' ) === 'done' ) {
					self::delete_state();
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
	 * @return array<string, mixed>
	 */
	public static function run_step( array &$state ): array {
		$importer = new AsreKhodro_Importer( $state['import_dir'] );
		$importer->hydrate_state( $state );

		$phase  = (string) $state['phase'];
		$offset = (int) $state['offset'];

		if ( $phase === 'done' ) {
			return self::build_progress_response( $state, true, __( 'Import already finished.', 'asrekhodro' ) );
		}

		if ( $phase === 'reset' ) {
			return self::run_reset_step( $state, $importer );
		}

		$label      = '';
		$step_count = 1;

		switch ( $phase ) {
			case 'categories':
				$items      = self::load_categories( $state['import_dir'] );
				$batch_size = self::category_batch_size();
				$total      = count( $items );

				if ( $offset >= $total ) {
					return self::complete_phase(
						$state,
						$importer,
						'posts',
						__( 'Categories finished. Starting posts…', 'asrekhodro' )
					);
				}

				$slice     = array_slice( $items, $offset, $batch_size );
				$processed = count( $slice );
				foreach ( $slice as $category ) {
					$label = $importer->import_one_category( $category );
				}

				$state['offset'] += $processed;
				$step_count       = $processed;
				$label            = self::range_label( __( 'Categories', 'asrekhodro' ), $offset, $processed, $total );
				break;

			case 'posts':
				$batch_size  = self::post_batch_size( $state );
				$chunk_index = (int) ( $state['chunk_index'] ?? 0 );
				$offset      = (int) $state['offset'];
				$cache       = $state['chunk_cache'] ?? null;
				$total       = (int) ( $state['totals']['posts'] ?? 0 );
				$result      = AsreKhodro_Import_Chunks::slice_items(
					$state['import_dir'],
					'posts',
					$chunk_index,
					$offset,
					$batch_size,
					$cache
				);
				$state['chunk_cache'] = $cache;

				if ( self::is_collection_phase_complete( $state, $result ) ) {
					$importer->log( 'Posts imported: ' . self::imported_count( $state, 'posts' ) );
					$importer->log( 'External media thumbnails registered: ' . (int) $state['external_media_count'] );

					return self::complete_phase(
						$state,
						$importer,
						'post_categories',
						__( 'Posts finished. Assigning categories…', 'asrekhodro' )
					);
				}

				$slice     = $result['slice'];
				$processed = count( $slice );
				$label     = $processed > 0 ? $importer->import_posts_batch( $slice ) : '';

				self::apply_chunk_advance( $state, $chunk_index, $offset, $processed, $result['chunk_item_count'], $result['chunk_file_count'] );

				if ( self::is_collection_phase_complete( $state, $result ) ) {
					$importer->log( 'Posts imported: ' . self::imported_count( $state, 'posts' ) );
					$importer->log( 'External media thumbnails registered: ' . (int) $state['external_media_count'] );

					return self::complete_phase(
						$state,
						$importer,
						'post_categories',
						__( 'Posts finished. Assigning categories…', 'asrekhodro' )
					);
				}
				$step_count = max( 1, $processed );
				$label      = self::range_label( __( 'Posts', 'asrekhodro' ), (int) $state['offset'] - $processed, $processed, $total, $label );
				break;

			case 'post_categories':
				$batch_size = self::taxonomy_batch_size();
				$total      = (int) ( $state['totals']['post_categories'] ?? 0 );
				$append     = AsreKhodro_Import_Chunks::has_chunks( $state['import_dir'], 'post-categories' );
				$taxonomy   = self::slice_taxonomy_queue(
					$state,
					$importer,
					'post_categories',
					'post-categories',
					static fn( array $rows ): array => $importer->build_post_category_queue( $rows ),
					$batch_size
				);

				if ( $taxonomy['complete'] ) {
					return self::complete_phase(
						$state,
						$importer,
						'tags',
						__( 'Post categories finished. Assigning tags…', 'asrekhodro' )
					);
				}

				$slice     = $taxonomy['slice'];
				$processed = count( $slice );
				foreach ( $slice as $entry ) {
					$importer->assign_post_categories( (int) $entry['post_id'], $entry['term_ids'], $append );
				}

				$step_count = max( 1, $processed );
				$label      = self::range_label(
					__( 'Post categories', 'asrekhodro' ),
					(int) ( $state['queue_offset'] ?? 0 ) - $processed,
					$processed,
					$total
				);
				break;

			case 'tags':
				$batch_size = self::taxonomy_batch_size();
				$total      = (int) ( $state['totals']['tags'] ?? 0 );
				$append     = AsreKhodro_Import_Chunks::has_chunks( $state['import_dir'], 'tags' );
				$taxonomy   = self::slice_taxonomy_queue(
					$state,
					$importer,
					'tags',
					'tags',
					static fn( array $rows ): array => $importer->build_tag_queue( $rows ),
					$batch_size
				);

				if ( $taxonomy['complete'] ) {
					AsreKhodro_Legacy_Redirects::regenerate_from_tags( $state['import_dir'], $importer );

					return self::complete_phase(
						$state,
						$importer,
						'post_relations',
						__( 'Tags finished. Assigning related posts…', 'asrekhodro' )
					);
				}

				$slice     = $taxonomy['slice'];
				$processed = count( $slice );
				foreach ( $slice as $entry ) {
					$importer->assign_post_tags( (int) $entry['post_id'], $entry['tags'], $append );
				}

				$step_count = max( 1, $processed );
				$label      = self::range_label(
					__( 'Tags', 'asrekhodro' ),
					(int) ( $state['queue_offset'] ?? 0 ) - $processed,
					$processed,
					$total
				);
				break;

			case 'post_relations':
				$batch_size = self::taxonomy_batch_size();
				$total      = (int) ( $state['totals']['post_relations'] ?? 0 );
				$append     = AsreKhodro_Import_Chunks::has_chunks( $state['import_dir'], 'post-relations' );
				$taxonomy   = self::slice_taxonomy_queue(
					$state,
					$importer,
					'post_relations',
					'post-relations',
					static fn( array $rows ): array => $importer->build_post_relation_queue( $rows ),
					$batch_size
				);

				if ( $total === 0 && $taxonomy['complete'] ) {
					return self::complete_phase(
						$state,
						$importer,
						'front_sections',
						__( 'No post relations. Assigning homepage sections…', 'asrekhodro' )
					);
				}

				if ( $taxonomy['complete'] ) {
					return self::complete_phase(
						$state,
						$importer,
						'front_sections',
						__( 'Related posts finished. Assigning homepage sections…', 'asrekhodro' )
					);
				}

				$slice     = $taxonomy['slice'];
				$processed = count( $slice );
				foreach ( $slice as $entry ) {
					$importer->assign_post_relations( (int) $entry['post_id'], $entry['related_post_ids'], $append );
				}

				$step_count = max( 1, $processed );
				$label      = self::range_label(
					__( 'Related posts', 'asrekhodro' ),
					(int) ( $state['queue_offset'] ?? 0 ) - $processed,
					$processed,
					$total
				);
				break;

			case 'front_sections':
				$total = (int) ( $state['totals']['front_sections'] ?? 0 );
				if ( $total <= 0 ) {
					return self::complete_phase(
						$state,
						$importer,
						'videos',
						__( 'No homepage sections. Importing videos…', 'asrekhodro' )
					);
				}

				$importer->import_front_sections();
				$state['imported_counts']['front_sections'] = $total;
				$state['phase_items_done']                  = $total;

				return self::complete_phase(
					$state,
					$importer,
					'videos',
					__( 'Homepage sections finished. Importing videos…', 'asrekhodro' )
				);

			case 'videos':
				$batch_size  = self::video_batch_size();
				$chunk_index = (int) ( $state['chunk_index'] ?? 0 );
				$offset      = (int) $state['offset'];
				$cache       = $state['chunk_cache'] ?? null;
				$total       = (int) ( $state['totals']['videos'] ?? 0 );
				$result      = AsreKhodro_Import_Chunks::slice_items(
					$state['import_dir'],
					'videos',
					$chunk_index,
					$offset,
					$batch_size,
					$cache
				);
				$state['chunk_cache'] = $cache;

				if ( $total === 0 && self::is_collection_phase_complete( $state, $result ) ) {
					return self::complete_phase(
						$state,
						$importer,
						'video_categories',
						__( 'No videos in export. Assigning video categories…', 'asrekhodro' )
					);
				}

				if ( self::is_collection_phase_complete( $state, $result ) ) {
					$importer->log( 'Videos imported: ' . count( $state['video_map'] ) );

					return self::complete_phase(
						$state,
						$importer,
						'video_categories',
						__( 'Videos finished. Assigning video categories…', 'asrekhodro' )
					);
				}

				$slice     = $result['slice'];
				$processed = count( $slice );
				$label     = $processed > 0 ? $importer->import_videos_batch( $slice ) : '';

				self::apply_chunk_advance( $state, $chunk_index, $offset, $processed, $result['chunk_item_count'], $result['chunk_file_count'] );

				if ( self::is_collection_phase_complete( $state, $result ) ) {
					$importer->log( 'Videos imported: ' . count( $state['video_map'] ) );

					return self::complete_phase(
						$state,
						$importer,
						'video_categories',
						__( 'Videos finished. Assigning video categories…', 'asrekhodro' )
					);
				}

				$step_count = max( 1, $processed );
				$label      = self::range_label( __( 'Videos', 'asrekhodro' ), (int) $state['offset'] - $processed, $processed, $total, $label );
				break;

			case 'video_categories':
				$batch_size = self::taxonomy_batch_size();
				$total      = (int) ( $state['totals']['video_categories'] ?? 0 );
				$append     = AsreKhodro_Import_Chunks::has_chunks( $state['import_dir'], 'video-categories' );
				$taxonomy   = self::slice_taxonomy_queue(
					$state,
					$importer,
					'video_categories',
					'video-categories',
					static fn( array $rows ): array => $importer->build_video_category_queue( $rows ),
					$batch_size
				);

				if ( $total === 0 && $taxonomy['complete'] ) {
					return self::complete_phase(
						$state,
						$importer,
						'reviews',
						__( 'No video categories. Importing reviews…', 'asrekhodro' )
					);
				}

				if ( $taxonomy['complete'] ) {
					return self::complete_phase(
						$state,
						$importer,
						'reviews',
						__( 'Video categories finished. Importing reviews…', 'asrekhodro' )
					);
				}

				$slice     = $taxonomy['slice'];
				$processed = count( $slice );
				foreach ( $slice as $entry ) {
					$importer->assign_video_categories( (int) $entry['post_id'], $entry['term_ids'], $append );
				}

				$step_count = max( 1, $processed );
				$label      = self::range_label(
					__( 'Video categories', 'asrekhodro' ),
					(int) ( $state['queue_offset'] ?? 0 ) - $processed,
					$processed,
					$total
				);
				break;

			case 'reviews':
				$batch_size  = self::review_batch_size();
				$chunk_index = (int) ( $state['chunk_index'] ?? 0 );
				$offset      = (int) $state['offset'];
				$cache       = $state['chunk_cache'] ?? null;
				$total       = (int) ( $state['totals']['reviews'] ?? 0 );
				$result      = AsreKhodro_Import_Chunks::slice_items(
					$state['import_dir'],
					'reviews',
					$chunk_index,
					$offset,
					$batch_size,
					$cache
				);
				$state['chunk_cache'] = $cache;

				if ( $total === 0 && self::is_collection_phase_complete( $state, $result ) ) {
					return self::complete_phase(
						$state,
						$importer,
						'magazines',
						__( 'No reviews in export. Importing magazines…', 'asrekhodro' )
					);
				}

				if ( self::is_collection_phase_complete( $state, $result ) ) {
					$importer->log( 'Reviews imported: ' . count( $state['review_map'] ) );

					return self::complete_phase(
						$state,
						$importer,
						'magazines',
						__( 'Reviews finished. Importing magazines…', 'asrekhodro' )
					);
				}

				$slice     = $result['slice'];
				$processed = count( $slice );
				$label     = $processed > 0 ? $importer->import_reviews_batch( $slice ) : '';

				self::apply_chunk_advance( $state, $chunk_index, $offset, $processed, $result['chunk_item_count'], $result['chunk_file_count'] );

				if ( self::is_collection_phase_complete( $state, $result ) ) {
					$importer->log( 'Reviews imported: ' . count( $state['review_map'] ) );

					return self::complete_phase(
						$state,
						$importer,
						'magazines',
						__( 'Reviews finished. Importing magazines…', 'asrekhodro' )
					);
				}

				$step_count = max( 1, $processed );
				$label      = self::range_label( __( 'Reviews', 'asrekhodro' ), (int) $state['offset'] - $processed, $processed, $total, $label );
				break;

			case 'magazines':
				$batch_size  = self::magazine_batch_size();
				$chunk_index = (int) ( $state['chunk_index'] ?? 0 );
				$offset      = (int) $state['offset'];
				$cache       = $state['chunk_cache'] ?? null;
				$total       = (int) ( $state['totals']['magazines'] ?? 0 );
				$result      = AsreKhodro_Import_Chunks::slice_items(
					$state['import_dir'],
					'magazines',
					$chunk_index,
					$offset,
					$batch_size,
					$cache
				);
				$state['chunk_cache'] = $cache;

				if ( $total === 0 && self::is_collection_phase_complete( $state, $result ) ) {
					return self::complete_phase(
						$state,
						$importer,
						'ads',
						__( 'No magazines in export. Importing ads…', 'asrekhodro' )
					);
				}

				if ( self::is_collection_phase_complete( $state, $result ) ) {
					$importer->log( 'Magazines imported: ' . count( $state['magazine_map'] ) );

					return self::complete_phase(
						$state,
						$importer,
						'ads',
						__( 'Magazines finished. Importing ads…', 'asrekhodro' )
					);
				}

				$slice     = $result['slice'];
				$processed = count( $slice );
				$label     = $processed > 0 ? $importer->import_magazines_batch( $slice ) : '';

				self::apply_chunk_advance( $state, $chunk_index, $offset, $processed, $result['chunk_item_count'], $result['chunk_file_count'] );

				if ( self::is_collection_phase_complete( $state, $result ) ) {
					$importer->log( 'Magazines imported: ' . count( $state['magazine_map'] ) );

					return self::complete_phase(
						$state,
						$importer,
						'ads',
						__( 'Magazines finished. Importing ads…', 'asrekhodro' )
					);
				}

				$step_count = max( 1, $processed );
				$label      = self::range_label( __( 'Magazines', 'asrekhodro' ), (int) $state['offset'] - $processed, $processed, $total, $label );
				break;

			case 'ads':
				$batch_size  = self::ad_batch_size();
				$chunk_index = (int) ( $state['chunk_index'] ?? 0 );
				$offset      = (int) $state['offset'];
				$cache       = $state['chunk_cache'] ?? null;
				$total       = (int) ( $state['totals']['ads'] ?? 0 );
				$result      = AsreKhodro_Import_Chunks::slice_items(
					$state['import_dir'],
					'ads',
					$chunk_index,
					$offset,
					$batch_size,
					$cache
				);
				$state['chunk_cache'] = $cache;

				if ( $total === 0 && self::is_collection_phase_complete( $state, $result ) ) {
					return self::complete_phase(
						$state,
						$importer,
						'comments',
						__( 'No ads in export. Starting comments…', 'asrekhodro' )
					);
				}

				if ( self::is_collection_phase_complete( $state, $result ) ) {
					$importer->log( 'Ads imported: ' . count( $state['ad_map'] ) );

					return self::complete_phase(
						$state,
						$importer,
						'comments',
						__( 'Ads finished. Importing comments…', 'asrekhodro' )
					);
				}

				$slice     = $result['slice'];
				$processed = count( $slice );
				$label     = $processed > 0 ? $importer->import_ads_batch( $slice ) : '';

				self::apply_chunk_advance( $state, $chunk_index, $offset, $processed, $result['chunk_item_count'], $result['chunk_file_count'] );

				if ( self::is_collection_phase_complete( $state, $result ) ) {
					$importer->log( 'Ads imported: ' . count( $state['ad_map'] ) );

					return self::complete_phase(
						$state,
						$importer,
						'comments',
						__( 'Ads finished. Importing comments…', 'asrekhodro' )
					);
				}

				$step_count = max( 1, $processed );
				$label      = self::range_label( __( 'Ads', 'asrekhodro' ), (int) $state['offset'] - $processed, $processed, $total, $label );
				break;

			case 'comments':
				$batch_size  = self::comment_batch_size();
				$chunk_index = (int) ( $state['chunk_index'] ?? 0 );
				$offset      = (int) $state['offset'];
				$cache       = $state['chunk_cache'] ?? null;
				$total       = (int) ( $state['totals']['comments'] ?? 0 );
				$result      = AsreKhodro_Import_Chunks::slice_items(
					$state['import_dir'],
					'comments',
					$chunk_index,
					$offset,
					$batch_size,
					$cache
				);
				$state['chunk_cache'] = $cache;

				if ( self::is_collection_phase_complete( $state, $result ) ) {
					self::finish_import( $state, $importer );
					$importer->sync_state( $state );
					return self::build_progress_response(
						$state,
						true,
						__( 'Import finished.', 'asrekhodro' ),
						array(
							'result' => self::build_result( $state ),
						)
					);
				}

				$slice     = $result['slice'];
				$processed = count( $slice );
				if ( $processed > 0 ) {
					$importer->import_comments_batch( $slice );
				}

				self::apply_chunk_advance( $state, $chunk_index, $offset, $processed, $result['chunk_item_count'], $result['chunk_file_count'] );

				if ( self::is_collection_phase_complete( $state, $result ) ) {
					self::finish_import( $state, $importer );
					$importer->sync_state( $state );
					return self::build_progress_response(
						$state,
						true,
						__( 'Import finished.', 'asrekhodro' ),
						array(
							'result' => self::build_result( $state ),
						)
					);
				}

				$from      = (int) $state['offset'] - $processed + 1;
				$to        = min( (int) $state['offset'], $total );
				$label     = sprintf(
					/* translators: 1: first comment number, 2: last comment number, 3: total comments */
					__( 'Comments %1$d–%2$d of %3$d', 'asrekhodro' ),
					max( 1, $from ),
					max( 1, $to ),
					$total
				);

				$step_count = max( 1, $processed );
				break;

			default:
				throw new RuntimeException( 'Unknown import phase: ' . $phase );
		}

		$importer->sync_state( $state );
		$state['overall_done'] += $step_count;
		$state['phase_items_done'] = (int) ( $state['phase_items_done'] ?? 0 ) + $step_count;
		$state['imported_counts'][ $phase ] = (int) ( $state['imported_counts'][ $phase ] ?? 0 ) + $step_count;

		return self::build_progress_response( $state, false, $label );
	}

	/**
	 * @param array<string, mixed> $manifest
	 * @param array<string, int> $totals
	 * @param array<string, bool> $reset_flags
	 * @return array<string, mixed>
	 */
	private static function initial_state( string $import_dir, array $manifest, array $totals, int $post_batch, array $reset_flags = array() ): array {
		$log   = array( 'Starting import from ' . $import_dir );
		$phase = 'categories';
		$reset = null;

		$reset_queue = AsreKhodro_Import_Reset::build_queue( $reset_flags, $totals );
		if ( $reset_queue !== array() ) {
			$phase = 'reset';
			$reset = array(
				'queue'   => $reset_queue,
				'index'   => 0,
				'deleted' => array(),
			);
			$log[] = 'Reset before import: ' . implode(
				', ',
				array_map(
					static fn( string $type ): string => AsreKhodro_Import_Reset::type_label( $type ),
					$reset_queue
				)
			);
		}

		$state = array(
			'token'                => wp_generate_password( 32, false, false ),
			'import_dir'           => $import_dir,
			'post_batch_size'      => $post_batch,
			'phase'                => $phase,
			'offset'               => 0,
			'chunk_index'          => 0,
			'queue_offset'         => 0,
			'phase_items_done'     => 0,
			'category_map'         => array(),
			'post_map'             => array(),
			'comment_map'          => array(),
			'ad_map'               => array(),
			'video_map'            => array(),
			'review_map'           => array(),
			'magazine_map'         => array(),
			'video_category_map'   => array(),
			'log'                  => $log,
			'external_media_count' => 0,
			'queues'               => array(),
			'manifest'             => $manifest,
			'totals'               => $totals,
			'overall_total'        => array_sum( $totals ),
			'overall_done'         => 0,
			'imported_counts'      => array(),
		);

		if ( is_array( $reset ) ) {
			$state['reset'] = $reset;
		}

		return $state;
	}

	/**
	 * @return array<string, bool>
	 */
	private static function parse_reset_flags(): array {
		$raw = isset( $_POST['reset'] ) ? wp_unslash( $_POST['reset'] ) : array();
		if ( ! is_array( $raw ) ) {
			return array();
		}

		return AsreKhodro_Import_Reset::parse_flags( $raw );
	}

	/**
	 * @param array<string, mixed> $state
	 * @return array<string, mixed>
	 */
	private static function run_reset_step( array &$state, AsreKhodro_Importer $importer ): array {
		$reset = is_array( $state['reset'] ?? null ) ? $state['reset'] : null;
		if ( $reset === null || ! is_array( $reset['queue'] ?? null ) || $reset['queue'] === array() ) {
			$state['phase'] = 'categories';
			unset( $state['reset'] );
			$state['phase_items_done'] = 0;

			return self::build_progress_response(
				$state,
				false,
				__( 'Reset finished. Starting categories…', 'asrekhodro' )
			);
		}

		$index   = (int) ( $reset['index'] ?? 0 );
		$queue   = $reset['queue'];
		$current = (string) ( $queue[ $index ] ?? '' );

		if ( $current === '' ) {
			$state['phase']            = 'categories';
			$state['phase_items_done'] = 0;
			unset( $state['reset'] );
			$importer->sync_state( $state );

			return self::build_progress_response(
				$state,
				false,
				__( 'Reset finished. Starting categories…', 'asrekhodro' )
			);
		}

		$deleted = AsreKhodro_Import_Reset::reset_type( $current );
		if ( $deleted > 0 ) {
			$importer->log(
				sprintf(
					'Reset %1$s: removed %2$d item(s) (fast SQL).',
					AsreKhodro_Import_Reset::type_label( $current ),
					$deleted
				)
			);
		} else {
			$importer->log(
				sprintf(
					'Reset %1$s: nothing to remove.',
					AsreKhodro_Import_Reset::type_label( $current )
				)
			);
		}

		$reset['deleted'][ $current ] = $deleted;
		++$index;
		$reset['index'] = $index;

		if ( $index >= count( $queue ) ) {
			$state['phase']            = 'categories';
			$state['phase_items_done'] = 0;
			unset( $state['reset'] );
			$importer->sync_state( $state );

			return self::build_progress_response(
				$state,
				false,
				__( 'Reset finished. Starting categories…', 'asrekhodro' )
			);
		}

		$state['reset']            = $reset;
		$state['phase_items_done'] = $index;
		$importer->sync_state( $state );

		$next  = (string) ( $queue[ $index ] ?? '' );
		$label = sprintf(
			/* translators: 1: content type just reset, 2: next content type */
			__( 'Reset %1$s done. Resetting %2$s…', 'asrekhodro' ),
			AsreKhodro_Import_Reset::type_label( $current ),
			AsreKhodro_Import_Reset::type_label( $next )
		);

		return self::build_progress_response( $state, false, $label );
	}

	private static function schedule_background_tick( string $token ): void {
		if ( ! wp_next_scheduled( 'asrekhodro_import_background_tick', array( $token ) ) ) {
			wp_schedule_single_event( time() + 1, 'asrekhodro_import_background_tick', array( $token ) );
		}

		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}

	private static function range_label( string $phase, int $offset, int $processed, int $total, string $detail = '' ): string {
		$from = $offset + 1;
		$to   = min( $offset + $processed, $total );
		$base = sprintf(
			/* translators: 1: phase name, 2: range start, 3: range end, 4: total */
			__( '%1$s %2$d–%3$d of %4$d', 'asrekhodro' ),
			$phase,
			$from,
			$to,
			$total
		);

		if ( $detail !== '' && $processed === 1 ) {
			return $base . ' — ' . $detail;
		}

		return $base;
	}

	private static function category_batch_size(): int {
		return max( 1, (int) apply_filters( 'asrekhodro_import_category_batch_size', self::CATEGORY_BATCH_SIZE ) );
	}

	private static function post_batch_size( array $state ): int {
		if ( isset( $state['post_batch_size'] ) ) {
			return self::sanitize_post_batch_size( (int) $state['post_batch_size'] );
		}

		return self::sanitize_post_batch_size(
			(int) apply_filters( 'asrekhodro_import_post_batch_size', self::POST_BATCH_SIZE )
		);
	}

	public static function default_post_batch_size(): int {
		return self::sanitize_post_batch_size(
			(int) apply_filters( 'asrekhodro_import_post_batch_size', self::POST_BATCH_SIZE )
		);
	}

	public static function max_post_batch_size(): int {
		return max( 1, (int) apply_filters( 'asrekhodro_import_post_batch_size_max', 10000 ) );
	}

	public static function sanitize_post_batch_size( int $value ): int {
		return max( 1, min( self::max_post_batch_size(), $value ) );
	}

	private static function taxonomy_batch_size(): int {
		return max( 1, (int) apply_filters( 'asrekhodro_import_taxonomy_batch_size', self::TAXONOMY_BATCH_SIZE ) );
	}

	private static function ad_batch_size(): int {
		return max( 1, (int) apply_filters( 'asrekhodro_import_ad_batch_size', self::AD_BATCH_SIZE ) );
	}

	private static function video_batch_size(): int {
		return max( 1, (int) apply_filters( 'asrekhodro_import_video_batch_size', self::VIDEO_BATCH_SIZE ) );
	}

	private static function review_batch_size(): int {
		return max( 1, (int) apply_filters( 'asrekhodro_import_review_batch_size', self::REVIEW_BATCH_SIZE ) );
	}

	private static function magazine_batch_size(): int {
		return max( 1, (int) apply_filters( 'asrekhodro_import_magazine_batch_size', self::MAGAZINE_BATCH_SIZE ) );
	}

	private static function comment_batch_size(): int {
		/**
		 * Filter how many comments are imported per AJAX step.
		 *
		 * @param int $batch_size Default 20.
		 */
		$size = (int) apply_filters( 'asrekhodro_import_comment_batch_size', self::COMMENT_BATCH_SIZE );

		return max( 1, $size );
	}

	/**
	 * @param array<string, mixed> $state
	 * @param array{slice: array<int, array<string, mixed>>, total: int, chunk_item_count: int, chunk_file_count: int} $result
	 */
	private static function is_collection_phase_complete( array $state, array $result ): bool {
		if ( $result['chunk_file_count'] === 0 ) {
			return true;
		}

		if ( $result['slice'] !== array() ) {
			return false;
		}

		$chunk_index = (int) ( $state['chunk_index'] ?? 0 );
		$offset      = (int) ( $state['offset'] ?? 0 );

		return $chunk_index >= $result['chunk_file_count'] - 1 && $offset >= $result['chunk_item_count'];
	}

	/**
	 * @param array<string, mixed> $state
	 */
	private static function apply_chunk_advance(
		array &$state,
		int $chunk_index,
		int $offset,
		int $processed,
		int $chunk_item_count,
		int $chunk_file_count
	): void {
		$advance = AsreKhodro_Import_Chunks::advance_position(
			$chunk_index,
			$offset,
			$processed,
			$chunk_item_count,
			$chunk_file_count
		);

		$state['chunk_index'] = $advance['chunk_index'];
		$state['offset']      = $advance['offset'];

		if ( $advance['clear_cache'] ) {
			unset( $state['chunk_cache'] );
		}
	}

	/**
	 * @param array<string, mixed> $state
	 * @param callable(array<int, array<string, mixed>>): array<int, array<string, mixed>> $build_queue
	 * @return array{slice: array<int, array<string, mixed>>, complete: bool}
	 */
	private static function slice_taxonomy_queue(
		array &$state,
		AsreKhodro_Importer $importer,
		string $phase,
		string $collection,
		callable $build_queue,
		int $batch_size
	): array {
		$queue_key = $phase;

		while ( true ) {
			if (
				! isset( $state['queues'][ $queue_key ] )
				|| (int) ( $state['queue_offset'] ?? 0 ) >= count( $state['queues'][ $queue_key ] )
			) {
				$queue_exhausted = isset( $state['queues'][ $queue_key ] )
					&& (int) ( $state['queue_offset'] ?? 0 ) >= count( $state['queues'][ $queue_key ] );

				unset( $state['queues'][ $queue_key ] );

				if ( ! AsreKhodro_Import_Chunks::has_chunks( $state['import_dir'], $collection ) ) {
					if ( $queue_exhausted ) {
						return array(
							'slice'    => array(),
							'complete' => true,
						);
					}

					if ( $phase === 'post_categories' ) {
						$relations = $importer->read_json( 'post-categories.json' );
						$state['queues'][ $queue_key ] = $importer->build_post_category_queue( $relations );
					} elseif ( $phase === 'tags' ) {
						$tags = $importer->read_json( 'tags.json' );
						$state['queues'][ $queue_key ] = $importer->build_tag_queue( $tags );
					} elseif ( $phase === 'post_relations' ) {
						$relations = $importer->read_json_optional( 'post-relations.json' );
						$state['queues'][ $queue_key ] = $importer->build_post_relation_queue( $relations );
					} else {
						$relations = $importer->read_json_optional( 'video-categories.json' );
						$state['queues'][ $queue_key ] = $importer->build_video_category_queue( $relations );
					}

					$state['queue_offset'] = 0;
					$state['chunk_index']  = 1;
					break;
				}

				$chunk_index = (int) ( $state['chunk_index'] ?? 0 );
				$files       = AsreKhodro_Import_Chunks::list_chunk_files( $state['import_dir'], $collection );

				if ( $chunk_index >= count( $files ) ) {
					return array(
						'slice'    => array(),
						'complete' => true,
					);
				}

				$cache  = null;
				$result = AsreKhodro_Import_Chunks::slice_items(
					$state['import_dir'],
					$collection,
					$chunk_index,
					0,
					PHP_INT_MAX,
					$cache
				);

				$state['chunk_index'] = $chunk_index + 1;
				unset( $state['chunk_cache'] );

				if ( $result['slice'] === array() ) {
					continue;
				}

				$state['queues'][ $queue_key ] = $build_queue( $result['slice'] );
				$state['queue_offset']         = 0;
				break;
			}

			break;
		}

		$queue = $state['queues'][ $queue_key ] ?? array();
		if ( $queue === array() ) {
			return array(
				'slice'    => array(),
				'complete' => true,
			);
		}

		$queue_offset = (int) ( $state['queue_offset'] ?? 0 );
		$slice        = array_slice( $queue, $queue_offset, $batch_size );
		$state['queue_offset'] = $queue_offset + count( $slice );

		$files    = AsreKhodro_Import_Chunks::list_chunk_files( $state['import_dir'], $collection );
		$complete = count( $slice ) === 0
			&& (int) ( $state['queue_offset'] ?? 0 ) >= count( $queue )
			&& ( $files === array() || (int) ( $state['chunk_index'] ?? 0 ) >= count( $files ) );

		return array(
			'slice'    => $slice,
			'complete' => $complete,
		);
	}

	/**
	 * @param array<string, mixed> $state
	 */
	private static function complete_phase(
		array &$state,
		AsreKhodro_Importer $importer,
		string $next_phase,
		string $message
	): array {
		self::clear_phase_queue( $state, (string) $state['phase'] );
		$state['phase']            = $next_phase;
		$state['offset']           = 0;
		$state['chunk_index']      = 0;
		$state['queue_offset']     = 0;
		$state['phase_items_done'] = 0;
		unset( $state['chunk_cache'] );
		$importer->sync_state( $state );

		return self::build_progress_response( $state, false, $message );
	}

	/**
	 * @param array<string, mixed> $state
	 */
	private static function clear_phase_queue( array &$state, string $phase ): void {
		unset( $state['queues'][ $phase ] );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function load_categories( string $import_dir ): array {
		$importer = new AsreKhodro_Importer( $import_dir );
		return $importer->read_categories_sorted();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function load_posts( string $import_dir ): array {
		$importer = new AsreKhodro_Importer( $import_dir );
		return $importer->read_json( 'posts.json' );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function load_comments( string $import_dir ): array {
		$importer = new AsreKhodro_Importer( $import_dir );
		return $importer->read_comments_sorted();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function load_ads( string $import_dir ): array {
		$importer = new AsreKhodro_Importer( $import_dir );
		return $importer->read_ads_sorted();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function load_videos( string $import_dir ): array {
		$importer = new AsreKhodro_Importer( $import_dir );
		return $importer->read_videos_sorted();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function load_reviews( string $import_dir ): array {
		$importer = new AsreKhodro_Importer( $import_dir );
		return $importer->read_reviews_sorted();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function load_magazines( string $import_dir ): array {
		$importer = new AsreKhodro_Importer( $import_dir );
		return $importer->read_magazines_sorted();
	}

	/**
	 * @param array<string, mixed> $state
	 * @param array<string, mixed> $extra
	 * @return array<string, mixed>
	 */
	private static function build_progress_response( array $state, bool $done, string $label, array $extra = array() ): array {
		$metrics = self::compute_progress_metrics( $state );

		return array_merge(
			array(
				'done'          => $done,
				'phase'         => (string) $state['phase'],
				'phase_label'   => self::phase_label( (string) $state['phase'], $state ),
				'phase_done'    => $metrics['phase_done'],
				'phase_total'   => $metrics['phase_total'],
				'overall_done'  => $metrics['overall_done'],
				'overall_total' => $metrics['overall_total'],
				'phase_percent' => $metrics['phase_percent'],
				'overall_percent' => $metrics['overall_percent'],
				'label'         => $label,
				'log_tail'      => array_slice( $state['log'], -8 ),
				'counts'        => array(
					'categories'     => count( $state['category_map'] ?? array() ),
					'posts'          => self::imported_count( $state, 'posts' ),
					'videos'         => count( $state['video_map'] ?? array() ),
					'reviews'        => count( $state['review_map'] ?? array() ),
					'magazines'      => count( $state['magazine_map'] ?? array() ),
					'ads'            => self::imported_count( $state, 'ads' ),
					'comments'       => self::imported_count( $state, 'comments' ),
					'external_media' => (int) $state['external_media_count'],
				),
			),
			$extra
		);
	}

	/**
	 * @param array<string, mixed> $state
	 * @return array{phase_done: int, phase_total: int, overall_done: int, overall_total: int, phase_percent: int, overall_percent: int}
	 */
	private static function compute_progress_metrics( array $state ): array {
		$phase = (string) ( $state['phase'] ?? '' );

		if ( $phase === 'reset' && is_array( $state['reset'] ?? null ) ) {
			$reset       = $state['reset'];
			$queue       = is_array( $reset['queue'] ?? null ) ? $reset['queue'] : array();
			$phase_total = max( 1, count( $queue ) );
			$phase_done  = min( (int) ( $reset['index'] ?? 0 ), $phase_total );
			$overall_total = $phase_total;
			$overall_done  = $phase_done;
		} elseif ( isset( $state['queues'][ $phase ] ) && is_array( $state['queues'][ $phase ] ) ) {
			$phase_total = max( 1, count( $state['queues'][ $phase ] ) );
			$phase_done  = min(
				max(
					(int) ( $state['imported_counts'][ $phase ] ?? 0 ),
					(int) ( $state['queue_offset'] ?? 0 ),
					(int) ( $state['phase_items_done'] ?? 0 )
				),
				$phase_total
			);
			$overall_total = self::compute_import_work_total( $state );
			$overall_done  = self::compute_import_work_done( $state, $phase, $phase_done );
		} else {
			$phase_total = max( 0, (int) ( $state['totals'][ $phase ] ?? 0 ) );
			$phase_done  = min(
				max(
					(int) ( $state['imported_counts'][ $phase ] ?? 0 ),
					(int) ( $state['offset'] ?? 0 ),
					(int) ( $state['phase_items_done'] ?? 0 )
				),
				$phase_total > 0 ? $phase_total : PHP_INT_MAX
			);
			if ( $phase_total === 0 && $phase_done > 0 ) {
				$phase_total = $phase_done;
			}
			$overall_total = self::compute_import_work_total( $state );
			$overall_done  = self::compute_import_work_done( $state, $phase, $phase_done );
		}

		return array(
			'phase_done'        => max( 0, $phase_done ),
			'phase_total'       => max( 0, $phase_total ),
			'overall_done'      => max( 0, min( $overall_done, max( 1, $overall_total ) ) ),
			'overall_total'     => max( 1, $overall_total ),
			'phase_percent'     => self::percent( $phase_done, $phase_total ),
			'overall_percent'   => self::percent( $overall_done, $overall_total ),
		);
	}

	/**
	 * @param array<string, mixed> $state
	 */
	private static function compute_import_work_total( array $state ): int {
		$totals = is_array( $state['totals'] ?? null ) ? $state['totals'] : array();
		$total  = 0;

		foreach ( $totals as $value ) {
			$total += max( 0, (int) $value );
		}

		return max( 1, $total );
	}

	/**
	 * @param array<string, mixed> $state
	 */
	private static function compute_import_work_done( array $state, string $current_phase, int $current_phase_done ): int {
		$order = array(
			'categories',
			'posts',
			'post_categories',
			'tags',
			'post_relations',
			'front_sections',
			'videos',
			'video_categories',
			'reviews',
			'magazines',
			'ads',
			'comments',
		);

		$done       = 0;
		$seen_phase = false;

		foreach ( $order as $phase ) {
			if ( $phase === $current_phase ) {
				$done      += $current_phase_done;
				$seen_phase = true;
				break;
			}

			$done += (int) ( $state['imported_counts'][ $phase ] ?? 0 );
		}

		if ( ! $seen_phase && $current_phase !== 'reset' && $current_phase !== 'done' ) {
			$done += $current_phase_done;
		}

		return $done;
	}

	private static function percent( int $done, int $total ): int {
		if ( $total <= 0 ) {
			return $done > 0 ? 100 : 0;
		}

		return min( 100, max( 0, (int) round( ( $done / $total ) * 100 ) ) );
	}

	/**
	 * @param array<string, mixed> $state
	 */
	private static function advance_phase( array &$state, string $next_phase ): void {
		self::clear_phase_queue( $state, (string) $state['phase'] );
		$state['phase']  = $next_phase;
		$state['offset'] = 0;
	}

	/**
	 * @param array<string, mixed> $state
	 */
	private static function finish_import( array &$state, AsreKhodro_Importer $importer ): void {
		$importer->log( 'Comments imported: ' . count( $state['comment_map'] ) );
		$importer->log( 'Import finished.' );

		$mapping = array(
			'importedAt'  => gmdate( 'c' ),
			'categoryMap' => $state['category_map'],
			'postMap'     => array(),
			'videoMap'    => $state['video_map'] ?? array(),
			'reviewMap'   => $state['review_map'] ?? array(),
			'magazineMap' => $state['magazine_map'] ?? array(),
			'adMap'       => $state['ad_map'] ?? array(),
			'commentMap'  => $state['comment_map'],
		);

		file_put_contents(
			$state['import_dir'] . '/mapping.json',
			wp_json_encode( $mapping, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE )
		);

		$state['phase'] = 'done';
	}

	/**
	 * @param array<string, mixed> $state
	 * @return array<string, mixed>
	 */
	private static function build_result( array $state ): array {
		return array(
			'manifest' => $state['manifest'] ?? array(),
			'log'      => $state['log'],
			'counts'   => array(
				'categories'     => count( $state['category_map'] ?? array() ),
				'posts'          => self::imported_count( $state, 'posts' ),
				'videos'         => count( $state['video_map'] ?? array() ),
				'reviews'        => count( $state['review_map'] ?? array() ),
				'magazines'      => count( $state['magazine_map'] ?? array() ),
				'ads'            => count( $state['ad_map'] ?? array() ),
				'comments'       => count( $state['comment_map'] ?? array() ),
				'external_media' => (int) $state['external_media_count'],
			),
		);
	}

	/**
	 * @param array<string, mixed> $state
	 */
	private static function imported_count( array $state, string $phase ): int {
		if ( isset( $state['imported_counts'][ $phase ] ) ) {
			return (int) $state['imported_counts'][ $phase ];
		}

		if ( $phase === 'posts' ) {
			return count( $state['post_map'] ?? array() );
		}

		if ( $phase === 'ads' ) {
			return count( $state['ad_map'] ?? array() );
		}

		if ( $phase === 'comments' ) {
			return count( $state['comment_map'] ?? array() );
		}

		return 0;
	}

	/**
	 * @param array<string, mixed> $state
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_post_category_queue( array &$state, AsreKhodro_Importer $importer ): array {
		if ( ! isset( $state['queues']['post_categories'] ) ) {
			$relations = $importer->read_json( 'post-categories.json' );
			$state['queues']['post_categories'] = $importer->build_post_category_queue( $relations );
			$state['totals']['post_categories'] = count( $state['queues']['post_categories'] );
			$state['overall_total']               = array_sum( $state['totals'] );
		}

		return $state['queues']['post_categories'];
	}

	/**
	 * @param array<string, mixed> $state
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_tag_queue( array &$state, AsreKhodro_Importer $importer ): array {
		if ( ! isset( $state['queues']['tags'] ) ) {
			$tags = $importer->read_json( 'tags.json' );
			$state['queues']['tags'] = $importer->build_tag_queue( $tags );
			$state['totals']['tags'] = count( $state['queues']['tags'] );
			$state['overall_total']  = array_sum( $state['totals'] );
		}

		return $state['queues']['tags'];
	}

	/**
	 * @param array<string, mixed> $state
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_video_category_queue( array &$state, AsreKhodro_Importer $importer ): array {
		if ( ! isset( $state['queues']['video_categories'] ) ) {
			$relations = $importer->read_json_optional( 'video-categories.json' );
			$state['queues']['video_categories'] = $importer->build_video_category_queue( $relations );
			$state['totals']['video_categories'] = count( $state['queues']['video_categories'] );
			$state['overall_total']               = array_sum( $state['totals'] );
		}

		return $state['queues']['video_categories'];
	}

	private static function phase_label( string $phase, array $state = array() ): string {
		if ( $phase === 'reset' && is_array( $state['reset'] ?? null ) ) {
			$reset   = $state['reset'];
			$index   = (int) ( $reset['index'] ?? 0 );
			$queue   = is_array( $reset['queue'] ?? null ) ? $reset['queue'] : array();
			$current = (string) ( $queue[ $index ] ?? '' );
			if ( $current !== '' ) {
				return sprintf(
					/* translators: %s: content type being reset */
					__( 'Reset: %s', 'asrekhodro' ),
					AsreKhodro_Import_Reset::type_label( $current )
				);
			}

			return __( 'Reset', 'asrekhodro' );
		}

		return match ( $phase ) {
			'categories'      => __( 'Categories', 'asrekhodro' ),
			'posts'           => __( 'Posts', 'asrekhodro' ),
			'post_categories' => __( 'Post categories', 'asrekhodro' ),
			'tags'            => __( 'Tags', 'asrekhodro' ),
			'post_relations'  => __( 'Related posts', 'asrekhodro' ),
			'front_sections'  => __( 'Homepage sections', 'asrekhodro' ),
			'videos'          => __( 'Videos', 'asrekhodro' ),
			'video_categories' => __( 'Video categories', 'asrekhodro' ),
			'reviews'         => __( 'Reviews', 'asrekhodro' ),
			'magazines'       => __( 'Magazines', 'asrekhodro' ),
			'ads'             => __( 'Ads', 'asrekhodro' ),
			'comments'        => __( 'Comments', 'asrekhodro' ),
			'done'            => __( 'Done', 'asrekhodro' ),
			default           => $phase,
		};
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
	private static function save_state( array $state, ?string $path = null ): void {
		$path = $path ?? self::state_path();

		// Never persist large JSON blobs in the session file.
		$persist = $state;
		unset( $persist['post_map'], $persist['queues']['posts'], $persist['queues']['comments'], $persist['queues']['categories'], $persist['chunk_cache'] );

		$json = wp_json_encode( $persist, JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) {
			throw new RuntimeException( 'Failed to encode import session.' );
		}

		if ( false === file_put_contents( $path, $json, LOCK_EX ) ) {
			throw new RuntimeException( 'Failed to save import session.' );
		}
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function load_state( ?string $path = null ): ?array {
		$path = $path ?? self::state_path();
		if ( ! file_exists( $path ) ) {
			return null;
		}

		$data = json_decode( (string) file_get_contents( $path ), true );

		return is_array( $data ) ? $data : null;
	}

	private static function save_partial_mapping( array $state ): void {
		$mapping = array(
			'importedAt'  => gmdate( 'c' ),
			'cancelled'   => true,
			'phase'       => (string) ( $state['phase'] ?? '' ),
			'categoryMap' => $state['category_map'] ?? array(),
			'postMap'     => array(),
			'videoMap'    => $state['video_map'] ?? array(),
			'reviewMap'   => $state['review_map'] ?? array(),
			'magazineMap' => $state['magazine_map'] ?? array(),
			'adMap'       => $state['ad_map'] ?? array(),
			'commentMap'  => $state['comment_map'] ?? array(),
		);

		file_put_contents(
			$state['import_dir'] . '/mapping.json',
			wp_json_encode( $mapping, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE )
		);
	}

	private static function delete_state(): void {
		$path = self::state_path();
		if ( file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function read_json_file( string $path ): array {
		if ( ! file_exists( $path ) ) {
			throw new RuntimeException( 'Missing file: ' . $path );
		}

		$data = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $data ) ) {
			throw new RuntimeException( 'Invalid JSON: ' . $path );
		}

		return $data;
	}
}
