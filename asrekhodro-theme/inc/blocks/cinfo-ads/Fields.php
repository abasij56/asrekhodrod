<?php

namespace AsreKhodro\Theme\AcfBlocks\CinfoAds;

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
				'key'      => 'group_cinfo_ads',
				'title'    => 'Car info — 3D wall ads',
				'fields'   => array(
					array(
						'key'           => 'field_cinfo_ads_selected',
						'label'         => 'تبلیغات دیوار پس‌زمینه',
						'name'          => 'selected_ads',
						'type'          => 'relationship',
						'post_type'     => array( 'ad_slot' ),
						'return_format' => 'id',
						'min'           => 0,
						'max'           => 12,
						'filters'       => array( 'search' ),
						'elements'      => array( 'featured_image' ),
						'instructions'  => 'تصویر تبلیغ (فیلد تصویر یا تصویر شاخص) روی دیوار پشت صحنهٔ ۳D نمایش داده می‌شود و بین آیتم‌ها به‌صورت چرخشی جابه‌جا می‌گردد.',
					),
					array(
						'key'               => 'field_cinfo_ads_rotation_interval',
						'label'             => 'فاصلهٔ چرخش (ثانیه)',
						'name'              => 'rotation_interval',
						'type'              => 'number',
						'default_value'     => 5,
						'min'               => 3,
						'max'               => 30,
						'step'              => 1,
						'append'            => 'ثانیه',
						'instructions'      => 'زمان نمایش هر تبلیغ قبل از جابه‌جایی به بعدی.',
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
