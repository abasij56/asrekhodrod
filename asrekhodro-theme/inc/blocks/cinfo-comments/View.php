<?php

namespace AsreKhodro\Theme\AcfBlocks\CinfoComments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class View {

	/**
	 * @param array<string, mixed> $fields
	 * @return array<string, mixed>
	 */
	public static function context( array $fields, ?\Timber\Post $post = null ): array {
		$title          = trim( (string) ( $fields['title'] ?? '' ) );
		$default_anchor = (string) ( Block::config()['default_anchor'] ?? 'comments' );
		$comments_open  = $post && (string) $post->comment_status === 'open';
		$comment_count  = $post ? (int) $post->comment_count : 0;

		return array(
			'comments_title'          => $title,
			'comments_default_anchor' => $default_anchor,
			'comments_open'           => $comments_open,
			'comments_count'          => $comment_count,
			'comments_has_content'    => $title !== '' || $comments_open || $comment_count > 0,
		);
	}
}
