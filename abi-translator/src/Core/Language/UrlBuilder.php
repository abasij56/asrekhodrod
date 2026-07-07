<?php

namespace ABI\Translator\Core\Language;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABI\Translator\Core\Settings;

/**
 * Builds language-prefixed URLs (default language has no prefix, secondary
 * languages use /{lang}/). Because LanguageRouter strips the prefix before
 * routing, $wp->request always holds the default-language relative path, which
 * we use as the canonical base for every language variant.
 */
final class UrlBuilder {

	/**
	 * URL prefix segment for a language ('' for the default language).
	 */
	public static function prefix_for( string $lang ): string {
		return $lang === Settings::default_lang() ? '' : $lang;
	}

	/**
	 * Relative path of the current request (no leading slash, no host, no query).
	 * Falls back to parsing REQUEST_URI when $wp->request is unavailable.
	 */
	public static function current_relative_path(): string {
		global $wp;

		if ( $wp instanceof \WP && isset( $wp->request ) && is_string( $wp->request ) ) {
			return trim( $wp->request, '/' );
		}

		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home = rtrim( $home, '/' );

		if ( $home !== '' && str_starts_with( $path, $home ) ) {
			$path = substr( $path, strlen( $home ) );
		}

		return trim( $path, '/' );
	}

	/**
	 * Build an absolute URL for a language given a default-language relative path.
	 */
	public static function url_for( string $lang, ?string $relative = null, string $query = '' ): string {
		$relative = $relative === null ? self::current_relative_path() : trim( $relative, '/' );
		$prefix   = self::prefix_for( $lang );

		$path = trim( $prefix . '/' . $relative, '/' );
		$url  = $path === '' ? home_url( '/' ) : home_url( user_trailingslashit( $path ) );

		if ( $query !== '' ) {
			$url .= '?' . ltrim( $query, '?' );
		}

		/**
		 * Filter a language URL.
		 *
		 * @param string $url
		 * @param string $lang
		 * @param mixed  $object Optional context object (null here).
		 */
		return (string) apply_filters( 'abi_translator_language_url', $url, $lang, null );
	}

	/**
	 * URL of the current request in another language (preserves the query string).
	 */
	public static function switch_url( string $lang ): string {
		$query = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : '';

		return self::url_for( $lang, null, $query );
	}

	/**
	 * Convert any internal URL to its variant in $lang (strips an existing
	 * language prefix, then applies the target language's prefix).
	 */
	public static function build( string $lang, string $url ): string {
		$parsed = wp_parse_url( $url );
		$path   = isset( $parsed['path'] ) ? (string) $parsed['path'] : '/';
		$query  = isset( $parsed['query'] ) ? (string) $parsed['query'] : '';

		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home = rtrim( $home, '/' );
		if ( $home !== '' && str_starts_with( $path, $home ) ) {
			$path = substr( $path, strlen( $home ) );
		}

		$relative = trim( $path, '/' );
		$segments = $relative === '' ? array() : explode( '/', $relative );

		// Drop a leading secondary-language segment if present.
		if ( $segments !== array() && self::is_secondary_prefix( $segments[0] ) ) {
			array_shift( $segments );
		}

		return self::url_for( $lang, implode( '/', $segments ), $query );
	}

	private static function is_secondary_prefix( string $segment ): bool {
		return $segment !== Settings::default_lang()
			&& in_array( $segment, Settings::target_languages(), true );
	}
}
