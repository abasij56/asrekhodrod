<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PersianDate {

	/** @var array<int, string> */
	private const WEEKDAYS = array(
		'یکشنبه',
		'دوشنبه',
		'سه‌شنبه',
		'چهارشنبه',
		'پنجشنبه',
		'جمعه',
		'شنبه',
	);

	/** @var array<int, string> */
	private const MONTHS = array(
		'فروردین',
		'اردیبهشت',
		'خرداد',
		'تیر',
		'مرداد',
		'شهریور',
		'مهر',
		'آبان',
		'آذر',
		'دی',
		'بهمن',
		'اسفند',
	);

	public static function format_date( int $timestamp ): string {
		return self::format_jalali( $timestamp );
	}

	public static function format_datetime( int $timestamp ): string {
		return trim( self::format_jalali( $timestamp ) . ' ' . self::format_time( $timestamp ) );
	}

	/**
	 * @param \Timber\Post|\Timber\Comment|\WP_Post|\WP_Comment|mixed $object
	 */
	public static function format_object_date( $object ): string {
		$timestamp = self::object_timestamp( $object );

		return $timestamp > 0 ? self::format_jalali( $timestamp ) : '';
	}

	/**
	 * @param \Timber\Post|\Timber\Comment|\WP_Post|\WP_Comment|mixed $object
	 */
	public static function format_object_time( $object ): string {
		$timestamp = self::object_timestamp( $object );

		return $timestamp > 0 ? self::format_time( $timestamp ) : '';
	}

	/**
	 * @param \Timber\Post|\Timber\Comment|\WP_Post|\WP_Comment|mixed $object
	 */
	public static function format_object_datetime( $object ): string {
		$timestamp = self::object_timestamp( $object );

		return $timestamp > 0 ? self::format_datetime( $timestamp ) : '';
	}

	/**
	 * @return array{year: int, month: int, day: int}
	 */
	public static function now_jalali_parts(): array {
		$timezone = wp_timezone();
		$datetime = ( new \DateTimeImmutable( 'now', $timezone ) );

		[ $year, $month, $day ] = self::gregorian_to_jalali(
			(int) $datetime->format( 'Y' ),
			(int) $datetime->format( 'n' ),
			(int) $datetime->format( 'j' )
		);

		return array(
			'year'  => $year,
			'month' => $month,
			'day'   => $day,
		);
	}

	/**
	 * @return list<array{value: int, label: string}>
	 */
	public static function jalali_month_options(): array {
		$options = array();
		foreach ( self::MONTHS as $index => $label ) {
			$options[] = array(
				'value' => $index + 1,
				'label' => $label,
			);
		}

		return $options;
	}

	public static function is_jalali_leap_year( int $year ): bool {
		$cycle = ( $year - ( $year > 0 ? 474 : 473 ) ) % 2820 + 474;

		return ( ( ( $cycle + 38 ) * 682 ) % 2816 ) < 682;
	}

	public static function jalali_month_length( int $year, int $month ): int {
		if ( $month >= 1 && $month <= 6 ) {
			return 31;
		}

		if ( $month >= 7 && $month <= 11 ) {
			return 30;
		}

		if ( $month === 12 ) {
			return self::is_jalali_leap_year( $year ) ? 30 : 29;
		}

		return 31;
	}

	/**
	 * @return array{0:int,1:int,2:int}
	 */
	public static function jalali_to_gregorian( int $year, int $month, int $day ): array {
		$year  = $year - 979;
		$month = $month - 1;
		$day   = $day - 1;

		$day_number = 365 * $year + intdiv( $year, 33 ) * 8 + intdiv( ( $year % 33 ) + 3, 4 );
		for ( $index = 0; $index < $month; ++$index ) {
			$day_number += $index < 6 ? 31 : 30;
		}
		$day_number += $day;

		$gregorian_day = $day_number + 79;

		$gregorian_year = 1600 + 400 * intdiv( $gregorian_day, 146097 );
		$gregorian_day %= 146097;

		$leap = true;
		if ( $gregorian_day >= 36525 ) {
			$gregorian_day--;
			$gregorian_year += 100 * intdiv( $gregorian_day, 36524 );
			$gregorian_day  %= 36524;
			if ( $gregorian_day >= 365 ) {
				$gregorian_day++;
			} else {
				$leap = false;
			}
		}

		$gregorian_year += 4 * intdiv( $gregorian_day, 1461 );
		$gregorian_day  %= 1461;

		if ( $gregorian_day >= 366 ) {
			$leap = false;
			$gregorian_day--;
			$gregorian_year += intdiv( $gregorian_day, 365 );
			$gregorian_day   = $gregorian_day % 365;
		}

		$month_lengths = array( 0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
		$gregorian_month = 0;
		while ( $gregorian_month < 13 && $gregorian_day >= $month_lengths[ $gregorian_month + 1 ] ) {
			$gregorian_day -= $month_lengths[ $gregorian_month + 1 ];
			++$gregorian_month;
		}

		return array( $gregorian_year, $gregorian_month + 1, $gregorian_day + 1 );
	}

	/**
	 * Inclusive Gregorian datetime bounds for a Jalali filter.
	 *
	 * @return array{after: string, before: string}|null
	 */
	public static function jalali_filter_bounds( int $year, int $month, int $day ): ?array {
		if ( $year < 1300 || $year > 1600 ) {
			return null;
		}

		if ( $month > 0 && ( $month < 1 || $month > 12 ) ) {
			return null;
		}

		if ( $day > 0 ) {
			if ( $month <= 0 ) {
				return null;
			}

			$max_day = self::jalali_month_length( $year, $month );
			if ( $day < 1 || $day > $max_day ) {
				return null;
			}

			[ $gy, $gm, $gd ] = self::jalali_to_gregorian( $year, $month, $day );
			$start            = array( $gy, $gm, $gd );
			$end              = $start;
		} elseif ( $month > 0 ) {
			$start = self::jalali_to_gregorian( $year, $month, 1 );
			$end   = self::jalali_to_gregorian( $year, $month, self::jalali_month_length( $year, $month ) );
		} else {
			$start = self::jalali_to_gregorian( $year, 1, 1 );
			$end   = self::jalali_to_gregorian( $year, 12, self::jalali_month_length( $year, 12 ) );
		}

		$timezone = wp_timezone();
		$after    = ( new \DateTimeImmutable(
			sprintf( '%04d-%02d-%02d 00:00:00', $start[0], $start[1], $start[2] ),
			$timezone
		) )->format( 'Y-m-d H:i:s' );
		$before   = ( new \DateTimeImmutable(
			sprintf( '%04d-%02d-%02d 23:59:59', $end[0], $end[1], $end[2] ),
			$timezone
		) )->format( 'Y-m-d H:i:s' );

		return array(
			'after'  => $after,
			'before' => $before,
		);
	}

	private static function format_time( int $timestamp ): string {
		$timezone = wp_timezone();
		$datetime = ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $timezone );

		return PersianDigits::convert( $datetime->format( 'H:i' ) );
	}

	private static function format_jalali( int $timestamp ): string {
		$timezone = wp_timezone();
		$datetime = ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $timezone );

		$gy = (int) $datetime->format( 'Y' );
		$gm = (int) $datetime->format( 'n' );
		$gd = (int) $datetime->format( 'j' );

		[ $jy, $jm, $jd ] = self::gregorian_to_jalali( $gy, $gm, $gd );
		$weekday          = self::WEEKDAYS[ (int) $datetime->format( 'w' ) ] ?? '';
		$month            = self::MONTHS[ $jm - 1 ] ?? '';

		return trim(
			$weekday . ' ' .
			PersianDigits::convert( (string) $jd ) . ' ' .
			$month . ' ' .
			PersianDigits::convert( (string) $jy )
		);
	}

	/**
	 * @return array{0:int,1:int,2:int}
	 */
	private static function gregorian_to_jalali( int $gy, int $gm, int $gd ): array {
		$g_days_in_month = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		$gy2             = $gm > 2 ? $gy + 1 : $gy;
		$days            = 355666 + ( 365 * $gy ) + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 ) + $gd + $g_days_in_month[ $gm - 1 ];
		$jy              = -1595 + ( 33 * intdiv( $days, 12053 ) );
		$days           %= 12053;
		$jy             += 4 * intdiv( $days, 1461 );
		$days           %= 1461;

		if ( $days > 365 ) {
			$jy   += intdiv( $days - 1, 365 );
			$days  = ( $days - 1 ) % 365;
		}

		if ( $days < 186 ) {
			$jm = 1 + intdiv( $days, 31 );
			$jd = 1 + ( $days % 31 );
		} else {
			$jm = 7 + intdiv( $days - 186, 30 );
			$jd = 1 + ( ( $days - 186 ) % 30 );
		}

		return array( $jy, $jm, $jd );
	}

	/**
	 * @param mixed $object
	 */
	private static function object_timestamp( $object ): int {
		if ( $object instanceof \Timber\Post || $object instanceof \Timber\Comment ) {
			$ts = strtotime( $object->date( 'Y-m-d H:i:s' ) );

			return $ts ? $ts : 0;
		}

		if ( $object instanceof \WP_Post ) {
			$ts = strtotime( (string) $object->post_date );

			return $ts ? $ts : 0;
		}

		if ( $object instanceof \WP_Comment ) {
			$ts = strtotime( (string) $object->comment_date );

			return $ts ? $ts : 0;
		}

		return 0;
	}
}
