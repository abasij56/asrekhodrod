<?php

namespace AsreKhodro\Theme\AcfBlocks\CinfoRelated;

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
				'key'      => 'group_cinfo_related',
				'title'    => 'Car info — related models',
				'fields'   => array(
					array(
						'key'          => 'field_cinfo_related_title',
						'label'        => 'Section title',
						'name'         => 'title',
						'type'         => 'text',
						'instructions' => 'Leave empty to hide the section heading.',
						'placeholder'  => 'مدل‌های دیگر آئودی',
					),
					array(
						'key'          => 'field_cinfo_related_cards',
						'label'        => 'Related cards',
						'name'         => 'related_cards',
						'type'         => 'repeater',
						'layout'       => 'block',
						'button_label' => 'Add card',
						'sub_fields'   => array(
							array(
								'key'           => 'field_cinfo_related_card_image',
								'label'         => 'Image',
								'name'          => 'image',
								'type'          => 'image',
								'return_format' => 'array',
								'preview_size'  => 'medium',
								'library'       => 'all',
							),
							array(
								'key'   => 'field_cinfo_related_card_title',
								'label' => 'Title',
								'name'  => 'title',
								'type'  => 'text',
							),
							array(
								'key'   => 'field_cinfo_related_card_subtitle',
								'label' => 'Subtitle',
								'name'  => 'subtitle',
								'type'  => 'text',
							),
							array(
								'key'          => 'field_cinfo_related_card_link',
								'label'        => 'Link',
								'name'         => 'link',
								'type'         => 'url',
								'instructions' => 'Optional URL for this card.',
							),
						),
					),
					array(
						'key'         => 'field_cinfo_related_archive_link_title',
						'label'       => 'Archive link title',
						'name'        => 'archive_link_title',
						'type'        => 'text',
						'placeholder' => 'همه مدل‌های آئودی ←',
					),
					array(
						'key'          => 'field_cinfo_related_archive_link_href',
						'label'        => 'Archive link URL',
						'name'         => 'archive_link_href',
						'type'         => 'url',
						'instructions' => 'Link to the brand or models archive page.',
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
