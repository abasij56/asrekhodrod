<?php

namespace AsreKhodro\Theme\CdnServer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Composites a theme-option watermark onto images before CDN upload.
 *
 * Settings are read via get_option() — never get_field() — to avoid ACF re-entry.
 */
final class Watermark {

	private const SUPPORTED_MIMES = array(
		'image/jpeg',
		'image/png',
		'image/webp',
	);

	private const POSITIONS = array(
		'bottom_left',
		'bottom_right',
		'top_left',
		'top_right',
	);

	/**
	 * @return array{attachment_id: int, opacity: int, margin: int, position: string}|null
	 */
	public static function settings(): ?array {
		$enabled = get_option( 'options_site_watermark_enabled', 0 );
		if ( ! (bool) $enabled ) {
			return null;
		}

		$id = (int) get_option( 'options_site_watermark', 0 );
		if ( $id <= 0 ) {
			return null;
		}

		$opacity  = (int) get_option( 'options_site_watermark_opacity', 70 );
		$margin   = (int) get_option( 'options_site_watermark_margin', 16 );
		$position = sanitize_key( (string) get_option( 'options_site_watermark_position', 'bottom_left' ) );

		if ( ! in_array( $position, self::POSITIONS, true ) ) {
			$position = 'bottom_left';
		}

		return array(
			'attachment_id' => $id,
			'opacity'       => max( 10, min( 100, $opacity ?: 70 ) ),
			'margin'        => max( 0, $margin ),
			'position'      => $position,
		);
	}

	public static function should_apply( string $mime ): bool {
		return self::settings() !== null
			&& in_array( strtolower( $mime ), self::SUPPORTED_MIMES, true );
	}

	/**
	 * @return string|\WP_Error Path of watermarked temp file, or error.
	 */
	public static function apply( string $source_path, string $mime ): string|\WP_Error {
		$settings = self::settings();
		if ( $settings === null ) {
			return $source_path;
		}

		if ( ! is_readable( $source_path ) ) {
			return new \WP_Error( 'ak_watermark_source', __( 'فایل منبع برای واترمارک خوانا نیست.', 'asrekhodro' ) );
		}

		$watermark_path = get_attached_file( $settings['attachment_id'] );
		if ( ! is_string( $watermark_path ) || $watermark_path === '' || ! is_readable( $watermark_path ) ) {
			return new \WP_Error( 'ak_watermark_missing', __( 'فایل واترمارک در کتابخانه رسانه یافت نشد.', 'asrekhodro' ) );
		}

		$mime = strtolower( $mime );

		if ( extension_loaded( 'imagick' ) && class_exists( '\Imagick' ) ) {
			return self::apply_with_imagick( $source_path, $mime, $watermark_path, $settings );
		}

		if ( function_exists( 'imagecreatetruecolor' ) ) {
			return self::apply_with_gd( $source_path, $mime, $watermark_path, $settings );
		}

		return new \WP_Error( 'ak_watermark_no_engine', __( 'برای واترمارک به GD یا Imagick در PHP نیاز است.', 'asrekhodro' ) );
	}

	/**
	 * @param array{attachment_id: int, opacity: int, margin: int, position: string} $settings
	 * @return string|\WP_Error
	 */
	private static function apply_with_imagick( string $source_path, string $mime, string $watermark_path, array $settings ): string|\WP_Error {
		try {
			$image = new \Imagick( $source_path );
			$stamp = new \Imagick( $watermark_path );

			$image->setImageColorspace( \Imagick::COLORSPACE_SRGB );
			$stamp->setImageColorspace( \Imagick::COLORSPACE_SRGB );

			self::resize_stamp_imagick( $stamp, (int) $image->getImageWidth() );

			$opacity = $settings['opacity'] / 100;
			$stamp->evaluateImage( \Imagick::EVALUATE_MULTIPLY, $opacity, \Imagick::CHANNEL_ALPHA );

			$coords = self::position_coords(
				(int) $image->getImageWidth(),
				(int) $image->getImageHeight(),
				(int) $stamp->getImageWidth(),
				(int) $stamp->getImageHeight(),
				$settings['margin'],
				$settings['position']
			);

			$image->compositeImage( $stamp, \Imagick::COMPOSITE_OVER, $coords['x'], $coords['y'] );

			$output = self::temp_output_path( $mime );
			$image->writeImage( $output );
			$image->clear();
			$stamp->clear();

			return $output;
		} catch ( \Throwable $exception ) {
			return new \WP_Error(
				'ak_watermark_imagick_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'اعمال واترمارک ناموفق بود: %s', 'asrekhodro' ),
					$exception->getMessage()
				)
			);
		}
	}

	/**
	 * @param array{attachment_id: int, opacity: int, margin: int, position: string} $settings
	 * @return string|\WP_Error
	 */
	private static function apply_with_gd( string $source_path, string $mime, string $watermark_path, array $settings ): string|\WP_Error {
		$base = self::gd_load( $source_path, $mime );
		if ( $base === null ) {
			return new \WP_Error( 'ak_watermark_base_load', __( 'بارگذاری تصویر اصلی برای واترمارک ناموفق بود.', 'asrekhodro' ) );
		}

		$stamp = self::gd_load_any( $watermark_path );
		if ( $stamp === null ) {
			imagedestroy( $base );

			return new \WP_Error( 'ak_watermark_stamp_load', __( 'بارگذاری تصویر واترمارک ناموفق بود.', 'asrekhodro' ) );
		}

		$stamp = self::resize_stamp_gd( $stamp, imagesx( $base ) );

		$coords = self::position_coords(
			imagesx( $base ),
			imagesy( $base ),
			imagesx( $stamp ),
			imagesy( $stamp ),
			$settings['margin'],
			$settings['position']
		);

		self::imagecopymerge_alpha( $base, $stamp, $coords['x'], $coords['y'], 0, 0, imagesx( $stamp ), imagesy( $stamp ), $settings['opacity'] );
		imagedestroy( $stamp );

		$output = self::temp_output_path( $mime );
		$saved  = self::gd_save( $base, $output, $mime );
		imagedestroy( $base );

		if ( ! $saved ) {
			return new \WP_Error( 'ak_watermark_save', __( 'ذخیره تصویر واترمارک‌شده ناموفق بود.', 'asrekhodro' ) );
		}

		return $output;
	}

	/**
	 * @return array{x: int, y: int}
	 */
	private static function position_coords( int $base_width, int $base_height, int $stamp_width, int $stamp_height, int $margin, string $position ): array {
		$margin = max( 0, $margin );
		$max_x  = max( 0, $base_width - $stamp_width - $margin );
		$max_y  = max( 0, $base_height - $stamp_height - $margin );

		switch ( $position ) {
			case 'bottom_right':
				return array( 'x' => $max_x, 'y' => $max_y );
			case 'top_left':
				return array( 'x' => $margin, 'y' => $margin );
			case 'top_right':
				return array( 'x' => $max_x, 'y' => $margin );
			case 'bottom_left':
			default:
				return array( 'x' => $margin, 'y' => $max_y );
		}
	}

	private static function resize_stamp_imagick( \Imagick $stamp, int $base_width ): void {
		$stamp_width = (int) $stamp->getImageWidth();
		$max_width   = max( 48, (int) round( $base_width * 0.22 ) );

		if ( $stamp_width <= $max_width ) {
			return;
		}

		$stamp->resizeImage( $max_width, 0, \Imagick::FILTER_LANCZOS, 1, true );
	}

	/**
	 * @param \GdImage|resource $stamp
	 * @return \GdImage|resource
	 */
	private static function resize_stamp_gd( $stamp, int $base_width ) {
		$stamp_width  = imagesx( $stamp );
		$stamp_height = imagesy( $stamp );
		$max_width    = max( 48, (int) round( $base_width * 0.22 ) );

		if ( $stamp_width <= $max_width ) {
			return $stamp;
		}

		$target_height = (int) round( $stamp_height * ( $max_width / $stamp_width ) );
		$resized       = imagecreatetruecolor( $max_width, $target_height );
		imagealphablending( $resized, false );
		imagesavealpha( $resized, true );
		$transparent = imagecolorallocatealpha( $resized, 0, 0, 0, 127 );
		imagefilledrectangle( $resized, 0, 0, $max_width, $target_height, $transparent );
		imagecopyresampled( $resized, $stamp, 0, 0, 0, 0, $max_width, $target_height, $stamp_width, $stamp_height );
		imagedestroy( $stamp );

		return $resized;
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
	 * @return \GdImage|resource|null
	 */
	private static function gd_load_any( string $path ) {
		$img = @imagecreatefrompng( $path );
		if ( $img ) {
			return $img;
		}

		if ( function_exists( 'imagecreatefromwebp' ) ) {
			$img = @imagecreatefromwebp( $path );
			if ( $img ) {
				return $img;
			}
		}

		$img = @imagecreatefromjpeg( $path );

		return $img ?: null;
	}

	/**
	 * @param \GdImage|resource $dst_im
	 * @param \GdImage|resource $src_im
	 */
	private static function imagecopymerge_alpha( $dst_im, $src_im, int $dst_x, int $dst_y, int $src_x, int $src_y, int $src_w, int $src_h, int $opacity ): void {
		$opacity = max( 0, min( 100, $opacity ) );

		for ( $y = 0; $y < $src_h; $y++ ) {
			for ( $x = 0; $x < $src_w; $x++ ) {
				$dest_x = $dst_x + $x;
				$dest_y = $dst_y + $y;

				if ( $dest_x < 0 || $dest_y < 0 || $dest_x >= imagesx( $dst_im ) || $dest_y >= imagesy( $dst_im ) ) {
					continue;
				}

				$src_rgba = imagecolorat( $src_im, $src_x + $x, $src_y + $y );
				$src_a    = ( $src_rgba & 0x7F000000 ) >> 24;
				if ( $src_a >= 127 ) {
					continue;
				}

				$src_r = ( $src_rgba >> 16 ) & 0xFF;
				$src_g = ( $src_rgba >> 8 ) & 0xFF;
				$src_b = $src_rgba & 0xFF;

				$alpha = ( ( 127 - $src_a ) / 127 ) * ( $opacity / 100 );
				if ( $alpha <= 0 ) {
					continue;
				}

				$dst_rgba = imagecolorat( $dst_im, $dest_x, $dest_y );
				$dst_r    = ( $dst_rgba >> 16 ) & 0xFF;
				$dst_g    = ( $dst_rgba >> 8 ) & 0xFF;
				$dst_b    = $dst_rgba & 0xFF;

				$new_r = (int) round( ( 1 - $alpha ) * $dst_r + $alpha * $src_r );
				$new_g = (int) round( ( 1 - $alpha ) * $dst_g + $alpha * $src_g );
				$new_b = (int) round( ( 1 - $alpha ) * $dst_b + $alpha * $src_b );

				$color = imagecolorallocate( $dst_im, $new_r, $new_g, $new_b );
				if ( $color !== false ) {
					imagesetpixel( $dst_im, $dest_x, $dest_y, $color );
				}
			}
		}
	}

	/**
	 * @param \GdImage|resource $image
	 */
	private static function gd_save( $image, string $path, string $mime ): bool {
		switch ( $mime ) {
			case 'image/jpeg':
				return (bool) imagejpeg( $image, $path, 90 );
			case 'image/png':
				return (bool) imagepng( $image, $path, 6 );
			case 'image/webp':
				return function_exists( 'imagewebp' ) ? (bool) imagewebp( $image, $path, 90 ) : false;
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

		$tmp = wp_tempnam( 'ak-watermark-' . wp_unique_id() . '.' . $extension );
		if ( ! is_string( $tmp ) || $tmp === '' ) {
			return sys_get_temp_dir() . '/ak-watermark-' . wp_unique_id() . '.' . $extension;
		}

		return $tmp;
	}
}
