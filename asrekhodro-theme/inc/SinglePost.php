<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SinglePost {

	private const UNDER_TITLE_BLOCK   = 'acf/ak-under-title';
	private const RELATED_CACHE_GROUP = 'ak_related_posts';
	private const RELATED_CACHE_TTL   = 43200; // 12 hours

	/** @var array<int, string> */
	private static array $under_title_cache = array();

	public static function init(): void {
		add_filter( 'body_class', array( self::class, 'filter_body_class' ) );
		add_filter( 'timber/context', array( self::class, 'filter_timber_context' ) );
		add_filter( 'timber/twig/functions', array( self::class, 'register_twig_functions' ) );
		add_filter( 'the_content', array( self::class, 'strip_duplicate_over_title' ), 5 );
		add_filter( 'render_block', array( self::class, 'suppress_under_title_block_render' ), 10, 2 );
		add_action( 'save_post_post', array( self::class, 'flush_related_cache' ), 20, 1 );
	}

	/**
	 * Imported posts often repeat the rotiter as the first line of the body.
	 * The rotiter is already rendered above the title in the single hero, so
	 * drop the duplicated first line/paragraph from the content.
	 */
	public static function strip_duplicate_over_title( string $content ): string {
		if ( ! is_singular( 'post' ) ) {
			return $content;
		}

		$over_title = trim( (string) get_post_meta( (int) get_queried_object_id(), PostOverTitleMeta::META_KEY, true ) );
		if ( $over_title === '' || trim( $content ) === '' ) {
			return $content;
		}

		$normalized_over = self::normalize_text( $over_title );
		if ( $normalized_over === '' ) {
			return $content;
		}

		$trimmed = ltrim( $content );

		// First paragraph as an HTML block (<p>…</p>).
		if ( preg_match( '#^<p\b[^>]*>(.*?)</p>#is', $trimmed, $matches ) ) {
			if ( self::normalize_text( $matches[1] ) === $normalized_over ) {
				return ltrim( substr( $trimmed, strlen( $matches[0] ) ) );
			}

			return $content;
		}

		// Plain-text content (before wpautop): first line up to the newline.
		$newline_pos = strpos( $trimmed, "\n" );
		$first_line  = $newline_pos === false ? $trimmed : substr( $trimmed, 0, $newline_pos );

		if ( self::normalize_text( $first_line ) === $normalized_over ) {
			return $newline_pos === false ? '' : ltrim( substr( $trimmed, $newline_pos + 1 ) );
		}

		return $content;
	}

	private static function normalize_text( string $text ): string {
		$text = wp_strip_all_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		// Unify invisible characters common in imported Persian text.
		$text = str_replace( array( "\xC2\xA0", "\xE2\x80\x8C", "\xE2\x80\x8F", "\xE2\x80\x8E" ), ' ', $text );
		$text = (string) preg_replace( '/\s+/u', ' ', $text );

		return trim( $text );
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

		$context['post_lead']         = ImporterBridge::get_list_excerpt( $post, ImporterBridge::excerpt_max( 'single' ) );
		$context['post_datetime']     = ImporterBridge::format_post_datetime( $post );
		$context['post_tags']                  = self::get_tags_with_counts( $post );
		$context['post_under_title']           = self::get_under_title_from_block( $post );
		$context['post_has_under_title_block'] = self::has_under_title_block( $post );
		$context['post_gallery_images']        = ImporterBridge::get_post_gallery_images( $post );
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

		if ( $option_key === 'share_instagram' ) {
			$url = FooterSocial::url_for_label( $options, 'instagram' );
			if ( $url !== '' ) {
				return $url;
			}
		}

		if ( $option_key === 'share_telegram' ) {
			$url = FooterSocial::url_for_label( $options, 'telegram' );
			if ( $url !== '' ) {
				return $url;
			}
		}

		return esc_url( $default );
	}

	public static function has_under_title_block( \Timber\Post $post ): bool {
		if ( ! function_exists( 'has_block' ) ) {
			return false;
		}

		return has_block( self::UNDER_TITLE_BLOCK, (int) $post->ID );
	}

	public static function get_under_title_from_block( \Timber\Post $post ): string {
		$post_id = (int) $post->ID;
		if ( array_key_exists( $post_id, self::$under_title_cache ) ) {
			return self::$under_title_cache[ $post_id ];
		}

		$content = (string) $post->post_content;
		if ( $content === '' || ! function_exists( 'parse_blocks' ) ) {
			self::$under_title_cache[ $post_id ] = '';

			return self::$under_title_cache[ $post_id ];
		}

		self::$under_title_cache[ $post_id ] = self::find_under_title_in_blocks( parse_blocks( $content ) );

		return self::$under_title_cache[ $post_id ];
	}

	/**
	 * @param array<int, mixed> $blocks
	 */
	private static function find_under_title_in_blocks( array $blocks ): string {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			if ( ( $block['blockName'] ?? '' ) === self::UNDER_TITLE_BLOCK ) {
				$data = $block['attrs']['data'] ?? array();
				if ( is_array( $data ) ) {
					$text = trim( (string) ( $data['text'] ?? '' ) );
					if ( $text !== '' ) {
						return $text;
					}
				}
			}

			$inner = $block['innerBlocks'] ?? array();
			if ( is_array( $inner ) && $inner !== array() ) {
				$text = self::find_under_title_in_blocks( $inner );
				if ( $text !== '' ) {
					return $text;
				}
			}
		}

		return '';
	}

	/**
	 * @param array<string, mixed> $block
	 */
	public static function suppress_under_title_block_render( string $content, array $block ): string {
		if ( ! is_singular( 'post' ) ) {
			return $content;
		}

		if ( ( $block['blockName'] ?? '' ) === self::UNDER_TITLE_BLOCK ) {
			return '';
		}

		return $content;
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
	 * Drop cached related IDs when a post is saved (tags/manual related may change).
	 */
	public static function flush_related_cache( int $post_id ): void {
		if ( $post_id <= 0 || wp_is_post_revision( $post_id ) ) {
			return;
		}

		wp_cache_delete( self::related_cache_key( $post_id ), self::RELATED_CACHE_GROUP );
	}

	private static function related_cache_key( int $post_id ): string {
		return 'ids_v2_' . $post_id;
	}

	/**
	 * Related posts: manual → tags (newest per tag, split quota) → same category → latest site-wide.
	 * ID collection is cached (object cache / Redis) so single views stay fast.
	 *
	 * @return \Timber\PostQuery
	 */
	private static function get_related_posts( \Timber\Post $post, int $limit ): \Timber\PostQuery {
		$post_id = (int) $post->ID;
		$limit   = max( 1, $limit );

		$manual = self::get_manual_related_ids( $post_id, $limit );
		if ( $manual !== array() ) {
			return self::query_related_by_ids( $manual );
		}

		$cache_key = self::related_cache_key( $post_id );
		$cached    = wp_cache_get( $cache_key, self::RELATED_CACHE_GROUP );
		if ( is_array( $cached ) ) {
			$cached = array_values(
				array_filter(
					array_map( 'intval', $cached ),
					static fn( int $id ): bool => $id > 0 && $id !== $post_id
				)
			);

			return self::query_related_by_ids( array_slice( $cached, 0, $limit ) );
		}

		$ids = self::collect_related_ids( $post_id, $limit );
		wp_cache_set( $cache_key, $ids, self::RELATED_CACHE_GROUP, self::RELATED_CACHE_TTL );

		return self::query_related_by_ids( $ids );
	}

	/**
	 * @return list<int>
	 */
	private static function get_manual_related_ids( int $post_id, int $limit ): array {
		if ( ! function_exists( 'get_field' ) ) {
			return array();
		}

		$related_ids = get_field( 'related_posts', $post_id );
		if ( ! is_array( $related_ids ) || $related_ids === array() ) {
			return array();
		}

		$related_ids = array_values(
			array_filter(
				array_map( 'intval', $related_ids ),
				static fn( int $related_id ): bool => $related_id > 0 && $related_id !== $post_id
			)
		);

		return array_slice( $related_ids, 0, $limit );
	}

	/**
	 * @return list<int>
	 */
	private static function collect_related_ids( int $post_id, int $limit ): array {
		$ids = array();

		$tag_ids = wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $tag_ids ) && is_array( $tag_ids ) && $tag_ids !== array() ) {
			$tag_ids = array_values( array_filter( array_map( 'intval', $tag_ids ) ) );
			$n       = count( $tag_ids );
			if ( $n > 0 ) {
				$per_tag   = max( 1, (int) floor( $limit / $n ) );
				$remainder = $limit - ( $per_tag * $n );

				foreach ( $tag_ids as $index => $tag_id ) {
					if ( count( $ids ) >= $limit ) {
						break;
					}

					$quota = $per_tag + ( $index < $remainder ? 1 : 0 );
					$need  = min( $quota, $limit - count( $ids ) );
					if ( $need <= 0 ) {
						continue;
					}

					$found = self::query_related_ids(
						array(
							'tag_id'         => $tag_id,
							'posts_per_page' => $need,
							'post__not_in'   => array_merge( array( $post_id ), $ids ),
						)
					);
					$ids = array_merge( $ids, $found );
				}
			}
		}

		if ( count( $ids ) < $limit ) {
			$categories = wp_get_post_categories( $post_id );
			if ( is_array( $categories ) && $categories !== array() ) {
				$found = self::query_related_ids(
					array(
						'category__in'   => $categories,
						'posts_per_page' => $limit - count( $ids ),
						'post__not_in'   => array_merge( array( $post_id ), $ids ),
					)
				);
				$ids = array_merge( $ids, $found );
			}
		}

		if ( count( $ids ) < $limit ) {
			$found = self::query_related_ids(
				array(
					'posts_per_page' => $limit - count( $ids ),
					'post__not_in'   => array_merge( array( $post_id ), $ids ),
				)
			);
			$ids = array_merge( $ids, $found );
		}

		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	/**
	 * Lightweight ID-only query for related collection.
	 *
	 * @param array<string, mixed> $args
	 * @return list<int>
	 */
	private static function query_related_ids( array $args ): array {
		$query_args = array_merge(
			array(
				'post_type'              => 'post',
				'post_status'            => 'publish',
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			),
			$args
		);

		$posts_per_page = (int) ( $query_args['posts_per_page'] ?? 0 );
		if ( $posts_per_page <= 0 ) {
			return array();
		}

		$found = get_posts( $query_args );
		if ( ! is_array( $found ) || $found === array() ) {
			return array();
		}

		return array_values( array_map( 'intval', $found ) );
	}

	/**
	 * @param list<int> $ids
	 * @return \Timber\PostQuery
	 */
	private static function query_related_by_ids( array $ids ): \Timber\PostQuery {
		if ( $ids === array() ) {
			return ImporterBridge::query_posts(
				array(
					'post__in'       => array( 0 ),
					'posts_per_page' => 1,
				)
			);
		}

		return ImporterBridge::query_posts(
			array(
				'posts_per_page' => count( $ids ),
				'post__in'       => $ids,
				'orderby'        => 'post__in',
			)
		);
	}
}
