<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Searchable parent category dropdown on category add/edit admin screens.
 */
final class AdminCategoryParent {

	public static function init(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
		add_filter( 'category_parent_dropdown_args', array( self::class, 'filter_dropdown_args' ), 10, 3 );
	}

	public static function enqueue_assets( string $hook ): void {
		unset( $hook );

		if ( ! self::is_category_admin_screen() ) {
			return;
		}

		$js_path  = ASREKHODRO_THEME_DIR . '/assets/admin/category-parent-select.js';
		$css_path = ASREKHODRO_THEME_DIR . '/assets/admin/category-parent-select.css';
		if ( ! is_readable( $js_path ) || ! is_readable( $css_path ) ) {
			return;
		}

		wp_enqueue_style(
			'asrekhodro-category-parent-select',
			ASREKHODRO_THEME_URI . '/assets/admin/category-parent-select.css',
			array(),
			(string) filemtime( $css_path )
		);

		wp_enqueue_script(
			'asrekhodro-category-parent-select',
			ASREKHODRO_THEME_URI . '/assets/admin/category-parent-select.js',
			array(),
			(string) filemtime( $js_path ),
			true
		);

		wp_localize_script(
			'asrekhodro-category-parent-select',
			'akCategoryParentSelect',
			array(
				'placeholder' => __( 'جستجو یا انتخاب کتگوری والد…', 'asrekhodro' ),
				'noResults'   => __( 'نتیجه‌ای یافت نشد', 'asrekhodro' ),
				'termTree'    => self::category_term_tree(),
			)
		);
	}

	/**
	 * @return array{terms: array<string, array{id: int, parent: int, name: string}>}
	 */
	private static function category_term_tree(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
			)
		);

		$terms_map = array();

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array( 'terms' => $terms_map );
		}

		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$terms_map[ (string) $term->term_id ] = array(
				'id'     => (int) $term->term_id,
				'parent' => (int) $term->parent,
				'name'   => $term->name,
			);
		}

		return array( 'terms' => $terms_map );
	}

	/**
	 * @param array<string, mixed> $args
	 * @param mixed                $taxonomy
	 * @param mixed                $context
	 * @return array<string, mixed>
	 */
	public static function filter_dropdown_args( array $args, $taxonomy = 'category', $context = '' ): array {
		unset( $context );

		if ( (string) $taxonomy !== 'category' ) {
			return $args;
		}

		$class         = isset( $args['class'] ) ? (string) $args['class'] : '';
		$args['class'] = trim( $class . ' ak-category-parent-select' );

		return $args;
	}

	private static function is_category_admin_screen(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $screen instanceof \WP_Screen ) {
			if ( ( $screen->taxonomy ?? '' ) === 'category' ) {
				return true;
			}

			if ( ( $screen->id ?? '' ) === 'edit-category' ) {
				return true;
			}
		}

		return isset( $_GET['taxonomy'] ) && sanitize_key( (string) wp_unslash( $_GET['taxonomy'] ) ) === 'category';
	}
}
