<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Small helpers for shared homepage context (ads, archive URL).
 * Block content comes from Layout Builder placements only.
 */
final class HomepageData {

	/**
	 * @return array<string, mixed>
	 */
	public static function section( string $name ): array {
		return match ( $name ) {
			'content_row_ad'   => array( 'content_row_ad' => ImporterBridge::get_ads_by_position( 'content_row', 1 ) ),
			'news_archive_url' => array( 'news_archive_url' => self::news_archive_url() ),
			default            => array(),
		};
	}

	public static function news_archive_url(): string {
		$posts_page = get_permalink( (int) get_option( 'page_for_posts' ) );

		return $posts_page ? (string) $posts_page : home_url( '/' );
	}
}
