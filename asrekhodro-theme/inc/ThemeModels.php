<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared 3D model files for the whole theme (not appearance-specific).
 */
final class ThemeModels {

	public static function dir(): string {
		return ASREKHODRO_THEME_DIR . '/models';
	}

	public static function uri(): string {
		return ASREKHODRO_THEME_URI . '/models';
	}

	public static function path( string $filename ): string {
		return self::dir() . '/' . self::sanitize_filename( $filename );
	}

	public static function url( string $filename ): string {
		return self::uri() . '/' . rawurlencode( self::sanitize_filename( $filename ) );
	}

	public static function exists( string $filename ): bool {
		return is_file( self::path( $filename ) );
	}

	/**
	 * Resolve a block field value to a public model URL.
	 */
	public static function resolve_url( string $value ): string {
		$value = trim( $value );
		if ( $value === '' ) {
			return '';
		}

		if ( wp_http_validate_url( $value ) ) {
			return esc_url_raw( $value );
		}

		$filename = ltrim( str_replace( '\\', '/', $value ), '/' );
		if ( str_starts_with( $filename, 'models/' ) ) {
			$filename = substr( $filename, 7 );
		}

		$filename = self::sanitize_filename( $filename );
		if ( $filename === '' ) {
			return '';
		}

		return self::url( $filename );
	}

	private static function sanitize_filename( string $filename ): string {
		$filename = basename( str_replace( '\\', '/', $filename ) );

		if ( $filename === '' || ! preg_match( '/^[A-Za-z0-9._-]+\.glb$/', $filename ) ) {
			return '';
		}

		return $filename;
	}
}
