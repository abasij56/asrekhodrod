<?php

namespace AsreKhodro\Theme\AcfBlocks\CarsinfoDirectory;

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
				'key'      => 'group_carsinfo_directory',
				'title'    => 'دانشنامه خودرو — آرشیو',
				'fields'   => array(
					array(
						'key'           => 'field_carsinfo_directory_eyebrow',
						'label'         => 'Eyebrow',
						'name'          => 'eyebrow',
						'type'          => 'text',
						'default_value' => 'Car Encyclopedia',
					),
					array(
						'key'           => 'field_carsinfo_directory_title',
						'label'         => 'Title',
						'name'          => 'title',
						'type'          => 'text',
						'default_value' => 'دانشنامه خودرو',
					),
					array(
						'key'           => 'field_carsinfo_directory_lead',
						'label'         => 'Lead',
						'name'          => 'lead',
						'type'          => 'textarea',
						'rows'          => 3,
						'default_value' => 'ابتدا برند را انتخاب کنید، سپس مدل مورد نظر را بیابید و وارد صفحه اختصاصی آن شوید.',
					),
					array(
						'key'           => 'field_carsinfo_directory_search_placeholder',
						'label'         => 'Search placeholder',
						'name'          => 'search_placeholder',
						'type'          => 'text',
						'default_value' => 'جستجوی برند یا مدل...',
					),
					array(
						'key'           => 'field_carsinfo_directory_show_steps',
						'label'         => 'Show steps',
						'name'          => 'show_steps',
						'type'          => 'true_false',
						'ui'            => 1,
						'default_value' => 1,
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
