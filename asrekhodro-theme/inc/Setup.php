<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Setup {

	public static function init(): void {
		add_action( 'after_setup_theme', array( self::class, 'theme_setup' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
		add_action( 'wp_head', array( self::class, 'preload_font' ), 1 );
		add_action( 'wp_head', array( self::class, 'output_site_icons' ), 2 );
		add_filter( 'get_site_icon_url', array( self::class, 'filter_site_icon_url' ), 10, 2 );
		add_action( 'init', array( self::class, 'register_menus' ) );
		add_action( 'init', array( self::class, 'ensure_ad_positions' ), 20 );
		add_action( 'after_switch_theme', array( self::class, 'on_activation' ) );
		add_action( 'pre_get_posts', array( self::class, 'adjust_main_queries' ) );
		add_filter( 'get_the_archive_title', array( self::class, 'filter_archive_title' ) );
		add_filter( 'document_title_parts', array( self::class, 'filter_document_title_parts' ) );
	}

	public static function ensure_ad_positions(): void {
		$positions = array(
			'menu_strip'   => 'Menu strip (below nav)',
			'sidebar_left' => 'Sidebar left',
			'content_row'  => 'Content row banner',
			'kiosk'        => 'Kiosk / magazine carousel',
		);

		foreach ( $positions as $slug => $name ) {
			if ( ! term_exists( $slug, 'ad_position' ) ) {
				wp_insert_term( $name, 'ad_position', array( 'slug' => $slug ) );
			}
		}
	}

	public static function on_activation(): void {
		PostTypes::register();
		NewsPermalinks::register_rewrites();
		self::ensure_ad_positions();

		flush_rewrite_rules();
	}

	public static function adjust_main_queries( \WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( self::is_news_archive_query( $query ) ) {
			$query->set( 'posts_per_page', self::news_archive_posts_per_page() );
		}

		if ( $query->is_post_type_archive( 'ak_magazine' ) ) {
			$query->set( 'posts_per_page', self::magazine_archive_posts_per_page() );
		}

		if ( $query->is_post_type_archive( 'ak_video' ) || $query->is_tax( 'video_category' ) ) {
			$query->set( 'posts_per_page', self::video_archive_posts_per_page() );
		}
	}

	public static function video_archive_posts_per_page(): int {
		$default = 24;
		if ( ! function_exists( 'get_field' ) ) {
			return $default;
		}

		$value = get_field( 'video_archive_posts_per_page', 'option' );
		if ( $value === null || $value === '' ) {
			return $default;
		}

		return max( 1, min( 100, (int) $value ) );
	}

	public static function magazine_archive_posts_per_page(): int {
		$default = 24;
		if ( ! function_exists( 'get_field' ) ) {
			return $default;
		}

		$value = get_field( 'magazine_archive_posts_per_page', 'option' );
		if ( $value === null || $value === '' ) {
			return $default;
		}

		return max( 1, min( 100, (int) $value ) );
	}

	public static function news_archive_posts_per_page(): int {
		$default = 40;
		if ( ! function_exists( 'get_field' ) ) {
			return $default;
		}

		$value = get_field( 'news_archive_posts_per_page', 'option' );
		if ( $value === null || $value === '' ) {
			return $default;
		}

		return max( 1, min( 100, (int) $value ) );
	}

	private static function is_news_archive_query( \WP_Query $query ): bool {
		if ( $query->is_home() ) {
			return true;
		}

		if ( ! $query->is_archive() ) {
			return false;
		}

		$post_type = $query->get( 'post_type' );

		if ( is_array( $post_type ) ) {
			return $post_type === array( 'post' );
		}

		return $post_type === '' || $post_type === 'post';
	}

	public static function filter_archive_title( string $title ): string {
		$label = self::current_taxonomy_archive_label();

		return $label !== '' ? $label : $title;
	}

	/**
	 * @param array<string, string> $parts
	 * @return array<string, string>
	 */
	public static function filter_document_title_parts( array $parts ): array {
		$label = self::current_taxonomy_archive_label();

		if ( $label !== '' ) {
			$parts['title'] = $label;
		}

		return $parts;
	}

	private static function current_taxonomy_archive_label(): string {
		if ( is_category() ) {
			return single_cat_title( '', false );
		}

		if ( is_tag() ) {
			return single_tag_title( '', false );
		}

		if ( is_tax() ) {
			return single_term_title( '', false );
		}

		return '';
	}

	public static function theme_setup(): void {
		load_theme_textdomain( 'asrekhodro', ASREKHODRO_THEME_DIR . '/languages' );

		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
		add_theme_support( 'responsive-embeds' );

		add_filter( 'default_comment_status', static fn() => 'open' );

		add_image_size( 'ak-news-list', 163, 109, true );
		add_image_size( 'ak-card', 500, 312, true );
		add_image_size( 'ak-hero', 1200, 800, true );
		add_image_size( 'ak-ad-strip', 435, 60, true );
	}

	public static function register_menus(): void {
		register_nav_menus(
			array(
				'main-nav'          => __( 'Main navigation', 'asrekhodro' ),
				'footer-about'      => __( 'Footer — About', 'asrekhodro' ),
				'footer-categories' => __( 'Footer — Categories', 'asrekhodro' ),
			)
		);
	}

	public static function enqueue_assets(): void {
		Appearance::enqueue_assets();
	}

	public static function preload_font(): void {
		Appearance::preload_font();
	}

	public static function output_site_icons(): void {
		if ( ! function_exists( 'get_field' ) ) {
			return;
		}

		$favicon     = self::option_image_url( get_field( 'site_favicon', 'option' ) );
		$favicon_16  = self::option_image_url( get_field( 'site_favicon_16', 'option' ) );
		$apple       = self::option_image_url( get_field( 'site_apple_touch_icon', 'option' ) );
		$icon_192    = self::option_image_url( get_field( 'site_icon_192', 'option' ) );
		$icon_512    = self::option_image_url( get_field( 'site_icon_512', 'option' ) );
		$ms_tile     = self::option_image_url( get_field( 'site_ms_tile_image', 'option' ) );
		$ms_color    = (string) get_field( 'site_ms_tile_color', 'option' );
		$theme_color = (string) get_field( 'site_theme_color', 'option' );

		if ( $favicon_16 ) {
			printf(
				'<link rel="icon" type="image/png" sizes="16x16" href="%s">' . "\n",
				esc_url( $favicon_16 )
			);
		}

		if ( $favicon ) {
			printf(
				'<link rel="icon" type="image/png" sizes="32x32" href="%s">' . "\n",
				esc_url( $favicon )
			);
			printf( '<link rel="shortcut icon" href="%s">' . "\n", esc_url( $favicon ) );
		}

		if ( $apple ) {
			printf(
				'<link rel="apple-touch-icon" sizes="180x180" href="%s">' . "\n",
				esc_url( $apple )
			);
		}

		if ( $icon_192 ) {
			printf(
				'<link rel="icon" type="image/png" sizes="192x192" href="%s">' . "\n",
				esc_url( $icon_192 )
			);
		}

		if ( $icon_512 ) {
			printf(
				'<link rel="icon" type="image/png" sizes="512x512" href="%s">' . "\n",
				esc_url( $icon_512 )
			);
		}

		if ( $ms_tile ) {
			printf(
				'<meta name="msapplication-TileImage" content="%s">' . "\n",
				esc_url( $ms_tile )
			);
		}

		if ( $ms_color ) {
			printf(
				'<meta name="msapplication-TileColor" content="%s">' . "\n",
				esc_attr( $ms_color )
			);
		}

		if ( $theme_color ) {
			printf(
				'<meta name="theme-color" content="%s">' . "\n",
				esc_attr( $theme_color )
			);
		}
	}

	/**
	 * @param mixed $field
	 */
	private static function option_image_url( $field ): ?string {
		if ( empty( $field ) ) {
			return null;
		}

		if ( is_array( $field ) && ! empty( $field['url'] ) ) {
			return (string) $field['url'];
		}

		if ( is_numeric( $field ) ) {
			$url = wp_get_attachment_url( (int) $field );
			return $url ? (string) $url : null;
		}

		return is_string( $field ) ? $field : null;
	}

	public static function filter_site_icon_url( $url, $size ) {
		if ( ! function_exists( 'get_field' ) ) {
			return $url;
		}

		$favicon = self::option_image_url( get_field( 'site_favicon', 'option' ) );
		if ( $favicon ) {
			return $favicon;
		}

		return $url;
	}
}
