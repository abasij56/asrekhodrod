<?php

namespace AsreKhodro\Theme\AcfBlocks\CinfoRelated;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class View {

	/**
	 * @param array<string, mixed> $fields
	 * @return array<string, mixed>
	 */
	public static function context( array $fields, ?\Timber\Post $post = null ): array {
		$fallback_alt = $post ? (string) $post->title() : '';
		$title        = trim( (string) ( $fields['title'] ?? '' ) );
		$cards        = array();

		foreach ( (array) ( $fields['related_cards'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$card_title = trim( (string) ( $row['title'] ?? '' ) );
			$subtitle   = trim( (string) ( $row['subtitle'] ?? '' ) );
			$link       = esc_url( (string) ( $row['link'] ?? '' ) );
			$image      = self::normalize_image( $row['image'] ?? null, $card_title, $fallback_alt );

			if ( $card_title === '' && $subtitle === '' && $image === null ) {
				continue;
			}

			$cards[] = array(
				'title'    => $card_title,
				'subtitle' => $subtitle,
				'link'     => $link,
				'image'    => $image,
			);
		}

		$archive_title = trim( (string) ( $fields['archive_link_title'] ?? '' ) );
		$archive_href  = esc_url( (string) ( $fields['archive_link_href'] ?? '' ) );
		$has_archive   = $archive_title !== '' && $archive_href !== '';
		$default_anchor = (string) ( Block::config()['default_anchor'] ?? 'related' );

		return array(
			'related_title'          => $title,
			'related_cards'          => $cards,
			'related_archive_title'  => $archive_title,
			'related_archive_href'   => $archive_href,
			'related_has_archive'    => $has_archive,
			'related_default_anchor' => $default_anchor,
			'related_has_content'    => $title !== '' || $cards !== array() || $has_archive,
		);
	}

	/**
	 * @param mixed $image
	 * @return array{url: string, alt: string, width: int, height: int}|null
	 */
	private static function normalize_image( $image, string $card_title, string $fallback_alt ): ?array {
		if ( ! is_array( $image ) ) {
			return null;
		}

		$url = (string) ( $image['url'] ?? '' );
		if ( $url === '' ) {
			return null;
		}

		$sizes = is_array( $image['sizes'] ?? null ) ? $image['sizes'] : array();
		$alt   = trim( (string) ( $image['alt'] ?? '' ) );

		if ( $alt === '' ) {
			$alt = $card_title !== '' ? $card_title : $fallback_alt;
		}

		return array(
			'url'    => $url,
			'alt'    => $alt,
			'width'  => (int) ( $sizes['medium-width'] ?? $image['width'] ?? 400 ),
			'height' => (int) ( $sizes['medium-height'] ?? $image['height'] ?? 250 ),
		);
	}
}
