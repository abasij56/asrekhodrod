<?php

namespace ABI\Translator\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Typed accessor for plugin options stored in wp_options (abi_translator_settings).
 */
final class Settings {

	/** @var array<string, mixed>|null */
	private static ?array $cache = null;

	/**
	 * Languages the UI can offer (code => human label). Extend as needed.
	 *
	 * @var array<string, string>
	 */
	public const KNOWN_LANGUAGES = array(
		'fa' => 'فارسی (fa)',
		'en' => 'English (en)',
		'ar' => 'العربية (ar)',
	);

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'provider'      => 'gapgpt',
			'api_key'       => '',
			'base_url'      => 'https://api.gapgpt.app/v1',
			'model'         => 'gpt-4o-mini',
			'temperature'   => 0.3,
			'max_tokens'    => 2000,
			'timeout'       => 30,
			'default_lang'  => 'fa',
			'languages'     => array( 'fa', 'en' ),
			'rate_limit_enabled' => false,
			'rate_limit_max'     => 60,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		if ( self::$cache !== null ) {
			return self::$cache;
		}

		$stored = get_option( ABI_TRANSLATOR_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		self::$cache = array_merge( self::defaults(), $stored );

		return self::$cache;
	}

	/**
	 * @param mixed $default
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	public static function api_key(): string {
		return trim( (string) self::get( 'api_key', '' ) );
	}

	public static function provider(): string {
		return (string) self::get( 'provider', 'gapgpt' );
	}

	public static function base_url(): string {
		return rtrim( (string) self::get( 'base_url', '' ), '/' );
	}

	public static function model(): string {
		return (string) self::get( 'model', 'gpt-4o-mini' );
	}

	public static function temperature(): float {
		return (float) self::get( 'temperature', 0.3 );
	}

	public static function max_tokens(): int {
		return (int) self::get( 'max_tokens', 2000 );
	}

	public static function timeout(): int {
		return max( 5, (int) self::get( 'timeout', 30 ) );
	}

	public static function default_lang(): string {
		return (string) self::get( 'default_lang', 'fa' );
	}

	/**
	 * All active languages, default first.
	 *
	 * @return array<int, string>
	 */
	public static function languages(): array {
		$langs = self::get( 'languages', array( 'fa', 'en' ) );
		$langs = is_array( $langs ) ? array_values( array_filter( array_map( 'strval', $langs ) ) ) : array( 'fa', 'en' );

		if ( $langs === array() ) {
			$langs = array( 'fa', 'en' );
		}

		// Guarantee the default language is present and first.
		$default = self::default_lang();
		$langs   = array_values( array_unique( array_merge( array( $default ), $langs ) ) );

		return $langs;
	}

	/**
	 * Secondary (non-default) languages that are served under a URL prefix.
	 *
	 * @return array<int, string>
	 */
	public static function target_languages(): array {
		$default = self::default_lang();

		return array_values(
			array_filter(
				self::languages(),
				static fn( string $lang ): bool => $lang !== $default
			)
		);
	}

	public static function has_api_key(): bool {
		return self::api_key() !== '';
	}

	public static function rate_limit_enabled(): bool {
		return (bool) self::get( 'rate_limit_enabled', false );
	}

	/**
	 * Maximum provider requests allowed per IP per minute (min 1).
	 */
	public static function rate_limit_max(): int {
		return max( 1, (int) self::get( 'rate_limit_max', 60 ) );
	}

	/**
	 * Reset the in-request cache (used after saving settings in the same request).
	 */
	public static function flush(): void {
		self::$cache = null;
	}
}
