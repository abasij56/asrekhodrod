<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves normalized placements per page from ACF options or manifest defaults.
 */
final class LayoutResolver {

	private const CACHE_TTL = HOUR_IN_SECONDS;

	public static function init(): void {
		add_action( 'update_option_' . LayoutStorage::OPTION_KEY, array( self::class, 'flush_cache' ) );
		add_action( 'update_option_active_appearance', array( self::class, 'flush_cache' ) );
	}

	public static function flush_cache(): void {
		update_option( 'ak_layout_cache_ver', (string) time(), false );
	}

	/**
	 * @return array<string, list<array<string, mixed>>>
	 */
	public static function zones_for_page( string $page_key, ?string $appearance_id = null ): array {
		$appearance_id = $appearance_id ?? Appearance::id();
		$cache_key     = self::cache_key( $appearance_id, $page_key );
		$cached        = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$manifest   = Appearance::load_manifest( $appearance_id );
		$page       = $manifest['pages'][ $page_key ] ?? array();
		$placements = self::resolve_placements( $page_key, is_array( $page ) ? $page : array(), $appearance_id );
		$grouped    = self::group_by_zone( $placements, is_array( $page ) ? $page : array() );

		set_transient( $cache_key, $grouped, self::CACHE_TTL );

		return $grouped;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function flat_placements( string $page_key, ?string $appearance_id = null ): array {
		$zones = self::zones_for_page( $page_key, $appearance_id );
		$flat  = array();

		foreach ( $zones as $zone_placements ) {
			foreach ( $zone_placements as $placement ) {
				$flat[] = $placement;
			}
		}

		return $flat;
	}

	/**
	 * @param array<string, mixed> $page_config
	 * @return list<array<string, mixed>>
	 */
	private static function resolve_placements( string $page_key, array $page_config, string $appearance_id ): array {
		$from_storage = self::placements_from_storage( $page_key );
		if ( $from_storage !== array() ) {
			$validated = self::validate_placements(
				self::expand_legacy_sidebar_rail( $from_storage ),
				$page_config,
				$appearance_id
			);
			if ( $validated !== array() ) {
				return $validated;
			}
		}

		$defaults = $page_config['defaults'] ?? array();
		if ( is_array( $defaults ) && $defaults !== array() ) {
			return self::validate_placements(
				self::expand_legacy_sidebar_rail( $defaults ),
				$page_config,
				$appearance_id
			);
		}

		$sections = $page_config['sections'] ?? array();
		if ( is_array( $sections ) && $sections !== array() ) {
			return self::validate_placements(
				self::expand_legacy_sidebar_rail(
					LayoutSchema::placements_from_sections( array_values( $sections ) )
				),
				$page_config,
				$appearance_id
			);
		}

		return array();
	}

	/**
	 * Expand deprecated combined sidebar block into individual widgets.
	 *
	 * @param list<array<string, mixed>> $placements
	 * @return list<array<string, mixed>>
	 */
	private static function expand_legacy_sidebar_rail( array $placements ): array {
		$expanded = array();

		foreach ( $placements as $placement ) {
			if ( ! is_array( $placement ) ) {
				continue;
			}

			$block = (string) ( $placement['block'] ?? '' );
			if ( $block !== 'ak-sidebar-rail' ) {
				$expanded[] = $placement;
				continue;
			}

			$zone = (string) ( $placement['zone'] ?? 'sidebar' );
			foreach ( LayoutSchema::SIDEBAR_RAIL_SPLIT_BLOCKS as $split_block ) {
				$expanded[] = array(
					'zone'  => $zone,
					'block' => $split_block,
				);
			}
		}

		return $expanded;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function placements_from_storage( string $page_key ): array {
		$placements = array();
		foreach ( LayoutStorage::for_page( $page_key ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$row_page = (string) ( $row['placement_page'] ?? '' );
			if ( $row_page !== $page_key ) {
				continue;
			}

			$block = (string) ( $row['placement_block'] ?? '' );
			$zone  = (string) ( $row['placement_zone'] ?? '' );
			if ( $block === '' || $zone === '' ) {
				continue;
			}

			$placement = array(
				'zone'  => $zone,
				'block' => $block,
			);

			if ( ! empty( $row['data_post_type'] ) ) {
				$placement['post_type'] = (string) $row['data_post_type'];
			}
			if ( ! empty( $row['data_category'] ) ) {
				$placement['category'] = (int) $row['data_category'];
			}
			if ( isset( $row['data_count'] ) && $row['data_count'] !== '' ) {
				$placement['count']    = (int) $row['data_count'];
				$placement['data_count'] = (int) $row['data_count'];
			}
			if ( ! empty( $row['data_strategy'] ) ) {
				$placement['strategy'] = (string) $row['data_strategy'];
			}
			if ( ! empty( $row['data_manual_posts'] ) && is_array( $row['data_manual_posts'] ) ) {
				$placement['manual_posts'] = array_values( array_map( 'intval', $row['data_manual_posts'] ) );
			}
			if ( array_key_exists( 'data_title', $row ) ) {
				$placement['data_title'] = (string) $row['data_title'];
			}

			$placements[] = LayoutSchema::merge_placement_defaults( $block, $placement );
		}

		return $placements;
	}

	/**
	 * @param list<array<string, mixed>>  $placements
	 * @param array<string, mixed>        $page_config
	 * @return list<array<string, mixed>>
	 */
	private static function validate_placements( array $placements, array $page_config, string $appearance_id ): array {
		$zones_config = is_array( $page_config['zones'] ?? null ) ? $page_config['zones'] : array();
		$validated    = array();
		$zone_counts  = array();

		foreach ( $placements as $placement ) {
			if ( ! is_array( $placement ) ) {
				continue;
			}

			$zone  = (string) ( $placement['zone'] ?? '' );
			$block = (string) ( $placement['block'] ?? '' );
			if ( $zone === '' || $block === '' ) {
				continue;
			}

			$zone_def = $zones_config[ $zone ] ?? array();
			$allowed  = LayoutSchema::zone_block_names( $zone_def, $appearance_id );
			if ( is_array( $allowed ) && ! in_array( $block, $allowed, true ) ) {
				continue;
			}

			$meta = LayoutSchema::block_meta( $block );
			if ( ! LayoutSchema::is_layout_placeable( $block, $meta ) ) {
				continue;
			}
			if ( empty( $meta['partial'] ) && empty( $meta['template'] ) ) {
				continue;
			}

			$multiple = (bool) ( $zone_def['multiple'] ?? true );
			$zone_counts[ $zone ] = ( $zone_counts[ $zone ] ?? 0 ) + 1;
			if ( ! $multiple && $zone_counts[ $zone ] > 1 ) {
				continue;
			}

			$validated[] = array_merge(
				array(
					'zone'       => $zone,
					'block'      => $block,
					'partial'    => BlockRegistry::resolve_partial( $block ) ?: Appearance::resolve_template( (string) ( $meta['partial'] ?? '' ) ),
					'title'      => LayoutSchema::resolve_placement_title( $placement, $meta ),
					'full_bleed' => ! empty( $meta['full_bleed'] ),
				),
				LayoutSchema::merge_placement_defaults( $block, $placement )
			);
		}

		return $validated;
	}

	/**
	 * @param list<array<string, mixed>> $placements
	 * @param array<string, mixed>        $page_config
	 * @return array<string, list<array<string, mixed>>>
	 */
	private static function group_by_zone( array $placements, array $page_config ): array {
		$zones_config = is_array( $page_config['zones'] ?? null ) ? $page_config['zones'] : array();
		$grouped      = array();

		foreach ( array_keys( $zones_config ) as $zone_key ) {
			$grouped[ $zone_key ] = array();
		}

		foreach ( $placements as $placement ) {
			$zone = (string) ( $placement['zone'] ?? 'main' );
			if ( ! isset( $grouped[ $zone ] ) ) {
				$grouped[ $zone ] = array();
			}
			$grouped[ $zone ][] = $placement;
		}

		return $grouped;
	}

	private static function cache_key( string $appearance_id, string $page_key ): string {
		$ver = (string) get_option( 'ak_layout_cache_ver', '0' );

		return 'ak_layout_' . $appearance_id . '_' . $page_key . '_' . $ver . '_' . LayoutStorage::hash();
	}
}
