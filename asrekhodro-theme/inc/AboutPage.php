<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AboutPage {

	public static function init(): void {
		add_filter( 'body_class', array( self::class, 'filter_body_class' ) );
		add_filter( 'template_include', array( self::class, 'filter_template_include' ), 99 );
	}

	/**
	 * @param array<int, string> $classes
	 * @return array<int, string>
	 */
	public static function filter_body_class( array $classes ): array {
		if ( self::is_about_page() ) {
			$classes[] = 'about-page';
		}

		return $classes;
	}

	public static function filter_template_include( string $template ): string {
		if ( ! self::is_about_page() || is_page_template( 'page-about.php' ) ) {
			return $template;
		}

		$about_template = get_stylesheet_directory() . '/page-about.php';
		if ( is_readable( $about_template ) ) {
			return $about_template;
		}

		return $template;
	}

	public static function is_about_page(): bool {
		if ( is_page_template( 'page-about.php' ) ) {
			return true;
		}

		if ( ! is_page() ) {
			return false;
		}

		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		$slug = sanitize_title( (string) $post->post_name );
		$known_slugs = array(
			'about',
			'about-us',
			'darbare-ma',
			'darbarema',
			'aboutus',
		);

		if ( in_array( $slug, $known_slugs, true ) ) {
			return true;
		}

		$title = trim( (string) $post->post_title );

		return $title === 'درباره ما' || $title === 'درباره‌ما';
	}
}
