<?php

namespace ABI\Translator\Core\Language;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABI\Translator\Core\Settings;

/**
 * Holds the language resolved for the current request and exposes helpers
 * used by filters/SEO. The actual detection/URL rewriting lives in LanguageRouter.
 */
final class LanguageDetector {

	private static ?string $current = null;

	public static function set_current( string $lang ): void {
		self::$current = $lang;
	}

	/**
	 * Currently active language code (falls back to the default language).
	 */
	public static function current(): string {
		if ( self::$current !== null ) {
			return self::$current;
		}

		return Settings::default_lang();
	}

	public static function is_default(): bool {
		return self::current() === Settings::default_lang();
	}

	/**
	 * All languages the plugin serves (default first). Filterable.
	 *
	 * @return array<int, string>
	 */
	public static function active_languages(): array {
		$languages = Settings::languages();

		/** @param array<int, string> $languages */
		$languages = apply_filters( 'abi_translator_active_languages', $languages );

		return is_array( $languages ) && $languages !== array() ? array_values( $languages ) : array( 'fa', 'en' );
	}

	/**
	 * Non-default languages (the ones served under a URL prefix).
	 *
	 * @return array<int, string>
	 */
	public static function secondary_languages(): array {
		$default = Settings::default_lang();

		return array_values(
			array_filter(
				self::active_languages(),
				static fn( string $lang ): bool => $lang !== $default
			)
		);
	}

	public static function is_supported( string $lang ): bool {
		return in_array( $lang, self::active_languages(), true );
	}
}
