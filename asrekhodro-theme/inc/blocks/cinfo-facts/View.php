<?php

namespace AsreKhodro\Theme\AcfBlocks\CinfoFacts;

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

		$items = array();

		foreach ( (array) ( $fields['fact_items'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$label = trim( (string) ( $row['item_label'] ?? '' ) );
			$value = trim( (string) ( $row['item_value'] ?? '' ) );

			if ( $label === '' && $value === '' ) {
				continue;
			}

			$items[] = array(
				'label' => $label,
				'value' => $value,
			);
		}

		return array(
			'facts_items' => $items,
		);
	}
}
