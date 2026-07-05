<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NewsPermalinks {

	public const BASE_PATH = 'News';

	public static function init(): void {
		add_action( 'init', array( self::class, 'register_rewrites' ) );
		add_action( 'init', array( self::class, 'maybe_repair_category_slugs' ), 20 );
		add_filter( 'query_vars', array( self::class, 'register_query_vars' ) );
		add_filter( 'post_link', array( self::class, 'filter_permalink' ), 10, 2 );
		add_filter( 'post_type_link', array( self::class, 'filter_permalink' ), 10, 2 );
		add_action( 'parse_request', array( self::class, 'parse_request' ) );
		add_filter( 'redirect_canonical', array( self::class, 'filter_redirect_canonical' ), 10, 2 );
	}

	/**
	 * @param array<int, string> $vars
	 * @return array<int, string>
	 */
	public static function register_query_vars( array $vars ): array {
		$vars[] = 'ak_news_content_id';

		return $vars;
	}

	public static function register_rewrites(): void {
		add_rewrite_tag( '%ak_news_content_id%', '([0-9]+)' );

		add_rewrite_rule(
			'^News/([0-9]+)/([^/]+)/?$',
			'index.php?ak_news_content_id=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			'^News/([0-9]+)/?$',
			'index.php?ak_news_content_id=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			'^Home/News/([0-9]+)/([^/]+)/?$',
			'index.php?ak_news_content_id=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			'^Home/News/([0-9]+)/?$',
			'index.php?ak_news_content_id=$matches[1]',
			'top'
		);
	}

	public static function parse_request( \WP $wp ): void {
		if ( empty( $wp->query_vars['ak_news_content_id'] ) ) {
			return;
		}

		$content_id = (int) $wp->query_vars['ak_news_content_id'];
		$post_id    = self::find_post_id_by_content_id( $content_id );

		if ( $post_id <= 0 ) {
			return;
		}

		$wp->query_vars['p'] = $post_id;
	}

	/**
	 * Keep /News/{id}/{slug} URLs instead of redirecting to the default post permalink structure.
	 *
	 * @param mixed $redirect_url
	 */
	public static function filter_redirect_canonical( $redirect_url, string $requested_url ) {
		if ( preg_match( '#/(?:Home/)?News/\d+#i', $requested_url ) ) {
			return false;
		}

		return $redirect_url;
	}

	public static function filter_permalink( string $permalink, \WP_Post $post ): string {
		if ( $post->post_type !== 'post' ) {
			return $permalink;
		}

		$legacy = self::build_url_for_post( (int) $post->ID );
		if ( $legacy === null ) {
			return $permalink;
		}

		return $legacy;
	}

	public static function slug_from_title( string $title ): string {
		$title = trim( wp_strip_all_tags( $title ) );
		if ( $title === '' ) {
			return '';
		}

		$slug = preg_replace( '/[^\p{L}\p{N}]+/u', '-', $title );
		if ( ! is_string( $slug ) ) {
			return '';
		}

		$slug = trim( $slug, '-' );
		$slug = preg_replace( '/-+/', '-', $slug );

		return is_string( $slug ) && $slug !== '' ? $slug : '';
	}

	public static function unique_category_slug( string $title, int $term_id = 0, int $fallback_id = 0 ): string {
		$slug = self::slug_from_title( $title );
		if ( $slug === '' ) {
			return $fallback_id > 0 ? 'cat-' . $fallback_id : 'category-' . max( 0, $term_id );
		}

		return wp_unique_term_slug(
			$slug,
			(object) array(
				'term_id'  => $term_id,
				'taxonomy' => 'category',
			)
		);
	}

	public static function maybe_repair_category_slugs(): void {
		if ( get_option( 'ak_category_slugs_repaired_v3' ) ) {
			return;
		}

		$count = self::repair_imported_category_slugs();
		update_option( 'ak_category_slugs_repaired_v3', 1, false );

		if ( $count > 0 ) {
			flush_rewrite_rules( false );
		}
	}

	public static function repair_imported_category_slugs(): int {
		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'meta_query' => array(
					array(
						'key'     => '_asrekhodro_category_id',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		if ( is_wp_error( $terms ) || $terms === array() ) {
			return 0;
		}

		$count = 0;

		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$old_id = (int) get_term_meta( $term->term_id, '_asrekhodro_category_id', true );
			if ( $old_id <= 0 ) {
				continue;
			}

			if ( ! preg_match( '/^cat-\d+$/i', $term->slug ) ) {
				continue;
			}

			$slug = self::unique_category_slug( $term->name, $term->term_id, $old_id );
			$result = wp_update_term(
				$term->term_id,
				'category',
				array(
					'slug' => $slug,
				)
			);

			if ( ! is_wp_error( $result ) ) {
				++$count;
			}
		}

		return $count;
	}

	public static function build_legacy_path( int $content_id, string $slug ): string {
		return '/' . self::BASE_PATH . '/' . $content_id . '/' . trim( $slug, '/' );
	}

	public static function build_url( int $content_id, string $slug ): string {
		return home_url( user_trailingslashit( self::BASE_PATH . '/' . $content_id . '/' . $slug ) );
	}

	public static function build_url_for_post( int $post_id ): ?string {
		$content_id = (int) get_post_meta( $post_id, '_asrekhodro_content_id', true );
		if ( $content_id <= 0 ) {
			return null;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}

		$slug = self::resolve_slug( $post );

		return self::build_url( $content_id, $slug );
	}

	public static function resolve_slug( \WP_Post $post ): string {
		$slug = (string) $post->post_name;

		if ( $slug === '' || str_starts_with( $slug, 'content-' ) ) {
			$from_title = self::slug_from_title( $post->post_title );
			if ( $from_title !== '' ) {
				return $from_title;
			}
		}

		return $slug !== '' ? $slug : 'post-' . $post->ID;
	}

	public static function sync_post( int $post_id, int $content_id, string $title ): string {
		$slug = self::slug_from_title( $title );
		if ( $slug === '' ) {
			$slug = 'content-' . $content_id;
		}

		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => $slug,
			)
		);

		update_post_meta( $post_id, '_asrekhodro_legacy_path', self::build_legacy_path( $content_id, $slug ) );

		return $slug;
	}

	public static function find_post_id_by_content_id( int $content_id ): int {
		if ( $content_id <= 0 ) {
			return 0;
		}

		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'meta_key'       => '_asrekhodro_content_id',
				'meta_value'     => $content_id,
				'fields'         => 'ids',
			)
		);

		return ! empty( $posts ) ? (int) $posts[0] : 0;
	}
}
