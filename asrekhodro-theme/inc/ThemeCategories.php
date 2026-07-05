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

	public static function term_image_id( int $term_id ): int {
		if ( $term_id <= 0 || ! function_exists( 'get_field' ) ) {
			return 0;
		}

		$value = get_field( 'ak_category_image', 'category_' . $term_id );
		if ( is_array( $value ) && ! empty( $value['ID'] ) ) {
			return (int) $value['ID'];
		}

		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * Brand logo for carsinfo directory cards.
	 * Priority: 1) uploaded category image, 2) bundled logo from dropdown.
	 *
	 * @return array{url: string, alt: string, width: int, height: int}
	 */
	public static function term_directory_logo( int $term_id, string $alt = '' ): array {
		return self::term_image( $term_id, 'medium', $alt );
	}

	/**
	 * @return array{url: string, alt: string, width: int, height: int}
	 */
	public static function term_image( int $term_id, string $size = 'thumbnail', string $alt = '' ): array {
		$empty = array(
			'url'    => '',
			'alt'    => $alt,
			'width'  => 0,
			'height' => 0,
		);

		if ( $term_id <= 0 ) {
			return $empty;
		}

		if ( $alt === '' ) {
			$term = get_term( $term_id, 'category' );
			if ( $term instanceof \WP_Term && ! is_wp_error( $term ) ) {
				$alt = (string) $term->name;
			}
		}

		$attachment_id = self::term_image_id( $term_id );
		if ( $attachment_id > 0 ) {
			$src = wp_get_attachment_image_src( $attachment_id, $size );
			if ( is_array( $src ) && ! empty( $src[0] ) ) {
				$meta_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
				if ( is_string( $meta_alt ) && $meta_alt !== '' ) {
					$alt = $meta_alt;
				}

				return array(
					'url'    => (string) $src[0],
					'alt'    => $alt,
					'width'  => (int) ( $src[1] ?? 0 ),
					'height' => (int) ( $src[2] ?? 0 ),
				);
			}

			return $empty;
		}

		$bundled_slug = CarBrandAssets::bundled_slug_for_term( $term_id );
		if ( $bundled_slug !== '' ) {
			return CarBrandAssets::image_for_slug( $bundled_slug, $alt );
		}

		return $empty;
	}
}
