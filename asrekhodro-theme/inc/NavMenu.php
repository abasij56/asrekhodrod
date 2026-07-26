<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main navigation helpers (submenus + news date filter placement).
 */
final class NavMenu {

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function items(): array {
		$menu = \Timber\Timber::get_menu( 'main-nav' );
		if ( $menu instanceof \Timber\Menu && ! empty( $menu->items ) ) {
			return self::map_items( $menu->items );
		}

		return self::default_items();
	}

	/**
	 * @param array<int, \Timber\MenuItem> $items
	 * @return list<array<string, mixed>>
	 */
	private static function map_items( array $items ): array {
		$mapped = array();

		foreach ( $items as $item ) {
			if ( ! $item instanceof \Timber\MenuItem ) {
				continue;
			}

			$children       = is_array( $item->children ) ? self::map_items( $item->children ) : array();
			$show_filter    = self::item_shows_date_filter( $item );
			$has_submenu    = $children !== array() || $show_filter;

			$mapped[] = array(
				'title'            => (string) $item->title,
				'link'             => (string) $item->link,
				'current'          => (bool) $item->current,
				'children'         => $children,
				'has_submenu'      => $has_submenu,
				'show_date_filter' => $show_filter,
			);
		}

		return $mapped;
	}

	private static function item_shows_date_filter( \Timber\MenuItem $item ): bool {
		$classes = is_array( $item->classes ?? null ) ? $item->classes : array();
		if ( in_array( 'has-date-filter', $classes, true ) ) {
			return true;
		}

		return self::is_news_archive_link( (string) $item->link );
	}

	public static function is_news_archive_link( string $url ): bool {
		$url = self::normalize_url( $url );
		if ( $url === '' ) {
			return false;
		}

		foreach ( self::news_archive_urls() as $candidate ) {
			if ( $url === $candidate ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return list<string>
	 */
	private static function news_archive_urls(): array {
		$urls = array(
			HomepageData::news_archive_url(),
			(string) get_post_type_archive_link( 'post' ),
		);

		$posts_page = (int) get_option( 'page_for_posts' );
		if ( $posts_page > 0 ) {
			$urls[] = (string) get_permalink( $posts_page );
		}

		$normalized = array();
		foreach ( $urls as $url ) {
			$url = self::normalize_url( $url );
			if ( $url !== '' ) {
				$normalized[] = $url;
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	private static function normalize_url( string $url ): string {
		$url = strtolower( untrailingslashit( (string) strtok( $url, '?' ) ) );

		return $url;
	}

	/**
	 * URL for «جدیدترین شرایط فروش» — prefers main-nav match, then page/category by title.
	 */
	public static function sales_conditions_url(): string {
		$label = 'جدیدترین شرایط فروش';

		foreach ( self::items() as $item ) {
			if ( trim( (string) ( $item['title'] ?? '' ) ) === $label ) {
				$link = trim( (string) ( $item['link'] ?? '' ) );
				if ( $link !== '' && $link !== '#' ) {
					return $link;
				}
			}
		}

		$page = get_page_by_path( 'jadidtarin-sharayet-foroush' );
		if ( ! $page instanceof \WP_Post ) {
			$pages = get_posts(
				array(
					'post_type'              => 'page',
					'title'                  => $label,
					'post_status'            => 'publish',
					'posts_per_page'         => 1,
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			$page = $pages[0] ?? null;
		}
		if ( $page instanceof \WP_Post ) {
			$url = get_permalink( $page );
			if ( is_string( $url ) && $url !== '' ) {
				return $url;
			}
		}

		$term = get_term_by( 'name', $label, 'category' );
		if ( $term instanceof \WP_Term && ! is_wp_error( $term ) ) {
			$link = get_term_link( $term );
			if ( is_string( $link ) && $link !== '' ) {
				return $link;
			}
		}

		return home_url( '/' );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function default_items(): array {
		$archive_url = HomepageData::news_archive_url();

		return array(
			array(
				'title'            => __( 'اخبار خودرو', 'asrekhodro' ),
				'link'             => $archive_url,
				'current'          => is_home(),
				'children'         => array(),
				'has_submenu'      => true,
				'show_date_filter' => true,
			),
			array(
				'title'            => __( 'بازار خودرو', 'asrekhodro' ),
				'link'             => '#',
				'current'          => false,
				'children'         => array(),
				'has_submenu'      => false,
				'show_date_filter' => false,
			),
			array(
				'title'            => __( 'خودروهای داخلی', 'asrekhodro' ),
				'link'             => '#',
				'current'          => false,
				'children'         => array(),
				'has_submenu'      => false,
				'show_date_filter' => false,
			),
			array(
				'title'            => __( 'خودروهای خارجی', 'asrekhodro' ),
				'link'             => '#',
				'current'          => false,
				'children'         => array(),
				'has_submenu'      => false,
				'show_date_filter' => false,
			),
			array(
				'title'            => __( 'خودروهای برقی', 'asrekhodro' ),
				'link'             => '#',
				'current'          => false,
				'children'         => array(),
				'has_submenu'      => false,
				'show_date_filter' => false,
			),
			array(
				'title'            => __( 'تست و بررسی', 'asrekhodro' ),
				'link'             => '#',
				'current'          => false,
				'children'         => array(),
				'has_submenu'      => false,
				'show_date_filter' => false,
			),
			array(
				'title'            => __( 'ویدئو', 'asrekhodro' ),
				'link'             => '#',
				'current'          => false,
				'children'         => array(),
				'has_submenu'      => false,
				'show_date_filter' => false,
			),
			array(
				'title'            => __( 'مجلات', 'asrekhodro' ),
				'link'             => '#',
				'current'          => false,
				'children'         => array(),
				'has_submenu'      => false,
				'show_date_filter' => false,
			),
		);
	}
}
