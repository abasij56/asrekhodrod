<?php

namespace AsreKhodro\Theme\AcfBlocks\CinfoHero;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Fields {

	private const MAX_CARD_ITEMS = 4;

	private static bool $hooks_registered = false;

	public static function register(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			array(
				'key'      => 'group_cinfo_hero',
				'title'    => 'Car info — hero',
				'fields'   => array(
					array(
						'key'           => 'field_cinfo_hero_badge',
						'label'         => 'Badge',
						'name'          => 'badge',
						'type'          => 'text',
						'default_value' => 'بررسی مدل',
						'placeholder'   => 'بررسی مدل',
					),
					array(
						'key'          => 'field_cinfo_hero_title',
						'label'        => 'Title',
						'name'         => 'title',
						'type'         => 'text',
						'instructions' => 'Leave empty to use the post title.',
					),
					array(
						'key'   => 'field_cinfo_hero_subtitle',
						'label' => 'Subtitle',
						'name'  => 'subtitle',
						'type'  => 'text',
					),
					array(
						'key'           => 'field_cinfo_hero_overall_rate',
						'label'         => 'Overall rate',
						'name'          => 'overall_rate',
						'type'          => 'number',
						'instructions'  => 'Score out of 10 (e.g. 4.6).',
						'min'           => 0,
						'max'           => 10,
						'step'          => 0.1,
						'default_value' => 0,
					),
					array(
						'key'          => 'field_cinfo_hero_rate_items',
						'label'        => 'Rate items',
						'name'         => 'rate_items',
						'type'         => 'repeater',
						'layout'       => 'table',
						'button_label' => 'Add rate',
						'instructions' => 'برای نمایش در کارت‌های لیست، حداکثر ' . self::MAX_CARD_ITEMS . ' مورد را علامت بزنید.',
						'sub_fields'   => array(
							array(
								'key'   => 'field_cinfo_hero_rate_item_title',
								'label' => 'Title',
								'name'  => 'item_title',
								'type'  => 'text',
							),
							array(
								'key'   => 'field_cinfo_hero_rate_item_rate',
								'label' => 'Rate',
								'name'  => 'item_rate',
								'type'  => 'number',
								'min'   => 0,
								'max'   => 10,
								'step'  => 0.1,
							),
							array(
								'key'           => 'field_cinfo_hero_rate_item_show_in_card',
								'label'         => 'نمایش در کارت',
								'name'          => 'show_in_card',
								'type'          => 'true_false',
								'ui'            => 1,
								'default_value' => 0,
							),
						),
					),
					array(
						'key'           => 'field_cinfo_hero_image',
						'label'         => 'Hero image',
						'name'          => 'image',
						'type'          => 'image',
						'return_format' => 'array',
						'preview_size'  => 'medium',
						'instructions'  => 'Leave empty to use the post featured image.',
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

		self::register_hooks();
	}

	private static function register_hooks(): void {
		if ( self::$hooks_registered ) {
			return;
		}

		self::$hooks_registered = true;

		add_filter( 'acf/validate_value/key=field_cinfo_hero_rate_items', array( self::class, 'validate_rate_items' ), 10, 2 );
	}

	/**
	 * @param mixed $valid
	 * @param mixed $value
	 * @return mixed
	 */
	public static function validate_rate_items( $valid, $value ) {
		if ( $valid !== true ) {
			return $valid;
		}

		$count = self::count_card_items( $value );
		if ( $count > self::MAX_CARD_ITEMS ) {
			return sprintf(
				/* translators: %d: maximum selectable items */
				__( 'حداکثر %d مورد برای نمایش در کارت قابل انتخاب است.', 'asrekhodro' ),
				self::MAX_CARD_ITEMS
			);
		}

		return $valid;
	}

	/**
	 * @param mixed $value
	 */
	private static function count_card_items( $value ): int {
		$count = 0;

		foreach ( (array) $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( ! empty( $row['show_in_card'] ) ) {
				++$count;
			}
		}

		return $count;
	}
}
