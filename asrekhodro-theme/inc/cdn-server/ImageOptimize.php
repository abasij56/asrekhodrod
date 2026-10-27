<?php

namespace AsreKhodro\Theme\CdnServer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resize + recompress images before watermark (media library and CDN).
 *
 * Settings are read via get_option() — never get_field() — to avoid ACF re-entry.
 */
final class ImageOptimize {

	private const SUPPORTED_MIMES = array(
		'image/jpeg',
		'image/png',
		'image/webp',
	);

	private const DEFAULT_MAX_WIDTH        = 1200;
	private const DEFAULT_JPEG_QUALITY     = 90;
	private const DEFAULT_WEBP_QUALITY     = 88;
	private const DEFAULT_PNG_COMPRESSION  = 6;

	public static function init(): void {
		// Before Watermark (priority 20) so the stamp lands on the resized file.
		add_filter( 'wp_handle_upload', array( self::class, 'filter_media_upload' ), 10 );
		add_filter( 'wp_handle_sideload', array( self::class, 'filter_media_upload' ), 10 );
	}

	/**
	 * Optimize the uploaded original in place so watermark and thumbnails use it.
	 *
	 * @param array<string, string> $upload
	 * @return array<string, string>
	 */
	public static function filter_media_upload( array $upload ): array {
		if ( ! empty( $upload['error'] ) ) {
			return $upload;
		}

		$file = isset( $upload['file'] ) ? (string) $upload['file'] : '';
		$mime = isset( $upload['type'] ) ? strtolower( (string) $upload['type'] ) : '';

		if ( $file === '' || ! is_readable( $file ) || ! self::should_apply( $mime ) ) {
			return $upload;
		}

		$optimized = self::apply( $file, $mime );
		if ( is_wp_error( $optimized ) ) {
			self::log_upload( $file, 'failed_' . $optimized->get_error_code() );
			return $upload;
		}

		if ( ! is_string( $optimized ) || $optimized === '' || $optimized === $file ) {
			self::log_upload( $file, 'unchanged' );
			return $upload;
		}

		$replaced = self::replace_file( $file, $optimized );
		self::cleanup_temp( $optimized );
		self::log_upload( $file, $replaced ? 'applied' : 'failed_replace' );

		return $upload;
	}

	/**
	 * @return array{
	 *   enabled: bool,
	 *   max_width: int,
	 *   jpeg_quality: int,
	 *   webp_quality: int,
	 *   png_compression: int
	 * }|null Null when feature is disabled.
	 */
	public static function settings(): ?array {
		$enabled = get_option( 'options_cdn_image_optimize_enabled', 1 );
		if ( ! (bool) $enabled ) {
			return null;
		}

		$max_width = (int) get_option( 'options_cdn_image_max_width', self::DEFAULT_MAX_WIDTH );
		if ( $max_width < 100 ) {
			$max_width = self::DEFAULT_MAX_WIDTH;
		}
		$max_width = min( 5000, $max_width );

		$jpeg = (int) get_option( 'options_cdn_image_jpeg_quality', self::DEFAULT_JPEG_QUALITY );
		$webp = (int) get_option( 'options_cdn_image_webp_quality', self::DEFAULT_WEBP_QUALITY );
		$png  = (int) get_option( 'options_cdn_image_png_compression', self::DEFAULT_PNG_COMPRESSION );

		return array(
			'enabled'         => true,
			'max_width'       => $max_width,
			'jpeg_quality'    => max( 60, min( 95, $jpeg ?: self::DEFAULT_JPEG_QUALITY ) ),
			'webp_quality'    => max( 60, min( 95, $webp ?: self::DEFAULT_WEBP_QUALITY ) ),
			'png_compression' => max( 0, min( 9, $png ) ),
		);
	}

	public static function should_apply( string $mime ): bool {
		return self::settings() !== null
			&& in_array( strtolower( $mime ), self::SUPPORTED_MIMES, true );
	}

	/**
	 * Resize (if wider than max) then recompress. Returns original path when unchanged.
	 *
	 * @return string|\WP_Error Path to use for next pipeline step.
	 */
	public static function apply( string $source_path, string $mime ): string|\WP_Error {
		$settings = self::settings();
		if ( $settings === null ) {
			return $source_path;
		}

		if ( ! is_readable( $source_path ) ) {
			return new \WP_Error( 'ak_optimize_source', __( 'فایل منبع برای بهینه‌سازی خوانا نیست.', 'asrekhodro' ) );
		}

		$mime = strtolower( $mime );
		if ( ! in_array( $mime, self::SUPPORTED_MIMES, true ) ) {
			return $source_path;
		}

		if ( extension_loaded( 'imagick' ) && class_exists( '\Imagick' ) ) {
			return self::apply_with_imagick( $source_path, $mime, $settings );
		}

		if ( function_exists( 'imagecreatetruecolor' ) ) {
			return self::apply_with_gd( $source_path, $mime, $settings );
		}

		return new \WP_Error( 'ak_optimize_no_engine', __( 'برای بهینه‌سازی تصویر به GD یا Imagick در PHP نیاز است.', 'asrekhodro' ) );
	}

	/**
	 * @param array{max_width: int, jpeg_quality: int, webp_quality: int, png_compression: int} $settings
	 * @return string|\WP_Error
	 */
	private static function apply_with_imagick( string $source_path, string $mime, array $settings ): string|\WP_Error {
		try {
			$image = new \Imagick( $source_path );
			$did_orient = self::imagick_auto_orient( $image );
			$image->setImageColorspace( \Imagick::COLORSPACE_SRGB );

			$width        = (int) $image->getImageWidth();
			$needs_resize = $width > $settings['max_width'];

			if ( $needs_resize ) {
				$image->resizeImage( $settings['max_width'], 0, \Imagick::FILTER_LANCZOS, 1, true );
			}

			$image->stripImage();

			if ( $mime === 'image/jpeg' ) {
				$image->setImageFormat( 'jpeg' );
				$image->setImageCompression( \Imagick::COMPRESSION_JPEG );
				$image->setImageCompressionQuality( $settings['jpeg_quality'] );
				if ( method_exists( $image, 'setInterlaceScheme' ) ) {
					$image->setInterlaceScheme( \Imagick::INTERLACE_JPEG );
				}
			} elseif ( $mime === 'image/webp' ) {
				$image->setImageFormat( 'webp' );
				$image->setImageCompressionQuality( $settings['webp_quality'] );
			} else {
				$image->setImageFormat( 'png' );
				// Imagick PNG: quality 0–100 maps roughly; use compression level via define.
				$image->setOption( 'png:compression-level', (string) $settings['png_compression'] );
			}

			$output = self::temp_output_path( $mime );
			$image->writeImage( $output );
			$image->clear();

			return self::prefer_smaller_or_resized( $source_path, $output, $needs_resize || $did_orient );
		} catch ( \Throwable $exception ) {
			return new \WP_Error(
				'ak_optimize_imagick_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'بهینه‌سازی تصویر ناموفق بود: %s', 'asrekhodro' ),
					$exception->getMessage()
				)
			);
		}
	}

	/**
	 * @param array{max_width: int, jpeg_quality: int, webp_quality: int, png_compression: int} $settings
	 * @return string|\WP_Error
	 */
	private static function apply_with_gd( string $source_path, string $mime, array $settings ): string|\WP_Error {
		$image = self::gd_load( $source_path, $mime );
		if ( $image === null ) {
			return new \WP_Error( 'ak_optimize_gd_load', __( 'بارگذاری تصویر برای بهینه‌سازی ناموفق بود.', 'asrekhodro' ) );
		}

		$oriented = self::gd_apply_exif_orientation( $image, $source_path, $mime );
		$image    = $oriented['image'];

		$src_w        = imagesx( $image );
		$src_h        = imagesy( $image );
		$needs_resize = $src_w > $settings['max_width'];

		if ( $needs_resize ) {
			$target_w = $settings['max_width'];
			$target_h = max( 1, (int) round( $src_h * ( $target_w / $src_w ) ) );
			$resized  = imagecreatetruecolor( $target_w, $target_h );

			if ( $mime === 'image/png' || $mime === 'image/webp' ) {
				imagealphablending( $resized, false );
				imagesavealpha( $resized, true );
				$transparent = imagecolorallocatealpha( $resized, 0, 0, 0, 127 );
				imagefilledrectangle( $resized, 0, 0, $target_w, $target_h, $transparent );
			}

			imagecopyresampled( $resized, $image, 0, 0, 0, 0, $target_w, $target_h, $src_w, $src_h );
			imagedestroy( $image );
			$image = $resized;
		}

		$output = self::temp_output_path( $mime );
		$saved  = self::gd_save( $image, $output, $mime, $settings );
		imagedestroy( $image );

		if ( ! $saved ) {
			return new \WP_Error( 'ak_optimize_gd_save', __( 'ذخیره تصویر بهینه‌شده ناموفق بود.', 'asrekhodro' ) );
		}

		return self::prefer_smaller_or_resized( $source_path, $output, $needs_resize || $oriented['changed'] );
	}

	/**
	 * Keep optimized file when smaller, resized, or re-oriented.
	 * Otherwise discard temp and keep the original.
	 */
	private static function prefer_smaller_or_resized( string $source_path, string $output_path, bool $needs_resize ): string {
		if ( ! is_file( $output_path ) ) {
			return $source_path;
		}

		$out_size = (int) filesize( $output_path );
		$src_size = (int) filesize( $source_path );

		if ( $out_size <= 0 ) {
			wp_delete_file( $output_path );

			return $source_path;
		}

		if ( $needs_resize || $out_size < $src_size ) {
			return $output_path;
		}

		wp_delete_file( $output_path );

		return $source_path;
	}

	/**
	 * @return \GdImage|resource|null
	 */
	private static function gd_load( string $path, string $mime ) {
		switch ( $mime ) {
			case 'image/jpeg':
				$img = @imagecreatefromjpeg( $path );
				return $img ?: null;
			case 'image/png':
				$img = @imagecreatefrompng( $path );
				return $img ?: null;
			case 'image/webp':
				if ( ! function_exists( 'imagecreatefromwebp' ) ) {
					return null;
				}
				$img = @imagecreatefromwebp( $path );
				return $img ?: null;
			default:
				return null;
		}
	}

	/**
	 * @param \GdImage|resource                                                                 $image
	 * @param array{jpeg_quality: int, webp_quality: int, png_compression: int} $settings
	 */
	private static function gd_save( $image, string $path, string $mime, array $settings ): bool {
		switch ( $mime ) {
			case 'image/jpeg':
				return (bool) imagejpeg( $image, $path, $settings['jpeg_quality'] );
			case 'image/png':
				return (bool) imagepng( $image, $path, $settings['png_compression'] );
			case 'image/webp':
				return function_exists( 'imagewebp' )
					? (bool) imagewebp( $image, $path, $settings['webp_quality'] )
					: false;
			default:
				return false;
		}
	}

	private static function imagick_auto_orient( \Imagick $image ): bool {
		$before = method_exists( $image, 'getImageOrientation' )
			? (int) $image->getImageOrientation()
			: 0;

		if ( method_exists( $image, 'autoOrient' ) ) {
			$image->autoOrient();
		} elseif ( method_exists( $image, 'autoOrientImage' ) ) {
			$image->autoOrientImage();
		} else {
			return false;
		}

		if ( $before <= 1 ) {
			return false;
		}

		return ! method_exists( $image, 'getImageOrientation' )
			|| (int) $image->getImageOrientation() !== $before;
	}

	/**
	 * @param \GdImage|resource $image
	 * @return array{image: \GdImage|resource, changed: bool}
	 */
	private static function gd_apply_exif_orientation( $image, string $path, string $mime ): array {
		if ( $mime !== 'image/jpeg' || ! function_exists( 'exif_read_data' ) || ! function_exists( 'imagerotate' ) ) {
			return array( 'image' => $image, 'changed' => false );
		}

		$exif = @exif_read_data( $path );
		if ( ! is_array( $exif ) || empty( $exif['Orientation'] ) ) {
			return array( 'image' => $image, 'changed' => false );
		}

		switch ( (int) $exif['Orientation'] ) {
			case 3:
				$rotated = imagerotate( $image, 180, 0 );
				break;
			case 6:
				$rotated = imagerotate( $image, 270, 0 );
				break;
			case 8:
				$rotated = imagerotate( $image, 90, 0 );
				break;
			default:
				return array( 'image' => $image, 'changed' => false );
		}

		if ( $rotated === false ) {
			return array( 'image' => $image, 'changed' => false );
		}

		imagedestroy( $image );

		return array( 'image' => $rotated, 'changed' => true );
	}

	private static function replace_file( string $destination, string $source ): bool {
		if ( @copy( $source, $destination ) ) {
			return true;
		}

		$contents = @file_get_contents( $source );
		if ( ! is_string( $contents ) || $contents === '' ) {
			return false;
		}

		return false !== @file_put_contents( $destination, $contents );
	}

	private static function cleanup_temp( string $path ): void {
		if ( $path !== '' && is_file( $path ) ) {
			@unlink( $path );
		}
	}

	private static function log_upload( string $file, string $result ): void {
		if ( ! is_admin() ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( sprintf( '[ak-optimize] %s result=%s', wp_basename( $file ), $result ) );
	}

	private static function temp_output_path( string $mime ): string {
		$extension = 'jpg';
		if ( $mime === 'image/png' ) {
			$extension = 'png';
		} elseif ( $mime === 'image/webp' ) {
			$extension = 'webp';
		}

		$tmp = wp_tempnam( 'ak-optimize-' . wp_unique_id() . '.' . $extension );
		if ( ! is_string( $tmp ) || $tmp === '' ) {
			return sys_get_temp_dir() . '/ak-optimize-' . wp_unique_id() . '.' . $extension;
		}

		return $tmp;
	}
}
