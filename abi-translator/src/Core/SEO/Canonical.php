<?php

namespace ABI\Translator\Core\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABI\Translator\Core\Language\LanguageDetector;
use ABI\Translator\Core\Language\UrlBuilder;

/**
 * Per-language canonical URL + prevention of WordPress redirecting /{lang}/
 * requests back to the default-language URL.
 */
final class Canonical {

	public function register(): void {
		// Stop WP from canonical-redirecting secondary-language requests.
		add_filter( 'redirect_canonical', array( $this, 'maybe_block_redirect' ), 10, 1 );

		// Output our own canonical only when no major SEO plugin owns it.
		if ( ! $this->seo_plugin_active() ) {
			remove_action( 'wp_head', 'rel_canonical' );
			add_action( 'wp_head', array( $this, 'render_canonical' ), 1 );
		}
	}

	/**
	 * @param mixed $redirect_url
	 * @return mixed
	 */
	public function maybe_block_redirect( $redirect_url ) {
		if ( ! LanguageDetector::is_default() ) {
			return false;
		}

		return $redirect_url;
	}

	public function render_canonical(): void {
		if ( is_admin() || is_404() ) {
			return;
		}

		$lang = LanguageDetector::current();

		printf(
			'<link rel="canonical" href="%s" />' . "\n",
			esc_url( UrlBuilder::url_for( $lang ) )
		);
	}

	private function seo_plugin_active(): bool {
		$active = defined( 'WPSEO_VERSION' )            // Yoast SEO
			|| class_exists( 'WPSEO_Frontend' )
			|| defined( 'RANK_MATH_VERSION' )           // Rank Math
			|| defined( 'AIOSEO_VERSION' )              // All in One SEO
			|| defined( 'SEOPRESS_VERSION' );           // SEOPress

		/**
		 * Allow forcing our canonical on/off regardless of SEO plugin detection.
		 *
		 * @param bool $active
		 */
		return (bool) apply_filters( 'abi_translator_seo_plugin_active', $active );
	}
}
