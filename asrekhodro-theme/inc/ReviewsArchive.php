<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReviewsArchive {

	public static function init(): void {
		// Reserved for future Twig helpers.
	}

	/**
	 * @return array<string, string|bool>
	 */
	public static function get_archive_page_context(): array {
		$hero = ArchiveHero::from_acf_option( 'review_archive_hero_image' );

		return array(
			'review_archive_title'              => self::get_archive_title(),
			'review_archive_subtitle'           => self::get_archive_subtitle(),
			'review_archive_hero_image'         => $hero['url'],
			'review_archive_hero_aspect'        => $hero['aspect'],
			'review_archive_hero_aspect_fallback' => $hero['aspect_fallback'],
		);
	}

	public static function get_archive_title(): string {
		if ( function_exists( 'get_field' ) ) {
			$title = get_field( 'review_archive_title', 'option' );
			if ( is_string( $title ) && trim( $title ) !== '' ) {
				return trim( $title );
			}
		}

		return __( 'تست و بررسی خودرو', 'asrekhodro' );
	}

	public static function get_archive_subtitle(): string {
		if ( function_exists( 'get_field' ) ) {
			$subtitle = get_field( 'review_archive_subtitle', 'option' );
			if ( is_string( $subtitle ) && trim( $subtitle ) !== '' ) {
				return trim( $subtitle );
			}
		}

		return __( 'آخرین بررسی‌های منتشر شده', 'asrekhodro' );
	}

	public static function get_archive_hero_image_url(): string {
		return ArchiveHero::from_acf_option( 'review_archive_hero_image' )['url'];
	}
}
