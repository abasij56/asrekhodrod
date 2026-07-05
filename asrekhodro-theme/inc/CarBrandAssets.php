<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bundled car brand logos in assets/car-brands/.
 */
final class CarBrandAssets {

	private const DIR = ASREKHODRO_THEME_DIR . '/assets/car-brands';

	/** @var list<array{slug: string, filename: string, url: string, width: int, height: int}>|null */
	private static ?array $catalog = null;

	/** @var list<array{slug: string, filename: string, url: string}>|null */
	private static ?array $admin_catalog = null;

	/**
	 * Lightweight list for admin picker (no getimagesize).
	 *
	 * @return list<array{slug: string, filename: string, url: string}>
	 */
	public static function admin_catalog(): array {
		if ( self::$admin_catalog !== null ) {
			return self::$admin_catalog;
		}

		self::$admin_catalog = array();

		if ( ! is_dir( self::DIR ) ) {
			return self::$admin_catalog;
		}

		$files = glob( self::DIR . '/*.png' ) ?: array();
		sort( $files, SORT_NATURAL | SORT_FLAG_CASE );

		foreach ( $files as $path ) {
			if ( ! is_readable( $path ) ) {
				continue;
			}

			$filename = basename( $path );
			$slug     = (string) pathinfo( $filename, PATHINFO_FILENAME );
			if ( $slug === '' ) {
				continue;
			}

			self::$admin_catalog[] = array(
				'slug'     => $slug,
				'filename' => $filename,
				'url'      => self::url_for_slug( $slug ),
			);
		}

		return self::$admin_catalog;
	}

	/**
	 * @return list<array{slug: string, filename: string, url: string, width: int, height: int}>
	 */
	public static function catalog(): array {
		if ( self::$catalog !== null ) {
			return self::$catalog;
		}

		self::$catalog = array();

		if ( ! is_dir( self::DIR ) ) {
			return self::$catalog;
		}

		$files = glob( self::DIR . '/*.png' ) ?: array();
		sort( $files, SORT_NATURAL | SORT_FLAG_CASE );

		foreach ( $files as $path ) {
			if ( ! is_readable( $path ) ) {
				continue;
			}

			$filename = basename( $path );
			$slug     = (string) pathinfo( $filename, PATHINFO_FILENAME );
			if ( $slug === '' ) {
				continue;
			}

			$size = self::read_dimensions( $path );

			self::$catalog[] = array(
				'slug'     => $slug,
				'filename' => $filename,
				'url'      => self::url_for_slug( $slug ),
				'width'    => $size['width'],
				'height'   => $size['height'],
			);
		}

		return self::$catalog;
	}

	/**
	 * @return array<string, string>
	 */
	public static function acf_choices(): array {
		$choices = array(
			'' => __( '— بدون لوگوی آماده —', 'asrekhodro' ),
		);

		foreach ( self::catalog() as $item ) {
			$choices[ (string) $item['slug'] ] = (string) $item['filename'];
		}

		return $choices;
	}

	/**
	 * @return array{url: string, alt: string, width: int, height: int}
	 */
	public static function image_for_slug( string $slug, string $alt = '' ): array {
		$empty = array(
			'url'    => '',
			'alt'    => $alt,
			'width'  => 0,
			'height' => 0,
		);

		$slug = sanitize_key( $slug );
		if ( $slug === '' ) {
			return $empty;
		}

		$path = self::DIR . '/' . $slug . '.png';
		if ( ! is_readable( $path ) ) {
			return $empty;
		}

		$size = self::read_dimensions( $path );

		return array(
			'url'    => self::url_for_slug( $slug ),
			'alt'    => $alt !== '' ? $alt : $slug,
			'width'  => $size['width'],
			'height' => $size['height'],
		);
	}

	public static function url_for_slug( string $slug ): string {
		$slug = sanitize_key( $slug );
		if ( $slug === '' ) {
			return '';
		}

		return ASREKHODRO_THEME_URI . '/assets/car-brands/' . rawurlencode( $slug ) . '.png';
	}

	public static function bundled_slug_for_term( int $term_id ): string {
		if ( $term_id <= 0 || ! function_exists( 'get_field' ) ) {
			return '';
		}

		$value = get_field( 'ak_category_brand_logo', 'category_' . $term_id );

		return is_string( $value ) ? sanitize_key( $value ) : '';
	}

	/**
	 * @param array<string, mixed> $field
	 * @return array<string, mixed>
	 */
	public static function load_acf_select_field( array $field ): array {
		$field['choices'] = self::acf_choices();

		return $field;
	}

	/**
	 * @return array{width: int, height: int}
	 */
	private static function read_dimensions( string $path ): array {
		$size = @getimagesize( $path );
		if ( is_array( $size ) ) {
			return array(
				'width'  => (int) ( $size[0] ?? 0 ),
				'height' => (int) ( $size[1] ?? 0 ),
			);
		}

		return array(
			'width'  => 0,
			'height' => 0,
		);
	}
}
