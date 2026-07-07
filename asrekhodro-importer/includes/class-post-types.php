<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register CPTs/taxonomies required for import (works even when theme is inactive during cron).
 */
final class AsreKhodro_Importer_Post_Types {

	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ), 5 );
	}

	/**
	 * @return array<string, string>
	 */
	private static function admin_labels( string $name, string $singular_name ): array {
		return array(
			'name'               => $name,
			'singular_name'      => $singular_name,
			'menu_name'          => $name,
			'name_admin_bar'     => $singular_name,
			'add_new'            => 'افزودن',
			'add_new_item'       => 'افزودن ' . $singular_name . ' جدید',
			'edit_item'          => 'ویرایش ' . $singular_name,
			'new_item'           => $singular_name . ' جدید',
			'view_item'          => 'مشاهده ' . $singular_name,
			'search_items'       => 'جستجوی ' . $name,
			'not_found'          => $name . ' یافت نشد.',
			'not_found_in_trash' => $name . ' در زباله‌دان یافت نشد.',
			'all_items'          => 'همه ' . $name,
		);
	}

	public static function register(): void {
		if ( ! post_type_exists( 'ad_slot' ) ) {
			register_post_type(
				'ad_slot',
				array(
					'labels'       => self::admin_labels( 'تبلیغات', 'تبلیغ' ),
					'public'       => false,
					'show_ui'      => true,
					'show_in_menu' => true,
					'supports'     => array( 'title' ),
					'has_archive'  => false,
				)
			);
		}

		if ( ! taxonomy_exists( 'ad_position' ) ) {
			register_taxonomy(
				'ad_position',
				'ad_slot',
				array(
					'labels'       => array(
						'name'          => 'موقعیت‌های تبلیغ',
						'singular_name' => 'موقعیت تبلیغ',
						'menu_name'     => 'موقعیت‌های تبلیغ',
					),
					'public'       => false,
					'show_ui'      => true,
					'hierarchical' => true,
				)
			);
		}

		if ( ! post_type_exists( 'ak_video' ) ) {
			register_post_type(
				'ak_video',
				array(
					'labels'       => self::admin_labels( 'ویدیوها', 'ویدیو' ),
					'public'       => true,
					'has_archive'  => true,
					'rewrite'      => array( 'slug' => 'video' ),
					'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
					'show_in_rest' => true,
				)
			);
		}

		if ( ! taxonomy_exists( 'video_category' ) ) {
			register_taxonomy(
				'video_category',
				'ak_video',
				array(
					'labels'       => array(
						'name'          => 'دسته‌بندی ویدیو',
						'singular_name' => 'دسته ویدیو',
					),
					'public'       => true,
					'hierarchical' => true,
					'rewrite'      => array( 'slug' => 'video-category' ),
					'show_in_rest' => true,
				)
			);
		}

		if ( ! post_type_exists( 'ak_magazine' ) ) {
			register_post_type(
				'ak_magazine',
				array(
					'labels'       => self::admin_labels( 'مجله‌ها', 'مجله' ),
					'public'       => true,
					'has_archive'  => 'Home/Kiosk',
					'rewrite'      => array(
						'slug'       => 'Home/Kiosk',
						'with_front' => false,
					),
					'supports'     => array( 'title', 'thumbnail', 'excerpt' ),
					'show_in_rest' => true,
				)
			);
		}

		if ( ! post_type_exists( 'ak_review' ) ) {
			register_post_type(
				'ak_review',
				array(
					'labels'       => self::admin_labels( 'بررسی‌ها', 'بررسی' ),
					'public'       => true,
					'has_archive'  => true,
					'rewrite'      => array( 'slug' => 'review' ),
					'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
					'show_in_rest' => true,
				)
			);
		}
	}
}
