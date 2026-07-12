<?php

namespace AsreKhodro\Theme\AcfBlocks\CinfoFacts;

use AsreKhodro\Theme\CarSpecIcons;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Fields {

	private const MAX_CARD_ITEMS = 3;

	private static bool $hooks_registered = false;

	public static function register(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			array(
				'key'      => 'group_cinfo_facts',
				'title'    => 'Car info — key facts',
				'fields'   => array(
					array(
						'key'          => 'field_cinfo_facts_items',
						'label'        => 'Fact items',
						'name'         => 'fact_items',
						'type'         => 'repeater',
						'layout'       => 'block',
						'button_label' => 'Add fact',
						'instructions' => 'برای نمایش در کارت‌های لیست، حداکثر ' . self::MAX_CARD_ITEMS . ' مورد را علامت بزنید.',
						'sub_fields'   => array(
							array(
								'key'   => 'field_cinfo_facts_item_label',
								'label' => 'Title',
								'name'  => 'item_label',
								'type'  => 'text',
							),
							array(
								'key'   => 'field_cinfo_facts_item_value',
								'label' => 'Value',
								'name'  => 'item_value',
								'type'  => 'text',
							),
							array(
								'key'           => 'field_cinfo_facts_item_icon',
								'label'         => 'آیکون',
								'name'          => 'item_icon',
								'type'          => 'select',
								'choices'       => array(),
								'allow_null'    => 1,
								'ui'            => 0,
								'return_format' => 'value',
							),
							array(
								'key'          => 'field_cinfo_facts_item_icon_svg',
								'label'        => 'آیکون سفارشی (SVG)',
								'name'         => 'item_icon_svg',
								'type'         => 'textarea',
								'instructions' => 'اگر آیکون مناسب در لیست بالا نبود، کد SVG را اینجا بچسبان. این مورد بر آیکون انتخابی اولویت دارد.',
								'rows'         => 4,
								'placeholder'  => '<svg viewBox="0 0 24 24" ...>...</svg>',
							),
							array(
								'key'           => 'field_cinfo_facts_item_show_in_card',
								'label'         => 'نمایش در کارت',
								'name'          => 'show_in_card',
								'type'          => 'true_false',
								'ui'            => 1,
								'default_value' => 0,
							),
						),
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

		add_filter( 'acf/load_field/key=field_cinfo_facts_item_icon', array( CarSpecIcons::class, 'load_acf_select_field' ) );
		add_filter( 'acf/validate_value/key=field_cinfo_facts_items', array( self::class, 'validate_fact_items' ), 10, 2 );
	}

	/**
	 * @param mixed $valid
	 * @param mixed $value
	 * @return mixed
	 */
	public static function validate_fact_items( $valid, $value ) {
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
