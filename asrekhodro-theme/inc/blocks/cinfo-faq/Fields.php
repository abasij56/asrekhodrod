<?php

namespace AsreKhodro\Theme\AcfBlocks\CinfoFaq;

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
				'key'      => 'group_cinfo_faq',
				'title'    => 'Car info — FAQ',
				'fields'   => array(
					array(
						'key'          => 'field_cinfo_faq_title',
						'label'        => 'Section title',
						'name'         => 'title',
						'type'         => 'text',
						'instructions' => 'Leave empty to hide the section heading.',
						'placeholder'  => 'سوالات متداول',
					),
					array(
						'key'           => 'field_cinfo_faq_open_first',
						'label'         => 'Open first item by default',
						'name'          => 'open_first',
						'type'          => 'true_false',
						'default_value' => 1,
						'ui'            => 1,
					),
					array(
						'key'          => 'field_cinfo_faq_items',
						'label'        => 'FAQ items',
						'name'         => 'faq_items',
						'type'         => 'repeater',
						'layout'       => 'block',
						'button_label' => 'Add question',
						'sub_fields'   => array(
							array(
								'key'   => 'field_cinfo_faq_item_question',
								'label' => 'Question',
								'name'  => 'question',
								'type'  => 'text',
							),
							array(
								'key'          => 'field_cinfo_faq_item_answer',
								'label'        => 'Answer',
								'name'         => 'answer',
								'type'         => 'textarea',
								'rows'         => 4,
								'new_lines'    => 'br',
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
