<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PostTypes {

	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ) );
	}

	public static function register(): void {
		self::register_ad_slot();
		self::register_video();
		self::register_magazine();
		self::register_review();
		self::register_carsinfo();
	}

	private static function register_ad_slot(): void {
		register_post_type(
			'ad_slot',
			array(
				'labels'       => array(
					'name'          => __( 'Ads', 'asrekhodro' ),
					'singular_name' => __( 'Ad', 'asrekhodro' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => true,
				'menu_icon'    => 'dashicons-megaphone',
				'supports'     => array( 'title', 'thumbnail' ),
				'has_archive'  => false,
			)
		);

		register_taxonomy(
			'ad_position',
			'ad_slot',
			array(
				'labels'            => array(
					'name'          => __( 'موقعیت‌های تبلیغ', 'asrekhodro' ),
					'singular_name' => __( 'موقعیت تبلیغ', 'asrekhodro' ),
					'menu_name'     => __( 'موقعیت‌های تبلیغ', 'asrekhodro' ),
					'all_items'     => __( 'همه موقعیت‌ها', 'asrekhodro' ),
					'edit_item'     => __( 'ویرایش موقعیت', 'asrekhodro' ),
					'view_item'     => __( 'مشاهده موقعیت', 'asrekhodro' ),
					'update_item'   => __( 'به‌روزرسانی موقعیت', 'asrekhodro' ),
					'add_new_item'  => __( 'افزودن موقعیت', 'asrekhodro' ),
					'new_item_name' => __( 'نام موقعیت جدید', 'asrekhodro' ),
					'search_items'  => __( 'جستجوی موقعیت', 'asrekhodro' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_menu'      => 'edit.php?post_type=ad_slot',
				'hierarchical'      => true,
				'rewrite'           => false,
				'query_var'         => false,
			)
		);
	}

	private static function register_video(): void {
		register_post_type(
			'ak_video',
			array(
				'labels'       => array(
					'name'          => __( 'Videos', 'asrekhodro' ),
					'singular_name' => __( 'Video', 'asrekhodro' ),
				),
				'public'       => true,
				'has_archive'  => true,
				'rewrite'      => array( 'slug' => 'video' ),
				'menu_icon'    => 'dashicons-video-alt3',
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
				'show_in_rest' => true,
			)
		);

		register_taxonomy(
			'video_category',
			'ak_video',
			array(
				'labels'       => array( 'name' => __( 'Video categories', 'asrekhodro' ) ),
				'public'       => true,
				'hierarchical' => true,
				'rewrite'      => array( 'slug' => 'video-category' ),
				'show_in_rest' => true,
			)
		);
	}

	private static function register_magazine(): void {
		register_post_type(
			'ak_magazine',
			array(
				'labels'       => array(
					'name'          => __( 'Magazines', 'asrekhodro' ),
					'singular_name' => __( 'Magazine', 'asrekhodro' ),
				),
				'public'       => true,
				'has_archive'  => 'Home/Kiosk',
				'rewrite'      => array(
					'slug'       => 'Home/Kiosk',
					'with_front' => false,
				),
				'menu_icon'    => 'dashicons-book-alt',
				'supports'     => array( 'title', 'thumbnail', 'excerpt' ),
				'show_in_rest' => true,
			)
		);
	}

	private static function register_review(): void {
		register_post_type(
			'ak_review',
			array(
				'labels'       => array(
					'name'          => __( 'Reviews', 'asrekhodro' ),
					'singular_name' => __( 'Review', 'asrekhodro' ),
				),
				'public'       => true,
				'has_archive'  => true,
				'rewrite'      => array( 'slug' => 'review' ),
				'menu_icon'    => 'dashicons-star-filled',
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
				'show_in_rest' => true,
			)
		);
	}

	private static function register_carsinfo(): void {
		register_post_type(
			'carsinfo',
			array(
				'labels'       => array(
					'name'          => __( 'Cars info', 'asrekhodro' ),
					'singular_name' => __( 'Car info', 'asrekhodro' ),
					'add_new_item'  => __( 'Add car info', 'asrekhodro' ),
					'edit_item'     => __( 'Edit car info', 'asrekhodro' ),
				),
				'public'       => true,
				'has_archive'  => false,
				'rewrite'      => array( 'slug' => 'carsinfo' ),
				'menu_icon'    => 'dashicons-car',
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'comments' ),
				'show_in_rest' => true,
				'taxonomies'   => array( 'category' ),
			)
		);

		register_taxonomy_for_object_type( 'category', 'carsinfo' );
	}
}
