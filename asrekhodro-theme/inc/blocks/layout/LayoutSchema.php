<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema helpers: section↔block map, zone labels, ACF choice builders.
 */
final class LayoutSchema {

	/** @var array<string, string> */
	public const SECTION_BLOCK_MAP = array(
		'ticker'            => 'ak-ticker',
		'hero'              => 'ak-hero',
		'featured'          => 'ak-featured-grid',
		'asrekhodro_featured' => 'ak-asrekhodro-featured',
		'news_list'         => 'ak-news-list',
		'picture_frame'     => 'ak-picture-frame',
		'magazines'         => 'ak-magazines',
		'videos_2'          => 'ak-videos-2',
		'videos'            => 'ak-videos',
		'reviews'           => 'ak-reviews',
		'content_row_ad'    => 'ak-content-row-ad',
		'news_archive_url'  => '',
	);

	/** @var list<string> */
	public const SIDEBAR_RAIL_SPLIT_BLOCKS = array(
		'ak-sidebar-popular-2day',
		'ak-sidebar-kiosk',
		'ak-sidebar-videos',
		'ak-sidebar-latest-news',
	);

	/** @var array<string, string> */
	public const ZONE_LABELS = array(
		'before_main' => 'بالای صفحه',
		'main'        => 'محتوای اصلی',
		'main_after'  => 'زیر محتوای اصلی',
		'sidebar'     => 'سایدبار',
		'after_main'  => 'قبل از فوتر',
	);

	/** @var array<string, string> */
	public const PAGE_LABELS = array(
		'front_page'  => 'صفحه اصلی',
		'archive'     => 'آرشیو',
		'single_post' => 'تک‌نوشته',
		'page_about'  => 'درباره ما',
		'page_contact'=> 'تماس با ما',
		'page_car'      => 'صفحه خودرو',
		'page_carsinfo_directory' => 'دانشنامه خودرو',
		'carsinfo_3d2'  => 'اطلاعات خودرو — 3D2',
		'not_found'     => 'صفحه ۴۰۴',
	);

	/** @var array<string, string> */
	public const STRATEGY_LABELS = array(
		'latest' => 'جدیدترین',
		'oldest' => 'قدیمی‌ترین',
		'manual' => 'انتخابی',
	);

	/** @var array<string, string>|null */
	private static ?array $shared_blocks = null;

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function shared_blocks(): array {
		if ( self::$shared_blocks !== null ) {
			return self::$shared_blocks;
		}

		self::$shared_blocks = BlockRegistry::manifest_entries();

		return self::$shared_blocks;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function block_meta( string $block_name ): array {
		$manifest = Appearance::block_config( $block_name );
		$shared   = self::shared_blocks()[ $block_name ] ?? array();

		return array_merge( $shared, is_array( $manifest ) ? $manifest : array() );
	}

	/**
	 * Whether a block may appear in layout builder zones (excludes ACF / cinfo-*).
	 *
	 * @param array<string, mixed> $meta
	 */
	public static function is_layout_placeable( string $block_name, array $meta = array() ): bool {
		if ( $meta === array() ) {
			$meta = self::block_meta( $block_name );
		}

		if ( (string) ( $meta['source'] ?? '' ) === 'acf' ) {
			return false;
		}

		$config = BlockRegistry::config( $block_name );
		if ( (string) ( $config['type'] ?? '' ) === 'acf' ) {
			return false;
		}

		return true;
	}

	/**
	 * Fill missing query fields from block config defaults (manifest / Add Block window).
	 *
	 * @param array<string, mixed> $placement
	 * @return array<string, mixed>
	 */
	public static function merge_placement_defaults( string $block_name, array $placement ): array {
		$meta     = self::block_meta( $block_name );
		$defaults = is_array( $meta['defaults'] ?? null ) ? $meta['defaults'] : array();

		if ( empty( $placement['post_type'] ) && ! empty( $placement['data_post_type'] ) ) {
			$placement['post_type'] = sanitize_key( (string) $placement['data_post_type'] );
		}
		if ( empty( $placement['strategy'] ) && ! empty( $placement['data_strategy'] ) ) {
			$strategy = (string) $placement['data_strategy'];
			if ( isset( self::STRATEGY_LABELS[ $strategy ] ) ) {
				$placement['strategy'] = $strategy;
			}
		}
		if ( ! isset( $placement['manual_posts'] ) && ! empty( $placement['data_manual_posts'] ) && is_array( $placement['data_manual_posts'] ) ) {
			$placement['manual_posts'] = array_values(
				array_filter( array_map( 'intval', $placement['data_manual_posts'] ) )
			);
		}
		if ( ! array_key_exists( 'count', $placement ) && isset( $placement['data_count'] ) && $placement['data_count'] !== '' ) {
			$placement['count'] = (int) $placement['data_count'];
		}
		if ( ! isset( $placement['category'] ) && ! empty( $placement['data_category'] ) ) {
			$placement['category'] = (int) $placement['data_category'];
		}

		if ( empty( $placement['post_type'] ) && ! empty( $defaults['post_type'] ) ) {
			$placement['post_type'] = (string) $defaults['post_type'];
		}

		if ( ! array_key_exists( 'count', $placement ) && isset( $defaults['count'] ) ) {
			$placement['count'] = (int) $defaults['count'];
		}

		if ( empty( $placement['strategy'] ) && ! empty( $defaults['strategy'] ) ) {
			$placement['strategy'] = (string) $defaults['strategy'];
		}

		if ( ! isset( $placement['category'] ) && ! empty( $defaults['category'] ) ) {
			$placement['category'] = (int) $defaults['category'];
		}

		// Layout builder saves data_count; always prefer it over default count (6).
		if ( isset( $placement['data_count'] ) && $placement['data_count'] !== '' && $placement['data_count'] !== null ) {
			$placement['count'] = (int) $placement['data_count'];
		}

		return $placement;
	}

	/**
	 * Resolved section heading for a placement (empty = hide title in Twig).
	 *
	 * @param array<string, mixed> $placement
	 * @param array<string, mixed> $meta
	 */
	public static function resolve_placement_title( array $placement, array $meta ): string {
		if ( array_key_exists( 'data_title', $placement ) ) {
			return trim( sanitize_text_field( (string) $placement['data_title'] ) );
		}

		return trim( (string) ( $meta['default_title'] ?? '' ) );
	}

	/**
	 * @param array<string, mixed> $meta
	 */
	public static function block_has_configurable_title( array $meta ): bool {
		return trim( (string) ( $meta['default_title'] ?? '' ) ) !== '';
	}

	/**
	 * @return array<string, string>
	 */
	public static function page_choices( ?string $appearance_id = null ): array {
		$manifest = Appearance::load_manifest( $appearance_id );
		$pages    = $manifest['pages'] ?? array();
		$choices  = array();

		foreach ( $pages as $page_key => $page_config ) {
			if ( ! is_array( $page_config ) ) {
				continue;
			}
			$label = (string) ( $page_config['label'] ?? self::PAGE_LABELS[ $page_key ] ?? $page_key );
			$choices[ $page_key ] = $label;
		}

		return $choices !== array() ? $choices : self::PAGE_LABELS;
	}

	/**
	 * Expand deprecated combined sidebar rows (storage / builder format).
	 *
	 * @param list<array<string, mixed>> $rows
	 * @return list<array<string, mixed>>
	 */
	public static function expand_sidebar_rail_rows( array $rows ): array {
		$expanded = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$block = (string) ( $row['placement_block'] ?? '' );
			if ( $block !== 'ak-sidebar-rail' ) {
				$expanded[] = $row;
				continue;
			}

			foreach ( self::SIDEBAR_RAIL_SPLIT_BLOCKS as $split_block ) {
				$expanded[] = array_merge( $row, array( 'placement_block' => $split_block ) );
			}
		}

		return $expanded;
	}

	/**
	 * @return array<string, string>
	 */
	public static function zone_choices( string $page_key, ?string $appearance_id = null ): array {
		$zones   = Appearance::page_zones( $page_key, $appearance_id );
		$choices = array();

		foreach ( $zones as $zone_key => $zone_config ) {
			if ( ! is_array( $zone_config ) ) {
				continue;
			}
			$choices[ $zone_key ] = (string) ( $zone_config['label'] ?? self::ZONE_LABELS[ $zone_key ] ?? $zone_key );
		}

		return $choices;
	}

	/**
	 * @return array<string, string>
	 */
	public static function block_choices( ?string $appearance_id = null ): array {
		$definitions = Appearance::all_block_definitions( $appearance_id );
		$choices     = array();

		foreach ( $definitions as $block_name => $config ) {
			if ( ! is_array( $config ) ) {
				continue;
			}
			$meta = self::block_meta( $block_name );
			if ( ! self::is_layout_placeable( $block_name, $meta ) ) {
				continue;
			}
			if ( ! empty( $meta['hidden'] ) ) {
				continue;
			}
			if ( empty( $meta['partial'] ) && empty( $meta['template'] ) ) {
				continue;
			}
			$choices[ $block_name ] = (string) ( $meta['label'] ?? $block_name );
		}

		asort( $choices );

		return $choices;
	}

	/**
	 * @return array<string, string>
	 */
	public static function post_type_choices(): array {
		$choices = array(
			'post'        => __( 'نوشته', 'asrekhodro' ),
			'ak_video'    => __( 'ویدیو', 'asrekhodro' ),
			'ak_magazine' => __( 'مجله', 'asrekhodro' ),
			'ak_review'   => __( 'بررسی', 'asrekhodro' ),
			'ad_slot'     => __( 'تبلیغات', 'asrekhodro' ),
		);

		return $choices;
	}

	/**
	 * @return list<string>
	 */
	public static function placeable_block_names( ?string $appearance_id = null ): array {
		$names = array();

		foreach ( Appearance::all_block_definitions( $appearance_id ) as $block_name => $config ) {
			if ( ! is_array( $config ) ) {
				continue;
			}

			$meta = self::block_meta( $block_name );
			if ( ! self::is_layout_placeable( $block_name, $meta ) ) {
				continue;
			}
			if ( ! empty( $meta['hidden'] ) ) {
				continue;
			}
			if ( empty( $meta['partial'] ) && empty( $meta['template'] ) ) {
				continue;
			}

			$names[] = $block_name;
		}

		sort( $names );

		return $names;
	}

	/**
	 * Resolve manifest zone `blocks` — any non-empty zone allows all placeable blocks.
	 *
	 * @param array<string, mixed> $zone_config
	 * @return list<string>|null Null = no restriction (legacy).
	 */
	public static function zone_block_names( array $zone_config, ?string $appearance_id = null ): ?array {
		$blocks = $zone_config['blocks'] ?? null;

		if ( ! is_array( $blocks ) ) {
			return null;
		}

		if ( $blocks === array() ) {
			return array();
		}

		return self::placeable_block_names( $appearance_id );
	}

	/**
	 * @param list<string> $sections
	 * @return list<array<string, string>>
	 */
	public static function placements_from_sections( array $sections ): array {
		$placements = array(
			array(
				'zone'  => 'before_main',
				'block' => 'ak-ad-strip',
			),
		);

		foreach ( $sections as $section ) {
			$block = self::SECTION_BLOCK_MAP[ $section ] ?? '';
			if ( $block === '' ) {
				continue;
			}

			$zone = $section === 'picture_frame' ? 'after_main' : 'main';
			if ( $section === 'ticker' ) {
				$zone = 'before_main';
			}

			$placements[] = self::merge_placement_defaults(
				$block,
				array(
					'zone'  => $zone,
					'block' => $block,
				)
			);
		}

		if ( ! self::has_block_in_zone( $placements, 'sidebar', 'ak-sidebar-ads' ) ) {
			$placements[] = array(
				'zone'  => 'sidebar',
				'block' => 'ak-sidebar-ads',
			);
		}

		return $placements;
	}

	/**
	 * @param list<array<string, mixed>> $placements
	 */
	private static function has_block_in_zone( array $placements, string $zone, string $block ): bool {
		foreach ( $placements as $placement ) {
			if ( ( $placement['zone'] ?? '' ) === $zone && ( $placement['block'] ?? '' ) === $block ) {
				return true;
			}
		}

		return false;
	}
}
