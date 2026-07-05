<?php

namespace AsreKhodro\Theme\AcfBlocks\CinfoOverview;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class View {

	/**
	 * @param array<string, mixed> $fields
	 * @return array<string, mixed>
	 */
	public static function context( array $fields, ?\Timber\Post $post = null ): array {
		unset( $post );

		$title   = trim( (string) ( $fields['title'] ?? '' ) );
		$content = trim( (string) ( $fields['content'] ?? '' ) );

		if ( $content !== '' ) {
			$content = (string) apply_filters( 'the_content', $content );
		}

		$default_anchor = (string) ( Block::config()['default_anchor'] ?? 'overview' );

		return array(
			'overview_title'          => $title,
			'overview_content'        => $content,
			'overview_default_anchor' => $default_anchor,
			'overview_has_content'    => $title !== '' || $content !== '',
		);
	}
}
