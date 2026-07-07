<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SidebarWidgets {

	/**
	 * Reusable sidebar rail data for single, archive, search, etc.
	 *
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	public static function get_rail_context( array $args = array() ): array {
		$exclude = isset( $args['exclude_post_id'] ) ? (int) $args['exclude_post_id'] : 0;
		$latest  = isset( $args['latest_limit'] ) ? (int) $args['latest_limit'] : 15;
		$popular = isset( $args['popular_limit'] ) ? (int) $args['popular_limit'] : 10;
		$kiosk   = isset( $args['kiosk_limit'] ) ? (int) $args['kiosk_limit'] : 10;

		return array(
			'popular_2day'             => self::get_popular_posts( 'week', $popular, $exclude ),
			'popular_2day_archive_url' => HomepageData::news_archive_url(),
			'latest_posts'       => self::get_latest_posts( $latest, $exclude ),
			'kiosk_items'        => self::get_kiosk_items( $kiosk ),
			'kiosk_archive_url'  => self::get_kiosk_archive_url(),
		);
	}

	/**
	 * @return \Timber\PostQuery
	 */
	public static function get_latest_posts( int $limit, int $exclude_post_id = 0 ): \Timber\PostQuery {
		$args = array(
			'posts_per_page' => max( 1, $limit ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( $exclude_post_id > 0 ) {
			$args['post__not_in'] = array( $exclude_post_id );
		}

		return ImporterBridge::query_posts( $args );
	}

	/**
	 * @return \Timber\PostQuery
	 */
	public static function get_popular_posts( string $period, int $limit, int $exclude_post_id = 0 ): \Timber\PostQuery {
		$days      = $period === 'week' ? 7 : 2;
		$limit     = max( 1, min( 40, $limit ) );
		$cache_key = 'ak_popular_' . $period . '_v2';
		$pool_size = 120;

		$cached_ids = get_transient( $cache_key );
		if ( ! is_array( $cached_ids ) || count( $cached_ids ) < $limit ) {
			$cached_ids = self::build_popular_ids( $days, $pool_size );
			set_transient( $cache_key, $cached_ids, 10 * MINUTE_IN_SECONDS );
		}

		$ids = array_values(
			array_filter(
				array_map( 'intval', $cached_ids ),
				static fn( int $id ) => $id > 0 && $id !== $exclude_post_id
			)
		);
		$ids = array_slice( $ids, 0, $limit );

		if ( empty( $ids ) ) {
			return self::get_latest_posts( $limit, $exclude_post_id );
		}

		return \Timber\Timber::get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'post__in'       => $ids,
				'orderby'        => 'post__in',
				'posts_per_page' => count( $ids ),
				'no_found_rows'  => true,
			)
		);
	}

	/**
	 * @return array<int, int>
	 */
	private static function build_popular_ids( int $days, int $pool_size ): array {
		$pool_size = max( 1, $pool_size );
		$scan_size = max( $pool_size * 2, 200 );

		$post_ids = get_posts(
			array(
				'post_type'              => 'post',
				'post_status'            => 'publish',
				'posts_per_page'         => $scan_size,
				'fields'                 => 'ids',
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'ignore_sticky_posts'    => true,
			)
		);

		$post_ids = array_map( 'intval', $post_ids );
		$scores   = array();

		foreach ( $post_ids as $post_id ) {
			$score = PostViews::get_period_views( $post_id, $days );
			if ( $score > 0 ) {
				$scores[ $post_id ] = $score;
			}
		}

		arsort( $scores, SORT_NUMERIC );
		$ranked        = array_map( 'intval', array_keys( $scores ) );
		$ranked_lookup = array_fill_keys( $ranked, true );

		foreach ( $post_ids as $post_id ) {
			if ( count( $ranked ) >= $pool_size ) {
				break;
			}
			if ( ! isset( $ranked_lookup[ $post_id ] ) ) {
				$ranked[]                   = $post_id;
				$ranked_lookup[ $post_id ] = true;
			}
		}

		return array_slice( $ranked, 0, $pool_size );
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	public static function get_kiosk_items( int $limit = 10 ): array {
		$limit = max( 1, min( 40, $limit ) );
		$items = self::get_magazine_kiosk_items( $limit );

		if ( count( $items ) >= 3 ) {
			return array_slice( $items, 0, $limit );
		}

		$ads = ImporterBridge::get_ads_by_position( 'kiosk', $limit );
		foreach ( $ads as $ad ) {
			if ( empty( $ad['image'] ) ) {
				continue;
			}

			$items[] = array(
				'title' => (string) ( $ad['label'] ?? $ad['title'] ?? '' ),
				'link'  => (string) ( $ad['link'] ?? '#' ),
				'image' => (string) $ad['image'],
				'alt'   => (string) ( $ad['image_alt'] ?? '' ),
			);
		}

		return array_slice( $items, 0, $limit );
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private static function get_magazine_kiosk_items( int $limit ): array {
		$magazines = \Timber\Timber::get_posts(
			array(
				'post_type'      => 'ak_magazine',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		$items = array();
		foreach ( $magazines as $magazine ) {
			$image = self::get_magazine_cover_url( $magazine );
			if ( $image === '' ) {
				continue;
			}

			$items[] = array(
				'title' => (string) $magazine->title,
				'link'  => (string) $magazine->link,
				'image' => $image,
				'alt'   => (string) $magazine->title,
			);
		}

		return $items;
	}

	private static function get_magazine_cover_url( \Timber\Post $magazine ): string {
		return Magazines::get_cover_url( $magazine );
	}

	public static function get_kiosk_archive_url(): string {
		return Magazines::get_archive_url();
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	public static function get_video_kiosk_items( int $limit = 10 ): array {
		$limit = max( 1, min( 40, $limit ) );
		$videos = \Timber\Timber::get_posts(
			array(
				'post_type'      => 'ak_video',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		$items = array();
		foreach ( $videos as $video ) {
			if ( ! $video instanceof \Timber\Post ) {
				continue;
			}

			$image = ImporterBridge::get_post_image_url( $video );
			if ( $image === '' ) {
				continue;
			}

			$title = trim( (string) $video->title() );
			$items[] = array(
				'title' => $title,
				'link'  => (string) $video->link(),
				'image' => $image,
				'alt'   => $title !== '' ? $title : __( 'ویدیو', 'asrekhodro' ),
			);
		}

		// Oldest on the left, newest on the right (RTL carousel order).
		return array_reverse( $items );
	}

	public static function get_video_kiosk_archive_url(): string {
		$archive = get_post_type_archive_link( 'ak_video' );

		return is_string( $archive ) && $archive !== '' ? $archive : home_url( '/' );
	}
}
