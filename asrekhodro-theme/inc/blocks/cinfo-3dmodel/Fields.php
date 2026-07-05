<?php

namespace AsreKhodro\Theme\AcfBlocks\Cinfo3dmodel;

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
				'key'      => 'group_cinfo_3dmodel',
				'title'    => 'Car info — 3D model',
				'fields'   => array(
					array(
						'key'          => 'field_cinfo_3dmodel_url',
						'label'        => 'آدرس مدل (GLB)',
						'name'         => 'model_url',
						'type'         => 'text',
						'instructions' => 'آدرس کامل GLB یا فقط نام فایل داخل پوشه models تم (مثلاً ferrari.glb). اگر خالی باشد و تصویر وارد شده باشد، تصویر ثابت در مرکز نمایش داده می‌شود.',
						'placeholder'  => 'ferrari.glb',
					),
					array(
						'key'          => 'field_cinfo_3dmodel_image',
						'label'        => 'تصویر جایگزین',
						'name'         => 'image',
						'type'         => 'image',
						'return_format' => 'array',
						'preview_size' => 'medium',
						'instructions' => 'وقتی مدل GLB وارد نشده باشد، این تصویر بدون چرخش در مرکز صحنه نمایش داده می‌شود.',
					),
					array(
						'key'          => 'field_cinfo_3dmodel_extra_info',
						'label'        => 'اطلاعات اضافه',
						'name'         => 'extra_info',
						'type'         => 'textarea',
						'rows'         => 4,
						'new_lines'    => '',
					),
					array(
						'key'           => 'field_cinfo_3dmodel_default_color',
						'label'         => 'رنگ پیش‌فرض بدنه',
						'name'          => 'default_color',
						'type'          => 'select',
						'choices'       => array(
							'1' => 'رنگ ۱',
							'2' => 'رنگ ۲',
							'3' => 'رنگ ۳',
							'4' => 'رنگ ۴',
							'5' => 'رنگ ۵',
						),
						'default_value' => '1',
						'ui'            => 1,
						'instructions'  => 'رنگی که هنگام بارگذاری صفحه روی مدل ۳D اعمال می‌شود.',
					),
					array(
						'key'           => 'field_cinfo_3dmodel_initial_rotation',
						'label'         => 'زاویه نمایش اولیه مدل',
						'name'          => 'initial_rotation',
						'type'          => 'number',
						'min'           => 0,
						'max'           => 360,
						'step'          => 1,
						'default_value' => 32,
						'append'        => '°',
						'instructions'  => 'چرخش اولیه مدل ۳D حول محور عمودی (۰ تا ۳۶۰ درجه).',
					),
					array(
						'key'           => 'field_cinfo_3dmodel_color_1',
						'label'         => 'رنگ ۱',
						'name'          => 'color_1',
						'type'          => 'color_picker',
						'default_value' => '#c41e2a',
					),
					array(
						'key'   => 'field_cinfo_3dmodel_color_1_label',
						'label' => 'برچسب رنگ ۱',
						'name'  => 'color_1_label',
						'type'  => 'text',
						'placeholder' => 'قرمز متالیک',
					),
					array(
						'key'           => 'field_cinfo_3dmodel_color_2',
						'label'         => 'رنگ ۲',
						'name'          => 'color_2',
						'type'          => 'color_picker',
						'default_value' => '#1a3568',
					),
					array(
						'key'   => 'field_cinfo_3dmodel_color_2_label',
						'label' => 'برچسب رنگ ۲',
						'name'  => 'color_2_label',
						'type'  => 'text',
						'placeholder' => 'آبی نیمه‌شب',
					),
					array(
						'key'           => 'field_cinfo_3dmodel_color_3',
						'label'         => 'رنگ ۳',
						'name'          => 'color_3',
						'type'          => 'color_picker',
						'default_value' => '#9aa3ad',
					),
					array(
						'key'   => 'field_cinfo_3dmodel_color_3_label',
						'label' => 'برچسب رنگ ۳',
						'name'  => 'color_3_label',
						'type'  => 'text',
						'placeholder' => 'نقره‌ای متالیک',
					),
					array(
						'key'           => 'field_cinfo_3dmodel_color_4',
						'label'         => 'رنگ ۴',
						'name'          => 'color_4',
						'type'          => 'color_picker',
						'default_value' => '#0d5c48',
					),
					array(
						'key'   => 'field_cinfo_3dmodel_color_4_label',
						'label' => 'برچسب رنگ ۴',
						'name'  => 'color_4_label',
						'type'  => 'text',
						'placeholder' => 'سبز زمردی',
					),
					array(
						'key'           => 'field_cinfo_3dmodel_color_5',
						'label'         => 'رنگ ۵',
						'name'          => 'color_5',
						'type'          => 'color_picker',
						'default_value' => '#c4a35a',
					),
					array(
						'key'   => 'field_cinfo_3dmodel_color_5_label',
						'label' => 'برچسب رنگ ۵',
						'name'  => 'color_5_label',
						'type'  => 'text',
						'placeholder' => 'شامپاین طلایی',
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
