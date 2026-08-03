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
			if ( in_array( $status, array( 'uploaded', 'rewritten', 'would_upload', 'repaired', 'repaired_content', 'would_repair' ), true ) ) {
				if ( in_array( $status, array( 'uploaded', 'would_upload' ), true ) ) {
					++$result['uploaded'];
				}
				if (
					$status === 'rewritten'
					|| $status === 'repaired'
					|| $status === 'repaired_content'
					|| ( $status === 'uploaded' && ! empty( $item['rewritten'] ) )
				) {
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

		$current_url = self::get_local_or_public_url( $attachment_id );
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
			'optimize'     => ImageOptimize::should_apply( $mime ),
			'dry_run'      => $dry_run,
		);

		$already = (string) get_post_meta( $attachment_id, self::META_MIGRATED, true );
		if ( $already !== '' && self::url_is_cdn( $already ) ) {
			if ( self::attachment_rewrite_is_complete( $attachment_id, $already ) ) {
				// Attachment OK — still repair leftover local URLs in post content/meta.
				if ( $dry_run ) {
					return array_merge(
						$base,
						array(
							'status'  => 'skipped_migrated',
							'new_url' => $already,
							'message' => __( 'قبلاً به CDN منتقل شده است (پیوست کامل است).', 'asrekhodro' ),
						)
					);
				}

				$from       = (string) get_post_meta( $attachment_id, self::META_MIGRATED_FROM, true );
				$old_urls   = self::collect_old_urls( $attachment_id, $from !== '' ? $from : $current_url );
				$old_urls[] = $from;
				$old_urls   = self::normalize_old_urls( $old_urls, $already );
				$hits       = self::rewrite_content_urls( $old_urls, $already );

				if ( ( $hits['posts'] + $hits['meta'] ) > 0 ) {
					return array_merge(
						$base,
						array(
							'status'       => 'repaired_content',
							'new_url'      => $already,
							'content_hits' => $hits,
							'message'      => __( 'پیوست از قبل روی CDN بود؛ لینک‌های باقی‌مانده در محتوا/متا اصلاح شد.', 'asrekhodro' ),
						)
					);
				}

				return array_merge(
					$base,
					array(
						'status'  => 'skipped_migrated',
						'new_url' => $already,
						'message' => __( 'قبلاً به CDN منتقل شده است.', 'asrekhodro' ),
					)
				);
			}

			// Incomplete prior run: meta set but attachment/GUID/content not fully switched.
			if ( $dry_run ) {
				return array_merge(
					$base,
					array(
						'status'  => 'would_repair',
						'new_url' => $already,
						'message' => __( 'در حالت واقعی: تعمیر مهاجرت ناقص (بدون آپلود مجدد) + بازنویسی محتوا + علامت‌گذاری لوکال.', 'asrekhodro' ),
					)
				);
			}

			return self::repair_incomplete_migration( $attachment_id, $already, $current_url, $base );
		}

		if ( self::url_is_cdn( $current_url ) ) {
			if ( ! $dry_run && $already !== $current_url ) {
				update_post_meta( $attachment_id, self::META_MIGRATED, $current_url );
			}

			return array_merge(
				$base,
				array(
					'status'  => 'skipped_on_cdn',
					'new_url' => $current_url,
					'message' => __( 'روی CDN موجود است.', 'asrekhodro' ),
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

		$original_name = self::original_basename_for_upload( $local_path );
		if ( $dry_run ) {
			$parts = array();
			if ( ImageOptimize::should_apply( $mime ) ) {
				$parts[] = __( 'بهینه‌سازی (resize/فشرده)', 'asrekhodro' );
			}
			if ( Watermark::should_apply( $mime ) ) {
				$parts[] = __( 'واترمارک', 'asrekhodro' );
			}
			$pipeline = $parts !== array()
				? implode( ' + ', $parts ) . ' + '
				: '';

			return array_merge(
				$base,
				array(
					'status'     => 'would_upload',
					'local_path' => $local_path,
					'message'    => sprintf(
						/* translators: %s: pipeline steps prefix */
						__( 'در حالت واقعی: %sآپلود به CDN + بازنویسی امن + تغییر نام محلی با -move2cdn.', 'asrekhodro' ),
						$pipeline
					),
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
		$old_urls  = self::normalize_old_urls(
			self::collect_old_urls( $attachment_id, $current_url ),
			$new_url
		);
		$to_rename = self::collect_local_files_for_rename( $attachment_id, $local_path );
		$rewritten = self::rewrite_attachment_to_cdn( $attachment_id, $new_url, $local_path, $old_urls );

		$content_hits = self::rewrite_content_urls( $old_urls, $new_url );

		$debug        = Uploader::get_last_upload_debug();
		$renamed      = array();
		$rename_error = '';

		// Rename only when attachment rewrite completed and public URL resolves to CDN.
		$safe_to_rename = $rewritten && self::attachment_rewrite_is_complete( $attachment_id, $new_url );
		if ( $safe_to_rename ) {
			$rename_result = self::rename_local_files_moved( $to_rename, $attachment_id );
			$renamed       = $rename_result['renamed'];
			$rename_error  = $rename_result['error'];
		}

		$message = __( 'آپلود شد اما بازنویسی پیوست ناقص بود؛ فایل محلی تغییر نام داده نشد.', 'asrekhodro' );
		if ( $rewritten ) {
			if ( ! $safe_to_rename ) {
				$message = __( 'آپلود و بخشی از بازنویسی انجام شد، اما URL پیوست هنوز CDN نیست؛ فایل محلی تغییر نام داده نشد.', 'asrekhodro' );
			} elseif ( $renamed !== array() ) {
				$message = sprintf(
					/* translators: %s: count */
					__( 'آپلود و بازنویسی انجام شد. %s فایل محلی با پسوند -move2cdn علامت‌گذاری شد.', 'asrekhodro' ),
					number_format_i18n( count( $renamed ) )
				);
			} elseif ( $rename_error !== '' ) {
				$message = __( 'آپلود و بازنویسی انجام شد، اما تغییر نام فایل محلی ناموفق بود: ', 'asrekhodro' ) . $rename_error;
			} else {
				$message = __( 'آپلود و بازنویسی انجام شد. فایل محلی برای تغییر نام یافت نشد یا از قبل علامت‌گذاری شده بود.', 'asrekhodro' );
			}
		}

		return array_merge(
			$base,
			array(
				'status'         => 'uploaded',
				'rewritten'      => $rewritten,
				'new_url'        => $new_url,
				'old_urls'       => $old_urls,
				'content_hits'   => $content_hits,
				'local_renamed'  => $renamed,
				'watermark_done' => ! empty( $debug['watermark_applied'] ),
				'optimize_done'  => ! empty( $debug['optimize_applied'] ),
				'message'        => $message,
				'debug'          => $debug,
			)
		);
	}

	/**
	 * Finish a migration that uploaded to CDN but left attachment/content incomplete.
	 *
	 * @param array<string, mixed> $base
	 * @return array<string, mixed>
	 */
	private static function repair_incomplete_migration( int $attachment_id, string $cdn_url, string $current_url, array $base ): array {
		$from      = (string) get_post_meta( $attachment_id, self::META_MIGRATED_FROM, true );
		$seed_url  = $from !== '' ? $from : $current_url;
		$local     = self::resolve_local_file( $attachment_id );
		$old_urls  = self::normalize_old_urls( self::collect_old_urls( $attachment_id, $seed_url ), $cdn_url );
		$to_rename = $local !== '' ? self::collect_local_files_for_rename( $attachment_id, $local ) : array();

		$rewritten = self::rewrite_attachment_to_cdn(
			$attachment_id,
			$cdn_url,
			$local !== '' ? $local : '',
			$old_urls !== array() ? $old_urls : array( $seed_url )
		);

		$content_hits = self::rewrite_content_urls( $old_urls, $cdn_url );

		$renamed      = array();
		$rename_error = '';
		if ( $rewritten && self::attachment_rewrite_is_complete( $attachment_id, $cdn_url ) && $to_rename !== array() ) {
			$rename_result = self::rename_local_files_moved( $to_rename, $attachment_id );
			$renamed       = $rename_result['renamed'];
			$rename_error  = $rename_result['error'];
		}

		$message = __( 'تعمیر مهاجرت ناقص انجام شد.', 'asrekhodro' );
		if ( ! $rewritten ) {
			$message = __( 'تعمیر مهاجرت ناقص ناموفق بود (بازنویسی پیوست).', 'asrekhodro' );
		} elseif ( $renamed !== array() ) {
			$message = sprintf(
				/* translators: %s: count */
				__( 'تعمیر مهاجرت ناقص انجام شد. %s فایل محلی علامت‌گذاری شد.', 'asrekhodro' ),
				number_format_i18n( count( $renamed ) )
			);
		} elseif ( $rename_error !== '' ) {
			$message = __( 'تعمیر پیوست انجام شد، اما تغییر نام لوکال ناموفق بود: ', 'asrekhodro' ) . $rename_error;
		}

		return array_merge(
			$base,
			array(
				'status'        => $rewritten ? 'repaired' : 'error',
				'rewritten'     => $rewritten,
				'new_url'       => $cdn_url,
				'old_urls'      => $old_urls,
				'content_hits'  => $content_hits,
				'local_renamed' => $renamed,
				'message'       => $message,
			)
		);
	}

	/**
	 * Whether attachment points at CDN (meta + external flag + empty local file + CDN guid).
	 */
	private static function attachment_rewrite_is_complete( int $attachment_id, string $expected_cdn_url ): bool {
		if ( ! self::url_is_cdn( $expected_cdn_url ) ) {
			return false;
		}

		if ( (string) get_post_meta( $attachment_id, '_ak_external_media', true ) !== '1' ) {
			return false;
		}

		$attached = get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( is_string( $attached ) && $attached !== '' ) {
			return false;
		}

		$post = get_post( $attachment_id );
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		$guid = is_string( $post->guid ) ? $post->guid : '';

		return self::url_is_cdn( $guid ) && untrailingslashit( $guid ) === untrailingslashit( $expected_cdn_url );
	}

	/**
	 * Prefer raw local uploads URL (unfiltered) for rewrite seeds; fall back to public URL.
	 */
	private static function get_local_or_public_url( int $attachment_id ): string {
		$attached = get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( is_string( $attached ) && $attached !== '' ) {
			$uploads = wp_get_upload_dir();
			$baseurl = isset( $uploads['baseurl'] ) ? (string) $uploads['baseurl'] : '';
			if ( $baseurl !== '' ) {
				return trailingslashit( $baseurl ) . ltrim( str_replace( '\\', '/', $attached ), '/' );
			}
		}

		$from = (string) get_post_meta( $attachment_id, self::META_MIGRATED_FROM, true );
		if ( $from !== '' ) {
			return $from;
		}

		$url = wp_get_attachment_url( $attachment_id );

		return is_string( $url ) ? $url : '';
	}

	/**
	 * @param list<string> $urls
	 * @return list<string>
	 */
	private static function normalize_old_urls( array $urls, string $new_url ): array {
		$out = array();
		foreach ( $urls as $url ) {
			$url = trim( (string) $url );
			if ( $url === '' || $url === $new_url || self::url_is_cdn( $url ) ) {
				continue;
			}
			$out[] = $url;

			// Persian / encoded filename variants commonly stored in content.
			$decoded = rawurldecode( $url );
			if ( $decoded !== $url ) {
				$out[] = $decoded;
			}
			$parts = wp_parse_url( $url );
			if ( is_array( $parts ) && ! empty( $parts['path'] ) && is_string( $parts['path'] ) ) {
				$path     = $parts['path'];
				$encoded  = implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) );
				$encoded  = str_replace( '%2F', '/', $encoded );
				$scheme   = isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '';
				$host     = $parts['host'] ?? '';
				$built    = $scheme . $host . $encoded;
				if ( $built !== $url ) {
					$out[] = $built;
				}
			}
		}

		$out = array_values( array_unique( array_filter( $out ) ) );
		usort(
			$out,
			static fn( string $a, string $b ): int => strlen( $b ) <=> strlen( $a )
		);

		return $out;
	}

	private static function original_basename_for_upload( string $local_path ): string {
		$name = wp_basename( $local_path );
		$name = preg_replace( '/-move2cdn(?=\.[^.]+$)/i', '', $name );
		return is_string( $name ) && $name !== '' ? $name : wp_basename( $local_path );
	}

	/**
	 * Collect main file + intermediate sizes (+ original_image) for rename after success.
	 *
	 * @return list<string>
	 */
	private static function collect_local_files_for_rename( int $attachment_id, string $main_path ): array {
		$files = array();
		$main  = wp_normalize_path( $main_path );
		if ( $main !== '' && is_file( $main ) ) {
			$files[] = $main;
		}

		$dir  = $main !== '' ? dirname( $main ) : '';
		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $meta ) || $dir === '' ) {
			return array_values( array_unique( $files ) );
		}

		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size ) {
				if ( ! is_array( $size ) || empty( $size['file'] ) ) {
					continue;
				}
				$candidate = wp_normalize_path( trailingslashit( $dir ) . wp_basename( (string) $size['file'] ) );
				if ( is_file( $candidate ) ) {
					$files[] = $candidate;
				}
			}
		}

		if ( ! empty( $meta['original_image'] ) && is_string( $meta['original_image'] ) ) {
			$candidate = wp_normalize_path( trailingslashit( $dir ) . wp_basename( $meta['original_image'] ) );
			if ( is_file( $candidate ) ) {
				$files[] = $candidate;
			}
		}

		return array_values( array_unique( $files ) );
	}

	/**
	 * Rename local backups with -move2cdn before the extension (for later disk cleanup).
	 *
	 * @param list<string> $paths
	 * @return array{renamed: list<string>, error: string}
	 */
	private static function rename_local_files_moved( array $paths, int $attachment_id ): array {
		$renamed = array();
		$error   = '';

		foreach ( $paths as $path ) {
			$path = wp_normalize_path( (string) $path );
			if ( $path === '' || ! is_file( $path ) ) {
				continue;
			}

			$target = self::build_moved_local_path( $path );
			if ( $target === '' || $target === $path ) {
				continue;
			}

			if ( is_file( $target ) ) {
				$error = sprintf(
					/* translators: %s: filename */
					__( 'مقصد از قبل وجود دارد: %s', 'asrekhodro' ),
					wp_basename( $target )
				);
				continue;
			}

			$ok = @rename( $path, $target ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( ! $ok ) {
				$error = sprintf(
					/* translators: %s: filename */
					__( 'تغییر نام ناموفق: %s', 'asrekhodro' ),
					wp_basename( $path )
				);
				continue;
			}

			$renamed[] = $target;
		}

		if ( $renamed !== array() ) {
			$uploads        = wp_get_upload_dir();
			$basedir        = isset( $uploads['basedir'] ) ? wp_normalize_path( (string) $uploads['basedir'] ) : '';
			$primary_source = isset( $paths[0] ) ? wp_normalize_path( (string) $paths[0] ) : '';
			$primary_target = $primary_source !== '' ? self::build_moved_local_path( $primary_source ) : '';
			$meta_path      = ( $primary_target !== '' && is_file( $primary_target ) )
				? $primary_target
				: wp_normalize_path( $renamed[0] );

			if ( $basedir !== '' && str_starts_with( $meta_path, trailingslashit( $basedir ) ) ) {
				update_post_meta(
					$attachment_id,
					self::META_LOCAL_FILE,
					ltrim( substr( $meta_path, strlen( trailingslashit( $basedir ) ) ), '/\\' )
				);
			} else {
				update_post_meta( $attachment_id, self::META_LOCAL_FILE, $meta_path );
			}
		}

		return array(
			'renamed' => $renamed,
			'error'   => $error,
		);
	}

	/**
	 * photo.jpg → photo-move2cdn.jpg (idempotent if already marked).
	 */
	private static function build_moved_local_path( string $path ): string {
		$basename = wp_basename( $path );
		$dir      = dirname( $path );

		if ( preg_match( '/-move2cdn(\.[^.]+)?$/i', $basename ) ) {
			return $path;
		}

		$dot = strrpos( $basename, '.' );
		if ( false === $dot || $dot === 0 ) {
			$new_name = $basename . '-move2cdn';
		} else {
			$new_name = substr( $basename, 0, $dot ) . '-move2cdn' . substr( $basename, $dot );
		}

		return wp_normalize_path( trailingslashit( $dir ) . $new_name );
	}

	private static function resolve_local_file( int $attachment_id ): string {
		$candidates = array();

		$path = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$uploads = wp_get_upload_dir();
		$basedir = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';

		if ( is_string( $path ) && $path !== '' && $basedir !== '' ) {
			$candidates[] = wp_normalize_path( trailingslashit( $basedir ) . ltrim( str_replace( '\\', '/', $path ), '/' ) );
		}

		// Unfiltered get_attached_file can still help when meta exists.
		$attached = get_attached_file( $attachment_id, true );
		if ( is_string( $attached ) && $attached !== '' ) {
			$candidates[] = wp_normalize_path( $attached );
		}

		$backup = (string) get_post_meta( $attachment_id, self::META_LOCAL_FILE, true );
		if ( $backup !== '' ) {
			if ( $basedir !== '' ) {
				$candidates[] = wp_normalize_path( trailingslashit( $basedir ) . ltrim( $backup, '/\\' ) );
			}
			$candidates[] = wp_normalize_path( $backup );
		}

		foreach ( $candidates as $candidate ) {
			if ( $candidate === '' ) {
				continue;
			}
			if ( is_readable( $candidate ) && is_file( $candidate ) ) {
				return $candidate;
			}
			$moved = self::build_moved_local_path( $candidate );
			if ( $moved !== $candidate && is_readable( $moved ) && is_file( $moved ) ) {
				return $moved;
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
		global $wpdb;

		$relative = get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( is_string( $relative ) && $relative !== '' ) {
			update_post_meta( $attachment_id, self::META_LOCAL_FILE, $relative );
		} elseif ( $local_path !== '' ) {
			$uploads = wp_get_upload_dir();
			$basedir = isset( $uploads['basedir'] ) ? wp_normalize_path( (string) $uploads['basedir'] ) : '';
			$norm    = wp_normalize_path( $local_path );
			if ( $basedir !== '' && str_starts_with( $norm, trailingslashit( $basedir ) ) ) {
				$rel = ltrim( substr( $norm, strlen( trailingslashit( $basedir ) ) ), '/\\' );
				$rel = preg_replace( '/-move2cdn(?=\.[^.]+$)/i', '', $rel );
				if ( is_string( $rel ) && $rel !== '' ) {
					update_post_meta( $attachment_id, self::META_LOCAL_FILE, $rel );
				}
			}
		}

		$filename = sanitize_file_name( wp_basename( (string) ( wp_parse_url( $new_url, PHP_URL_PATH ) ?: 'cdn-file' ) ) );
		$meta     = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}
		$meta['file'] = $filename;
		if ( ! isset( $meta['sizes'] ) || ! is_array( $meta['sizes'] ) ) {
			$meta['sizes'] = array();
		}
		// Drop local intermediate size files from metadata; full points at CDN basename.
		$meta['sizes'] = array(
			'full' => array(
				'file'      => $filename,
				'width'     => (int) ( $meta['width'] ?? 0 ),
				'height'    => (int) ( $meta['height'] ?? 0 ),
				'mime-type' => (string) get_post_mime_type( $attachment_id ),
			),
		);

		wp_update_attachment_metadata( $attachment_id, $meta );

		// wp_update_post() often refuses to change guid — update directly.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$guid_ok = false !== $wpdb->update(
			$wpdb->posts,
			array( 'guid' => $new_url ),
			array( 'ID' => $attachment_id ),
			array( '%s' ),
			array( '%d' )
		);
		clean_post_cache( $attachment_id );

		if ( ! $guid_ok ) {
			$post = get_post( $attachment_id );
			$guid = ( $post instanceof \WP_Post && is_string( $post->guid ) ) ? $post->guid : '';
			if ( untrailingslashit( $guid ) !== untrailingslashit( $new_url ) ) {
				return false;
			}
		}

		// Empty attached file → ExternalMedia/guid path serves the CDN URL; local backup kept in META_LOCAL_FILE.
		delete_post_meta( $attachment_id, '_wp_attached_file' );
		update_post_meta( $attachment_id, '_ak_external_media', '1' );
		update_post_meta( $attachment_id, self::META_MIGRATED, $new_url );
		if ( isset( $old_urls[0] ) && is_string( $old_urls[0] ) && $old_urls[0] !== '' && ! self::url_is_cdn( $old_urls[0] ) ) {
			update_post_meta( $attachment_id, self::META_MIGRATED_FROM, $old_urls[0] );
		}

		clean_post_cache( $attachment_id );

		return self::attachment_rewrite_is_complete( $attachment_id, $new_url );
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
