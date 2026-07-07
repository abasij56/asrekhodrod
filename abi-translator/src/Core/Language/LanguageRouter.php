<?php

namespace ABI\Translator\Core\Language;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABI\Translator\Core\Settings;

/**
 * Detects a language URL prefix (e.g. /en/...) very early and strips it from
 * the request so WordPress (and the theme's own rewrite rules) resolve the
 * remaining path exactly as they do for the default language.
 *
 * This is a Core-only approach: because we strip the prefix before rewrite
 * matching, /en/News/{id}/{slug} resolves through the existing News rule with
 * NO site-specific Compat code required in phase 1.
 */
final class LanguageRouter {

	private static bool $handled = false;

	/**
	 * Register front-end filters. Detection itself is triggered directly from
	 * Plugin::boot() (also on plugins_loaded) so it runs before WP::parse_request.
	 */
	public function register(): void {
		// Reflect the active language on <html lang> / dir when available.
		add_filter( 'language_attributes', array( $this, 'filter_language_attributes' ), 20, 1 );
	}

	public function detect_and_strip(): void {
		if ( self::$handled ) {
			return;
		}
		self::$handled = true;

		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}

		$request_uri = (string) wp_unslash( $_SERVER['REQUEST_URI'] );
		$parts       = explode( '?', $request_uri, 2 );
		$path        = $parts[0];
		$query       = isset( $parts[1] ) ? '?' . $parts[1] : '';

		$home_path = $this->home_path();
		$relative  = $this->strip_home_path( $path, $home_path );

		// Ignore core/admin/API endpoints.
		if ( $this->is_reserved_path( $relative ) ) {
			return;
		}

		$segments = explode( '/', trim( $relative, '/' ) );
		$first    = $segments[0] ?? '';

		if ( $first === '' || ! LanguageDetector::is_supported( $first ) || $first === Settings::default_lang() ) {
			return;
		}

		// Matched a secondary language prefix: activate it and strip the segment.
		LanguageDetector::set_current( $first );

		array_shift( $segments );
		$new_relative = implode( '/', $segments );

		$new_path = $home_path;
		if ( $new_relative !== '' ) {
			$new_path = rtrim( $home_path, '/' ) . '/' . $new_relative;
			// Preserve a trailing slash if the original had one.
			if ( str_ends_with( $path, '/' ) && ! str_ends_with( $new_path, '/' ) ) {
				$new_path .= '/';
			}
		}

		if ( $new_path === '' ) {
			$new_path = '/';
		}

		$_SERVER['REQUEST_URI'] = $new_path . $query;

		if ( isset( $_SERVER['PATH_INFO'] ) ) {
			unset( $_SERVER['PATH_INFO'] );
		}
	}

	/**
	 * Add/replace the lang attribute for the active language (LTR for en).
	 */
	public function filter_language_attributes( string $output ): string {
		if ( LanguageDetector::is_default() ) {
			return $output;
		}

		$lang = LanguageDetector::current();
		$dir  = $lang === 'fa' || $lang === 'ar' ? 'rtl' : 'ltr';

		$output = preg_replace( '/lang="[^"]*"/', 'lang="' . esc_attr( $lang ) . '"', $output );
		if ( is_string( $output ) && strpos( $output, 'dir=' ) !== false ) {
			$output = preg_replace( '/dir="[^"]*"/', 'dir="' . esc_attr( $dir ) . '"', $output );
		} else {
			$output .= ' dir="' . esc_attr( $dir ) . '"';
		}

		return (string) $output;
	}

	private function home_path(): string {
		$path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

		return $path === '' ? '/' : $path;
	}

	private function strip_home_path( string $path, string $home_path ): string {
		$home_path = rtrim( $home_path, '/' );
		if ( $home_path !== '' && str_starts_with( $path, $home_path ) ) {
			$path = substr( $path, strlen( $home_path ) );
		}

		return $path === '' ? '/' : $path;
	}

	private function is_reserved_path( string $relative ): bool {
		$relative = ltrim( $relative, '/' );

		foreach ( array( 'wp-admin', 'wp-login.php', 'wp-json', 'wp-cron.php', 'xmlrpc.php', 'wp-content', 'wp-includes' ) as $reserved ) {
			if ( str_starts_with( $relative, $reserved ) ) {
				return true;
			}
		}

		return false;
	}
}
