<?php

namespace AsreKhodro\Theme\AcfBlocks\AkFeaturedCars;

use AsreKhodro\Theme\CarsInfoDirectory;

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

		$title      = trim( (string) ( $fields['section_title'] ?? '' ) );
		$view_url   = trim( (string) ( $fields['view_url'] ?? '' ) ) ?: CarsInfoDirectory::archive_url();
		$view_label = trim( (string) ( $fields['view_label'] ?? '' ) ) ?: 'آرشیو دانشنامه ←';
		$mode       = (string) ( $fields['selection_mode'] ?? 'latest' );

		if ( $mode === 'manual' ) {
			$ids   = array();
			foreach ( (array) ( $fields['featured_cars'] ?? array() ) as $post_id ) {
				$post_id = (int) $post_id;
				if ( $post_id > 0 ) {
					$ids[] = $post_id;
				}
			}
			$cards = CarsInfoDirectory::featured_cards( $ids );
		} else {
			$count = (int) ( $fields['count'] ?? 8 );
			$cat   = (int) ( $fields['category'] ?? 0 );
			$cards = CarsInfoDirectory::latest_featured_cards( $count, $cat );
		}

		return array(
			'featured_cars_title'      => $title !== '' ? $title : 'ماشین‌های منتخب',
			'featured_cars_link_url'   => $view_url,
			'featured_cars_link_label' => $view_label,
			'featured_cars'            => $cards,
			'featured_cars_has_items'  => $cards !== array(),
		);
	}
}
