<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VideosArchive {

	public static function init(): void {
		// Reserved for future Twig helpers.
	}

	public static function is_archive_request(): bool {
		return is_post_type_archive( 'ak_video' ) || is_tax( 'video_category' );
	}

	/**
	 * @return array<string, string|bool>
	 */
	public static function get_archive_page_context( string $wp_title = '' ): array {
		$hero = ArchiveHero::from_acf_option( 'video_archive_hero_image' );

		return array(
			'video_archive_title'                => self::get_archive_title(),
			'video_archive_subtitle'             => self::get_archive_subtitle(),
			'video_archive_hero_image'           => $hero['url'],
			'video_archive_hero_aspect'          => $hero['aspect'],
			'video_archive_hero_aspect_fallback' => $hero['aspect_fallback'],
			'archive_hero_title'                 => self::get_hero_title( $wp_title ),
		);
	}

	public static function get_archive_title(): string {
		if ( function_exists( 'get_field' ) ) {
			$title = get_field( 'video_archive_title', 'option' );
			if ( is_string( $title ) && trim( $title ) !== '' ) {
				return trim( $title );
			}
		}

		return __( 'ویدئوهای عصر خودرو', 'asrekhodro' );
	}

	public static function get_archive_subtitle(): string {
		if ( function_exists( 'get_field' ) ) {
			$subtitle = get_field( 'video_archive_subtitle', 'option' );
			if ( is_string( $subtitle ) && trim( $subtitle ) !== '' ) {
				return trim( $subtitle );
			}
		}

		return __( 'آخرین ویدئوهای منتشر شده', 'asrekhodro' );
	}

	public static function get_hero_title( string $wp_title = '' ): string {
		if ( is_tax( 'video_category' ) ) {
			$label = single_term_title( '', false );

			return is_string( $label ) && $label !== '' ? $label : self::get_archive_title();
		}

		return self::get_archive_title();
	}
}
