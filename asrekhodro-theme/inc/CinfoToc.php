<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Table of contents settings for carsinfo single pages.
 */
final class CinfoToc {

	public static function init(): void {
		add_action( 'acf/include_fields', array( self::class, 'register_fields' ) );
	}

	public static function register_fields(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			array(
				'key'      => 'group_carsinfo_toc',
				'title'    => 'Car info — table of contents',
				'fields'   => array(
					array(
						'key'           => 'field_carsinfo_show_toc',
						'label'         => 'نمایش فهرست',
						'name'          => 'show_toc',
						'type'          => 'true_false',
						'ui'            => 1,
						'default_value' => 0,
						'instructions'  => 'نمایش فهرست مطالب شناور در سمت راست (دسکتاپ) و منوی کشویی (موبایل).',
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'carsinfo',
						),
					),
				),
				'position' => 'side',
			)
		);
	}

	public static function show_for_post( int $post_id ): bool {
		if ( ! function_exists( 'get_field' ) ) {
			return false;
		}

		return (bool) get_field( 'show_toc', $post_id );
	}
}
