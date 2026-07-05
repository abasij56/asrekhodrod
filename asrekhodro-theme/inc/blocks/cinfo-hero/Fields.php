<?php

namespace AsreKhodro\Theme\AcfBlocks\CinfoHero;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Fields {

	public static function register(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			array(
				'key'      => 'group_cinfo_hero',
				'title'    => 'Car info — hero',
				'fields'   => array(
					array(
						'key'           => 'field_cinfo_hero_badge',
						'label'         => 'Badge',
						'name'          => 'badge',
						'type'          => 'text',
						'default_value' => 'بررسی مدل',
						'placeholder'   => 'بررسی مدل',
					),
					array(
						'key'          => 'field_cinfo_hero_title',
						'label'        => 'Title',
						'name'         => 'title',
						'type'         => 'text',
						'instructions' => 'Leave empty to use the post title.',
					),
					array(
						'key'   => 'field_cinfo_hero_subtitle',
						'label' => 'Subtitle',
						'name'  => 'subtitle',
						'type'  => 'text',
					),
					array(
						'key'           => 'field_cinfo_hero_overall_rate',
						'label'         => 'Overall rate',
						'name'          => 'overall_rate',
						'type'          => 'number',
						'instructions'  => 'Score out of 10 (e.g. 4.6).',
						'min'           => 0,
						'max'           => 10,
						'step'          => 0.1,
						'default_value' => 0,
					),
					array(
						'key'          => 'field_cinfo_hero_rate_items',
						'label'        => 'Rate items',
						'name'         => 'rate_items',
						'type'         => 'repeater',
						'layout'       => 'table',
						'button_label' => 'Add rate',
						'sub_fields'   => array(
							array(
								'key'   => 'field_cinfo_hero_rate_item_title',
								'label' => 'Title',
								'name'  => 'item_title',
								'type'  => 'text',
							),
							array(
								'key'   => 'field_cinfo_hero_rate_item_rate',
								'label' => 'Rate',
								'name'  => 'item_rate',
								'type'  => 'number',
								'min'   => 0,
								'max'   => 10,
								'step'  => 0.1,
							),
						),
					),
					array(
						'key'           => 'field_cinfo_hero_image',
						'label'         => 'Hero image',
						'name'          => 'image',
						'type'          => 'image',
						'return_format' => 'array',
						'preview_size'  => 'medium',
						'instructions'  => 'Leave empty to use the post featured image.',
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'block',
							'operator' => '==',
							'value'    => 'acf/' . Block::name(),
						),
					),
				),
			)
		);
	}
}
