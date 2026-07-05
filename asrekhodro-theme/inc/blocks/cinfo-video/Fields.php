<?php

namespace AsreKhodro\Theme\AcfBlocks\CinfoVideo;

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
				'key'      => 'group_cinfo_video',
				'title'    => 'Car info — video',
				'fields'   => array(
					array(
						'key'          => 'field_cinfo_video_title',
						'label'        => 'Title',
						'name'         => 'title',
						'type'         => 'text',
						'instructions' => 'Leave empty to hide the section heading.',
						'placeholder'  => 'ویدیو بررسی',
					),
					array(
						'key'          => 'field_cinfo_video_embed_code',
						'label'        => 'Video embed code',
						'name'         => 'embed_code',
						'type'         => 'textarea',
						'rows'         => 6,
						'instructions' => 'Paste iframe embed code or a script tag (e.g. YouTube, Aparat).',
						'new_lines'    => '',
					),
					array(
						'key'           => 'field_cinfo_video_selected_video',
						'label'         => 'انتخاب از ویدیوها',
						'name'          => 'selected_video',
						'type'          => 'post_object',
						'post_type'     => array( 'ak_video' ),
						'return_format' => 'id',
						'allow_null'    => 1,
						'ui'            => 1,
						'instructions'  => 'اگر کد embed خالی باشد، ویدیوی انتخاب‌شده استفاده می‌شود.',
					),
					array(
						'key'           => 'field_cinfo_video_selected_review',
						'label'         => 'انتخاب از بررسی‌ها',
						'name'          => 'selected_review',
						'type'          => 'post_object',
						'post_type'     => array( 'ak_review' ),
						'return_format' => 'id',
						'allow_null'    => 1,
						'ui'            => 1,
						'instructions'  => 'اگر کد embed و ویدیو خالی باشند، اولین ویدیوی متن این بررسی استفاده می‌شود.',
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
