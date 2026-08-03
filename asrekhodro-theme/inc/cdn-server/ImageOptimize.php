<?php

namespace AsreKhodro\Theme\CdnServer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resize + recompress images before CDN watermark/upload.
 *
 * Settings are read via get_option() — never get_field() — to avoid ACF re-entry.
 */
final class ImageOptimize {

	private const SUPPORTED_MIMES = array(
		'image/jpeg',
		'image/png',
		'image/webp',
	);

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

		$max_width = (int) get_option( 'options_cdn_image_max_width', 1200 );
		if ( $max_width < 100 ) {
			$max_width = 1200;
		}
		$max_width = min( 5000, $max_width );

		$jpeg = (int) get_option( 'options_cdn_image_jpeg_quality', 88 );
		$webp = (int) get_option( 'options_cdn_image_webp_quality', 85 );
		$png  = (int) get_option( 'options_cdn_image_png_compression', 6 );

		return array(
			'enabled'         => true,
			'max_width'       => $max_width,
			'jpeg_quality'    => max( 60, min( 95, $jpeg ?: 88 ) ),
			'webp_quality'    => max( 60, min( 95, $webp ?: 85 ) ),
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
			$image->setImageColorspace( \Imagick::COLORSPACE_SRGB );

			$width      = (int) $image->getImageWidth();
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

			return self::prefer_smaller_or_resized( $source_path, $output, $needs_resize );
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

		return self::prefer_smaller_or_resized( $source_path, $output, $needs_resize );
	}

	/**
	 * Keep optimized file when smaller, or whenever resize happened.
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
