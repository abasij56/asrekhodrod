<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PostTypes {

	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ), 4 );
		add_filter( 'post_type_labels_post', array( self::class, 'rename_post_labels' ) );
		add_filter( 'register_post_type_args', array( self::class, 'filter_post_type_args' ), 10, 2 );
		add_filter( 'register_taxonomy_args', array( self::class, 'filter_taxonomy_args' ), 10, 3 );
		add_action( 'admin_menu', array( self::class, 'localize_admin_menu' ), 999 );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	private static function post_type_label_map(): array {
		return array(
			'ad_slot'     => array( 'تبلیغات', 'تبلیغ' ),
			'ak_video'    => array( 'ویدیوها', 'ویدیو' ),
			'ak_magazine' => array( 'مجله‌ها', 'مجله' ),
			'ak_review'   => array( 'بررسی‌ها', 'بررسی' ),
			'carsinfo'    => array( 'اطلاعات خودرو', 'خودرو' ),
		);
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	public static function filter_post_type_args( array $args, string $post_type ): array {
		$map = self::post_type_label_map();
		if ( ! isset( $map[ $post_type ] ) ) {
			return $args;
		}

		$args['labels'] = array_merge(
			is_array( $args['labels'] ?? null ) ? $args['labels'] : array(),
			self::admin_labels( $map[ $post_type ][0], $map[ $post_type ][1] )
		);

		return $args;
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	public static function filter_taxonomy_args( array $args, string $taxonomy, array $object_type ): array {
		unset( $object_type );

		if ( $taxonomy === 'ad_position' ) {
			$args['labels'] = array_merge(
				is_array( $args['labels'] ?? null ) ? $args['labels'] : array(),
				array(
					'name'          => 'موقعیت‌های تبلیغ',
					'singular_name' => 'موقعیت تبلیغ',
					'menu_name'     => 'موقعیت‌های تبلیغ',
					'all_items'     => 'همه موقعیت‌ها',
					'edit_item'     => 'ویرایش موقعیت',
					'view_item'     => 'مشاهده موقعیت',
					'update_item'   => 'به‌روزرسانی موقعیت',
					'add_new_item'  => 'افزودن موقعیت',
					'new_item_name' => 'نام موقعیت جدید',
					'search_items'  => 'جستجوی موقعیت',
				)
			);
		}

		if ( $taxonomy === 'video_category' ) {
			$args['labels'] = array_merge(
				is_array( $args['labels'] ?? null ) ? $args['labels'] : array(),
				array(
					'name'          => 'دسته‌بندی ویدیو',
					'singular_name' => 'دسته ویدیو',
					'menu_name'     => 'دسته‌بندی ویدیو',
				)
			);
		}

		return $args;
	}

	public static function localize_admin_menu(): void {
		global $menu;

		if ( ! is_array( $menu ) ) {
			return;
		}

		$menu_titles = array(
			'edit.php'                       => 'اخبار',
			'edit.php?post_type=ad_slot'     => 'تبلیغات',
			'edit.php?post_type=ak_video'    => 'ویدیوها',
			'edit.php?post_type=ak_magazine' => 'مجله‌ها',
			'edit.php?post_type=ak_review'   => 'بررسی‌ها',
			'edit.php?post_type=carsinfo'    => 'اطلاعات خودرو',
			'asrekhodro-settings'            => 'تنظیمات تم',
		);

		foreach ( $menu as $index => $item ) {
			if ( ! is_array( $item ) || ! isset( $item[2], $menu_titles[ $item[2] ] ) ) {
				continue;
			}

			$menu[ $index ][0] = $menu_titles[ $item[2] ];
		}
	}

	/**
	 * @return array<string, string>
	 */
	private static function admin_labels( string $name, string $singular_name, ?string $menu_name = null ): array {
		$menu_name = $menu_name ?? $name;

		return array(
			'name'               => $name,
			'singular_name'      => $singular_name,
			'menu_name'          => $menu_name,
			'name_admin_bar'     => $singular_name,
			'add_new'            => __( 'افزودن', 'asrekhodro' ),
			'add_new_item'       => sprintf(
				/* translators: %s: post type singular name */
				__( 'افزودن %s جدید', 'asrekhodro' ),
				$singular_name
			),
			'edit_item'          => sprintf(
				/* translators: %s: post type singular name */
				__( 'ویرایش %s', 'asrekhodro' ),
				$singular_name
			),
			'new_item'           => sprintf(
				/* translators: %s: post type singular name */
				__( '%s جدید', 'asrekhodro' ),
				$singular_name
			),
			'view_item'          => sprintf(
				/* translators: %s: post type singular name */
				__( 'مشاهده %s', 'asrekhodro' ),
				$singular_name
			),
			'search_items'       => sprintf(
				/* translators: %s: post type plural name */
				__( 'جستجوی %s', 'asrekhodro' ),
				$name
			),
			'not_found'          => sprintf(
				/* translators: %s: post type plural name */
				__( '%s یافت نشد.', 'asrekhodro' ),
				$name
			),
			'not_found_in_trash' => sprintf(
				/* translators: %s: post type plural name */
				__( '%s در زباله‌دان یافت نشد.', 'asrekhodro' ),
				$name
			),
			'all_items'          => sprintf(
				/* translators: %s: post type plural name */
				__( 'همه %s', 'asrekhodro' ),
				$name
			),
		);
	}

	public static function rename_post_labels( \stdClass $labels ): \stdClass {
		foreach ( self::admin_labels( 'اخبار', 'خبر' ) as $key => $value ) {
			$labels->$key = $value;
		}

		return $labels;
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
				'labels'       => self::admin_labels( 'تبلیغات', 'تبلیغ' ),
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
				'labels'       => self::admin_labels( 'ویدیوها', 'ویدیو' ),
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
				'labels'       => array(
					'name'          => __( 'دسته‌بندی ویدیو', 'asrekhodro' ),
					'singular_name' => __( 'دسته ویدیو', 'asrekhodro' ),
				),
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
				'labels'       => self::admin_labels( 'مجله‌ها', 'مجله' ),
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
				'labels'       => self::admin_labels( 'بررسی‌ها', 'بررسی' ),
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
				'labels'       => self::admin_labels( 'اطلاعات خودرو', 'خودرو' ),
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
