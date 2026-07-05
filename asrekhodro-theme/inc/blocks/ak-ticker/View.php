<?php

namespace AsreKhodro\Theme\Blocks\AkTicker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps queried posts to ticker strip items (title + link only).
 */
final class View {

	/**
	 * @param iterable<int, \Timber\Post>|null $posts
	 * @return list<array{title: string, link: string}>
	 */
	public static function items( mixed $posts ): array {
		if ( $posts === null ) {
			return array();
		}

		$items = array();
		foreach ( $posts as $post ) {
			if ( ! $post instanceof \Timber\Post ) {
				continue;
			}

			$title = trim( (string) $post->title() );
			if ( $title === '' ) {
				continue;
			}

			$items[] = array(
				'title' => $title,
				'link'  => (string) $post->link(),
			);
		}

		return $items;
	}
}
