<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists custom layout placements (replaces ACF repeater).
 */
final class LayoutStorage {

	public const OPTION_KEY = 'ak_layout_placements';

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function all(): array {
		$rows = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		if ( $rows === array() ) {
			$rows = self::migrate_from_acf();
		}

		return $rows;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function for_page( string $page_key ): array {
		$rows = array();
		foreach ( self::all() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( (string) ( $row['placement_page'] ?? '' ) === $page_key ) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	/**
	 * Merge incoming rows by page — only pages present in $rows or $clear_pages are replaced.
	 *
	 * @param list<array<string, mixed>> $rows
	 * @param list<string>               $clear_pages Page keys whose saved rows should be removed.
	 */
	public static function save( array $rows, array $clear_pages = array() ): void {
		$incoming_by_page = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$clean = self::normalize_row( $row );
			if ( $clean === null ) {
				continue;
			}
			$page = (string) $clean['placement_page'];
			if ( ! isset( $incoming_by_page[ $page ] ) ) {
				$incoming_by_page[ $page ] = array();
			}
			$incoming_by_page[ $page ][] = $clean;
		}

		$replace_pages = array_unique(
			array_merge(
				array_keys( $incoming_by_page ),
				array_map(
					static function ( $page ): string {
						return sanitize_key( (string) $page );
					},
					$clear_pages
				)
			)
		);

		$merged = array();
		foreach ( self::all() as $existing ) {
			if ( ! is_array( $existing ) ) {
				continue;
			}
			$page = (string) ( $existing['placement_page'] ?? '' );
			if ( $page !== '' && in_array( $page, $replace_pages, true ) ) {
				continue;
			}
			$merged[] = $existing;
		}

		foreach ( $incoming_by_page as $page_rows ) {
			foreach ( $page_rows as $row ) {
				$merged[] = $row;
			}
		}

		update_option( self::OPTION_KEY, $merged, false );
		LayoutResolver::flush_cache();
	}

	public static function clear(): void {
		delete_option( self::OPTION_KEY );
		LayoutResolver::flush_cache();
	}

	public static function has_custom(): bool {
		return self::all() !== array();
	}

	public static function hash(): string {
		$rows = self::all();

		return $rows === array() ? 'empty' : md5( wp_json_encode( $rows ) );
	}

	/**
	 * @return array<string, list<array<string, mixed>>>
	 */
	public static function grouped_by_page(): array {
		$grouped = array();
		foreach ( self::all() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$page = (string) ( $row['placement_page'] ?? '' );
			if ( $page === '' ) {
				continue;
			}
			if ( ! isset( $grouped[ $page ] ) ) {
				$grouped[ $page ] = array();
			}
			$grouped[ $page ][] = $row;
		}

		return $grouped;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>|null
	 */
	public static function normalize_row( array $row ): ?array {
		$page  = sanitize_key( (string) ( $row['placement_page'] ?? '' ) );
		$zone  = sanitize_key( (string) ( $row['placement_zone'] ?? $row['zone'] ?? '' ) );
		$block = sanitize_key( (string) ( $row['placement_block'] ?? $row['block'] ?? '' ) );

		if ( $page === '' || $zone === '' || $block === '' ) {
			return null;
		}

		$normalized = array(
			'placement_page'  => $page,
			'placement_zone'  => $zone,
			'placement_block' => $block,
		);

		if ( ! empty( $row['data_post_type'] ) ) {
			$normalized['data_post_type'] = sanitize_key( (string) $row['data_post_type'] );
		}
		if ( ! empty( $row['data_category'] ) ) {
			$normalized['data_category'] = (int) $row['data_category'];
		}
		if ( isset( $row['data_count'] ) && $row['data_count'] !== '' ) {
			$normalized['data_count'] = max( 1, min( 40, (int) $row['data_count'] ) );
		}
		if ( ! empty( $row['data_strategy'] ) ) {
			$strategy = (string) $row['data_strategy'];
			if ( isset( LayoutSchema::STRATEGY_LABELS[ $strategy ] ) ) {
				$normalized['data_strategy'] = $strategy;
			}
		}
		if ( ! empty( $row['data_manual_posts'] ) && is_array( $row['data_manual_posts'] ) ) {
			$normalized['data_manual_posts'] = array_values(
				array_filter( array_map( 'intval', $row['data_manual_posts'] ) )
			);
		}
		if ( ! empty( $row['data_manual_post_titles'] ) && is_array( $row['data_manual_post_titles'] ) ) {
			$titles = array();
			foreach ( $row['data_manual_post_titles'] as $id => $title ) {
				$id = (int) $id;
				if ( $id > 0 && is_string( $title ) && $title !== '' ) {
					$titles[ (string) $id ] = sanitize_text_field( $title );
				}
			}
			if ( $titles !== array() ) {
				$normalized['data_manual_post_titles'] = $titles;
			}
		}
		if ( array_key_exists( 'data_title', $row ) ) {
			$normalized['data_title'] = sanitize_text_field( (string) $row['data_title'] );
		}
		foreach ( array( 'mobile', 'tablet', 'desktop' ) as $device ) {
			$key = 'data_visible_' . $device;
			if ( array_key_exists( $key, $row ) ) {
				$normalized[ $key ] = ! empty( $row[ $key ] ) ? 1 : 0;
			}
		}

		return $normalized;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function migrate_from_acf(): array {
		if ( ! function_exists( 'get_field' ) ) {
			return array();
		}

		$rows = get_field( 'layout_placements', 'option' );
		if ( ! is_array( $rows ) || $rows === array() ) {
			return array();
		}

		$normalized = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$clean = self::normalize_row( $row );
			if ( $clean !== null ) {
				$normalized[] = $clean;
			}
		}

		if ( $normalized !== array() ) {
			update_option( self::OPTION_KEY, $normalized, false );
		}

		return $normalized;
	}
}
