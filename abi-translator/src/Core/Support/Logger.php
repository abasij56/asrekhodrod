<?php

namespace ABI\Translator\Core\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Very small logging helper. Never logs API keys or secrets.
 */
final class Logger {

	public static function error( string $message, array $context = array() ): void {
		self::write( 'ERROR', $message, $context );
	}

	public static function warning( string $message, array $context = array() ): void {
		self::write( 'WARN', $message, $context );
	}

	private static function write( string $level, string $message, array $context ): void {
		if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			return;
		}

		$context = self::redact( $context );
		$suffix  = $context === array() ? '' : ' ' . wp_json_encode( $context );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( sprintf( '[ABI Translator][%s] %s%s', $level, $message, $suffix ) );
	}

	/**
	 * Strip anything that looks like a secret before logging.
	 *
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>
	 */
	private static function redact( array $context ): array {
		$secret_keys = array( 'api_key', 'apikey', 'authorization', 'key', 'token', 'secret' );

		foreach ( $context as $key => $value ) {
			if ( in_array( strtolower( (string) $key ), $secret_keys, true ) ) {
				$context[ $key ] = '***';
			}
		}

		return $context;
	}
}
