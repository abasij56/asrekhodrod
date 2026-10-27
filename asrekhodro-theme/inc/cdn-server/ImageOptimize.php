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

		$result = self::process( $file, $mime );
		if ( is_wp_error( $result ) ) {
			self::log_upload( $file, 'failed_' . $result->get_error_code() );
			return $upload;
		}

		$optimized = $result['path'];
		$out_mime  = $result['mime'];

		if ( $optimized === '' || ( $optimized === $file && $out_mime === $mime ) ) {
			self::log_upload( $file, 'unchanged' );
			return $upload;
		}

		$destination = $file;
		$converted   = $out_mime !== $mime;

		if ( $converted ) {
			$destination = self::renamed_upload_path( $file, $out_mime );
		}

		$replaced = self::replace_file( $destination, $optimized );
		if ( $optimized !== $file ) {
			self::cleanup_temp( $optimized );
		}

		if ( ! $replaced ) {
			self::log_upload( $file, 'failed_replace' );
			return $upload;
		}

		if ( $converted ) {
			if ( $destination !== $file && is_file( $file ) ) {
				wp_delete_file( $file );
			}
			$upload['file'] = $destination;
			$upload['type'] = $out_mime;
			if ( isset( $upload['url'] ) && is_string( $upload['url'] ) && $upload['url'] !== '' ) {
				$upload['url'] = (string) preg_replace(
					'#[^/]+$#',
					rawurlencode( wp_basename( $destination ) ),
					$upload['url']
				);
			}
			self::log_upload( $destination, 'converted_jpeg' );
			return $upload;
		}

		self::log_upload( $file, 'applied' );

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
		$result = self::process( $source_path, $mime );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result['path'];
	}

	/**
	 * @return array{path: string, mime: string}|\WP_Error
	 */
	public static function process( string $source_path, string $mime ): array|\WP_Error {
		$mime     = strtolower( $mime );
		$settings = self::settings();
		if ( $settings === null || ! in_array( $mime, self::SUPPORTED_MIMES, true ) ) {
			return array( 'path' => $source_path, 'mime' => $mime );
		}

		if ( ! is_readable( $source_path ) ) {
			return new \WP_Error( 'ak_optimize_source', __( 'فایل منبع برای بهینه‌سازی خوانا نیست.', 'asrekhodro' ) );
		}

		if ( extension_loaded( 'imagick' ) && class_exists( '\Imagick' ) ) {
			return self::apply_with_imagick( $source_path, $mime, $settings );
		}

		if ( function_exists( 'imagecreatetruecolor' ) ) {
			return self::apply_with_gd( $source_path, $mime, $settings );
		}

		return new \WP_Error( 'ak_optimize_no_engine', __( 'برای بهینه‌سازی تصویر به GD یا Imagick در PHP نیاز است.', 'asrekhodro' ) );
	}

	public static function filename_with_extension( string $filename, string $extension ): string {
		$base = pathinfo( $filename, PATHINFO_FILENAME );

		return ( $base !== '' ? $base : 'image' ) . '.' . ltrim( $extension, '.' );
	}

	/**
	 * @param array{max_width: int, jpeg_quality: int, webp_quality: int, png_compression: int} $settings
	 * @return array{path: string, mime: string}|\WP_Error
	 */
	private static function apply_with_imagick( string $source_path, string $mime, array $settings ): array|\WP_Error {
		try {
			$image = new \Imagick( $source_path );
			$did_orient = self::imagick_auto_orient( $image );
			$image->setImageColorspace( \Imagick::COLORSPACE_SRGB );

			$width        = (int) $image->getImageWidth();
			$needs_resize = $width > $settings['max_width'];

			if ( $needs_resize ) {
				$image->resizeImage( $settings['max_width'], 0, \Imagick::FILTER_LANCZOS, 1, true );
			}

			$output_mime = $mime;
			if ( $mime === 'image/png' && ! self::imagick_has_transparency( $image ) ) {
				$output_mime = 'image/jpeg';
			}

			$image->stripImage();
			self::imagick_prepare_format( $image, $output_mime, $settings );

			$output = self::temp_output_path( $output_mime );
			$image->writeImage( $output );
			$image->clear();

			$path = self::prefer_smaller_or_resized( $source_path, $output, $needs_resize || $did_orient );

			return array(
				'path' => $path,
				'mime' => $path === $source_path ? $mime : $output_mime,
			);
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
	 * @return array{path: string, mime: string}|\WP_Error
	 */
	private static function apply_with_gd( string $source_path, string $mime, array $settings ): array|\WP_Error {
		$image = self::gd_load( $source_path, $mime );
		if ( $image === null ) {
			return new \WP_Error( 'ak_optimize_gd_load', __( 'بارگذاری تصویر برای بهینه‌سازی ناموفق بود.', 'asrekhodro' ) );
		}

		$oriented = self::gd_apply_exif_orientation( $image, $source_path, $mime );
		$image    = $oriented['image'];

		$src_w        = imagesx( $image );
		$src_h        = imagesy( $image );
		$needs_resize = $src_w > $settings['max_width'];
		$output_mime  = $mime;
		$keep_alpha   = ( $mime === 'image/png' || $mime === 'image/webp' );

		if ( $mime === 'image/png' && ! self::gd_has_transparency( $image ) ) {
			$output_mime = 'image/jpeg';
			$keep_alpha  = false;
		}

		if ( $needs_resize ) {
			$target_w = $settings['max_width'];
			$target_h = max( 1, (int) round( $src_h * ( $target_w / $src_w ) ) );
			$resized  = imagecreatetruecolor( $target_w, $target_h );

			if ( $keep_alpha ) {
				imagealphablending( $resized, false );
				imagesavealpha( $resized, true );
				$transparent = imagecolorallocatealpha( $resized, 0, 0, 0, 127 );
				imagefilledrectangle( $resized, 0, 0, $target_w, $target_h, $transparent );
			} else {
				$white = imagecolorallocate( $resized, 255, 255, 255 );
				imagefilledrectangle( $resized, 0, 0, $target_w, $target_h, $white );
			}

			imagecopyresampled( $resized, $image, 0, 0, 0, 0, $target_w, $target_h, $src_w, $src_h );
			imagedestroy( $image );
			$image = $resized;
		} elseif ( $output_mime === 'image/jpeg' && $mime === 'image/png' ) {
			$image = self::gd_flatten_on_white( $image );
		}

		$output = self::temp_output_path( $output_mime );
		$saved  = self::gd_save( $image, $output, $output_mime, $settings );
		imagedestroy( $image );

		if ( ! $saved ) {
			return new \WP_Error( 'ak_optimize_gd_save', __( 'ذخیره تصویر بهینه‌شده ناموفق بود.', 'asrekhodro' ) );
		}

		$path = self::prefer_smaller_or_resized( $source_path, $output, $needs_resize || $oriented['changed'] );

		return array(
			'path' => $path,
			'mime' => $path === $source_path ? $mime : $output_mime,
		);
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

	/**
	 * @param array{jpeg_quality: int, webp_quality: int, png_compression: int} $settings
	 */
	private static function imagick_prepare_format( \Imagick $image, string $mime, array $settings ): void {
		if ( $mime === 'image/jpeg' ) {
			self::imagick_flatten_on_white( $image );
			$image->setImageFormat( 'jpeg' );
			$image->setImageCompression( \Imagick::COMPRESSION_JPEG );
			$image->setImageCompressionQuality( $settings['jpeg_quality'] );
			if ( method_exists( $image, 'setInterlaceScheme' ) ) {
				$image->setInterlaceScheme( \Imagick::INTERLACE_JPEG );
			}
			return;
		}

		if ( $mime === 'image/webp' ) {
			$image->setImageFormat( 'webp' );
			$image->setImageCompressionQuality( $settings['webp_quality'] );
			return;
		}

		$image->setImageFormat( 'png' );
		$image->setOption( 'png:compression-level', (string) $settings['png_compression'] );
	}

	private static function imagick_flatten_on_white( \Imagick $image ): void {
		$image->setImageBackgroundColor( 'white' );
		if ( defined( 'Imagick::ALPHACHANNEL_REMOVE' ) && method_exists( $image, 'setImageAlphaChannel' ) ) {
			$image->setImageAlphaChannel( \Imagick::ALPHACHANNEL_REMOVE );
		}
	}

	private static function imagick_has_transparency( \Imagick $image ): bool {
		if ( method_exists( $image, 'getImageAlphaChannel' ) && ! $image->getImageAlphaChannel() ) {
			return false;
		}

		try {
			$range = $image->getImageChannelRange( \Imagick::CHANNEL_ALPHA );
		} catch ( \Throwable $exception ) {
			return false;
		}

		$min     = isset( $range['minima'] ) ? (float) $range['minima'] : 0.0;
		$quantum = 65535.0;
		if ( method_exists( '\Imagick', 'getQuantumRange' ) ) {
			$qr = \Imagick::getQuantumRange();
			if ( isset( $qr['quantumRangeLong'] ) ) {
				$quantum = (float) $qr['quantumRangeLong'];
			}
		}

		// Treat nearly-opaque leftover alpha as no real transparency.
		return $min < ( $quantum * 0.995 );
	}

	/**
	 * @param \GdImage|resource $image
	 */
	private static function gd_has_transparency( $image ): bool {
		if ( imagecolortransparent( $image ) >= 0 ) {
			return true;
		}

		if ( ! imageistruecolor( $image ) ) {
			return false;
		}

		$width  = imagesx( $image );
		$height = imagesy( $image );
		for ( $y = 0; $y < $height; $y++ ) {
			for ( $x = 0; $x < $width; $x++ ) {
				$alpha = ( imagecolorat( $image, $x, $y ) & 0x7F000000 ) >> 24;
				if ( $alpha > 2 ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @param \GdImage|resource $image
	 * @return \GdImage|resource
	 */
	private static function gd_flatten_on_white( $image ) {
		$width  = imagesx( $image );
		$height = imagesy( $image );
		$canvas = imagecreatetruecolor( $width, $height );
		if ( $canvas === false ) {
			return $image;
		}

		$white = imagecolorallocate( $canvas, 255, 255, 255 );
		imagefilledrectangle( $canvas, 0, 0, $width, $height, $white );
		imagealphablending( $canvas, true );
		imagecopy( $canvas, $image, 0, 0, 0, 0, $width, $height );
		imagedestroy( $image );

		return $canvas;
	}

	private static function renamed_upload_path( string $original, string $mime ): string {
		$dir       = dirname( $original );
		$extension = 'jpg';
		if ( $mime === 'image/png' ) {
			$extension = 'png';
		} elseif ( $mime === 'image/webp' ) {
			$extension = 'webp';
		}

		$basename = self::filename_with_extension( wp_basename( $original ), $extension );
		$unique   = wp_unique_filename( $dir, $basename );

		return $dir . '/' . $unique;
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
