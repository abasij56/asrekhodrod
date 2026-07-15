<?php

namespace AsreKhodro\Theme\Blocks\AkHero;

use AsreKhodro\Theme\ImporterBridge;
use AsreKhodro\Theme\SinglePost;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps queried posts to hero slider UI shape.
 *
 * Main: image, excerpt, read-more link (+ title for heading).
 * Side: badge, title, date.
 */
final class View {

	/**
	 * @param iterable<int, \Timber\Post>|null $posts
	 * @return array{hero_main: ?array<string, mixed>, hero_side_items: list<array<string, mixed>>}
	 */
	public static function context( mixed $posts ): array {
		$collected = array();

		if ( $posts !== null ) {
			foreach ( $posts as $post ) {
				if ( $post instanceof \Timber\Post ) {
					$collected[] = $post;
				}
			}
		}

		if ( $collected === array() ) {
			$fallback = ImporterBridge::query_posts( array( 'posts_per_page' => 5 ) )->to_array();
			$main     = $fallback[0] ?? null;
			$side     = array_slice( $fallback, 1, 4 );

			return self::compose(
				$main instanceof \Timber\Post ? $main : null,
				$side
			);
		}

		return self::compose(
			$collected[0],
			array_slice( $collected, 1, 4 )
		);
	}

	/**
	 * @param array<string, mixed> $legacy
	 * @return array{hero_main: ?array<string, mixed>, hero_side_items: list<array<string, mixed>>}
	 */
	public static function from_legacy( array $legacy ): array {
		$main = $legacy['hero_main'] ?? null;
		if ( ! $main instanceof \Timber\Post ) {
			return array(
				'hero_main'             => null,
				'hero_side_items'       => array(),
				'hero_side_panels_json' => '[]',
			);
		}

		$visual = $legacy['hero_visual'] ?? $main;
		if ( ! $visual instanceof \Timber\Post ) {
			$visual = $main;
		}

		$side = $legacy['hero_side_posts'] ?? array();
		if ( ! is_array( $side ) ) {
			$side = array();
		}

		return self::compose( $main, $side, $visual );
	}

	/**
	 * @param list<\Timber\Post> $side_posts
	 * @return array{hero_main: ?array<string, mixed>, hero_side_items: list<array<string, mixed>>}
	 */
	private static function compose(
		?\Timber\Post $main,
		array $side_posts,
		?\Timber\Post $visual = null
	): array {
		if ( ! $main instanceof \Timber\Post ) {
			return array(
				'hero_main'             => null,
				'hero_side_items'       => array(),
				'hero_side_panels_json' => '[]',
			);
		}

		$visual = $visual ?? $main;
		if ( ! ImporterBridge::post_has_real_image( $main ) ) {
			$visual = ImporterBridge::find_first_post_with_image() ?? $main;
		}

		$nav_posts  = array_slice( array_merge( array( $main ), $side_posts ), 0, 5 );
		$side_items = self::map_side_items( $nav_posts, $visual );

		return array(
			'hero_main'             => self::map_main( $main, $visual ),
			'hero_side_items'       => $side_items,
			'hero_side_panels_json' => self::panels_json( $side_items ),
		);
	}

	/**
	 * @return array{id: int, link: string, title: string, excerpt: string, image: string}
	 */
	private static function map_main( \Timber\Post $main, \Timber\Post $visual ): array {
		return array(
			'id'          => (int) $main->ID,
			'link'        => (string) $main->link(),
			'title'       => trim( (string) $main->title() ),
			'under_title' => SinglePost::get_under_title_from_block( $main ),
			'excerpt'     => ImporterBridge::get_list_excerpt( $main, 220 ),
			'image'       => ImporterBridge::get_post_image_url( $visual ),
		);
	}

	/**
	 * @param list<\Timber\Post> $posts
	 * @return list<array{id: int, badge: string, title: string, date: string, date_iso: string, link: string, excerpt: string, image: string}>
	 */
	private static function map_side_items( array $posts, ?\Timber\Post $visual_for_first = null ): array {
		$items = array();

		foreach ( $posts as $index => $post ) {
			if ( ! $post instanceof \Timber\Post ) {
				continue;
			}

			$title = trim( (string) $post->title() );
			if ( $title === '' ) {
				continue;
			}

			$image_post = ( $index === 0 && $visual_for_first instanceof \Timber\Post )
				? $visual_for_first
				: $post;

			$items[] = array(
				'id'          => (int) $post->ID,
				'badge'       => trim( (string) $post->meta( '_asrekhodro_over_title' ) ),
				'title'       => $title,
				'under_title' => SinglePost::get_under_title_from_block( $post ),
				'date'        => ImporterBridge::format_post_date( $post ),
				'date_iso'    => (string) $post->date( 'Y-m-d' ),
				'link'        => (string) $post->link(),
				'excerpt'     => ImporterBridge::get_list_excerpt( $post, 220 ),
				'image'       => ImporterBridge::get_post_image_url( $image_post ),
			);
		}

		return $items;
	}

	/**
	 * @param list<array<string, mixed>> $side_items
	 */
	private static function panels_json( array $side_items ): string {
		$panels = array();

		foreach ( $side_items as $item ) {
			$panels[] = array(
				'id'          => (int) ( $item['id'] ?? 0 ),
				'title'       => (string) ( $item['title'] ?? '' ),
				'under_title' => (string) ( $item['under_title'] ?? '' ),
				'excerpt'     => (string) ( $item['excerpt'] ?? '' ),
				'image'       => (string) ( $item['image'] ?? '' ),
				'link'        => (string) ( $item['link'] ?? '' ),
			);
		}

		return wp_json_encode(
			$panels,
			JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		) ?: '[]';
	}
}
