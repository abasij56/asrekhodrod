<?php

namespace AsreKhodro\Theme\Blocks\AkMagazines;

use AsreKhodro\Theme\ImporterBridge;
use AsreKhodro\Theme\PostImage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps magazine posts to grid items (image, title, date, link).
 */
final class View {

	/**
	 * @param iterable<int, \Timber\Post>|null $posts
	 * @return list<array{id: int, link: string, title: string, image: string, date: string, date_iso: string}>
	 */
	public static function items( mixed $posts, string $strategy = 'latest' ): array {
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

			$items[] = PostImage::merge(
				array(
					'id'       => (int) $post->ID,
					'link'     => (string) $post->link(),
					'title'    => $title,
					'date'     => ImporterBridge::format_post_date( $post ),
					'date_iso' => (string) $post->date( 'Y-m-d' ),
				),
				$post,
				300,
				400
			);
		}

		// Carousel: oldest on the left, newest on the right (RTL homepage).
		if ( $strategy !== 'oldest' ) {
			$items = array_reverse( $items );
		}

		return $items;
	}
}
