<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extra homepage data for the rfarda appearance (latest feed, topic row, category blocks).
 */
final class RfPage {

	/**
	 * @return array<string, mixed>
	 */
	public static function get_context(): array {
		$context     = LayoutEngine::page_context( 'front_page' );
		$exclude_ids = self::collect_exclude_ids( $context );

		$context['rf_latest'] = ImporterBridge::query_posts(
			array(
				'posts_per_page' => 8,
				'post__not_in'   => $exclude_ids,
			)
		);

		$exclude_ids = self::merge_post_ids( $exclude_ids, $context['rf_latest'] );

		$featured_cat = self::featured_category_from_layout();

		$topic_args = array(
			'posts_per_page' => 5,
			'offset'         => 5,
			'post__not_in'   => $exclude_ids,
		);
		if ( $featured_cat > 0 ) {
			$topic_args['cat'] = $featured_cat;
		}
		$context['rf_topic_posts'] = ImporterBridge::query_posts( $topic_args );

		$exclude_ids = self::merge_post_ids( $exclude_ids, $context['rf_topic_posts'] );

		$context['rf_recent_posts'] = ImporterBridge::query_posts(
			array(
				'posts_per_page' => 30,
				'post__not_in'   => $exclude_ids,
			)
		);

		$context['rf_topic_title'] = __( 'اخبار خودرو', 'asrekhodro' );
		if ( $featured_cat > 0 ) {
			$term = get_term( $featured_cat, 'category' );
			if ( $term instanceof \WP_Term && ! is_wp_error( $term ) ) {
				$context['rf_topic_title'] = $term->name;
			}
		}

		$context['rf_topic_url'] = $featured_cat > 0
			? get_term_link( $featured_cat, 'category' )
			: ( $context['news_archive_url'] ?? home_url( '/' ) );

		if ( is_wp_error( $context['rf_topic_url'] ) ) {
			$context['rf_topic_url'] = home_url( '/' );
		}

		$context['rf_category_blocks'] = self::build_category_blocks(
			3,
			$exclude_ids,
			(string) ( $context['news_archive_url'] ?? home_url( '/' ) )
		);
		$context['rf_site_name']       = get_bloginfo( 'name' );

		return $context;
	}

	/**
	 * @param array<string, mixed> $context
	 * @return array<int, int>
	 */
	private static function collect_exclude_ids( array $context ): array {
		$ids = array();

		if ( ! empty( $context['hero_main'] ) ) {
			$main = $context['hero_main'];
			if ( $main instanceof \Timber\Post ) {
				$ids[] = (int) $main->ID;
			} elseif ( is_array( $main ) && ! empty( $main['id'] ) ) {
				$ids[] = (int) $main['id'];
			}
		}

		$hero_side = $context['hero_side_items'] ?? $context['hero_side_posts'] ?? array();
		if ( is_iterable( $hero_side ) ) {
			foreach ( $hero_side as $post ) {
				if ( $post instanceof \Timber\Post ) {
					$ids[] = (int) $post->ID;
				} elseif ( is_array( $post ) && ! empty( $post['id'] ) ) {
					$ids[] = (int) $post['id'];
				}
			}
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * @param array<int, int>     $ids
	 * @param iterable<\Timber\Post> $posts
	 * @return array<int, int>
	 */
	private static function merge_post_ids( array $ids, $posts ): array {
		foreach ( $posts as $post ) {
			$ids[] = (int) $post->ID;
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * @param array<int, int> $exclude_ids
	 * @return array<int, array{title: string, url: string, posts: \Timber\PostQuery, numbered?: bool}>
	 */
	private static function build_category_blocks( int $count, array $exclude_ids, string $archive_url ): array {
		$blocks = array();

		foreach ( ThemeCategories::homepage_terms( $count ) as $term ) {
			$posts = ImporterBridge::query_posts(
				array(
					'posts_per_page' => 3,
					'cat'            => (int) $term->term_id,
					'post__not_in'   => $exclude_ids,
				)
			);

			if ( ! $posts || count( $posts ) === 0 ) {
				continue;
			}

			$blocks[] = array(
				'title' => $term->name,
				'url'   => ThemeCategories::term_url( $term ),
				'posts' => $posts,
			);
		}

		$popular = SidebarWidgets::get_popular_posts( 'week', 3 );
		$blocks[] = array(
			'title'    => __( 'پربازدیدترین‌ها', 'asrekhodro' ),
			'url'      => $archive_url,
			'posts'    => $popular,
			'numbered' => true,
		);

		return $blocks;
	}

	private static function featured_category_from_layout(): int {
		foreach ( LayoutResolver::flat_placements( 'front_page' ) as $placement ) {
			$block = (string) ( $placement['block'] ?? '' );
			if ( ! in_array( $block, array( 'ak-asrekhodro-featured', 'ak-featured-grid' ), true ) ) {
				continue;
			}

			$placement = LayoutSchema::merge_placement_defaults( $block, $placement );
			$category  = (int) ( $placement['category'] ?? 0 );
			if ( $category > 0 ) {
				return $category;
			}
		}

		return 0;
	}
}
