<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ads admin list: position filter and taxonomy menu under Ads.
 */
final class AdminAds {

	public const TAXONOMY = 'ad_position';

	public const POST_TYPE = 'ad_slot';

	public static function init(): void {
		add_action( 'restrict_manage_posts', array( self::class, 'render_position_filter' ) );
		add_action( 'pre_get_posts', array( self::class, 'filter_posts_by_position' ) );
	}

	public static function render_position_filter( string $post_type ): void {
		if ( $post_type !== self::POST_TYPE ) {
			return;
		}

		$selected = isset( $_GET[ self::TAXONOMY ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ self::TAXONOMY ] ) ) : '';

		wp_dropdown_categories(
			array(
				'show_option_all' => __( 'همه موقعیت‌های تبلیغ', 'asrekhodro' ),
				'taxonomy'        => self::TAXONOMY,
				'name'            => self::TAXONOMY,
				'orderby'         => 'name',
				'selected'        => $selected,
				'show_count'      => true,
				'hide_empty'      => false,
				'value_field'     => 'slug',
				'hierarchical'    => true,
				'depth'           => 1,
			)
		);
	}

	public static function filter_posts_by_position( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		global $pagenow;
		if ( $pagenow !== 'edit.php' ) {
			return;
		}

		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( (string) $_GET['post_type'] ) : 'post';
		if ( $post_type !== self::POST_TYPE ) {
			return;
		}

		if ( empty( $_GET[ self::TAXONOMY ] ) ) {
			return;
		}

		$slug = sanitize_text_field( wp_unslash( (string) $_GET[ self::TAXONOMY ] ) );
		if ( $slug === '' || $slug === '0' ) {
			return;
		}

		$query->set(
			'tax_query',
			array(
				array(
					'taxonomy' => self::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $slug,
				),
			)
		);
	}
}
