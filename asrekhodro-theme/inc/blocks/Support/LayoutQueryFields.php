<?php

namespace AsreKhodro\Theme\AcfBlocks\Support;

use AsreKhodro\Theme\LayoutSchema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ACF fields mirroring layout-builder query settings for ak-* layout blocks.
 */
final class LayoutQueryFields {

	/**
	 * @param array<string, mixed> $defaults
	 */
	public static function register( string $group_key, string $block_name, string $field_prefix, array $defaults = array() ): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		$default_post_type = (string) ( $defaults['post_type'] ?? 'post' );
		$default_count     = (int) ( $defaults['count'] ?? 10 );
		$default_strategy  = (string) ( $defaults['strategy'] ?? 'latest' );

		acf_add_local_field_group(
			array(
				'key'      => $group_key,
				'title'    => LayoutSchema::block_meta( $block_name )['label'] ?? $block_name,
				'fields'   => self::field_definitions( $field_prefix, $default_post_type, $default_count, $default_strategy ),
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

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function field_definitions( string $field_prefix, string $default_post_type, int $default_count, string $default_strategy ): array {
		$post_type_choices = LayoutSchema::post_type_choices();
		$strategy_choices  = LayoutSchema::STRATEGY_LABELS;
		$strategy_field    = $field_prefix . 'data_strategy';

		$fields = array(
			array(
				'key'           => $field_prefix . 'data_post_type',
				'label'         => __( 'نوع پست', 'asrekhodro' ),
				'name'          => 'data_post_type',
				'type'          => 'select',
				'choices'       => $post_type_choices,
				'default_value' => $default_post_type,
			),
			array(
				'key'           => $field_prefix . 'data_count',
				'label'         => __( 'تعداد', 'asrekhodro' ),
				'name'          => 'data_count',
				'type'          => 'number',
				'min'           => 1,
				'max'           => 40,
				'default_value' => $default_count,
			),
			array(
				'key'           => $field_prefix . 'data_strategy',
				'label'         => __( 'نحوه گزینش', 'asrekhodro' ),
				'name'          => 'data_strategy',
				'type'          => 'select',
				'choices'       => $strategy_choices,
				'default_value' => $default_strategy,
			),
			array(
				'key'               => $field_prefix . 'data_category',
				'label'             => __( 'دسته‌بندی', 'asrekhodro' ),
				'name'              => 'data_category',
				'type'              => 'taxonomy',
				'taxonomy'          => 'category',
				'field_type'        => 'select',
				'allow_null'        => 1,
				'add_term'          => 0,
				'save_terms'        => 0,
				'load_terms'        => 0,
				'return_format'     => 'id',
				'multiple'          => 0,
				'conditional_logic' => array(
					array(
						array(
							'field'    => $strategy_field,
							'operator' => '!=',
							'value'    => 'manual',
						),
					),
				),
			),
			array(
				'key'               => $field_prefix . 'data_manual_posts',
				'label'             => __( 'آیتم‌های انتخابی', 'asrekhodro' ),
				'name'              => 'data_manual_posts',
				'type'              => 'relationship',
				'post_type'         => array_keys( $post_type_choices ),
				'filters'           => array( 'search', 'post_type' ),
				'elements'          => array( 'featured_image' ),
				'return_format'     => 'id',
				'min'               => 0,
				'max'               => 40,
				'conditional_logic' => array(
					array(
						array(
							'field'    => $strategy_field,
							'operator' => '==',
							'value'    => 'manual',
						),
					),
				),
			),
			array(
				'key'   => $field_prefix . 'visibility_tab',
				'label' => __( 'نمایش در', 'asrekhodro' ),
				'name'  => '',
				'type'  => 'tab',
			),
			array(
				'key'           => $field_prefix . 'data_visible_mobile',
				'label'         => __( 'موبایل', 'asrekhodro' ),
				'name'          => 'data_visible_mobile',
				'type'          => 'true_false',
				'default_value' => 1,
				'ui'            => 1,
			),
			array(
				'key'           => $field_prefix . 'data_visible_tablet',
				'label'         => __( 'تبلت', 'asrekhodro' ),
				'name'          => 'data_visible_tablet',
				'type'          => 'true_false',
				'default_value' => 1,
				'ui'            => 1,
			),
			array(
				'key'           => $field_prefix . 'data_visible_desktop',
				'label'         => __( 'دسکتاپ', 'asrekhodro' ),
				'name'          => 'data_visible_desktop',
				'type'          => 'true_false',
				'default_value' => 1,
				'ui'            => 1,
			),
		);

		return $fields;
	}

	/**
	 * @param array<string, mixed> $fields
	 * @return array<string, mixed>
	 */
	public static function placement_from_fields( array $fields ): array {
		$placement = array();

		if ( ! empty( $fields['data_post_type'] ) ) {
			$placement['data_post_type'] = sanitize_key( (string) $fields['data_post_type'] );
			$placement['post_type']      = $placement['data_post_type'];
		}

		if ( isset( $fields['data_count'] ) && $fields['data_count'] !== '' ) {
			$placement['data_count'] = max( 1, min( 40, (int) $fields['data_count'] ) );
			$placement['count']       = (int) $placement['data_count'];
		}

		if ( ! empty( $fields['data_strategy'] ) ) {
			$strategy = (string) $fields['data_strategy'];
			if ( isset( LayoutSchema::STRATEGY_LABELS[ $strategy ] ) ) {
				$placement['data_strategy'] = $strategy;
				$placement['strategy']      = $strategy;
			}
		}

		if ( ! empty( $fields['data_category'] ) ) {
			$placement['data_category'] = (int) $fields['data_category'];
			$placement['category']      = (int) $fields['data_category'];
		}

		if ( ! empty( $fields['data_manual_posts'] ) && is_array( $fields['data_manual_posts'] ) ) {
			$ids = array_values( array_filter( array_map( 'intval', $fields['data_manual_posts'] ) ) );
			if ( $ids !== array() ) {
				$placement['data_manual_posts'] = $ids;
				$placement['manual_posts']      = $ids;
			}
		}

		foreach ( array( 'mobile', 'tablet', 'desktop' ) as $device ) {
			$key = 'data_visible_' . $device;
			if ( array_key_exists( $key, $fields ) ) {
				$placement[ $key ] = ! empty( $fields[ $key ] ) ? 1 : 0;
			}
		}

		return $placement;
	}
}
