<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PostViews {

	private const META_DAILY  = '_asrekhodro_views_daily';
	private const META_TOTAL  = '_asrekhodro_view_count';

	public static function init(): void {
		add_action( 'template_redirect', array( self::class, 'maybe_record_view' ), 20 );
	}

	public static function maybe_record_view(): void {
		if ( ! is_singular( 'post' ) || is_preview() || is_admin() ) {
			return;
		}

		$post_id = (int) get_queried_object_id();
		if ( $post_id <= 0 ) {
			return;
		}

		$cookie = 'ak_view_' . $post_id;
		if ( isset( $_COOKIE[ $cookie ] ) ) {
			return;
		}

		self::record_view( $post_id );

		if ( ! headers_sent() ) {
			setcookie( $cookie, '1', strtotime( 'tomorrow' ), COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
		}
	}

	public static function record_view( int $post_id ): void {
		if ( $post_id <= 0 || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$day   = wp_date( 'Y-m-d', null, wp_timezone() );
		$daily = get_post_meta( $post_id, self::META_DAILY, true );
		if ( ! is_array( $daily ) ) {
			$daily = array();
		}

		$daily[ $day ] = (int) ( $daily[ $day ] ?? 0 ) + 1;
		$daily         = self::prune_daily( $daily );

		update_post_meta( $post_id, self::META_DAILY, $daily );
		update_post_meta( $post_id, self::META_TOTAL, (int) get_post_meta( $post_id, self::META_TOTAL, true ) + 1 );

		self::bust_popular_cache();
	}

	/**
	 * @param array<string, int> $daily
	 * @return array<string, int>
	 */
	private static function prune_daily( array $daily ): array {
		$cutoff = wp_date( 'Y-m-d', strtotime( '-30 days' ), wp_timezone() );

		foreach ( array_keys( $daily ) as $date ) {
			if ( $date < $cutoff ) {
				unset( $daily[ $date ] );
			}
		}

		return $daily;
	}

	public static function get_period_views( int $post_id, int $days ): int {
		$daily = get_post_meta( $post_id, self::META_DAILY, true );
		if ( ! is_array( $daily ) || empty( $daily ) ) {
			return 0;
		}

		$cutoff = wp_date( 'Y-m-d', strtotime( '-' . max( 0, $days - 1 ) . ' days' ), wp_timezone() );
		$sum    = 0;

		foreach ( $daily as $date => $count ) {
			if ( $date >= $cutoff ) {
				$sum += (int) $count;
			}
		}

		return $sum;
	}

	public static function bust_popular_cache(): void {
		foreach ( array( '2days', 'week' ) as $period ) {
			delete_transient( 'ak_popular_' . $period );
			delete_transient( 'ak_popular_' . $period . '_v2' );
		}
	}
}
