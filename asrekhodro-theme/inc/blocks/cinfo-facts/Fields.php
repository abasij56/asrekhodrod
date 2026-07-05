<?php

namespace AsreKhodro\Theme\AcfBlocks\CinfoFacts;

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
				'key'      => 'group_cinfo_facts',
				'title'    => 'Car info — key facts',
				'fields'   => array(
					array(
						'key'          => 'field_cinfo_facts_items',
						'label'        => 'Fact items',
						'name'         => 'fact_items',
						'type'         => 'repeater',
						'layout'       => 'table',
						'button_label' => 'Add fact',
						'sub_fields'   => array(
							array(
								'key'   => 'field_cinfo_facts_item_label',
								'label' => 'Title',
								'name'  => 'item_label',
								'type'  => 'text',
							),
							array(
								'key'   => 'field_cinfo_facts_item_value',
								'label' => 'Value',
								'name'  => 'item_value',
								'type'  => 'text',
							),
						),
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
