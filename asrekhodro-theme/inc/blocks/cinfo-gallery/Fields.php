<?php

namespace AsreKhodro\Theme\AcfBlocks\CinfoGallery;

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
				'key'      => 'group_cinfo_gallery',
				'title'    => 'Car info — gallery',
				'fields'   => array(
					array(
						'key'          => 'field_cinfo_gallery_title',
						'label'        => 'Section title',
						'name'         => 'title',
						'type'         => 'text',
						'instructions' => 'Leave empty to hide the section heading.',
						'placeholder'  => 'گالری تصاویر',
					),
					array(
						'key'          => 'field_cinfo_gallery_groups',
						'label'        => 'Gallery groups',
						'name'         => 'gallery_groups',
						'type'         => 'repeater',
						'layout'       => 'block',
						'button_label' => 'Add gallery group',
						'sub_fields'   => array(
							array(
								'key'          => 'field_cinfo_gallery_group_title',
								'label'        => 'Group title',
								'name'         => 'group_title',
								'type'         => 'text',
								'instructions' => 'Optional label above this group of images.',
							),
							array(
								'key'           => 'field_cinfo_gallery_group_images',
								'label'         => 'Photos',
								'name'          => 'images',
								'type'          => 'gallery',
								'return_format' => 'array',
								'preview_size'  => 'medium',
								'insert'        => 'append',
								'library'       => 'all',
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
