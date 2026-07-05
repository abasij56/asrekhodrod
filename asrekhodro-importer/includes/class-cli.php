<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Fast terminal importer for large datasets.
 */
final class AsreKhodro_Importer_CLI {

	private const POSTS_STATE_FILENAME = '.wpcli-post-import.json';

	/**
	 * Run the full batched import (all phases) without browser or WP-Cron.
	 *
	 * ## OPTIONS
	 *
	 * [--batch=<number>]
	 * : Posts per loop. Default 500.
	 *
	 * [--taxonomy-batch=<number>]
	 * : Post categories / tags / relations per loop. Default 200.
	 *
	 * [--import-dir=<path>]
	 * : Import folder. Default wp-content/asrekhodro-import.
	 *
	 * [--reset]
	 * : Delete WP-CLI resume state and start from the beginning.
	 *
	 * [--reset-content]
	 * : Before import, remove previously imported items for every export type that has data (fast SQL).
	 *
	 * ## EXAMPLES
	 *
	 *     wp asrekhodro-import run
	 *     wp asrekhodro-import run --batch=5000 --taxonomy-batch=200 --reset --reset-content
	 *     wp asrekhodro-import run --import-dir="D:/data/asrekhodro-import"
	 *
	 * @param array<int, string> $args
	 * @param array<string, mixed> $assoc_args
	 */
	public function run( array $args, array $assoc_args ): void {
		unset( $args );

		@set_time_limit( 0 );

		$import_dir = $this->resolve_import_dir( $assoc_args );
		$post_batch = AsreKhodro_Import_Session::sanitize_post_batch_size(
			max( 1, (int) ( $assoc_args['batch'] ?? AsreKhodro_Import_Session::default_post_batch_size() ) )
		);
		$taxonomy_batch = max( 1, (int) ( $assoc_args['taxonomy-batch'] ?? 200 ) );
		$force_reset    = ! empty( $assoc_args['reset'] );
		$reset_content  = ! empty( $assoc_args['reset-content'] );

		if ( ! is_dir( $import_dir ) ) {
			WP_CLI::error( 'Import directory not found: ' . $import_dir );
		}

		if ( ! is_file( trailingslashit( $import_dir ) . 'manifest.json' ) ) {
			WP_CLI::error( 'Missing manifest.json in: ' . $import_dir );
		}

		$this->apply_batch_filters( $taxonomy_batch );

		$state = null;
		if ( ! $force_reset ) {
			$state = AsreKhodro_Import_Session::load_cli_state( $import_dir );
		}

		if ( $force_reset ) {
			AsreKhodro_Import_Session::delete_cli_state( $import_dir );
			$state = null;
		}

		if ( $state === null ) {
			$reset_flags = array();
			if ( $reset_content ) {
				$totals      = AsreKhodro_Importer::get_file_totals( $import_dir );
				$reset_flags = AsreKhodro_Import_Reset::default_flags_for_totals( $totals );
				WP_CLI::log( 'Reset before import: ' . implode( ', ', array_keys( $reset_flags ) ) );
			}

			$state = AsreKhodro_Import_Session::create_state( $import_dir, $post_batch, $reset_flags );
			AsreKhodro_Import_Session::save_cli_state( $state );

			WP_CLI::log(
				sprintf(
					'Starting full import from %s (posts/batch=%d, taxonomy/batch=%d, phase=%s)',
					$import_dir,
					$post_batch,
					$taxonomy_batch,
					(string) ( $state['phase'] ?? '' )
				)
			);
		} else {
			$state['post_batch_size'] = $post_batch;
			WP_CLI::log(
				sprintf(
					'Resuming import from %s (phase=%s, chunk=%d, offset=%d)',
					$import_dir,
					(string) ( $state['phase'] ?? '' ),
					(int) ( $state['chunk_index'] ?? 0 ),
					(int) ( $state['offset'] ?? 0 )
				)
			);
		}

		wp_defer_term_counting( true );
		wp_defer_comment_counting( true );
		wp_suspend_cache_invalidation( true );

		$step_number = 0;

		try {
			while ( true ) {
				$result = AsreKhodro_Import_Session::run_step( $state );
				++$step_number;

				AsreKhodro_Import_Session::save_cli_state( $state );

				$label = (string) ( $result['label'] ?? '' );
				if ( $label === '' ) {
					$label = (string) ( $result['phase_label'] ?? $state['phase'] ?? '' );
				}

				WP_CLI::log(
					sprintf(
						'[%s] %s (%d%% overall)',
						(string) ( $result['phase_label'] ?? $state['phase'] ?? '' ),
						$label,
						(int) ( $result['overall_percent'] ?? 0 )
					)
				);

				if ( ! empty( $result['done'] ) || (string) ( $state['phase'] ?? '' ) === 'done' ) {
					AsreKhodro_Import_Session::delete_cli_state( $import_dir );
					$final = is_array( $result['result'] ?? null ) ? $result['result'] : array();

					if ( $final === array() && isset( $result['counts'] ) ) {
						$final = array( 'counts' => $result['counts'], 'log' => $state['log'] ?? array() );
					}

					$this->log_final_counts( $final );
					WP_CLI::success(
						sprintf(
							'Full import complete in %d steps.',
							$step_number
						)
					);
					return;
				}

				if ( function_exists( 'gc_collect_cycles' ) ) {
					gc_collect_cycles();
				}
			}
		} catch ( Throwable $e ) {
			AsreKhodro_Import_Session::save_cli_state( $state );
			WP_CLI::warning(
				'Import paused with error. Fix the issue and run the same command again to resume (without --reset).'
			);
			WP_CLI::error( $e->getMessage() );
		} finally {
			wp_suspend_cache_invalidation( false );
			wp_defer_comment_counting( false );
			wp_defer_term_counting( false );
		}
	}

	/**
	 * Import news posts from chunked JSON without browser/admin timeouts.
	 *
	 * ## OPTIONS
	 *
	 * [--batch=<number>]
	 * : Posts per loop. Default 1000.
	 *
	 * [--import-dir=<path>]
	 * : Import folder. Default wp-content/asrekhodro-import.
	 *
	 * [--reset]
	 * : Delete WP-CLI resume state and start posts from the first chunk.
	 *
	 * ## EXAMPLES
	 *
	 *     wp asrekhodro-import posts --batch=1000
	 *     wp asrekhodro-import posts --batch=2000 --reset
	 *
	 * @param array<int, string> $args
	 * @param array<string, mixed> $assoc_args
	 */
	public function posts( array $args, array $assoc_args ): void {
		unset( $args );

		@set_time_limit( 0 );

		$import_dir = isset( $assoc_args['import-dir'] )
			? rtrim( (string) $assoc_args['import-dir'], "/\\" )
			: ASREKHODRO_IMPORTER_DEFAULT_IMPORT_DIR;
		$batch      = max( 1, (int) ( $assoc_args['batch'] ?? 1000 ) );
		$state_file = trailingslashit( $import_dir ) . self::POSTS_STATE_FILENAME;

		if ( ! is_dir( $import_dir ) ) {
			WP_CLI::error( 'Import directory not found: ' . $import_dir );
		}

		if ( ! empty( $assoc_args['reset'] ) && is_file( $state_file ) ) {
			unlink( $state_file );
		}

		$state = $this->load_posts_state( $state_file );
		WP_CLI::log(
			sprintf(
				'Importing posts from %s (batch=%d, chunk=%d, offset=%d)',
				$import_dir,
				$batch,
				(int) $state['chunk_index'],
				(int) $state['offset']
			)
		);

		wp_defer_term_counting( true );
		wp_defer_comment_counting( true );
		wp_suspend_cache_invalidation( true );

		try {
			while ( true ) {
				$result = $this->read_posts_batch(
					$import_dir,
					(int) $state['chunk_index'],
					(int) $state['offset'],
					$batch
				);

				$slice = $result['slice'];
				if ( $slice === array() ) {
					break;
				}

				$importer = new AsreKhodro_Importer( $import_dir );
				$label    = $importer->import_posts_batch( $slice );
				$count    = count( $slice );

				$state['chunk_index'] = $result['next_chunk_index'];
				$state['offset']      = $result['next_offset'];
				$state['imported']    = (int) $state['imported'] + $count;
				$state['updated_at']  = gmdate( 'c' );

				$this->save_posts_state( $state_file, $state );

				WP_CLI::log(
					sprintf(
						'+%d posts (total this run/resume: %d, next chunk=%d offset=%d)%s',
						$count,
						(int) $state['imported'],
						(int) $state['chunk_index'],
						(int) $state['offset'],
						$label !== '' ? ' - ' . $label : ''
					)
				);

				if ( function_exists( 'gc_collect_cycles' ) ) {
					gc_collect_cycles();
				}
			}
		} finally {
			wp_suspend_cache_invalidation( false );
			wp_defer_comment_counting( false );
			wp_defer_term_counting( false );
		}

		$state['complete']   = true;
		$state['updated_at'] = gmdate( 'c' );
		$this->save_posts_state( $state_file, $state );

		WP_CLI::success(
			sprintf(
				'Posts import complete. Imported/updated in this resume state: %d',
				(int) $state['imported']
			)
		);
	}

	/**
	 * Read a CLI batch across multiple posts/posts-###.json files.
	 *
	 * @return array{slice: array<int, array<string, mixed>>, next_chunk_index: int, next_offset: int}
	 */
	private function read_posts_batch( string $import_dir, int $chunk_index, int $offset, int $limit ): array {
		$files = AsreKhodro_Import_Chunks::list_chunk_files( $import_dir, 'posts' );

		if ( $files === array() ) {
			$cache = null;
			$result = AsreKhodro_Import_Chunks::slice_items(
				$import_dir,
				'posts',
				0,
				$offset,
				$limit,
				$cache
			);

			return array(
				'slice'            => $result['slice'],
				'next_chunk_index' => 0,
				'next_offset'      => $offset + count( $result['slice'] ),
			);
		}

		$slice = array();
		$remaining = $limit;
		$current_chunk = max( 0, $chunk_index );
		$current_offset = max( 0, $offset );

		while ( $remaining > 0 && $current_chunk < count( $files ) ) {
			$items = AsreKhodro_Import_Chunks::read_chunk_file( $files[ $current_chunk ] );
			$chunk_count = count( $items );

			if ( $current_offset >= $chunk_count ) {
				++$current_chunk;
				$current_offset = 0;
				continue;
			}

			$take = min( $remaining, $chunk_count - $current_offset );
			$part = array_slice( $items, $current_offset, $take );
			array_push( $slice, ...$part );

			$remaining -= count( $part );
			$current_offset += count( $part );

			if ( $current_offset >= $chunk_count ) {
				++$current_chunk;
				$current_offset = 0;
			}
		}

		return array(
			'slice'            => $slice,
			'next_chunk_index' => $current_chunk,
			'next_offset'      => $current_offset,
		);
	}

	/**
	 * @return array{chunk_index:int,offset:int,imported:int,complete:bool,started_at:string,updated_at:string}
	 */
	private function load_posts_state( string $state_file ): array {
		if ( is_file( $state_file ) ) {
			$data = json_decode( (string) file_get_contents( $state_file ), true );
			if ( is_array( $data ) ) {
				return array(
					'chunk_index' => max( 0, (int) ( $data['chunk_index'] ?? 0 ) ),
					'offset'      => max( 0, (int) ( $data['offset'] ?? 0 ) ),
					'imported'    => max( 0, (int) ( $data['imported'] ?? 0 ) ),
					'complete'    => (bool) ( $data['complete'] ?? false ),
					'started_at'  => (string) ( $data['started_at'] ?? gmdate( 'c' ) ),
					'updated_at'  => (string) ( $data['updated_at'] ?? gmdate( 'c' ) ),
				);
			}
		}

		return array(
			'chunk_index' => 0,
			'offset'      => 0,
			'imported'    => 0,
			'complete'    => false,
			'started_at'  => gmdate( 'c' ),
			'updated_at'  => gmdate( 'c' ),
		);
	}

	/**
	 * @param array<string, mixed> $state
	 */
	private function save_posts_state( string $state_file, array $state ): void {
		file_put_contents(
			$state_file,
			wp_json_encode( $state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE )
		);
	}

	/**
	 * @param array<string, mixed> $state
	 * @param array{slice: array<int, array<string, mixed>>, total: int, chunk_item_count: int, chunk_file_count: int} $result
	 */
	private function is_complete( array $state, array $result ): bool {
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
	 * @param array<string, mixed> $assoc_args
	 */
	private function resolve_import_dir( array $assoc_args ): string {
		return isset( $assoc_args['import-dir'] )
			? rtrim( (string) $assoc_args['import-dir'], "/\\" )
			: ASREKHODRO_IMPORTER_DEFAULT_IMPORT_DIR;
	}

	private function apply_batch_filters( int $taxonomy_batch ): void {
		add_filter(
			'asrekhodro_import_taxonomy_batch_size',
			static function () use ( $taxonomy_batch ): int {
				return $taxonomy_batch;
			}
		);
	}

	/**
	 * @param array<string, mixed> $result
	 */
	private function log_final_counts( array $result ): void {
		$counts = is_array( $result['counts'] ?? null ) ? $result['counts'] : array();
		if ( $counts === array() ) {
			return;
		}

		WP_CLI::log(
			sprintf(
				'Categories: %d | Posts: %d | Videos: %d | Reviews: %d | Magazines: %d | Ads: %d | Comments: %d | External media: %d',
				(int) ( $counts['categories'] ?? 0 ),
				(int) ( $counts['posts'] ?? 0 ),
				(int) ( $counts['videos'] ?? 0 ),
				(int) ( $counts['reviews'] ?? 0 ),
				(int) ( $counts['magazines'] ?? 0 ),
				(int) ( $counts['ads'] ?? 0 ),
				(int) ( $counts['comments'] ?? 0 ),
				(int) ( $counts['external_media'] ?? 0 )
			)
		);
	}
}

WP_CLI::add_command( 'asrekhodro-import', AsreKhodro_Importer_CLI::class );
