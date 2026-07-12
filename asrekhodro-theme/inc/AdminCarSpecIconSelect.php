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
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_block_editor_assets' ) );
		add_action( 'wp_ajax_ak_car_spec_icons_query', array( self::class, 'ajax_query_icons' ) );
	}

	public static function enqueue_block_editor_assets(): void {
		if ( ! self::is_carsinfo_editor_screen() ) {
			return;
		}

		self::enqueue_assets();
	}

	public static function enqueue_assets(): void {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! self::is_carsinfo_editor_screen() && ! self::should_enqueue_from_request() ) {
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

		$initial = CarSpecIcons::quick_initial_admin_items();

		wp_localize_script(
			'asrekhodro-cinfo-spec-icon-select',
			'akCarSpecIcons',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'ak_car_spec_icons_query' ),
				'action'         => 'ak_car_spec_icons_query',
				'placeholder'    => __( 'جستجو یا انتخاب آیکون…', 'asrekhodro' ),
				'noResults'      => __( 'نتیجه‌ای یافت نشد', 'asrekhodro' ),
				'loadingLabel'   => __( 'در حال بارگذاری…', 'asrekhodro' ),
				'noneLabel'      => __( '— بدون آیکون —', 'asrekhodro' ),
				'spriteUrl'      => CarSpecIcons::sprite_url(),
				'initialLimit'   => CarSpecIcons::ADMIN_INITIAL_LIMIT,
				'pageSize'       => CarSpecIcons::ADMIN_PAGE_SIZE,
				'initialIcons'   => $initial,
				'initialTotal'   => 0,
				'initialHasMore' => true,
				'scrollHint'     => __( 'برای مشاهده بیشتر اسکرول کنید…', 'asrekhodro' ),
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

	public static function ajax_query_icons(): void {
		check_ajax_referer( 'ak_car_spec_icons_query', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی مجاز نیست.', 'asrekhodro' ) ), 403 );
		}

		$query      = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) : '';
		$offset     = isset( $_GET['offset'] ) ? max( 0, (int) $_GET['offset'] ) : 0;
		$limit      = isset( $_GET['limit'] ) ? max( 1, min( 50, (int) $_GET['limit'] ) ) : CarSpecIcons::ADMIN_PAGE_SIZE;
		$include_id = isset( $_GET['include'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['include'] ) ) : '';

		wp_send_json_success(
			CarSpecIcons::query_admin_items( $query, $offset, $limit, $include_id )
		);
	}

	private static function should_enqueue_from_request(): bool {
		$post_type = '';
		if ( isset( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_type = sanitize_key( (string) wp_unslash( $_GET['post_type'] ) );
		} elseif ( isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_id = (int) $_GET['post']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $post_id > 0 ) {
				$post_type = (string) get_post_type( $post_id );
			}
		}

		return $post_type === 'carsinfo';
	}

	private static function is_carsinfo_editor_screen(): bool {
		if ( self::should_enqueue_from_request() ) {
			return true;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen instanceof \WP_Screen && $screen->post_type === 'carsinfo' ) {
			return true;
		}

		global $post;
		if ( $post instanceof \WP_Post && $post->post_type === 'carsinfo' ) {
			return true;
		}

		return false;
	}

	private static function should_enqueue(): bool {
		return self::is_carsinfo_editor_screen();
	}
}
