<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AjaxSearch {

	private const CACHE_GROUP     = 'ak_ajax_search';
	private const CACHE_TTL       = 120;
	private const DROPDOWN_LIMIT  = 8;
	private const SEARCH_POST_TYPES = array( 'post', 'ak_video', 'ak_review' );

	public static function init(): void {
		add_action( 'wp_ajax_ak_search', array( self::class, 'handle' ) );
		add_action( 'wp_ajax_nopriv_ak_search', array( self::class, 'handle' ) );
		add_action( 'pre_get_posts', array( self::class, 'adjust_search_query' ) );
		add_filter( 'posts_search', array( self::class, 'limit_search_to_title' ), 10, 2 );
	}

	public static function adjust_search_query( \WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
			return;
		}

		$query->set( 'post_type', self::SEARCH_POST_TYPES );
	}

	public static function handle(): void {
		check_ajax_referer( 'ak_search', 'nonce' );

		$query = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		if ( mb_strlen( $query ) < 2 ) {
			wp_send_json_success(
				array(
					'items'    => array(),
					'has_more' => false,
				)
			);
		}

		$cache_key = 'q2_' . md5( mb_strtolower( $query ) );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( is_array( $cached ) && isset( $cached['items'] ) ) {
			if ( ! isset( $cached['has_more'] ) ) {
				$cached['has_more'] = count( $cached['items'] ) >= self::DROPDOWN_LIMIT;
			}
			wp_send_json_success( $cached );
		}

		$posts = new \WP_Query(
			array(
				'post_type'              => self::SEARCH_POST_TYPES,
				'post_status'            => 'publish',
				's'                      => $query,
				'posts_per_page'         => self::DROPDOWN_LIMIT + 1,
				'no_found_rows'          => true,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		$slice = array_slice( $posts->posts, 0, self::DROPDOWN_LIMIT );

		$items = array();
		foreach ( $slice as $post ) {
			$items[] = array(
				'title' => get_the_title( $post ),
				'url'   => get_permalink( $post ),
				'type'  => $post->post_type,
				'image' => self::preview_image_url( (int) $post->ID ),
			);
		}

		$payload = array(
			'items'    => $items,
			'has_more' => count( $items ) >= self::DROPDOWN_LIMIT,
		);

		wp_cache_set( $cache_key, $payload, self::CACHE_GROUP, self::CACHE_TTL );

		wp_send_json_success( $payload );
	}

	/**
	 * Search titles only — much faster on large catalogs than default WP search.
	 *
	 * @param string    $search SQL fragment.
	 * @param \WP_Query $query  Current query.
	 */
	public static function limit_search_to_title( string $search, \WP_Query $query ): string {
		if ( $search === '' || ! $query->is_search() || ! self::is_theme_search_query( $query ) ) {
			return $search;
		}

		global $wpdb;

		$term = $query->get( 's' );
		if ( ! is_string( $term ) || $term === '' ) {
			return $search;
		}

		$like = '%' . $wpdb->esc_like( $term ) . '%';

		return $wpdb->prepare( " AND ({$wpdb->posts}.post_title LIKE %s) ", $like );
	}

	private static function is_theme_search_query( \WP_Query $query ): bool {
		if ( $query->is_main_query() && ! is_admin() ) {
			return true;
		}

		return self::query_has_search_post_types( $query );
	}

	private static function query_has_search_post_types( \WP_Query $query ): bool {
		$post_types = $query->get( 'post_type' );
		if ( empty( $post_types ) ) {
			return false;
		}

		$types = is_array( $post_types ) ? $post_types : array( $post_types );
		sort( $types );

		$expected = self::SEARCH_POST_TYPES;
		sort( $expected );

		return $types === $expected;
	}

	private static function preview_image_url( int $post_id ): string {
		if ( $post_id <= 0 ) {
			return '';
		}

		$thumb = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
		if ( is_string( $thumb ) && $thumb !== '' ) {
			return $thumb;
		}

		$meta = get_post_meta( $post_id, '_asrekhodro_image_url', true );
		if ( is_string( $meta ) && $meta !== '' ) {
			return esc_url( $meta );
		}

		return '';
	}
}
