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
					array(
						'key'       => 'field_carsinfo_directory_tab_featured',
						'label'     => 'منتخب‌ها',
						'name'      => '',
						'type'      => 'tab',
						'placement' => 'top',
					),
					array(
						'key'           => 'field_carsinfo_directory_featured_title',
						'label'         => 'عنوان سکشن منتخب‌ها',
						'name'          => 'featured_title',
						'type'          => 'text',
						'default_value' => 'منتخب‌ها',
						'instructions'  => 'عنوان بخش کارت‌های منتخب در صفحه دانشنامه (زیر نوار جستجو).',
					),
					array(
						'key'           => 'field_carsinfo_directory_featured_cars',
						'label'         => 'ماشین‌های منتخب',
						'name'          => 'featured_cars',
						'type'          => 'relationship',
						'post_type'     => array( 'carsinfo' ),
						'return_format' => 'id',
						'min'           => 0,
						'max'           => 12,
						'filters'       => array( 'search' ),
						'elements'      => array( 'featured_image' ),
						'instructions'  => 'چند مدل carsinfo را جستجو و انتخاب کنید. ترتیب انتخاب در گرید فرانت حفظ می‌شود.',
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
