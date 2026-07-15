<?php

namespace AsreKhodro\Theme\Blocks\AkAsrekhodroFeatured;

use AsreKhodro\Theme\ImporterBridge;
use AsreKhodro\Theme\SinglePost;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lead post (first) + magazine flip pages (all remaining queried posts).
 */
final class View {

	/**
	 * @param iterable<int, \Timber\Post>|null $posts
	 * @return array{
	 *   featured_lead: ?array<string, mixed>,
	 *   featured_flip_posts: list<\Timber\Post>
	 * }
	 */
	public static function context( mixed $posts, int $requested_count = 6 ): array {
		$collected = self::collect_posts( $posts );

		if ( $collected === array() ) {
			$fallback = max( 2, min( 40, $requested_count ) );
			$collected = ImporterBridge::query_posts( array( 'posts_per_page' => $fallback ) )->to_array();
		}

		return self::compose( $collected );
	}

	/**
	 * @param mixed $featured_posts Timber posts from legacy featured section.
	 * @return array{
	 *   featured_lead: ?array<string, mixed>,
	 *   featured_flip_posts: list<\Timber\Post>
	 * }
	 */
	public static function from_legacy( mixed $featured_posts ): array {
		return self::compose( self::collect_posts( $featured_posts ) );
	}

	/**
	 * @param iterable<int, \Timber\Post>|null $posts
	 * @return list<\Timber\Post>
	 */
	private static function collect_posts( mixed $posts ): array {
		$collected = array();

		if ( $posts !== null ) {
			foreach ( $posts as $post ) {
				if ( $post instanceof \Timber\Post ) {
					$collected[] = $post;
				}
			}
		}

		return $collected;
	}

	/**
	 * @param list<\Timber\Post> $posts
	 * @return array{
	 *   featured_lead: ?array<string, mixed>,
	 *   featured_flip_posts: list<\Timber\Post>
	 * }
	 */
	private static function compose( array $posts ): array {
		if ( $posts === array() ) {
			return array(
				'featured_lead'       => null,
				'featured_flip_posts' => array(),
			);
		}

		$lead = self::map_lead( $posts[0] );
		$flip = array_slice( $posts, 1 );

		return array(
			'featured_lead'       => $lead,
			'featured_flip_posts' => $flip,
		);
	}

	/**
	 * @return ?array{
	 *   id: int,
	 *   link: string,
	 *   title: string,
	 *   excerpt: string,
	 *   image: string,
	 *   badge: string,
	 *   date: string,
	 *   date_iso: string
	 * }
	 */
	private static function map_lead( \Timber\Post $post ): ?array {
		$title = trim( (string) $post->title() );
		if ( $title === '' ) {
			return null;
		}

		$badge = ImporterBridge::get_primary_category_name( $post );
		if ( $badge === '' ) {
			$badge = trim( (string) $post->meta( '_asrekhodro_over_title' ) );
		}

		return array(
			'id'       => (int) $post->ID,
			'link'     => (string) $post->link(),
			'title'    => $title,
			'excerpt'  => ImporterBridge::get_list_excerpt( $post, 220 ),
			'image'    => ImporterBridge::get_post_image_url( $post ),
			'badge'    => $badge,
			'under_title' => SinglePost::get_under_title_from_block( $post ),
			'date'     => ImporterBridge::format_post_date( $post ),
			'date_iso' => (string) $post->date( 'Y-m-d' ),
		);
	}
}
