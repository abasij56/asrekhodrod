<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds page context from layout zones + block data resolution.
 */
final class LayoutEngine {

	/**
	 * @param array<string, mixed> $extra
	 * @return array<string, mixed>
	 */
	public static function page_context( string $page_key, array $extra = array() ): array {
		$zones   = LayoutResolver::zones_for_page( $page_key );
		$context = array(
			'layout_page' => $page_key,
		);

		foreach ( $zones as $zone_key => $zone_placements ) {
			foreach ( $zone_placements as $index => $placement ) {
				if ( ! is_array( $placement ) ) {
					continue;
				}

				$block = (string) ( $placement['block'] ?? '' );
				if ( $block === '' ) {
					continue;
				}

				$placement_data = array_merge(
					$placement,
					$extra,
					array( 'layout_page' => $page_key )
				);
				$block_context  = BlockDataResolver::resolve( $block, $placement_data );

				$zones[ $zone_key ][ $index ] = array_merge(
					$placement,
					$block_context,
					array( '_context_attached' => true )
				);
				$context                      = array_merge( $context, $block_context );
			}
		}

		$context['layout_zones'] = $zones;

		if ( in_array( $page_key, array( 'front_page', 'not_found' ), true ) && ! isset( $context['news_archive_url'] ) ) {
			$context = array_merge( $context, HomepageData::section( 'news_archive_url' ) );
		}

		return array_merge( $context, $extra );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function zone_placements( string $page_key, string $zone ): array {
		$zones = LayoutResolver::zones_for_page( $page_key );

		return $zones[ $zone ] ?? array();
	}
}
