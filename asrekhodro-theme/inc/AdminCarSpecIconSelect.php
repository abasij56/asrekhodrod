<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Searchable SVG icon picker for cinfo-facts repeater fields.
 */
final class AdminCarSpecIconSelect {

	public static function init(): void {
		add_action( 'acf/input/admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	public static function enqueue_assets(): void {
		if ( ! self::should_enqueue() ) {
			return;
		}

		$js_path  = ASREKHODRO_THEME_DIR . '/assets/admin/cinfo-spec-icon-select.js';
		$css_path = ASREKHODRO_THEME_DIR . '/assets/admin/cinfo-spec-icon-select.css';
		$limits   = ASREKHODRO_THEME_DIR . '/assets/admin/cinfo-card-show-limits.js';

		if ( ! is_readable( $js_path ) || ! is_readable( $css_path ) ) {
			return;
		}

		wp_enqueue_style(
			'asrekhodro-cinfo-spec-icon-select',
			ASREKHODRO_THEME_URI . '/assets/admin/cinfo-spec-icon-select.css',
			array(),
			(string) filemtime( $css_path )
		);

		wp_enqueue_script(
			'asrekhodro-cinfo-spec-icon-select',
			ASREKHODRO_THEME_URI . '/assets/admin/cinfo-spec-icon-select.js',
			array(),
			(string) filemtime( $js_path ),
			true
		);

		wp_localize_script(
			'asrekhodro-cinfo-spec-icon-select',
			'akCarSpecIcons',
			array(
				'placeholder' => __( 'جستجو یا انتخاب آیکون…', 'asrekhodro' ),
				'noResults'   => __( 'نتیجه‌ای یافت نشد', 'asrekhodro' ),
				'noneLabel'   => __( '— بدون آیکون —', 'asrekhodro' ),
				'spriteUrl'   => CarSpecIcons::sprite_url(),
				'icons'       => CarSpecIcons::admin_catalog(),
			)
		);

		if ( is_readable( $limits ) ) {
			wp_enqueue_script(
				'asrekhodro-cinfo-card-show-limits',
				ASREKHODRO_THEME_URI . '/assets/admin/cinfo-card-show-limits.js',
				array( 'jquery' ),
				(string) filemtime( $limits ),
				true
			);

			wp_localize_script(
				'asrekhodro-cinfo-card-show-limits',
				'akCinfoCardLimits',
				array(
					'heroFieldKey'  => 'field_cinfo_hero_rate_items',
					'heroToggleKey' => 'field_cinfo_hero_rate_item_show_in_card',
					'heroMax'       => 4,
					'heroMessage'   => __( 'حداکثر ۴ مورد برای نمایش در کارت قابل انتخاب است.', 'asrekhodro' ),
					'factsFieldKey' => 'field_cinfo_facts_items',
					'factsToggleKey'=> 'field_cinfo_facts_item_show_in_card',
					'factsMax'      => 3,
					'factsMessage'  => __( 'حداکثر ۳ مورد برای نمایش در کارت قابل انتخاب است.', 'asrekhodro' ),
				)
			);
		}
	}

	private static function should_enqueue(): bool {
		if ( ! is_admin() ) {
			return false;
		}

		$post_type = '';
		if ( isset( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_type = sanitize_key( (string) wp_unslash( $_GET['post_type'] ) );
		} elseif ( isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_id = (int) $_GET['post']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $post_id > 0 ) {
				$post_type = (string) get_post_type( $post_id );
			}
		}

		if ( $post_type === 'carsinfo' ) {
			return true;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen instanceof \WP_Screen && $screen->post_type === 'carsinfo' ) {
			return true;
		}

		return false;
	}
}
