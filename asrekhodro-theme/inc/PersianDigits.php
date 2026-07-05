<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Convert Western digits (0-9) to Persian (۰-۹) on the public site.
 */
final class PersianDigits {

	private static bool $buffer_started = false;

	public static function init(): void {
		add_action( 'template_redirect', array( self::class, 'maybe_start_buffer' ), 0 );
		add_filter( 'timber/twig/filters', array( self::class, 'register_twig_filters' ) );
		add_filter( 'timber/twig/functions', array( self::class, 'register_twig_functions' ) );
	}

	public static function should_convert(): bool {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		if ( function_exists( 'is_feed' ) && is_feed() ) {
			return false;
		}

		return true;
	}

	public static function maybe_start_buffer(): void {
		if ( ! self::should_convert() || self::$buffer_started ) {
			return;
		}

		self::$buffer_started = true;
		ob_start( array( self::class, 'convert_html' ) );
	}

	public static function convert( string $value ): string {
		if ( $value === '' || ! preg_match( '/[0-9۰-۹]|%%AKPD/u', $value ) ) {
			return $value;
		}

		$value = self::repair_leaked_mask_tokens( $value );
		$value = self::repair_corrupted_numeric_entities( $value );

		if ( ! preg_match( '/[0-9]/', $value ) ) {
			return $value;
		}

		$parts = preg_split(
			'/(&#(?:x[0-9a-fA-F]+|[0-9]+);)/',
			$value,
			-1,
			PREG_SPLIT_DELIM_CAPTURE
		);

		if ( ! is_array( $parts ) ) {
			return self::convert_digits_only( $value );
		}

		foreach ( $parts as $index => $part ) {
			if ( $part === '' || $index % 2 === 1 ) {
				continue;
			}

			$parts[ $index ] = self::convert_digits_only( $part );
		}

		return implode( '', $parts );
	}

	private static function convert_digits_only( string $value ): string {
		if ( ! preg_match( '/[0-9]/', $value ) ) {
			return $value;
		}

		/** @var array<int, string> $western */
		$western = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		/** @var array<int, string> $persian */
		$persian = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );

		return str_replace( $western, $persian, $value );
	}

	/**
	 * Remove mask tokens leaked by an earlier implementation (%%AKPD0%% → %%AKPD۰%%).
	 */
	private static function repair_leaked_mask_tokens( string $value ): string {
		$cleaned = preg_replace( '/%%AKPD[۰-۹0-9]+%%/u', '', $value );

		return is_string( $cleaned ) ? $cleaned : $value;
	}

	/**
	 * Undo prior conversion inside HTML numeric entities (e.g. &#۸۲۲۰; → &#8220;).
	 */
	private static function repair_corrupted_numeric_entities( string $value ): string {
		$repaired = preg_replace_callback(
			'/&#([xX][۰-۹]+|[۰-۹]+);/u',
			static function ( array $matches ): string {
				$digits = self::to_western_digits( $matches[1] );

				return '&#' . $digits . ';';
			},
			$value
		);

		return is_string( $repaired ) ? $repaired : $value;
	}

	private static function to_western_digits( string $value ): string {
		/** @var array<int, string> $persian */
		$persian = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		/** @var array<int, string> $western */
		$western = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );

		return str_replace( $persian, $western, $value );
	}

	/**
	 * Convert visible text nodes in HTML without touching tag attributes (URLs, ids, JSON-LD, etc.).
	 */
	public static function convert_html( string $html ): string {
		if ( $html === '' || ! preg_match( '/[0-9۰-۹]|%%AKPD/u', $html ) ) {
			return $html;
		}

		$parts = preg_split(
			'/(<(?:script|style|noscript)\b[^>]*>.*?<\/(?:script|style|noscript)>)/is',
			$html,
			-1,
			PREG_SPLIT_DELIM_CAPTURE
		);

		if ( ! is_array( $parts ) ) {
			return self::convert_html_chunk( $html );
		}

		foreach ( $parts as $index => $part ) {
			if ( $part === '' || $index % 2 === 1 ) {
				continue;
			}

			$parts[ $index ] = self::convert_html_chunk( $part );
		}

		return implode( '', $parts );
	}

	private static function convert_html_chunk( string $html ): string {
		$html = preg_replace_callback(
			'/>([^<]+)</u',
			static function ( array $matches ): string {
				return '>' . self::convert( $matches[1] ) . '<';
			},
			$html
		);

		if ( ! is_string( $html ) ) {
			return '';
		}

		return preg_replace_callback(
			'/^([^<]+)/u',
			static function ( array $matches ): string {
				return self::convert( $matches[1] );
			},
			$html,
			1
		) ?? $html;
	}

	/**
	 * @param array<string, mixed> $filters
	 * @return array<string, mixed>
	 */
	public static function register_twig_filters( array $filters ): array {
		$filters['persian_digits'] = array(
			'callable' => array( self::class, 'convert' ),
		);

		return $filters;
	}

	/**
	 * @param array<string, mixed> $functions
	 * @return array<string, mixed>
	 */
	public static function register_twig_functions( array $functions ): array {
		$functions['ak_persian_digits'] = array(
			'callable' => array( self::class, 'convert' ),
		);

		return $functions;
	}
}
