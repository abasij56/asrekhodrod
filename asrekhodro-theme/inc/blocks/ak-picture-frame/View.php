<?php

namespace AsreKhodro\Theme\Blocks\AkPictureFrame;

use AsreKhodro\Theme\ImporterBridge;
use AsreKhodro\Theme\MediaAlt;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps queried posts to picture-frame slider items.
 */
final class View {

	/**
	 * @param iterable<int, \Timber\Post>|null $posts
	 * @return array{
	 *   picture_frame_items: list<array{title: string, image: string, thumb: string, link: string, caption: string}>,
	 *   picture_frame_archive_url: string
	 * }
	 */
	public static function context( mixed $posts, int $category = 0, string $strategy = 'latest' ): array {
		return array(
			'picture_frame_items'       => self::items( $posts, $strategy ),
			'picture_frame_archive_url' => self::archive_url( $category ),
		);
	}

	/**
	 * @param iterable<int, \Timber\Post>|null $posts
	 * @return list<array{title: string, image: string, thumb: string, link: string, caption: string, rank: int}>
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
			$image = ImporterBridge::get_post_image_url( $post );
			$thumb = $image;
			if ( $post->thumbnail ) {
				$medium = $post->thumbnail->src( 'medium' );
				if ( is_string( $medium ) && $medium !== '' ) {
					$thumb = $medium;
				}
			}

			if ( $title === '' || $image === '' ) {
				continue;
			}

			$image_alt = MediaAlt::from_post_thumbnail( (int) $post->ID );
			if ( $image_alt === '' ) {
				$image_alt = MediaAlt::from_url( $image );
			}

			$items[] = array(
				'title'     => $title,
				'image'     => $image,
				'thumb'     => $thumb !== '' ? $thumb : $image,
				'image_alt' => $image_alt,
				'link'      => (string) $post->link(),
				'caption'   => ImporterBridge::get_list_excerpt( $post, 160 ),
			);
		}

		// Filmstrip: oldest on the left, newest (first queried) on the right.
		if ( $strategy !== 'oldest' ) {
			$items = array_reverse( $items );
		}

		$total = count( $items );
		foreach ( array_keys( $items ) as $i ) {
			// Newest (right) = 1, oldest (left) = total.
			$items[ $i ]['rank'] = $total - $i;
		}

		return $items;
	}

	public static function archive_url( int $category_id = 0 ): string {
		if ( $category_id > 0 ) {
			$link = get_term_link( $category_id, 'category' );
			if ( ! is_wp_error( $link ) ) {
				return esc_url( (string) $link );
			}
		}

		$term = get_term_by( 'name', 'در قاب تصویر', 'category' );
		if ( $term instanceof \WP_Term ) {
			$link = get_term_link( $term );
			if ( ! is_wp_error( $link ) ) {
				return esc_url( (string) $link );
			}
		}

		return '';
	}
}
