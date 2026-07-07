<?php

namespace ABI\Translator\Core\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABI\Translator\Core\Language\LanguageDetector;
use ABI\Translator\Core\Language\UrlBuilder;

/**
 * Front-end language switcher: [abi_language_switcher] shortcode and the
 * do_action('abi_translator_language_switcher') hook for theme placement.
 */
final class LanguageSwitcher {

	public function register(): void {
		add_shortcode( 'abi_language_switcher', array( $this, 'shortcode' ) );
		add_action( 'abi_translator_language_switcher', array( $this, 'output' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets(): void {
		wp_register_style(
			'abi-translator-switcher',
			ABI_TRANSLATOR_URL . 'assets/css/language-switcher.css',
			array(),
			ABI_TRANSLATOR_VERSION
		);
		wp_enqueue_style( 'abi-translator-switcher' );
	}

	/**
	 * @param array<string, mixed>|string $atts
	 */
	public function shortcode( $atts = array() ): string {
		return $this->render();
	}

	public function output(): void {
		echo $this->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render() escapes each part.
	}

	private function render(): string {
		$languages = LanguageDetector::active_languages();
		if ( count( $languages ) < 2 ) {
			return '';
		}

		$current = LanguageDetector::current();
		$items   = array();

		foreach ( $languages as $lang ) {
			$is_active = $lang === $current;
			$classes   = 'abi-lang-switcher__item' . ( $is_active ? ' is-active' : '' );

			$items[] = sprintf(
				'<a class="%s" href="%s" hreflang="%s"%s>%s</a>',
				esc_attr( $classes ),
				esc_url( UrlBuilder::switch_url( $lang ) ),
				esc_attr( $lang ),
				$is_active ? ' aria-current="true"' : '',
				esc_html( $this->label( $lang ) )
			);
		}

		return '<div class="abi-lang-switcher">' . implode( '', $items ) . '</div>';
	}

	private function label( string $lang ): string {
		$labels = array(
			'fa' => 'FA',
			'en' => 'EN',
			'ar' => 'AR',
		);

		$label = $labels[ $lang ] ?? strtoupper( $lang );

		/**
		 * Filter the switcher label for a language.
		 *
		 * @param string $label
		 * @param string $lang
		 */
		return (string) apply_filters( 'abi_translator_switcher_label', $label, $lang );
	}
}
