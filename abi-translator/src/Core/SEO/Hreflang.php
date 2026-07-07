<?php

namespace ABI\Translator\Core\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABI\Translator\Core\Language\LanguageDetector;
use ABI\Translator\Core\Language\UrlBuilder;
use ABI\Translator\Core\Settings;

/**
 * Emits <link rel="alternate" hreflang="..."> tags (plus x-default) in the head
 * so search engines understand the language variants of each URL.
 */
final class Hreflang {

	public function register(): void {
		add_action( 'wp_head', array( $this, 'render' ), 1 );
	}

	public function render(): void {
		if ( is_admin() || is_404() ) {
			return;
		}

		$languages = LanguageDetector::active_languages();
		if ( count( $languages ) < 2 ) {
			return;
		}

		$out = '';
		foreach ( $languages as $lang ) {
			$out .= sprintf(
				'<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
				esc_attr( $lang ),
				esc_url( UrlBuilder::url_for( $lang ) )
			);
		}

		// x-default points to the default language.
		$out .= sprintf(
			'<link rel="alternate" hreflang="x-default" href="%s" />' . "\n",
			esc_url( UrlBuilder::url_for( Settings::default_lang() ) )
		);

		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each URL/attr escaped above.
	}
}
