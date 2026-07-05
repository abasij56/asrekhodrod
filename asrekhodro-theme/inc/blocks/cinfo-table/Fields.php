<?php

namespace AsreKhodro\Theme\AcfBlocks\CinfoTable;

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
				'key'      => 'group_cinfo_table',
				'title'    => 'Car info — table',
				'fields'   => array(
					array(
						'key'          => 'field_cinfo_table_title',
						'label'        => 'Section title',
						'name'         => 'title',
						'type'         => 'text',
						'instructions' => 'Leave empty to hide the section heading.',
						'placeholder'  => 'مشخصات فنی',
					),
					array(
						'key'   => 'field_cinfo_table_col_1_label',
						'label' => 'Column 1 header',
						'name'  => 'col_1_label',
						'type'  => 'text',
						'default_value' => 'گروه',
					),
					array(
						'key'   => 'field_cinfo_table_col_2_label',
						'label' => 'Column 2 header',
						'name'  => 'col_2_label',
						'type'  => 'text',
						'default_value' => 'مشخصه',
					),
					array(
						'key'   => 'field_cinfo_table_col_3_label',
						'label' => 'Column 3 header',
						'name'  => 'col_3_label',
						'type'  => 'text',
						'default_value' => 'مقدار',
					),
					array(
						'key'          => 'field_cinfo_table_groups',
						'label'        => 'Groups',
						'name'         => 'groups',
						'type'         => 'repeater',
						'layout'       => 'block',
						'button_label' => 'Add group',
						'sub_fields'   => array(
							array(
								'key'   => 'field_cinfo_table_group_name',
								'label' => 'Group name',
								'name'  => 'group_name',
								'type'  => 'text',
							),
							array(
								'key'          => 'field_cinfo_table_group_items',
								'label'        => 'Rows',
								'name'         => 'items',
								'type'         => 'repeater',
								'layout'       => 'table',
								'button_label' => 'Add row',
								'sub_fields'   => array(
									array(
										'key'   => 'field_cinfo_table_item_title',
										'label' => 'Title',
										'name'  => 'item_title',
										'type'  => 'text',
									),
									array(
										'key'   => 'field_cinfo_table_item_value',
										'label' => 'Value',
										'name'  => 'item_value',
										'type'  => 'text',
									),
									array(
										'key'          => 'field_cinfo_table_item_note',
										'label'        => 'Note',
										'name'         => 'item_note',
										'type'         => 'text',
										'instructions' => 'Optional third column (e.g. توضیح). Used when the group has no name.',
									),
								),
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
