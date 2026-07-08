<?php

namespace ABI\Translator\Core\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABI\Translator\Core\Settings;

/**
 * Lightweight per-IP, per-minute request limiter backed by transients.
 *
 * Disabled by default. When enabled, protects the site (and the AI budget) from
 * a burst of cache-miss translations hammering the provider. Hitting the limit
 * is non-fatal: callers fall back to the original text, so pages never break.
 */
final class RateLimiter {

	private const PREFIX = 'abi_tr_rl_';

	/**
	 * Consume one request slot. Returns true when the call is allowed.
	 */
	public static function allow(): bool {
		if ( ! Settings::rate_limit_enabled() ) {
			return true;
		}

		$max = Settings::rate_limit_max();
		$key = self::PREFIX . md5( self::client_ip() . '|' . self::minute_bucket() );

		$count = (int) get_transient( $key );
		if ( $count >= $max ) {
			return false;
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );

		return true;
	}

	private static function minute_bucket(): int {
		return (int) floor( time() / MINUTE_IN_SECONDS );
	}

	private static function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		$ip = preg_replace( '/[^0-9a-f:.]/i', '', $ip ) ?? '';

		return $ip !== '' ? $ip : 'unknown';
	}
}
