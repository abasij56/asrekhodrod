<?php

namespace AsreKhodro\Theme\CdnServer;

use AsreKhodro\Theme\CdnServer\Config;
use AsreKhodro\Theme\CdnServer\Uploader;
use AsreKhodro\Theme\CdnServer\Watermark;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrate media-library attachments to the configured CDN (newest → oldest).
 *
 * Safety rules:
 * - Never delete the local file.
 * - Only rewrite URLs after the public CDN URL verifies.
 * - Dry-run reports intent without DB/CDN writes.
 */
final class MediaCdnMigrate {

	public const META_MIGRATED      = '_ak_cdn_migrated_url';
	public const META_MIGRATED_FROM = '_ak_cdn_migrated_from';
	public const META_LOCAL_FILE    = '_ak_cdn_local_file_backup';

	/**
	 * @return array{
	 *   done: bool,
	 *   offset: int,
	 *   next_offset: int,
	 *   total: int,
	 *   checked: int,
	 *   uploaded: int,
	 *   rewritten: int,
	 *   skipped: int,
	 *   errors: int,
	 *   items: list<array<string, mixed>>
	 * }
	 */
	public static function process_batch( int $offset, int $limit, bool $dry_run = false ): array {
		$offset = max( 0, $offset );
		$limit  = max( 1, min( 10, $limit ) );
		$total  = self::count_attachments();

		$result = array(
			'done'        => true,
			'offset'      => $offset,
			'next_offset' => $offset,
			'total'       => $total,
			'checked'     => 0,
			'uploaded'    => 0,
			'rewritten'   => 0,
			'skipped'     => 0,
			'errors'      => 0,
			'items'       => array(),
			'dry_run'     => $dry_run,
		);

		if ( $total <= 0 || $offset >= $total ) {
			return $result;
		}

		if ( ! Config::is_configured() ) {
			$result['errors']  = 1;
			$result['items'][] = array(
				'status'  => 'error',
				'message' => __( 'سرور CDN پیکربندی/فعال نیست.', 'asrekhodro' ),
			);
			$result['done']        = true;
			$result['next_offset'] = $offset;

			return $result;
		}

		$ids = get_posts(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'posts_per_page'         => $limit,
				'offset'                 => $offset,
				'orderby'                => 'ID',
				'order'                  => 'DESC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		if ( ! is_array( $ids ) || $ids === array() ) {
			$result['done']        = true;
			$result['next_offset'] = $total;

			return $result;
		}

		foreach ( $ids as $attachment_id ) {
			$item = self::process_attachment( (int) $attachment_id, $dry_run );
			$result['items'][] = $item;
			++$result['checked'];

			$status = (string) ( $item['status'] ?? '' );
			if ( $status === 'uploaded' || $status === 'rewritten' || $status === 'would_upload' ) {
				if ( $status === 'uploaded' || $status === 'would_upload' ) {
					++$result['uploaded'];
				}
				if ( $status === 'rewritten' || ( $status === 'uploaded' && ! empty( $item['rewritten'] ) ) ) {
					++$result['rewritten'];
				}
			} elseif ( $status === 'error' ) {
				++$result['errors'];
			} else {
				++$result['skipped'];
			}
		}

		$next = $offset + count( $ids );
		$result['next_offset'] = $next;
		$result['done']        = $next >= $total;

		return $result;
	}

	public static function count_attachments(): int {
		global $wpdb;

		$count = $wpdb->get_var(
			"SELECT COUNT(1) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit'"
		);

		return max( 0, (int) $count );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function process_attachment( int $attachment_id, bool $dry_run ): array {
		$post = get_post( $attachment_id );
		if ( ! $post instanceof \WP_Post || $post->post_type !== 'attachment' ) {
			return array(
				'id'      => $attachment_id,
				'status'  => 'error',
				'message' => __( 'پیوست یافت نشد.', 'asrekhodro' ),
			);
		}

		$current_url = (string) wp_get_attachment_url( $attachment_id );
		$mime        = (string) get_post_mime_type( $attachment_id );
		$usages      = self::find_usages( $attachment_id, $current_url );
		$base        = array(
			'id'           => $attachment_id,
			'title'        => get_the_title( $attachment_id ),
			'mime'         => $mime,
			'current_url'  => $current_url,
			'edit_link'    => get_edit_post_link( $attachment_id, 'raw' ) ?: '',
			'usages'       => $usages,
			'watermark'    => Watermark::should_apply( $mime ),
			'dry_run'      => $dry_run,
		);

		$already = (string) get_post_meta( $attachment_id, self::META_MIGRATED, true );
		if ( $already !== '' && self::url_is_cdn( $already ) && Uploader::public_url_is_reachable( $already ) ) {
			return array_merge(
				$base,
				array(
					'status'  => 'skipped_migrated',
					'new_url' => $already,
					'message' => __( 'قبلاً به CDN منتقل شده است.', 'asrekhodro' ),
				)
			);
		}

		if ( self::url_is_cdn( $current_url ) && Uploader::public_url_is_reachable( $current_url ) ) {
			if ( ! $dry_run && $already !== $current_url ) {
				update_post_meta( $attachment_id, self::META_MIGRATED, $current_url );
			}

			return array_merge(
				$base,
				array(
					'status'  => 'skipped_on_cdn',
					'new_url' => $current_url,
					'message' => __( 'روی CDN موجود و در دسترس است.', 'asrekhodro' ),
				)
			);
		}

		$local_path = self::resolve_local_file( $attachment_id );
		if ( $local_path === '' ) {
			return array_merge(
				$base,
				array(
					'status'  => 'skipped_no_local',
					'message' => __( 'فایل محلی پیدا نشد؛ از بازنویسی صرف‌نظر شد.', 'asrekhodro' ),
				)
			);
		}

		$original_name = wp_basename( $local_path );
		if ( $dry_run ) {
			return array_merge(
				$base,
				array(
					'status'     => 'would_upload',
					'local_path' => $local_path,
					'message'    => Watermark::should_apply( $mime )
						? __( 'در حالت واقعی: آپلود به CDN با واترمارک + بازنویسی امن.', 'asrekhodro' )
						: __( 'در حالت واقعی: آپلود به CDN + بازنویسی امن.', 'asrekhodro' ),
				)
			);
		}

		$upload = Uploader::handle_local_path( $local_path, $original_name, $mime );
		if ( is_wp_error( $upload ) ) {
			return array_merge(
				$base,
				array(
					'status'  => 'error',
					'message' => $upload->get_error_message(),
					'debug'   => Uploader::get_last_upload_debug(),
				)
			);
		}

		$new_url   = (string) $upload;
		$old_urls  = self::collect_old_urls( $attachment_id, $current_url );
		$rewritten = self::rewrite_attachment_to_cdn( $attachment_id, $new_url, $local_path, $old_urls );

		$content_hits = self::rewrite_content_urls( $old_urls, $new_url );

		$debug = Uploader::get_last_upload_debug();

		return array_merge(
			$base,
			array(
				'status'         => 'uploaded',
				'rewritten'      => $rewritten,
				'new_url'        => $new_url,
				'old_urls'       => $old_urls,
				'content_hits'   => $content_hits,
				'watermark_done' => ! empty( $debug['watermark_applied'] ),
				'message'        => $rewritten
					? __( 'آپلود و بازنویسی انجام شد. فایل محلی حذف نشد.', 'asrekhodro' )
					: __( 'آپلود شد اما بازنویسی پیوست ناقص بود.', 'asrekhodro' ),
				'debug'          => $debug,
			)
		);
	}

	private static function resolve_local_file( int $attachment_id ): string {
		$path = get_attached_file( $attachment_id, true );
		if ( is_string( $path ) && $path !== '' && is_readable( $path ) && is_file( $path ) ) {
			return wp_normalize_path( $path );
		}

		$backup = (string) get_post_meta( $attachment_id, self::META_LOCAL_FILE, true );
		if ( $backup !== '' ) {
			$uploads = wp_get_upload_dir();
			$basedir = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';
			if ( $basedir !== '' ) {
				$candidate = wp_normalize_path( trailingslashit( $basedir ) . ltrim( $backup, '/\\' ) );
				if ( is_readable( $candidate ) && is_file( $candidate ) ) {
					return $candidate;
				}
			}
			if ( is_readable( $backup ) && is_file( $backup ) ) {
				return wp_normalize_path( $backup );
			}
		}

		return '';
	}

	private static function url_is_cdn( string $url ): bool {
		$url  = trim( $url );
		$base = Config::get_public_base_url();
		if ( $url === '' || $base === '' ) {
			return false;
		}

		return str_starts_with( untrailingslashit( $url ), untrailingslashit( $base ) );
	}

	/**
	 * @return list<string>
	 */
	private static function collect_old_urls( int $attachment_id, string $current_url ): array {
		$urls = array();
		if ( $current_url !== '' ) {
			$urls[] = $current_url;
		}

		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $meta ) ) {
			$base_url = $current_url !== '' ? dirname( $current_url ) : '';
			$file     = isset( $meta['file'] ) ? (string) $meta['file'] : '';
			if ( $file !== '' ) {
				$full = wp_get_attachment_url( $attachment_id );
				if ( is_string( $full ) && $full !== '' ) {
					$urls[] = $full;
				}
			}

			$sizes = isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ? $meta['sizes'] : array();
			foreach ( $sizes as $size ) {
				if ( ! is_array( $size ) ) {
					continue;
				}
				$size_file = isset( $size['file'] ) ? (string) $size['file'] : '';
				if ( $size_file === '' || $base_url === '' || $base_url === '.' ) {
					continue;
				}
				$urls[] = trailingslashit( $base_url ) . ltrim( $size_file, '/' );
			}
		}

		$srcset_candidates = array();
		foreach ( array( 'thumbnail', 'medium', 'medium_large', 'large', 'full' ) as $size ) {
			$src = wp_get_attachment_image_src( $attachment_id, $size );
			if ( is_array( $src ) && ! empty( $src[0] ) ) {
				$srcset_candidates[] = (string) $src[0];
			}
		}

		$urls = array_merge( $urls, $srcset_candidates );
		$urls = array_values(
			array_unique(
				array_filter(
					array_map( 'trim', $urls ),
					static fn( string $u ): bool => $u !== ''
				)
			)
		);

		// Longest first so size URLs replace before their parent folder collisions.
		usort(
			$urls,
			static fn( string $a, string $b ): int => strlen( $b ) <=> strlen( $a )
		);

		return $urls;
	}

	/**
	 * @param list<string> $old_urls
	 */
	private static function rewrite_attachment_to_cdn( int $attachment_id, string $new_url, string $local_path, array $old_urls ): bool {
		$relative = get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( is_string( $relative ) && $relative !== '' ) {
			update_post_meta( $attachment_id, self::META_LOCAL_FILE, $relative );
		} else {
			$uploads = wp_get_upload_dir();
			$basedir = isset( $uploads['basedir'] ) ? wp_normalize_path( (string) $uploads['basedir'] ) : '';
			$norm    = wp_normalize_path( $local_path );
			if ( $basedir !== '' && str_starts_with( $norm, trailingslashit( $basedir ) ) ) {
				update_post_meta( $attachment_id, self::META_LOCAL_FILE, ltrim( substr( $norm, strlen( trailingslashit( $basedir ) ) ), '/\\' ) );
			}
		}

		$filename = sanitize_file_name( wp_basename( wp_parse_url( $new_url, PHP_URL_PATH ) ?: 'cdn-file' ) );
		$meta     = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}
		$meta['file'] = $filename;
		if ( ! isset( $meta['sizes'] ) || ! is_array( $meta['sizes'] ) ) {
			$meta['sizes'] = array();
		}
		$meta['sizes']['full'] = array(
			'file'      => $filename,
			'width'     => (int) ( $meta['width'] ?? 0 ),
			'height'    => (int) ( $meta['height'] ?? 0 ),
			'mime-type' => (string) get_post_mime_type( $attachment_id ),
		);

		wp_update_attachment_metadata( $attachment_id, $meta );

		$updated = wp_update_post(
			array(
				'ID'   => $attachment_id,
				'guid' => $new_url,
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			return false;
		}

		// Empty attached file → ExternalMedia/guid path serves the CDN URL; local backup kept in META_LOCAL_FILE.
		delete_post_meta( $attachment_id, '_wp_attached_file' );
		update_post_meta( $attachment_id, '_ak_external_media', '1' );
		update_post_meta( $attachment_id, self::META_MIGRATED, $new_url );
		if ( isset( $old_urls[0] ) ) {
			update_post_meta( $attachment_id, self::META_MIGRATED_FROM, $old_urls[0] );
		}

		return true;
	}

	/**
	 * Replace old media URLs in post_content and common postmeta URL fields.
	 *
	 * @param list<string> $old_urls
	 * @return array{posts:int,meta:int}
	 */
	private static function rewrite_content_urls( array $old_urls, string $new_url ): array {
		global $wpdb;

		$hits = array(
			'posts' => 0,
			'meta'  => 0,
		);

		if ( $old_urls === array() || $new_url === '' ) {
			return $hits;
		}

		foreach ( $old_urls as $old_url ) {
			if ( $old_url === '' || $old_url === $new_url ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$posts_changed = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s",
					$old_url,
					$new_url,
					'%' . $wpdb->esc_like( $old_url ) . '%'
				)
			);
			if ( is_int( $posts_changed ) && $posts_changed > 0 ) {
				$hits['posts'] += $posts_changed;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$meta_changed = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_value LIKE %s AND meta_key NOT IN (%s, %s, %s, %s)",
					$old_url,
					$new_url,
					'%' . $wpdb->esc_like( $old_url ) . '%',
					self::META_MIGRATED,
					self::META_MIGRATED_FROM,
					self::META_LOCAL_FILE,
					'_wp_attachment_metadata'
				)
			);
			if ( is_int( $meta_changed ) && $meta_changed > 0 ) {
				$hits['meta'] += $meta_changed;
			}
		}

		return $hits;
	}

	/**
	 * @return list<array{type:string,id:int,title:string,link:string,edit:string}>
	 */
	private static function find_usages( int $attachment_id, string $current_url ): array {
		global $wpdb;

		$usages = array();
		$seen   = array();

		$parent_id = (int) wp_get_post_parent_id( $attachment_id );
		if ( $parent_id > 0 ) {
			self::append_usage( $usages, $seen, $parent_id, 'parent' );
		}

		// Featured image.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$thumb_posts = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %s LIMIT 20",
				(string) $attachment_id
			)
		);
		foreach ( (array) $thumb_posts as $post_id ) {
			self::append_usage( $usages, $seen, (int) $post_id, 'featured' );
		}

		if ( $current_url !== '' ) {
			$like = '%' . $wpdb->esc_like( $current_url ) . '%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$content_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_status IN ('publish','draft','private','future') AND post_content LIKE %s LIMIT 20",
					$like
				)
			);
			foreach ( (array) $content_ids as $post_id ) {
				self::append_usage( $usages, $seen, (int) $post_id, 'content' );
			}
		}

		return array_values( $usages );
	}

	/**
	 * @param list<array{type:string,id:int,title:string,link:string,edit:string}> $usages
	 * @param array<int, true>                                                      $seen
	 */
	private static function append_usage( array &$usages, array &$seen, int $post_id, string $type ): void {
		if ( $post_id <= 0 || isset( $seen[ $post_id ] ) ) {
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$seen[ $post_id ] = true;
		$usages[]         = array(
			'type'  => $type,
			'id'    => $post_id,
			'title' => get_the_title( $post_id ),
			'link'  => get_permalink( $post_id ) ?: '',
			'edit'  => get_edit_post_link( $post_id, 'raw' ) ?: '',
		);
	}
}
