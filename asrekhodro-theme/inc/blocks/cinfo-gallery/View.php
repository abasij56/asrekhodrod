<?php

namespace AsreKhodro\Theme\AcfBlocks\CinfoGallery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class View {

	/**
	 * @param array<string, mixed> $fields
	 * @return array<string, mixed>
	 */
	public static function context( array $fields, ?\Timber\Post $post = null ): array {
		$title        = trim( (string) ( $fields['title'] ?? '' ) );
		$fallback_alt = $post ? (string) $post->title() : '';

		$groups      = array();
		$flat_images = array();

		foreach ( (array) ( $fields['gallery_groups'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$group_title = trim( (string) ( $row['group_title'] ?? '' ) );
			$images      = self::normalize_images( (array) ( $row['images'] ?? array() ), $group_title, $fallback_alt );

			if ( $images === array() ) {
				continue;
			}

			$groups[] = array(
				'title'  => $group_title,
				'images' => $images,
			);

			foreach ( $images as $image ) {
				$flat_images[] = $image;
			}
		}

		$default_anchor = (string) ( Block::config()['default_anchor'] ?? 'gallery' );

		return array(
			'gallery_title'          => $title,
			'gallery_groups'         => $groups,
			'gallery_images'         => $flat_images,
			'gallery_fallback_alt'   => $fallback_alt,
			'gallery_default_anchor' => $default_anchor,
			'gallery_has_content'    => $title !== '' || $flat_images !== array(),
		);
	}

	/**
	 * @param array<int, mixed> $images
	 * @return list<array{thumb_url: string, full_url: string, alt: string, width: int, height: int}>
	 */
	private static function normalize_images( array $images, string $group_title, string $fallback_alt ): array {
		$normalized = array();

		foreach ( $images as $image ) {
			if ( ! is_array( $image ) ) {
				continue;
			}

			$full_url = (string) ( $image['url'] ?? '' );
			if ( $full_url === '' ) {
				continue;
			}

			$sizes     = is_array( $image['sizes'] ?? null ) ? $image['sizes'] : array();
			$thumb_url = (string) ( $sizes['medium'] ?? $sizes['medium_large'] ?? $sizes['thumbnail'] ?? $full_url );
			$alt       = trim( (string) ( $image['alt'] ?? '' ) );

			if ( $alt === '' ) {
				$alt = $group_title !== '' ? $group_title : $fallback_alt;
			}

			$normalized[] = array(
				'thumb_url' => $thumb_url,
				'full_url'  => $full_url,
				'alt'       => $alt,
				'width'     => (int) ( $sizes['medium-width'] ?? $image['width'] ?? 400 ),
				'height'    => (int) ( $sizes['medium-height'] ?? $image['height'] ?? 300 ),
			);
		}

		return $normalized;
	}
}
