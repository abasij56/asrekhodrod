<?php

namespace AsreKhodro\Theme\AcfBlocks\CinfoHero;

use AsreKhodro\Theme\AcfBlocks\Support\RateFormatter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class View {

	/**
	 * @param array<string, mixed> $fields
	 * @return array<string, mixed>
	 */
	public static function context( array $fields, ?\Timber\Post $post = null ): array {
		$title = trim( (string) ( $fields['title'] ?? '' ) );
		if ( $title === '' && $post ) {
			$title = (string) $post->title();
		}

		$overall = RateFormatter::normalize( $fields['overall_rate'] ?? 0 );
		$items   = array();

		foreach ( (array) ( $fields['rate_items'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$item_title = trim( (string) ( $row['item_title'] ?? '' ) );
			if ( $item_title === '' ) {
				continue;
			}
			$item_rate = RateFormatter::normalize( $row['item_rate'] ?? 0 );
			$items[]   = array(
				'title'      => $item_title,
				'rate'       => $item_rate,
				'rate_label' => RateFormatter::format( $item_rate ),
				'bar_width'  => RateFormatter::bar_width( $item_rate ),
			);
		}

		$image = self::resolve_image( $fields['image'] ?? null, $post );

		return array(
			'hero_badge'            => trim( (string) ( $fields['badge'] ?? '' ) ) ?: 'بررسی مدل',
			'hero_title'            => $title,
			'hero_subtitle'         => trim( (string) ( $fields['subtitle'] ?? '' ) ),
			'hero_overall_rate'     => $overall,
			'hero_overall_label'    => RateFormatter::format( $overall ),
			'hero_stars'            => RateFormatter::stars( $overall ),
			'hero_rate_items'       => $items,
			'hero_image_url'        => $image['url'],
			'hero_image_alt'        => $image['alt'],
			'hero_image_width'      => $image['width'],
			'hero_image_height'     => $image['height'],
			'hero_score_aria_label' => $overall > 0
				? sprintf(
					/* translators: %s: overall score out of 10 */
					__( 'امتیاز کلی %s از ۱۰', 'asrekhodro' ),
					RateFormatter::format( $overall )
				)
				: '',
		);
	}

	/**
	 * @param mixed $image_field
	 * @return array{url: string, alt: string, width: int, height: int}
	 */
	private static function resolve_image( $image_field, ?\Timber\Post $post ): array {
		if ( is_array( $image_field ) && ! empty( $image_field['url'] ) ) {
			return array(
				'url'    => (string) $image_field['url'],
				'alt'    => (string) ( $image_field['alt'] ?? '' ),
				'width'  => (int) ( $image_field['width'] ?? 960 ),
				'height' => (int) ( $image_field['height'] ?? 600 ),
			);
		}

		if ( $post && $post->thumbnail() ) {
			$thumb = $post->thumbnail();

			return array(
				'url'    => (string) $thumb->src( 'large' ),
				'alt'    => (string) ( $thumb->alt() ?: $post->title() ),
				'width'  => (int) ( $thumb->width() ?: 960 ),
				'height' => (int) ( $thumb->height() ?: 600 ),
			);
		}

		return array(
			'url'    => '',
			'alt'    => '',
			'width'  => 960,
			'height' => 600,
		);
	}
}
