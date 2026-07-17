<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReviewPermalinks {

	public const CPT       = 'ak_review';
	public const BASE_PATH = 'review';

	public static function init(): void {
		add_action( 'init', array( self::class, 'register_rewrites' ) );
		add_filter( 'query_vars', array( self::class, 'register_query_vars' ) );
		add_filter( 'post_type_link', array( self::class, 'filter_permalink' ), 10, 2 );
		add_action( 'parse_request', array( self::class, 'parse_request' ), 0 );
		add_filter( 'pre_handle_404', array( self::class, 'pre_handle_404' ), 10, 2 );
		add_filter( 'redirect_canonical', array( self::class, 'filter_redirect_canonical' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( self::class, 'filter_insert_post_data' ), 50, 2 );
	}

	/**
	 * @param array<int, string> $vars
	 * @return array<int, string>
	 */
	public static function register_query_vars( array $vars ): array {
		$vars[] = 'ak_review_route_id';

		return $vars;
	}

	public static function register_rewrites(): void {
		add_rewrite_tag( '%ak_review_route_id%', '([0-9]+)' );

		add_rewrite_rule(
			'^review/([0-9]+)(?:/.*)?$',
			'index.php?ak_review_route_id=$matches[1]',
			'top'
		);

		if ( ! get_option( 'ak_review_rewrite_id_v1' ) ) {
			flush_rewrite_rules( false );
			update_option( 'ak_review_rewrite_id_v1', 1, false );
		}
	}

	public static function parse_request( \WP $wp ): void {
		$route_id = 0;

		if ( ! empty( $wp->query_vars['ak_review_route_id'] ) ) {
			$route_id = (int) $wp->query_vars['ak_review_route_id'];
		} else {
			$route_id = self::route_id_from_request_uri();
			if ( $route_id > 0 ) {
				$wp->query_vars['ak_review_route_id'] = $route_id;
			}
		}

		if ( $route_id <= 0 ) {
			return;
		}

		$post_id = self::resolve_post_id_by_route_id( $route_id );
		if ( $post_id <= 0 ) {
			return;
		}

		$wp->query_vars['p']           = $post_id;
		$wp->query_vars['post_type']   = self::CPT;
		$wp->query_vars['name']        = '';
		$wp->query_vars['pagename']    = '';
		$wp->query_vars['ak_review']   = '';
		$wp->query_vars['error']       = '';
		unset( $wp->query_vars['page_id'], $wp->query_vars['attachment'], $wp->query_vars['attachment_id'] );
	}

	/**
	 * @param mixed     $preempt
	 * @param \WP_Query $query
	 * @return mixed
	 */
	public static function pre_handle_404( $preempt, $query ) {
		if ( $preempt || ! $query instanceof \WP_Query || ! $query->is_main_query() ) {
			return $preempt;
		}

		$route_id = self::route_id_from_request_uri();
		if ( $route_id <= 0 ) {
			return $preempt;
		}

		$post_id = self::resolve_post_id_by_route_id( $route_id );
		$post    = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post instanceof \WP_Post || $post->post_type !== self::CPT || $post->post_status === 'trash' ) {
			return $preempt;
		}

		$query->posts             = array( $post );
		$query->post_count        = 1;
		$query->found_posts       = 1;
		$query->post              = $post;
		$query->queried_object    = $post;
		$query->queried_object_id = (int) $post->ID;
		$query->is_404            = false;
		$query->is_single         = true;
		$query->is_singular       = true;
		$query->is_page           = false;
		$query->is_archive        = false;
		$query->is_home           = false;

		status_header( 200 );
		nocache_headers();

		return true;
	}

	private static function route_id_from_request_uri(): int {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( $uri === '' ) {
			return 0;
		}

		$path = '/' . ltrim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' );

		if ( preg_match( '#/review/(\d+)(?:/|$)#i', $path, $matches ) ) {
			return (int) $matches[1];
		}

		if ( preg_match( '#/review/(\d+)#i', $path, $matches ) ) {
			return (int) $matches[1];
		}

		return 0;
	}

	/**
	 * @param mixed $redirect_url
	 */
	public static function filter_redirect_canonical( $redirect_url, string $requested_url ) {
		if ( preg_match( '#/review/\d+#i', $requested_url ) ) {
			return false;
		}

		return $redirect_url;
	}

	public static function filter_permalink( string $permalink, \WP_Post $post ): string {
		if ( $post->post_type !== self::CPT ) {
			return $permalink;
		}

		$url = self::build_url_for_post( (int) $post->ID );

		return $url ?? $permalink;
	}

	/**
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $postarr
	 * @return array<string, mixed>
	 */
	public static function filter_insert_post_data( array $data, array $postarr ): array {
		if ( ( $data['post_type'] ?? '' ) !== self::CPT ) {
			return $data;
		}

		if ( (string) ( $data['post_status'] ?? '' ) === 'auto-draft' ) {
			return $data;
		}

		$title = trim( (string) ( $data['post_title'] ?? '' ) );
		if ( $title === '' ) {
			return $data;
		}

		$desired = NewsPermalinks::slug_from_title( $title );
		$post_id = (int) ( $postarr['ID'] ?? 0 );
		if ( $desired === '' ) {
			$content_id = $post_id > 0 ? (int) get_post_meta( $post_id, '_asrekhodro_content_id', true ) : 0;
			$desired    = $content_id > 0 ? 'review-' . $content_id : ( $post_id > 0 ? 'review-' . $post_id : '' );
		}

		if ( $desired === '' ) {
			return $data;
		}

		$data['post_name'] = NewsPermalinks::unique_post_slug( $desired, $post_id, self::CPT );

		return $data;
	}

	public static function route_id_for_post( int $post_id ): int {
		$content_id = (int) get_post_meta( $post_id, '_asrekhodro_content_id', true );

		return $content_id > 0 ? $content_id : max( 0, $post_id );
	}

	public static function resolve_post_id_by_route_id( int $route_id ): int {
		if ( $route_id <= 0 ) {
			return 0;
		}

		$posts = get_posts(
			array(
				'post_type'      => self::CPT,
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'meta_key'       => '_asrekhodro_content_id',
				'meta_value'     => $route_id,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $posts ) ) {
			return (int) $posts[0];
		}

		$post = get_post( $route_id );
		if ( $post instanceof \WP_Post && $post->post_type === self::CPT && $post->post_status !== 'trash' ) {
			return (int) $post->ID;
		}

		return 0;
	}

	public static function build_url_for_post( int $post_id ): ?string {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || $post->post_type !== self::CPT ) {
			return null;
		}

		$route_id = self::route_id_for_post( $post_id );
		if ( $route_id <= 0 ) {
			return null;
		}

		$slug = NewsPermalinks::slug_from_title( $post->post_title );
		if ( $slug === '' ) {
			$slug = (string) $post->post_name;
		}
		if ( $slug === '' ) {
			$slug = 'review-' . $post_id;
		}

		return home_url( user_trailingslashit( self::BASE_PATH . '/' . $route_id . '/' . $slug ) );
	}
}
