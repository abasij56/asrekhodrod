<?php

namespace AsreKhodro\Theme\AcfBlocks\CinfoOverview;

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
				'key'      => 'group_cinfo_overview',
				'title'    => 'Car info — overview',
				'fields'   => array(
					array(
						'key'          => 'field_cinfo_overview_title',
						'label'        => 'Title',
						'name'         => 'title',
						'type'         => 'text',
						'instructions' => 'Leave empty to hide the section heading.',
						'placeholder'  => 'اطلاعات کلی',
					),
					array(
						'key'           => 'field_cinfo_overview_content',
						'label'         => 'Content',
						'name'          => 'content',
						'type'          => 'wysiwyg',
						'tabs'          => 'all',
						'toolbar'       => 'full',
						'media_upload'  => 1,
						'delay'         => 0,
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
