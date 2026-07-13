<?php

namespace AsreKhodro\Theme\AcfBlocks\AkUnderTitle;

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
				'key'      => 'group_ak_under_title',
				'title'    => 'تیتر دوم',
				'fields'   => array(
					array(
						'key'          => 'field_ak_under_title_text',
						'label'        => 'متن تیتر دوم',
						'name'         => 'text',
						'type'         => 'text',
						'instructions' => 'زیرعنوان خبر که زیر تیتر اصلی نمایش داده می‌شود.',
						'placeholder'  => 'مثال: جزئیات رونمایی در نمایشگاه…',
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
