<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Layout context helpers for non-front templates.
 */
final class PageLayout {

	/**
	 * @param array<string, mixed> $extra
	 * @return array<string, mixed>
	 */
	public static function archive_context( array $extra = array() ): array {
		return LayoutEngine::page_context( 'archive', $extra );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function single_context( int $post_id ): array {
		return LayoutEngine::page_context(
			'single_post',
			array(
				'exclude_post_id' => $post_id,
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function search_context(): array {
		return self::archive_context();
	}

	/**
	 * @param array<string, mixed> $extra
	 * @return array<string, mixed>
	 */
	public static function for_page( string $page_key, array $extra = array() ): array {
		return LayoutEngine::page_context( $page_key, $extra );
	}
}
