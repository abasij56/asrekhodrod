<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves Twig context for a single block placement.
 */
final class BlockDataResolver {

	public static function init(): void {
		add_filter( 'timber/twig/functions', array( self::class, 'register_twig_functions' ) );
	}

	/**
	 * @param array<string, mixed> $functions
	 * @return array<string, mixed>
	 */
	public static function register_twig_functions( array $functions ): array {
		$functions['ak_sidebar_ads_for_placement'] = array(
			'callable' => array( self::class, 'sidebar_ads_for_placement' ),
		);

		return $functions;
	}

	/**
	 * Resolve sidebar ads at render time from the placement row (respects data_count / count).
	 *
	 * @param array<string, mixed>|mixed $placement
	 * @return list<array<string, mixed>>
	 */
	public static function sidebar_ads_for_placement( $placement ): array {
		if ( ! is_array( $placement ) ) {
			return array();
		}

		$resolved = self::resolve( 'ak-sidebar-ads', $placement );
		$items    = $resolved['sidebar_ads'] ?? array();

		return is_array( $items ) ? $items : array();
	}

	/**
	 * @param array<string, mixed> $placement
	 * @return array<string, mixed>
	 */
	public static function resolve( string $block_name, array $placement = array() ): array {
		$placement = LayoutSchema::merge_placement_defaults( $block_name, $placement );
		$meta      = LayoutSchema::block_meta( $block_name );
		$source    = (string) ( $placement['source'] ?? $meta['source'] ?? 'legacy' );

		return match ( $source ) {
			'query'          => self::resolve_query( $block_name, $placement, $meta ),
			'static'         => self::resolve_static( $block_name, $meta ),
			'ads'            => self::resolve_ads( $meta ),
			'sidebar_widget' => self::resolve_sidebar_widget( $block_name, $placement ),
			'sidebar_rail'   => self::resolve_sidebar_rail( $placement ),
			default          => self::resolve_legacy( $block_name, $placement, $meta ),
		};
	}

	/**
	 * @param array<string, mixed> $meta
	 * @return array<string, mixed>
	 */
	private static function resolve_static( string $block_name, array $meta ): array {
		if ( $block_name === 'ak-not-found' ) {
			return array(
				'not_found_archive_url' => HomepageData::news_archive_url(),
			);
		}

		return array();
	}

	/**
	 * @param array<string, mixed> $placement
	 * @param array<string, mixed> $meta
	 * @return array<string, mixed>
	 */
	private static function resolve_query( string $block_name, array $placement, array $meta ): array {
		$defaults = is_array( $meta['defaults'] ?? null ) ? $meta['defaults'] : array();

		$post_type = (string) ( $placement['post_type'] ?? $defaults['post_type'] ?? 'post' );
		$count     = (int) ( $placement['count'] ?? $defaults['count'] ?? 10 );
		$count     = max( 1, min( 40, $count ) );
		$category  = (int) ( $placement['category'] ?? $defaults['category'] ?? 0 );
		$strategy  = (string) ( $placement['strategy'] ?? $defaults['strategy'] ?? 'latest' );

		$context_key = (string) ( $meta['context_key'] ?? '' );
		if ( $context_key === '' ) {
			$context_key = self::default_context_key( $block_name );
		}

		$custom_query_blocks = array(
			'ak-asrekhodro-featured',
			'ak-featured-grid',
			'ak-hero',
			'ak-magazines',
			'ak-videos-2',
			'ak-news-list',
			'ak-ad-strip',
			'ak-sidebar-ads',
			'ak-ticker',
			'ak-picture-frame',
		);

		if ( $strategy === 'manual' && ! in_array( $block_name, $custom_query_blocks, true ) ) {
			$manual = $placement['manual_posts'] ?? array();
			if ( is_array( $manual ) && $manual !== array() ) {
				return array(
					$context_key => self::query_by_ids( $manual, $count, $post_type ),
				);
			}
		}

		if ( $block_name === 'ak-featured-grid' ) {
			return self::resolve_featured_grid( $placement, $meta );
		}

		if ( $block_name === 'ak-asrekhodro-featured' ) {
			return self::resolve_asrekhodro_featured( $placement, $meta );
		}

		if ( $block_name === 'ak-hero' ) {
			return self::resolve_hero( $placement, $meta );
		}

		if ( $block_name === 'ak-news-list' ) {
			return self::resolve_news_list( $placement, $meta );
		}

		if ( $block_name === 'ak-ad-strip' ) {
			return self::resolve_ad_strip( $placement, $meta );
		}

		if ( $block_name === 'ak-sidebar-ads' ) {
			return self::resolve_sidebar_ads( $placement, $meta );
		}

		if ( $block_name === 'ak-ticker' ) {
			return self::resolve_ticker( $placement, $meta );
		}

		if ( $block_name === 'ak-magazines' ) {
			return self::resolve_magazines( $placement, $meta );
		}

		if ( $block_name === 'ak-videos-2' ) {
			return self::resolve_videos_2( $placement, $meta );
		}

		if ( $block_name === 'ak-picture-frame' ) {
			return self::resolve_picture_frame( $placement, $meta );
		}

		return array(
			$context_key => self::query_posts( $post_type, $count, $category, $strategy ),
		);
	}

	/**
	 * News list block — archive link follows selected post type.
	 *
	 * @param array<string, mixed> $placement
	 * @param array<string, mixed> $meta
	 * @return array<string, mixed>
	 */
	private static function resolve_news_list( array $placement, array $meta ): array {
		$defaults    = is_array( $meta['defaults'] ?? null ) ? $meta['defaults'] : array();
		$post_type   = (string) ( $placement['post_type'] ?? $defaults['post_type'] ?? 'post' );
		$count       = (int) ( $placement['count'] ?? $defaults['count'] ?? 40 );
		$count       = max( 1, min( 40, $count ) );
		$category    = (int) ( $placement['category'] ?? $defaults['category'] ?? 0 );
		$strategy    = (string) ( $placement['strategy'] ?? $defaults['strategy'] ?? 'latest' );
		$context_key = (string) ( $meta['context_key'] ?? 'news_list' );

		if ( $strategy === 'manual' ) {
			$manual = $placement['manual_posts'] ?? array();
			if ( is_array( $manual ) && $manual !== array() ) {
				$posts = self::query_by_ids( $manual, $count, $post_type );
			} else {
				$posts = self::query_posts( $post_type, $count, $category, $strategy );
			}
		} else {
			$posts = self::query_posts( $post_type, $count, $category, $strategy );
		}

		return array(
			$context_key       => $posts,
			'news_archive_url' => self::archive_url_for_post_type( $post_type ),
		);
	}

	/**
	 * Archive URL for layout-builder post type selection.
	 */
	private static function archive_url_for_post_type( string $post_type ): string {
		$post_type = sanitize_key( $post_type );

		if ( $post_type === '' || $post_type === 'post' ) {
			$posts_page = get_permalink( (int) get_option( 'page_for_posts' ) );

			return $posts_page ? (string) $posts_page : home_url( '/' );
		}

		if ( $post_type === 'ak_magazine' ) {
			return Magazines::get_archive_url();
		}

		$archive = get_post_type_archive_link( $post_type );
		if ( is_string( $archive ) && $archive !== '' ) {
			return $archive;
		}

		return home_url( '/' );
	}

	/**
	 * @param array<string, mixed> $meta
	 * @return array<string, mixed>
	 */
	private static function resolve_ads( array $meta ): array {
		$key = (string) ( $meta['ads_key'] ?? 'content_row_ad' );

		if ( $key === 'content_row_ad' ) {
			return HomepageData::section( 'content_row_ad' );
		}

		return array();
	}

	/**
	 * Featured grid from layout placement settings.
	 *
	 * @param array<string, mixed> $placement
	 * @param array<string, mixed> $meta
	 * @return array<string, mixed>
	 */
	private static function resolve_featured_grid( array $placement, array $meta ): array {
		$defaults  = is_array( $meta['defaults'] ?? null ) ? $meta['defaults'] : array();
		$post_type = (string) ( $placement['post_type'] ?? $defaults['post_type'] ?? 'post' );
		$count     = max( 1, min( 40, (int) ( $placement['count'] ?? $defaults['count'] ?? 14 ) ) );
		$category  = (int) ( $placement['category'] ?? $defaults['category'] ?? 0 );
		$strategy  = (string) ( $placement['strategy'] ?? $defaults['strategy'] ?? 'latest' );

		if ( $strategy === 'manual' ) {
			$manual = $placement['manual_posts'] ?? array();
			if ( is_array( $manual ) && $manual !== array() ) {
				$featured_posts = self::query_by_ids( $manual, $count, $post_type );
			} else {
				$featured_posts = self::query_posts( $post_type, $count, $category, $strategy );
			}
		} else {
			$featured_posts = self::query_posts( $post_type, $count, $category, $strategy );
		}

		return array_merge(
			array( 'featured_posts' => $featured_posts ),
			HomepageData::section( 'content_row_ad' ),
			HomepageData::section( 'news_archive_url' )
		);
	}

	/**
	 * @param array<string, mixed> $placement
	 * @param array<string, mixed> $meta
	 * @return array<string, mixed>
	 */
	private static function resolve_ad_strip( array $placement, array $meta ): array {
		$defaults       = is_array( $meta['defaults'] ?? null ) ? $meta['defaults'] : array();
		$post_type      = (string) ( $placement['post_type'] ?? $defaults['post_type'] ?? 'ad_slot' );
		$count          = self::placement_count( $placement, $defaults, 3 );
		$category       = (int) ( $placement['category'] ?? $defaults['category'] ?? 0 );
		$strategy       = (string) ( $placement['strategy'] ?? $defaults['strategy'] ?? 'latest' );
		$image_size     = (string) ( $meta['image_size'] ?? 'ak-ad-strip' );
		$context_key    = (string) ( $meta['context_key'] ?? 'menu_strip_ads' );
		$position_slug  = (string) ( $meta['ad_position'] ?? 'menu_strip' );
		$items          = array();

		if ( $strategy === 'manual' ) {
			$manual = $placement['manual_posts'] ?? array();
			if ( is_array( $manual ) && $manual !== array() ) {
				$posts = self::query_by_ids( $manual, $count, $post_type );
			} else {
				$posts = self::query_posts( $post_type, $count, $category, $strategy );
			}
			$items = self::ad_strip_items_from_posts( $posts, $image_size );
		} elseif ( $post_type === 'ad_slot' ) {
			$items = ImporterBridge::get_ads_by_position( $position_slug, $count );
		} else {
			$posts = self::query_posts( $post_type, $count, $category, $strategy );
			$items = self::ad_strip_items_from_posts( $posts, $image_size );
		}

		if ( $items === array() && $strategy !== 'manual' ) {
			$items = ImporterBridge::get_ads_by_position( $position_slug, $count );
		}

		return array( $context_key => array_slice( $items, 0, $count ) );
	}

	/**
	 * Sidebar ad slots — same query/manual options as menu strip; fallback to ad_position taxonomy.
	 *
	 * @param array<string, mixed> $placement
	 * @param array<string, mixed> $meta
	 * @return array<string, mixed>
	 */
	private static function resolve_sidebar_ads( array $placement, array $meta ): array {
		$defaults      = is_array( $meta['defaults'] ?? null ) ? $meta['defaults'] : array();
		$post_type     = (string) ( $placement['post_type'] ?? $defaults['post_type'] ?? 'ad_slot' );
		$count         = self::placement_count( $placement, $defaults, 6 );
		$category      = (int) ( $placement['category'] ?? $defaults['category'] ?? 0 );
		$strategy      = (string) ( $placement['strategy'] ?? $defaults['strategy'] ?? 'latest' );
		$image_size    = (string) ( $meta['image_size'] ?? 'medium' );
		$context_key   = (string) ( $meta['context_key'] ?? 'sidebar_ads' );
		$position_slug = (string) ( $meta['ad_position'] ?? 'sidebar_left' );
		$items         = array();

		if ( $strategy === 'manual' ) {
			$manual = $placement['manual_posts'] ?? array();
			if ( is_array( $manual ) && $manual !== array() ) {
				$posts = self::query_by_ids( $manual, $count, $post_type );
			} else {
				$posts = self::query_posts( $post_type, $count, $category, $strategy );
			}
			$items = self::ad_strip_items_from_posts( $posts, $image_size );
		} elseif ( $post_type === 'ad_slot' ) {
			$items = ImporterBridge::get_ads_by_position( $position_slug, $count );
		} else {
			$posts = self::query_posts( $post_type, $count, $category, $strategy );
			$items = self::ad_strip_items_from_posts( $posts, $image_size );
		}

		if ( $items === array() && $strategy !== 'manual' ) {
			$items = ImporterBridge::get_ads_by_position( $position_slug, $count );
		}

		return array( $context_key => array_slice( $items, 0, $count ) );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function ad_strip_items_from_posts( \Timber\PostQuery $posts, string $image_size ): array {
		$items = array();

		foreach ( $posts as $post ) {
			if ( ! $post instanceof \Timber\Post ) {
				continue;
			}
			$items[] = ImporterBridge::ad_strip_item_from_post( $post, $image_size );
		}

		return $items;
	}

	/**
	 * Lead post + magazine flip for remaining posts.
	 *
	 * @param array<string, mixed> $placement
	 * @param array<string, mixed> $meta
	 * @return array<string, mixed>
	 */
	private static function resolve_asrekhodro_featured( array $placement, array $meta ): array {
		require_once ASREKHODRO_THEME_DIR . '/inc/blocks/ak-asrekhodro-featured/View.php';

		$defaults  = is_array( $meta['defaults'] ?? null ) ? $meta['defaults'] : array();
		$post_type = (string) ( $placement['post_type'] ?? $defaults['post_type'] ?? 'post' );
		$count     = (int) ( $placement['count'] ?? $defaults['count'] ?? 6 );
		$count     = max( 2, min( 40, $count ) );
		$category  = (int) ( $placement['category'] ?? $defaults['category'] ?? 0 );
		$strategy  = (string) ( $placement['strategy'] ?? $defaults['strategy'] ?? 'latest' );

		if ( $strategy === 'manual' ) {
			$manual = $placement['manual_posts'] ?? array();
			if ( is_array( $manual ) && $manual !== array() ) {
				$posts = self::query_by_ids( $manual, $count, $post_type );
			} else {
				$posts = self::query_posts( $post_type, $count, $category, $strategy );
			}
		} else {
			$posts = self::query_posts( $post_type, $count, $category, $strategy );
		}

		return array_merge(
			\AsreKhodro\Theme\Blocks\AkAsrekhodroFeatured\View::context( $posts, $count ),
			array( 'news_archive_url' => self::archive_url_for_post_type( $post_type ) )
		);
	}

	/**
	 * Hero: query posts → main (image, excerpt, link) + side (badge, title, date).
	 *
	 * @param array<string, mixed> $placement
	 * @param array<string, mixed> $meta
	 * @return array<string, mixed>
	 */
	private static function resolve_hero( array $placement, array $meta ): array {
		require_once ASREKHODRO_THEME_DIR . '/inc/blocks/ak-hero/View.php';

		$defaults  = is_array( $meta['defaults'] ?? null ) ? $meta['defaults'] : array();
		$post_type = (string) ( $placement['post_type'] ?? $defaults['post_type'] ?? 'post' );
		$count     = (int) ( $placement['count'] ?? $defaults['count'] ?? 5 );
		$count     = max( 1, min( 10, $count ) );
		$category  = (int) ( $placement['category'] ?? $defaults['category'] ?? 0 );
		$strategy  = (string) ( $placement['strategy'] ?? $defaults['strategy'] ?? 'latest' );

		if ( $strategy === 'manual' ) {
			$manual = $placement['manual_posts'] ?? array();
			if ( is_array( $manual ) && $manual !== array() ) {
				$posts = self::query_by_ids( $manual, $count, $post_type );
			} else {
				$posts = self::query_posts( $post_type, $count, $category, $strategy );
			}
		} else {
			$posts = self::query_posts( $post_type, $count, $category, $strategy );
		}

		return \AsreKhodro\Theme\Blocks\AkHero\View::context( $posts );
	}

	/**
	 * Magazines grid — image, title, date; archive link label «آرشیو».
	 *
	 * @param array<string, mixed> $placement
	 * @param array<string, mixed> $meta
	 * @return array<string, mixed>
	 */
	private static function resolve_magazines( array $placement, array $meta ): array {
		require_once ASREKHODRO_THEME_DIR . '/inc/blocks/ak-magazines/View.php';

		$defaults  = is_array( $meta['defaults'] ?? null ) ? $meta['defaults'] : array();
		$post_type = (string) ( $placement['post_type'] ?? $defaults['post_type'] ?? 'ak_magazine' );
		$count     = (int) ( $placement['count'] ?? $defaults['count'] ?? 5 );
		$count     = max( 1, min( 40, $count ) );
		$category  = (int) ( $placement['category'] ?? $defaults['category'] ?? 0 );
		$strategy  = (string) ( $placement['strategy'] ?? $defaults['strategy'] ?? 'latest' );

		if ( $strategy === 'manual' ) {
			$manual = $placement['manual_posts'] ?? array();
			if ( is_array( $manual ) && $manual !== array() ) {
				$posts = self::query_by_ids( $manual, $count, $post_type );
			} else {
				$posts = self::query_posts( $post_type, $count, $category, $strategy );
			}
		} else {
			$posts = self::query_posts( $post_type, $count, $category, $strategy );
		}

		return array(
			'magazines'             => \AsreKhodro\Theme\Blocks\AkMagazines\View::items( $posts, $strategy ),
			'magazines_archive_url' => Magazines::get_archive_url(),
		);
	}

	/**
	 * Videos carousel (magazines-style layout).
	 *
	 * @param array<string, mixed> $placement
	 * @param array<string, mixed> $meta
	 * @return array<string, mixed>
	 */
	private static function resolve_videos_2( array $placement, array $meta ): array {
		require_once ASREKHODRO_THEME_DIR . '/inc/blocks/ak-videos-2/View.php';

		$defaults  = is_array( $meta['defaults'] ?? null ) ? $meta['defaults'] : array();
		$post_type = (string) ( $placement['post_type'] ?? $defaults['post_type'] ?? 'ak_video' );
		$count     = (int) ( $placement['count'] ?? $defaults['count'] ?? 5 );
		$count     = max( 1, min( 40, $count ) );
		$category  = (int) ( $placement['category'] ?? $defaults['category'] ?? 0 );
		$strategy  = (string) ( $placement['strategy'] ?? $defaults['strategy'] ?? 'latest' );

		if ( $strategy === 'manual' ) {
			$manual = $placement['manual_posts'] ?? array();
			if ( is_array( $manual ) && $manual !== array() ) {
				$posts = self::query_by_ids( $manual, $count, $post_type );
			} else {
				$posts = self::query_posts( $post_type, $count, $category, $strategy );
			}
		} else {
			$posts = self::query_posts( $post_type, $count, $category, $strategy );
		}

		$archive = get_post_type_archive_link( 'ak_video' );

		return array(
			'videos_2'             => \AsreKhodro\Theme\Blocks\AkVideos2\View::items( $posts, $strategy ),
			'videos_2_archive_url' => is_string( $archive ) && $archive !== '' ? $archive : home_url( '/' ),
		);
	}

	/**
	 * Ticker: query posts via layout params → title + link only.
	 *
	 * @param array<string, mixed> $placement
	 * @param array<string, mixed> $meta
	 * @return array<string, mixed>
	 */
	private static function resolve_ticker( array $placement, array $meta ): array {
		require_once ASREKHODRO_THEME_DIR . '/inc/blocks/ak-ticker/View.php';

		$defaults  = is_array( $meta['defaults'] ?? null ) ? $meta['defaults'] : array();
		$post_type = (string) ( $placement['post_type'] ?? $defaults['post_type'] ?? 'post' );
		$count     = (int) ( $placement['count'] ?? $defaults['count'] ?? 5 );
		$count     = max( 1, min( 40, $count ) );
		$category  = (int) ( $placement['category'] ?? $defaults['category'] ?? 0 );
		$strategy  = (string) ( $placement['strategy'] ?? $defaults['strategy'] ?? 'latest' );

		if ( $strategy === 'manual' ) {
			$manual = $placement['manual_posts'] ?? array();
			if ( is_array( $manual ) && $manual !== array() ) {
				$posts = self::query_by_ids( $manual, $count, $post_type );
			} else {
				$posts = self::query_posts( $post_type, $count, $category, $strategy );
			}
		} else {
			$posts = self::query_posts( $post_type, $count, $category, $strategy );
		}

		return array(
			'ticker_items' => \AsreKhodro\Theme\Blocks\AkTicker\View::items( $posts ),
		);
	}

	/**
	 * Picture frame slider from layout placement settings.
	 *
	 * @param array<string, mixed> $placement
	 * @param array<string, mixed> $meta
	 * @return array<string, mixed>
	 */
	private static function resolve_picture_frame( array $placement, array $meta ): array {
		require_once ASREKHODRO_THEME_DIR . '/inc/blocks/ak-picture-frame/View.php';

		$defaults  = is_array( $meta['defaults'] ?? null ) ? $meta['defaults'] : array();
		$post_type = (string) ( $placement['post_type'] ?? $defaults['post_type'] ?? 'post' );
		$count     = (int) ( $placement['count'] ?? $defaults['count'] ?? 10 );
		$count     = max( 1, min( 40, $count ) );
		$category  = (int) ( $placement['category'] ?? $defaults['category'] ?? 0 );
		$strategy  = (string) ( $placement['strategy'] ?? $defaults['strategy'] ?? 'latest' );

		if ( $strategy === 'manual' ) {
			$manual = $placement['manual_posts'] ?? array();
			if ( is_array( $manual ) && $manual !== array() ) {
				$posts = self::query_by_ids( $manual, $count, $post_type );
			} else {
				$posts = self::query_posts( $post_type, $count, $category, $strategy );
			}
		} else {
			$posts = self::query_posts( $post_type, $count, $category, $strategy );
		}

		return \AsreKhodro\Theme\Blocks\AkPictureFrame\View::context( $posts, $category, $strategy );
	}

	/**
	 * @param array<string, mixed> $placement
	 * @return array<string, mixed>
	 */
	private static function resolve_sidebar_widget( string $block_name, array $placement ): array {
		$exclude       = (int) ( $placement['exclude_post_id'] ?? 0 );
		$popular_limit = max( 1, min( 40, (int) ( $placement['count'] ?? 10 ) ) );
		$kiosk_limit   = max( 1, min( 40, (int) ( $placement['count'] ?? 10 ) ) );
		$latest_limit  = max( 1, min( 40, (int) ( $placement['count'] ?? 15 ) ) );

		return match ( $block_name ) {
			'ak-sidebar-popular-2day' => array(
				'popular_2day_posts'       => SidebarWidgets::get_popular_posts( 'week', $popular_limit, $exclude ),
				'popular_2day_archive_url' => HomepageData::news_archive_url(),
			),
			'ak-sidebar-kiosk' => array(
				'kiosk_items'       => SidebarWidgets::get_kiosk_items( $kiosk_limit ),
				'kiosk_archive_url' => SidebarWidgets::get_kiosk_archive_url(),
			),
			'ak-sidebar-videos' => array(
				'video_kiosk_items'       => SidebarWidgets::get_video_kiosk_items( $kiosk_limit ),
				'video_kiosk_archive_url' => SidebarWidgets::get_video_kiosk_archive_url(),
			),
			'ak-sidebar-latest-news' => array(
				'latest_sidebar_posts' => SidebarWidgets::get_latest_posts( $latest_limit, $exclude ),
			),
			default => array(),
		};
	}

	/**
	 * @param array<string, mixed> $placement
	 * @return array<string, mixed>
	 */
	private static function resolve_sidebar_rail( array $placement ): array {
		$args = array();
		if ( ! empty( $placement['exclude_post_id'] ) ) {
			$args['exclude_post_id'] = (int) $placement['exclude_post_id'];
		}
		if ( isset( $placement['show_kiosk'] ) ) {
			$args['show_kiosk'] = (bool) $placement['show_kiosk'];
		}
		if ( isset( $placement['count'] ) && $placement['count'] !== '' ) {
			$limit = max( 1, min( 40, (int) $placement['count'] ) );
			$args['popular_limit'] = $limit;
			$args['kiosk_limit']   = $limit;
		}

		return array(
			'sidebar_rail' => SidebarWidgets::get_rail_context( $args ),
		);
	}

	/**
	 * @param array<string, mixed> $placement
	 * @param array<string, mixed> $meta
	 * @return array<string, mixed>
	 */
	private static function resolve_legacy( string $block_name, array $placement, array $meta ): array {
		if ( ! empty( $meta['defaults'] ) || (string) ( $meta['source'] ?? '' ) === 'query' ) {
			$query_meta = array_merge( $meta, array( 'source' => 'query' ) );
			if ( empty( $query_meta['context_key'] ) ) {
				$query_meta['context_key'] = self::default_context_key( $block_name );
			}

			return self::resolve_query( $block_name, $placement, $query_meta );
		}

		return array();
	}

	private static function default_context_key( string $block_name ): string {
		return match ( $block_name ) {
			'ak-featured-grid' => 'featured_posts',
			'ak-news-list'     => 'news_list',
			'ak-ticker'        => 'ticker_items',
			'ak-magazines'     => 'magazines',
			'ak-videos-2'      => 'videos_2',
			'ak-videos'        => 'videos',
			'ak-reviews'       => 'reviews',
			'ak-ad-strip'      => 'menu_strip_ads',
			'ak-sidebar-ads'   => 'sidebar_ads',
			default            => str_replace( array( 'ak-', '-' ), array( '', '_' ), $block_name ),
		};
	}

	private static function query_posts(
		string $post_type,
		int $count,
		int $category,
		string $strategy,
		int $offset = 0
	): \Timber\PostQuery {
		$args = array(
			'post_type'      => $post_type,
			'posts_per_page' => $count,
			'post_status'    => 'publish',
			'offset'         => $offset,
		);

		if ( $post_type === 'post' ) {
			if ( $category > 0 ) {
				$args['cat'] = $category;
			}

			return ImporterBridge::query_posts(
				array_merge(
					$args,
					array(
						'orderby' => $strategy === 'oldest' ? 'date' : 'date',
						'order'   => $strategy === 'oldest' ? 'ASC' : 'DESC',
					)
				)
			);
		}

		$args['orderby'] = 'date';
		$args['order']   = $strategy === 'oldest' ? 'ASC' : 'DESC';

		if ( $category > 0 && $post_type === 'post' ) {
			$args['cat'] = $category;
		}

		return \Timber\Timber::get_posts( $args );
	}

	/**
	 * @param array<int, int> $ids
	 */
	private static function query_by_ids( array $ids, int $limit, string $post_type_hint = '' ): \Timber\PostQuery {
		$ids = array_values(
			array_filter(
				array_map( 'intval', $ids ),
				static fn( int $id ): bool => $id > 0
			)
		);

		if ( $limit > 0 ) {
			$ids = array_slice( $ids, 0, $limit );
		}

		if ( $ids === array() ) {
			return ImporterBridge::query_posts( array( 'posts_per_page' => 0 ) );
		}

		$post_types = array();
		foreach ( $ids as $id ) {
			$type = get_post_type( $id );
			if ( is_string( $type ) && $type !== '' ) {
				$post_types[ $type ] = true;
			}
		}

		$post_type_hint = sanitize_key( $post_type_hint );
		if ( $post_types === array() && $post_type_hint !== '' ) {
			$post_types[ $post_type_hint ] = true;
		}

		if ( $post_types === array() ) {
			$post_types = array_fill_keys( array_keys( LayoutSchema::post_type_choices() ), true );
		}

		$query_post_type = array_keys( $post_types );
		if ( count( $query_post_type ) === 1 ) {
			$query_post_type = $query_post_type[0];
		}

		return \Timber\Timber::get_posts(
			array(
				'post_type'      => $query_post_type,
				'post_status'    => 'publish',
				'posts_per_page' => count( $ids ),
				'post__in'       => $ids,
				'orderby'        => 'post__in',
				'no_found_rows'  => true,
			)
		);
	}

	/**
	 * @param array<string, mixed> $placement
	 * @param array<string, mixed> $defaults
	 */
	private static function placement_count( array $placement, array $defaults, int $fallback = 10, int $max = 40 ): int {
		if ( isset( $placement['data_count'] ) && $placement['data_count'] !== '' && $placement['data_count'] !== null ) {
			$count = (int) $placement['data_count'];
		} elseif ( array_key_exists( 'count', $placement ) && $placement['count'] !== '' && $placement['count'] !== null ) {
			$count = (int) $placement['count'];
		} else {
			$count = (int) ( $defaults['count'] ?? $fallback );
		}

		return max( 1, min( $max, $count ) );
	}
}
