<?php

namespace ABI\Translator\Compat\AsreKhodro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABI\Translator\Core\Language\UrlBuilder;
use ABI\Translator\Core\Settings;

/**
 * Keeps theme-specific permalink patterns compatible with /{lang}/ URL prefixes.
 *
 * Routing itself is handled by Core LanguageRouter (strip prefix before WP
 * rewrite matching). This bridge covers canonical redirect edge cases and
 * language URL building for singular posts with custom permalink structures.
 */
final class PermalinkBridge {

	/** @var list<string> Path prefixes (after the language segment) used by the theme. */
	private const THEME_PATH_PREFIXES = array(
		'News',
		'Home/News',
		'Home/Kiosk',
		'video',
		'review',
		'carsinfo',
		'Gallery/Content',
	);

	public function register(): void {
		add_filter( 'redirect_canonical', array( $this, 'maybe_block_redirect' ), 5, 2 );
		add_filter( 'abi_translator_language_url', array( $this, 'filter_language_url' ), 10, 3 );
	}

	/**
	 * Prevent WordPress (or the theme) from stripping the language prefix or
	 * rewriting /en/{theme-path}/… back to the default-language URL.
	 *
	 * @param mixed $redirect_url
	 * @param mixed $requested_url
	 * @return mixed
	 */
	public function maybe_block_redirect( $redirect_url, $requested_url ) {
		if ( ! is_string( $requested_url ) || $requested_url === '' ) {
			return $redirect_url;
		}

		$path = (string) wp_parse_url( $requested_url, PHP_URL_PATH );
		if ( $path === '' ) {
			return $redirect_url;
		}

		if ( $this->path_has_language_prefix( $path ) && $this->path_matches_theme_pattern( $this->strip_language_prefix_from_path( $path ) ) ) {
			return false;
		}

		return $redirect_url;
	}

	/**
	 * When a post object is supplied, build the language URL from its canonical
	 * permalink instead of the raw request path (helps custom News/Kiosk URLs).
	 *
	 * @param string $url
	 * @param string $lang
	 * @param mixed  $object
	 */
	public function filter_language_url( string $url, string $lang, $object ): string {
		if ( ! $object instanceof \WP_Post ) {
			return $url;
		}

		$permalink = get_permalink( $object );
		if ( ! is_string( $permalink ) || $permalink === '' ) {
			return $url;
		}

		return UrlBuilder::build( $lang, $permalink );
	}

	private function path_has_language_prefix( string $path ): bool {
		$relative = $this->path_to_relative( $path );
		$segment  = explode( '/', trim( $relative, '/' ) )[0] ?? '';

		return $segment !== ''
			&& $segment !== Settings::default_lang()
			&& in_array( $segment, Settings::target_languages(), true );
	}

	private function strip_language_prefix_from_path( string $path ): string {
		$relative = trim( $this->path_to_relative( $path ), '/' );
		$segments = $relative === '' ? array() : explode( '/', $relative );

		if ( $segments !== array() && in_array( $segments[0], Settings::target_languages(), true ) ) {
			array_shift( $segments );
		}

		return implode( '/', $segments );
	}

	private function path_matches_theme_pattern( string $relative ): bool {
		$relative = trim( $relative, '/' );
		if ( $relative === '' ) {
			return false;
		}

		foreach ( self::THEME_PATH_PREFIXES as $prefix ) {
			if ( str_starts_with( $relative, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	private function path_to_relative( string $path ): string {
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home = rtrim( $home, '/' );

		if ( $home !== '' && str_starts_with( $path, $home ) ) {
			$path = substr( $path, strlen( $home ) );
		}

		return $path;
	}
}
