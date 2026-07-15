<?php

namespace AsreKhodro\Theme\AcfBlocks\AkFeaturedCars;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Fields {

	public static function register( string $block_name ): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		$prefix        = 'field_ak_featured_cars_';
		$mode_field    = $prefix . 'selection_mode';

		acf_add_local_field_group(
			array(
				'key'      => 'group_ak_featured_cars',
				'title'    => 'ماشین‌های منتخب',
				'fields'   => array(
					array(
						'key'           => $prefix . 'title',
						'label'         => 'عنوان بخش',
						'name'          => 'section_title',
						'type'          => 'text',
						'default_value' => 'ماشین‌های منتخب',
						'instructions'  => 'برای مخفی کردن عنوان، خالی بگذارید.',
					),
					array(
						'key'           => $prefix . 'link_url',
						'label'         => 'لینک آرشیو دانشنامه',
						'name'          => 'view_url',
						'type'          => 'url',
						'instructions'  => 'خالی = /دانشنامه-خودرو/ — در غیر این صورت همان آدرس واردشده استفاده می‌شود.',
					),
					array(
						'key'           => $prefix . 'link_label',
						'label'         => 'متن لینک',
						'name'          => 'view_label',
						'type'          => 'text',
						'default_value' => 'آرشیو دانشنامه ←',
						'instructions'  => 'خالی = «آرشیو دانشنامه ←»',
					),
					array(
						'key'           => $mode_field,
						'label'         => 'نحوه انتخاب خودروها',
						'name'          => 'selection_mode',
						'type'          => 'select',
						'choices'       => array(
							'latest' => 'جدیدترین‌ها (خودکار)',
							'manual' => 'انتخاب دستی',
						),
						'default_value' => 'latest',
						'return_format' => 'value',
					),
					array(
						'key'               => $prefix . 'count',
						'label'             => 'تعداد',
						'name'              => 'count',
						'type'              => 'number',
						'min'               => 1,
						'max'               => 24,
						'default_value'     => 8,
						'conditional_logic' => array(
							array(
								array(
									'field'    => $mode_field,
									'operator' => '==',
									'value'    => 'latest',
								),
							),
						),
					),
					array(
						'key'               => $prefix . 'category',
						'label'             => 'محدود به دسته‌بندی',
						'name'              => 'category',
						'type'              => 'taxonomy',
						'taxonomy'          => 'category',
						'field_type'        => 'select',
						'allow_null'        => 1,
						'add_term'          => 0,
						'save_terms'        => 0,
						'load_terms'        => 0,
						'return_format'     => 'id',
						'multiple'          => 0,
						'instructions'      => 'اختیاری — فقط خودروهای این دسته (مثلاً یک برند) نمایش داده می‌شوند.',
						'conditional_logic' => array(
							array(
								array(
									'field'    => $mode_field,
									'operator' => '==',
									'value'    => 'latest',
								),
							),
						),
					),
					array(
						'key'               => $prefix . 'cars',
						'label'             => 'خودروهای منتخب',
						'name'              => 'featured_cars',
						'type'              => 'relationship',
						'post_type'         => array( 'carsinfo' ),
						'return_format'     => 'id',
						'min'               => 0,
						'max'               => 24,
						'filters'           => array( 'search' ),
						'elements'          => array( 'featured_image' ),
						'instructions'      => 'خودروها را جستجو و انتخاب کنید. ترتیب انتخاب در گرید حفظ می‌شود.',
						'conditional_logic' => array(
							array(
								array(
									'field'    => $mode_field,
									'operator' => '==',
									'value'    => 'manual',
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
							'value'    => 'acf/' . $block_name,
						),
					),
				),
			)
		);
	}
}
