<?php

namespace AsreKhodro\Theme\AcfBlocks\CinfoFacts;

use AsreKhodro\Theme\CarSpecIcons;

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

			$icon_id     = sanitize_text_field( (string) ( $row['item_icon'] ?? '' ) );
			$icon_item   = $icon_id !== '' ? CarSpecIcons::item_for_id( $icon_id ) : null;
			$custom_svg  = self::sanitize_svg( (string) ( $row['item_icon_svg'] ?? '' ) );

			$items[] = array(
				'label'        => $label,
				'value'        => $value,
				'icon'         => $icon_id,
				'icon_url'     => is_array( $icon_item ) ? (string) ( $icon_item['url'] ?? '' ) : '',
				'icon_title'   => is_array( $icon_item ) ? (string) ( $icon_item['title'] ?? '' ) : '',
				'icon_sprite'  => is_array( $icon_item ) && ! empty( $icon_item['sprite'] ),
				'icon_symbol'  => is_array( $icon_item ) ? (string) ( $icon_item['symbol'] ?? '' ) : '',
				'icon_svg'     => $custom_svg,
				'show_in_card' => ! empty( $row['show_in_card'] ),
			);
		}

		return array(
			'facts_items' => $items,
		);
	}

	/**
	 * Allow only a safe subset of inline SVG markup for custom icons.
	 */
	private static function sanitize_svg( string $svg ): string {
		$svg = trim( $svg );
		if ( $svg === '' || stripos( $svg, '<svg' ) === false ) {
			return '';
		}

		$attrs = array_fill_keys(
			array(
				'xmlns',
				'viewbox',
				'width',
				'height',
				'fill',
				'stroke',
				'stroke-width',
				'stroke-linecap',
				'stroke-linejoin',
				'stroke-miterlimit',
				'stroke-dasharray',
				'stroke-dashoffset',
				'class',
				'd',
				'points',
				'x',
				'y',
				'x1',
				'y1',
				'x2',
				'y2',
				'cx',
				'cy',
				'r',
				'rx',
				'ry',
				'transform',
				'gradientunits',
				'gradienttransform',
				'offset',
				'stop-color',
				'stop-opacity',
				'fill-rule',
				'clip-rule',
				'fill-opacity',
				'opacity',
				'id',
				'aria-hidden',
				'focusable',
			),
			true
		);

		$allowed = array(
			'svg'            => $attrs,
			'g'              => $attrs,
			'path'           => $attrs,
			'circle'         => $attrs,
			'ellipse'        => $attrs,
			'rect'           => $attrs,
			'line'           => $attrs,
			'polyline'       => $attrs,
			'polygon'        => $attrs,
			'defs'           => $attrs,
			'lineargradient' => $attrs,
			'radialgradient' => $attrs,
			'stop'           => $attrs,
			'title'          => $attrs,
			'use'            => $attrs,
		);

		return (string) wp_kses( $svg, $allowed );
	}
}
