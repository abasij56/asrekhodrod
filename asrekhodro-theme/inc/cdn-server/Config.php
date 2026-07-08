<?php

namespace AsreKhodro\Theme\CdnServer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single source of truth for all CDN Server settings.
 *
 * Reads ACF options from the "CDN Server" tab and falls back to defaults.php.
 */
final class Config {

	public const OPTION_PREFIX = 'cdn_';

	/** @var array<string, mixed>|null */
	private static ?array $defaults = null;

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		if ( self::$defaults === null ) {
			$loaded = require __DIR__ . '/defaults.php';
			self::$defaults = is_array( $loaded ) ? $loaded : array();
		}

		return self::$defaults;
	}

	/**
	 * Read a single setting, ACF option first then default.
	 */
	public static function get( string $key ): mixed {
		$defaults = self::defaults();
		$default  = $defaults[ $key ] ?? null;

		if ( ! function_exists( 'get_field' ) ) {
			return $default;
		}

		$value = get_field( self::OPTION_PREFIX . $key, 'option' );

		if ( $value === null || $value === '' || $value === false ) {
			// true_false false is a legit value; only treat as "unset" for non-bool defaults.
			if ( $value === false && is_bool( $default ) ) {
				return false;
			}
			if ( $value === '' || $value === null ) {
				return $default;
			}
		}

		return $value;
	}

	public static function is_enabled(): bool {
		return (bool) self::get( 'enabled' );
	}

	/**
	 * Saved FTP/SFTP credentials present (ignores the enable toggle).
	 */
	public static function is_connection_ready(): bool {
		return self::get_public_base_url() !== ''
			&& self::host() !== ''
			&& self::user() !== ''
			&& self::pass() !== ''
			&& self::remote_base_path() !== '';
	}

	/**
	 * True when upload is allowed: enabled + credentials saved.
	 */
	public static function is_configured(): bool {
		return self::is_enabled() && self::is_connection_ready();
	}

	/**
	 * @return array<int, string> Human-readable missing requirements (Persian).
	 */
	public static function missing_requirements(): array {
		$missing = array();

		if ( ! self::is_enabled() ) {
			$missing[] = __( 'گزینه «فعال‌سازی آپلود CDN» روشن نیست (تنظیمات تم → سرور CDN).', 'asrekhodro' );
		}
		if ( self::get_public_base_url() === '' ) {
			$missing[] = __( 'نشانی پایه عمومی خالی است.', 'asrekhodro' );
		}
		if ( self::host() === '' ) {
			$missing[] = __( 'میزبان (Host) خالی است.', 'asrekhodro' );
		}
		if ( self::user() === '' ) {
			$missing[] = __( 'نام کاربری خالی است.', 'asrekhodro' );
		}
		if ( self::pass() === '' ) {
			$missing[] = __( 'رمز عبور ذخیره نشده — دوباره وارد کنید و دکمه ذخیره را بزنید.', 'asrekhodro' );
		}

		return $missing;
	}

	public static function get_public_base_url(): string {
		return rtrim( trim( (string) self::get( 'public_base_url' ) ), '/' );
	}

	public static function protocol(): string {
		$protocol = strtolower( trim( (string) self::get( 'protocol' ) ) );

		return in_array( $protocol, array( 'ftp', 'ftps', 'sftp' ), true ) ? $protocol : 'ftp';
	}

	public static function host(): string {
		return trim( (string) self::get( 'host' ) );
	}

	public static function port(): int {
		$port = (int) self::get( 'port' );

		return $port > 0 ? $port : ( self::protocol() === 'sftp' ? 22 : 21 );
	}

	public static function user(): string {
		return trim( (string) self::get( 'user' ) );
	}

	public static function pass(): string {
		$pass = (string) self::get( 'pass' );
		if ( $pass !== '' ) {
			return $pass;
		}

		// ACF option fallback (field name: cdn_pass → option key options_cdn_pass).
		$raw = get_option( 'options_' . self::OPTION_PREFIX . 'pass' );
		if ( is_string( $raw ) && $raw !== '' ) {
			return $raw;
		}

		return '';
	}

	public static function remote_base_path(): string {
		$path = trim( (string) self::get( 'remote_base_path' ) );

		return $path === '' ? '/' : $path;
	}

	public static function max_upload_bytes(): int {
		$mb = (int) self::get( 'max_upload_mb' );
		$mb = $mb > 0 ? $mb : (int) ( self::defaults()['max_upload_mb'] ?? 50 );

		return $mb * 1024 * 1024;
	}

	public static function timeout(): int {
		$timeout = (int) self::get( 'timeout' );

		return $timeout > 0 ? $timeout : (int) ( self::defaults()['timeout'] ?? 20 );
	}

	public static function path_pattern(): string {
		$pattern = trim( (string) self::get( 'path_pattern' ) );

		return $pattern !== '' ? $pattern : (string) ( self::defaults()['path_pattern'] ?? 'Uploaded/{type}/{Y}/{m}/{name}-{rand}.{ext}' );
	}

	public static function rand_length(): int {
		$len = (int) ( self::defaults()['rand_length'] ?? 8 );

		return $len > 0 ? $len : 8;
	}

	/**
	 * Allowed MIME types as a clean list.
	 *
	 * @return array<int, string>
	 */
	public static function allowed_types(): array {
		$raw = self::get( 'allowed_types' );

		if ( is_string( $raw ) ) {
			$raw = preg_split( '/\R/', $raw ) ?: array();
		}

		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$types = array();
		foreach ( $raw as $type ) {
			$type = strtolower( trim( (string) $type ) );
			if ( $type !== '' ) {
				$types[] = $type;
			}
		}

		if ( $types === array() ) {
			$fallback = self::defaults()['allowed_types'] ?? array();
			$types    = is_array( $fallback ) ? $fallback : array();
		}

		return array_values( array_unique( $types ) );
	}

	/**
	 * Host portion of the public base URL (used to allow-list registered URLs).
	 */
	public static function public_host(): string {
		$host = wp_parse_url( self::get_public_base_url(), PHP_URL_HOST );

		return is_string( $host ) ? strtolower( $host ) : '';
	}

	/**
	 * Full settings snapshot for connections.
	 *
	 * @return array<string, mixed>
	 */
	public static function connection_settings(): array {
		return array(
			'protocol'         => self::protocol(),
			'host'             => self::host(),
			'port'             => self::port(),
			'user'             => self::user(),
			'pass'             => self::pass(),
			'remote_base_path' => self::remote_base_path(),
			'timeout'          => self::timeout(),
		);
	}

	/**
	 * Build connection settings for the test button.
	 *
	 * Uses values from the settings form when sent; falls back to saved options.
	 * Password: if the form field is empty, uses the saved password (ACF often
	 * leaves the input blank after save).
	 *
	 * @param array<string, mixed> $input Raw POST from the test AJAX request.
	 * @return array{settings: array<string, mixed>, source: string, pass_from_saved: bool}
	 */
	public static function connection_settings_for_test( array $input = array() ): array {
		$from_form = ! empty( $input['from_form'] ) || self::has_test_input( $input );

		if ( $from_form ) {
			$protocol = strtolower( trim( (string) ( $input['protocol'] ?? '' ) ) );
			$protocol = in_array( $protocol, array( 'ftp', 'ftps', 'sftp' ), true ) ? $protocol : self::protocol();

			$port = (int) ( $input['port'] ?? 0 );
			$port = $port > 0 ? $port : ( $protocol === 'sftp' ? 22 : 21 );

			$form_pass      = (string) ( $input['pass'] ?? '' );
			$pass_from_saved = $form_pass === '';
			$pass           = $pass_from_saved ? self::pass() : $form_pass;

			$settings = array(
				'protocol'         => $protocol,
				'host'             => trim( (string) ( $input['host'] ?? '' ) ) ?: self::host(),
				'port'             => $port,
				'user'             => trim( (string) ( $input['user'] ?? '' ) ) ?: self::user(),
				'pass'             => $pass,
				'remote_base_path' => trim( (string) ( $input['remote_base_path'] ?? '' ) ) ?: self::remote_base_path(),
				'timeout'          => self::timeout(),
			);

			return array(
				'settings'        => $settings,
				'source'          => 'form',
				'pass_from_saved' => $pass_from_saved,
			);
		}

		return array(
			'settings'        => self::connection_settings(),
			'source'          => 'saved',
			'pass_from_saved' => true,
		);
	}

	/**
	 * Human-readable snapshot for the connection-test debug panel.
	 *
	 * @param array<string, mixed> $settings
	 * @return array<int, array{label: string, value: string}>
	 */
	public static function format_debug_summary( array $settings, string $source, bool $pass_from_saved ): array {
		$protocol = strtoupper( (string) ( $settings['protocol'] ?? 'ftp' ) );
		$pass     = (string) ( $settings['pass'] ?? '' );

		$pass_display = $pass === ''
			? __( '(خالی — رمز ذخیره نشده؛ دوباره وارد و ذخیره کنید)', 'asrekhodro' )
			: ( $pass_from_saved && $source === 'form'
				? $pass . ' ' . __( '(از دیتابیس — فیلد فرم خالی بود)', 'asrekhodro' )
				: $pass );

		$source_label = $source === 'form'
			? __( 'مقادیر فرم (همین صفحه)', 'asrekhodro' )
			: __( 'مقادیر ذخیره‌شده در دیتابیس', 'asrekhodro' );

		$rows = array(
			array(
				'label' => __( 'منبع تنظیمات', 'asrekhodro' ),
				'value' => $source_label,
			),
			array(
				'label' => __( 'پروتکل', 'asrekhodro' ),
				'value' => $protocol,
			),
			array(
				'label' => __( 'میزبان', 'asrekhodro' ),
				'value' => (string) ( $settings['host'] ?? '' ),
			),
			array(
				'label' => __( 'پورت', 'asrekhodro' ),
				'value' => (string) ( $settings['port'] ?? '' ),
			),
			array(
				'label' => __( 'نام کاربری', 'asrekhodro' ),
				'value' => (string) ( $settings['user'] ?? '' ),
			),
			array(
				'label' => __( 'رمز عبور', 'asrekhodro' ),
				'value' => $pass_display,
			),
			array(
				'label' => __( 'مسیر پایه روی سرور', 'asrekhodro' ),
				'value' => (string) ( $settings['remote_base_path'] ?? '' ),
			),
			array(
				'label' => __( 'نشانی پایه عمومی', 'asrekhodro' ),
				'value' => self::get_public_base_url(),
			),
			array(
				'label' => __( 'فعال‌سازی CDN', 'asrekhodro' ),
				'value' => self::is_enabled() ? __( 'بله', 'asrekhodro' ) : __( 'خیر', 'asrekhodro' ),
			),
		);

		return $rows;
	}

	/**
	 * @param array<string, mixed> $input
	 */
	private static function has_test_input( array $input ): bool {
		foreach ( array( 'protocol', 'host', 'port', 'user', 'pass', 'remote_base_path' ) as $key ) {
			if ( isset( $input[ $key ] ) && trim( (string) $input[ $key ] ) !== '' ) {
				return true;
			}
		}

		return false;
	}
}
