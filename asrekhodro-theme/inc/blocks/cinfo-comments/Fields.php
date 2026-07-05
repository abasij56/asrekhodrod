<?php

namespace AsreKhodro\Theme\AcfBlocks\CinfoComments;

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
				'key'      => 'group_cinfo_comments',
				'title'    => 'Car info — comments',
				'fields'   => array(
					array(
						'key'          => 'field_cinfo_comments_title',
						'label'        => 'Section title',
						'name'         => 'title',
						'type'         => 'text',
						'instructions' => 'Leave empty to hide the section heading.',
						'placeholder'  => 'نظرات کاربران',
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
