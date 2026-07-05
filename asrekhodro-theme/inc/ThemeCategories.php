<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filters imported/news categories for public theme surfaces.
 */
final class ThemeCategories {

	/** @var list<string> */
	private const EXCLUDED_NAMES = array(
		'عمومی',
		'تبلیغات صفحه اصلی',
		'تبلیغات صفحه داخلی',
		'دکه مطبوعات',
		'تصاویر قدیمی',
		'در قاب تصویر',
		'Uncategorized',
		'دسته‌بندی نشده',
	);

	public static function is_excluded( \WP_Term $term ): bool {
		$name = trim( $term->name );
		if ( in_array( $name, self::EXCLUDED_NAMES, true ) ) {
			return true;
		}

		if ( preg_match( '/^cat-\d+$/i', $term->slug ) ) {
			return true;
		}

		return (bool) apply_filters( 'asrekhodro_exclude_homepage_category', false, $term );
	}

	/**
	 * Top news categories for homepage blocks (junk categories skipped).
	 *
	 * @return list<\WP_Term>
	 */
	public static function homepage_terms( int $count ): array {
		$count = max( 1, $count );
		$pool  = max( $count * 8, 24 );

		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => true,
				'number'     => $pool,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$selected = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term || self::is_excluded( $term ) ) {
				continue;
			}

			$selected[] = $term;
			if ( count( $selected ) >= $count ) {
				break;
			}
		}

		return $selected;
	}

	public static function term_url( \WP_Term $term ): string {
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			return home_url( '/' );
		}

		return (string) $link;
	}
}
