<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Searchable bundled brand-logo picker on category ACF fields.
 */
final class AdminCategoryBrandLogo {

	public static function init(): void {
		add_action( 'acf/input/admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	public static function enqueue_assets(): void {
		if ( ! self::is_category_taxonomy_screen() ) {
			return;
		}

		$js_path  = ASREKHODRO_THEME_DIR . '/assets/admin/category-brand-logo-select.js';
		$css_path = ASREKHODRO_THEME_DIR . '/assets/admin/category-brand-logo-select.css';
		if ( ! is_readable( $js_path ) || ! is_readable( $css_path ) ) {
			return;
		}

		wp_enqueue_style(
			'asrekhodro-category-brand-logo-select',
			ASREKHODRO_THEME_URI . '/assets/admin/category-brand-logo-select.css',
			array(),
			(string) filemtime( $css_path )
		);

		wp_enqueue_script(
			'asrekhodro-category-brand-logo-select',
			ASREKHODRO_THEME_URI . '/assets/admin/category-brand-logo-select.js',
			array(),
			(string) filemtime( $js_path ),
			true
		);

		wp_localize_script(
			'asrekhodro-category-brand-logo-select',
			'akCategoryBrandLogo',
			array(
				'placeholder' => __( 'جستجو یا انتخاب لوگوی برند…', 'asrekhodro' ),
				'noResults'   => __( 'نتیجه‌ای یافت نشد', 'asrekhodro' ),
				'noneLabel'   => __( '— بدون لوگوی آماده —', 'asrekhodro' ),
				'logos'       => CarBrandAssets::admin_catalog(),
			)
		);
	}

	private static function is_category_taxonomy_screen(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $screen instanceof \WP_Screen ) {
			if ( ( $screen->taxonomy ?? '' ) === 'category' ) {
				return true;
			}

			if ( in_array( (string) ( $screen->id ?? '' ), array( 'edit-category', 'category' ), true ) ) {
				return true;
			}
		}

		if ( isset( $_GET['taxonomy'] ) && sanitize_key( (string) wp_unslash( $_GET['taxonomy'] ) ) === 'category' ) {
			return true;
		}

		return false;
	}
}
