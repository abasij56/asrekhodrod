<?php

namespace ABI\Translator\Compat\AsreKhodro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABI\Translator\Core\Language\LanguageDetector;
use ABI\Translator\Core\Translation\TranslationService;

/**
 * Translates UI label strings (block section titles, button labels, ...) that
 * are not attached to a post. Stored under object_type=block_label, object_id=0,
 * with a per-label field derived from the source text so distinct labels never
 * collide on the unique key.
 */
final class BlockLabelsBridge {

	private TranslationService $service;

	public function __construct( TranslationService $service ) {
		$this->service = $service;
	}

	public function register(): void {
		add_filter( 'abi_translator_block_label', array( $this, 'filter_label' ), 10, 2 );
	}

	/**
	 * Filter callback: apply_filters('abi_translator_block_label', $text, $key).
	 *
	 * @param mixed $text
	 * @param mixed $key
	 */
	public function filter_label( $text, $key = '' ): string {
		return $this->translate( (string) $text, (string) $key );
	}

	/**
	 * Translate a label string. $key is an optional stable identifier; when empty
	 * a hash of the source text is used.
	 */
	public function translate( string $text, string $key = '' ): string {
		if ( LanguageDetector::is_default() || trim( $text ) === '' ) {
			return $text;
		}

		$field = $key !== ''
			? 'label_' . substr( preg_replace( '/[^a-z0-9_]+/i', '_', $key ) ?? '', 0, 40 )
			: 'label_' . substr( md5( $text ), 0, 32 );

		return $this->service->translate_field(
			'block_label',
			0,
			$field,
			$text,
			LanguageDetector::current(),
			array( 'field' => 'label' )
		);
	}
}
