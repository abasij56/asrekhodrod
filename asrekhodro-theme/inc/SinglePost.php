<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SinglePost {

	public static function init(): void {
		add_filter( 'body_class', array( self::class, 'filter_body_class' ) );
		add_filter( 'timber/context', array( self::class, 'filter_timber_context' ) );
		add_filter( 'timber/twig/functions', array( self::class, 'register_twig_functions' ) );
	}

	/**
	 * @param array<string, mixed> $functions
	 * @return array<string, mixed>
	 */
	public static function register_twig_functions( array $functions ): array {
		$functions['ak_single_uses_main_backdrop'] = array(
			'callable' => array( self::class, 'uses_main_backdrop' ),
		);

		return $functions;
	}

	/**
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>
	 */
	public static function filter_timber_context( array $context ): array {
		$post = $context['post'] ?? null;
		if ( $post instanceof \Timber\Post && $post->post_type === 'post' ) {
			$context['single_main_backdrop'] = self::uses_main_backdrop( $post );
		}

		return $context;
	}

	/**
	 * @param array<int, string> $classes
	 * @return array<int, string>
	 */
	public static function filter_body_class( array $classes ): array {
		if ( self::uses_main_backdrop() ) {
			$classes[] = 'single-main-backdrop';
		}

		return $classes;
	}

	public static function uses_main_backdrop( $post = null ): bool {
		if ( $post instanceof \Timber\Post ) {
			return $post->post_type === 'post';
		}

		if ( $post instanceof \WP_Post ) {
			return $post->post_type === 'post';
		}

		return is_singular( 'post' );
	}

	/**
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>
	 */
	public static function extend_context( array $context ): array {
		$post = $context['post'] ?? null;
		if ( ! $post instanceof \Timber\Post ) {
			return $context;
		}

		$post_id = (int) $post->ID;

		$context['single_main_backdrop'] = self::uses_main_backdrop( $post );

		$context['post_lead']         = ImporterBridge::get_list_excerpt( $post, 320 );
		$context['post_datetime']     = ImporterBridge::format_post_datetime( $post );
		$context['post_tags']         = self::get_tags_with_counts( $post );
		$context['related_posts']     = self::get_related_posts( $post, 20 );
		$context['latest_posts']      = ImporterBridge::query_posts(
			array(
				'posts_per_page' => 15,
				'post__not_in'   => array( $post_id ),
			)
		);
		$context['share_instagram']    = self::share_url( 'share_instagram', 'https://www.instagram.com/khodroemrooz/' );
		$context['share_telegram']     = self::share_url( 'share_telegram', 'https://telegram.me/asrekhodro' );
		$context['share_telegram_dl']  = self::share_url( 'share_telegram_download', 'https://telegram.org/' );

		return $context;
	}

	private static function share_url( string $option_key, string $default ): string {
		$options = function_exists( 'get_fields' ) ? ( get_fields( 'option' ) ?: array() ) : array();
		$url     = isset( $options[ $option_key ] ) ? (string) $options[ $option_key ] : '';

		if ( $url !== '' ) {
			return esc_url( $url );
		}

		if ( $option_key === 'share_instagram' && ! empty( $options['social_instagram'] ) ) {
			return esc_url( (string) $options['social_instagram'] );
		}

		if ( $option_key === 'share_telegram' && ! empty( $options['social_telegram'] ) ) {
			return esc_url( (string) $options['social_telegram'] );
		}

		return esc_url( $default );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_tags_with_counts( \Timber\Post $post ): array {
		$terms = $post->tags();
		if ( empty( $terms ) ) {
			return array();
		}

		$tags = array();
		foreach ( $terms as $term ) {
			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) {
				continue;
			}

			$tags[] = array(
				'name'  => $term->name,
				'link'  => $link,
				'count' => (int) $term->count,
			);
		}

		return $tags;
	}

	/**
	 * @return \Timber\PostQuery
	 */
	private static function get_related_posts( \Timber\Post $post, int $limit ): \Timber\PostQuery {
		$post_id = (int) $post->ID;

		if ( function_exists( 'get_field' ) ) {
			$related_ids = get_field( 'related_posts', $post_id );
			if ( is_array( $related_ids ) && ! empty( $related_ids ) ) {
				$related_ids = array_values(
					array_filter(
						array_map( 'intval', $related_ids ),
						static fn( int $related_id ): bool => $related_id > 0 && $related_id !== $post_id
					)
				);

				if ( ! empty( $related_ids ) ) {
					return ImporterBridge::query_posts(
						array(
							'posts_per_page' => min( $limit, count( $related_ids ) ),
							'post__in'       => array_slice( $related_ids, 0, $limit ),
							'orderby'        => 'post__in',
						)
					);
				}
			}
		}

		$categories = wp_get_post_categories( $post_id );

		$args = array(
			'posts_per_page' => $limit,
			'post__not_in'   => array( $post_id ),
		);

		if ( ! empty( $categories ) ) {
			$args['category__in'] = $categories;
		}

		$related = ImporterBridge::query_posts( $args );
		if ( count( $related ) >= 5 ) {
			return $related;
		}

		return ImporterBridge::query_posts(
			array(
				'posts_per_page' => $limit,
				'post__not_in'   => array( $post_id ),
			)
		);
	}
}
