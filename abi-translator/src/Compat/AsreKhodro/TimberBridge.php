<?php

namespace ABI\Translator\Compat\AsreKhodro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABI\Translator\Core\Frontend\LanguageSwitcher;
use ABI\Translator\Core\Language\LanguageDetector;
use ABI\Translator\Core\Translation\TranslationService;

/**
 * Injects language data + translation helpers into the Timber context and Twig
 * environment so theme templates can render translated labels/leads that never
 * pass through a standard WP filter (e.g. post_lead, hard-coded block titles).
 */
final class TimberBridge {

	private TranslationService $service;
	private BlockLabelsBridge $labels;

	public function __construct( TranslationService $service, BlockLabelsBridge $labels ) {
		$this->service = $service;
		$this->labels  = $labels;
	}

	public function register(): void {
		add_filter( 'timber/context', array( $this, 'inject_context' ), 20 );
		add_filter( 'timber/twig/functions', array( $this, 'register_twig_functions' ) );
	}

	/**
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>
	 */
	public function inject_context( array $context ): array {
		$context['abi_lang']             = LanguageDetector::current();
		$context['abi_is_default']       = LanguageDetector::is_default();
		$context['abi_dir']              = $this->dir( LanguageDetector::current() );
		$context['abi_active_languages'] = LanguageDetector::active_languages();

		return $context;
	}

	/**
	 * Expose Twig helpers:
	 *   {{ fn('abi_translate_label', 'مشاهده بیشتر') }}
	 *   {{ fn('abi_translate', post_lead, 'post', post.id, 'excerpt') }}
	 *   {{ fn('abi_language_switcher') }}
	 *
	 * @param array<string, mixed> $functions
	 * @return array<string, mixed>
	 */
	public function register_twig_functions( array $functions ): array {
		$functions['abi_translate_label'] = array(
			'callable' => function ( $text, $key = '' ): string {
				return $this->labels->translate( (string) $text, (string) $key );
			},
		);

		$functions['abi_translate'] = array(
			'callable' => function ( $text, $object_type = 'block_label', $object_id = 0, $field = 'label' ): string {
				if ( LanguageDetector::is_default() || trim( (string) $text ) === '' ) {
					return (string) $text;
				}

				return $this->service->translate_field(
					(string) $object_type,
					(int) $object_id,
					(string) $field,
					(string) $text,
					LanguageDetector::current(),
					array( 'field' => (string) $field )
				);
			},
		);

		$functions['abi_language_switcher'] = array(
			'callable' => static function (): string {
				return ( new LanguageSwitcher() )->shortcode();
			},
		);

		return $functions;
	}

	private function dir( string $lang ): string {
		return in_array( $lang, array( 'fa', 'ar' ), true ) ? 'rtl' : 'ltr';
	}
}
