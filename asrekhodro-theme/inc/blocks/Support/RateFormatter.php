<?php

namespace AsreKhodro\Theme\AcfBlocks\Support;

use AsreKhodro\Theme\PersianDigits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared score formatting for cinfo blocks.
 */
final class RateFormatter {

	public static function normalize( $value ): float {
		$rate = is_numeric( $value ) ? (float) $value : 0.0;

		return max( 0.0, min( 10.0, round( $rate, 1 ) ) );
	}

	public static function format( float $rate ): string {
		if ( $rate <= 0 ) {
			return '—';
		}

		$formatted = number_format( $rate, 1, '.', '' );
		$formatted = rtrim( rtrim( $formatted, '0' ), '.' );

		return PersianDigits::convert( $formatted );
	}

	public static function bar_width( float $rate ): int {
		if ( $rate <= 0 ) {
			return 0;
		}

		return (int) round( ( $rate / 10 ) * 100 );
	}

	public static function stars( float $rate ): string {
		if ( $rate <= 0 ) {
			return '';
		}

		$filled = (int) round( ( $rate / 10 ) * 5 );
		$filled = max( 0, min( 5, $filled ) );

		return str_repeat( '★', $filled ) . str_repeat( '☆', 5 - $filled );
	}

	public static function to_persian_digits( string $value ): string {
		return PersianDigits::convert( $value );
	}
}
