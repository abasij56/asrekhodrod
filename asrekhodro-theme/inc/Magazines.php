<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Magazines {

	public const LEGACY_ARCHIVE_PATH = '/Home/Kiosk';

	public static function init(): void {
		add_filter( 'timber/twig/functions', array( self::class, 'register_twig_functions' ) );
	}

	/**
	 * @param array<string, mixed> $functions
	 * @return array<string, mixed>
	 */
	public static function register_twig_functions( array $functions ): array {
		$functions['ak_magazine_archive_url'] = array(
			'callable' => array( self::class, 'get_archive_url' ),
		);
		$functions['ak_magazine_issue_number'] = array(
			'callable' => array( self::class, 'parse_issue_number_from_title' ),
		);
		$functions['ak_magazine_featured_image_url'] = array(
			'callable' => array( self::class, 'get_featured_image_url_for_post' ),
		);

		return $functions;
	}

	public static function get_archive_url(): string {
		$options = function_exists( 'get_fields' ) ? ( get_fields( 'option' ) ?: array() ) : array();
		if ( ! empty( $options['kiosk_archive_url'] ) ) {
			return esc_url( (string) $options['kiosk_archive_url'] );
		}

		$archive = get_post_type_archive_link( 'ak_magazine' );
		if ( $archive ) {
			return esc_url( $archive );
		}

		return esc_url( home_url( self::LEGACY_ARCHIVE_PATH . '/' ) );
	}

	public static function get_legacy_path( int $file_id ): string {
		return self::LEGACY_ARCHIVE_PATH . '/' . $file_id;
	}

	/**
	 * @return array<string, string|bool>
	 */
	public static function get_archive_page_context(): array {
		$hero = ArchiveHero::from_acf_option( 'kiosk_archive_hero_image' );

		return array(
			'kiosk_archive_title'              => self::get_archive_title(),
			'kiosk_archive_subtitle'             => self::get_archive_subtitle(),
			'kiosk_archive_hero_image'           => $hero['url'],
			'kiosk_archive_hero_aspect'          => $hero['aspect'],
			'kiosk_archive_hero_aspect_fallback' => $hero['aspect_fallback'],
		);
	}

	public static function get_archive_title(): string {
		if ( function_exists( 'get_field' ) ) {
			$title = get_field( 'kiosk_archive_title', 'option' );
			if ( is_string( $title ) && trim( $title ) !== '' ) {
				return trim( $title );
			}
		}

		return __( 'دکه مطبوعات', 'asrekhodro' );
	}

	public static function get_archive_subtitle(): string {
		if ( function_exists( 'get_field' ) ) {
			$subtitle = get_field( 'kiosk_archive_subtitle', 'option' );
			if ( is_string( $subtitle ) && trim( $subtitle ) !== '' ) {
				return trim( $subtitle );
			}
		}

		return __( 'آخرین مجلات اضافه شده', 'asrekhodro' );
	}

	public static function get_archive_hero_image_url(): string {
		return ArchiveHero::from_acf_option( 'kiosk_archive_hero_image' )['url'];
	}

	/**
	 * Extract issue number from a magazine title, e.g. "مجله شماره 749" → "749".
	 */
	public static function parse_issue_number_from_title( string $title ): string {
		$title = trim( $title );
		if ( $title === '' ) {
			return '';
		}

		$normalized = self::normalize_digits_to_ascii( $title );

		if ( preg_match( '/شماره\s*([0-9]+)/u', $normalized, $matches ) ) {
			return $matches[1];
		}

		if ( preg_match( '/([0-9]+)(?!.*[0-9])/u', $normalized, $matches ) ) {
			return $matches[1];
		}

		return '';
	}

	private static function normalize_digits_to_ascii( string $value ): string {
		$persian = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$arabic  = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$ascii   = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );

		return str_replace( $persian, $ascii, str_replace( $arabic, $ascii, $value ) );
	}

	/**
	 * Magazine cover from post thumbnail only (matches admin list).
	 */
	public static function get_featured_image_url_for_post( \Timber\Post $magazine, string $size = 'medium' ): string {
		return ImporterBridge::get_featured_image_url( (int) $magazine->ID, $size );
	}

	public static function get_cover_url( \Timber\Post $magazine ): string {
		return self::get_featured_image_url_for_post( $magazine, 'medium' );
	}
}
