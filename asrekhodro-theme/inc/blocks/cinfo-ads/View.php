<?php

namespace AsreKhodro\Theme\AcfBlocks\CinfoAds;

use AsreKhodro\Theme\CarInfo3d;
use AsreKhodro\Theme\ImporterBridge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class View {

	/**
	 * @param array<string, mixed> $fields
	 * @return array<string, mixed>
	 */
	public static function context( array $fields, ?\Timber\Post $post = null ): array {
		unset( $post );

		$items = self::resolve_items( $fields );
		$interval = self::resolve_interval( $fields );

		$payload = array(
			'items'    => $items,
			'interval' => $interval,
		);

		return array(
			'ads_items'             => $items,
			'ads_has_content'       => $items !== array(),
			'ads_json'              => wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			'ads_rotation_interval' => $interval,
		);
	}

	/**
	 * @param array<string, mixed> $fields
	 * @return list<array{title: string, image: string, link: string}>
	 */
	private static function resolve_items( array $fields ): array {
		$selected = $fields['selected_ads'] ?? $fields['field_cinfo_ads_selected'] ?? array();
		$items    = array();

		foreach ( self::normalize_ad_ids( $selected ) as $ad_id ) {
			if ( function_exists( 'get_field' ) ) {
				$active = get_field( 'ad_active', $ad_id );
				if ( $active === 0 || $active === false ) {
					continue;
				}
			}

			$image_url = self::resolve_image_url( $ad_id );
			if ( $image_url === '' ) {
				continue;
			}

			$item = ImporterBridge::format_ad( $ad_id );

			$items[] = array(
				'title' => (string) $item['title'],
				'image' => CarInfo3d::texture_image_url( $image_url ),
				'link'  => (string) ( $item['link'] ?: '#' ),
			);
		}

		return $items;
	}

	/**
	 * @param mixed $selected
	 * @return list<int>
	 */
	private static function normalize_ad_ids( $selected ): array {
		if ( $selected === null || $selected === '' || $selected === false ) {
			return array();
		}

		if ( ! is_array( $selected ) ) {
			$selected = array( $selected );
		}

		$ids = array();

		foreach ( $selected as $entry ) {
			$id = self::resolve_post_id( $entry );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * @param mixed $value
	 */
	private static function resolve_post_id( $value ): int {
		if ( is_numeric( $value ) ) {
			return max( 0, (int) $value );
		}

		if ( is_object( $value ) && isset( $value->ID ) ) {
			return max( 0, (int) $value->ID );
		}

		if ( is_array( $value ) ) {
			if ( isset( $value['ID'] ) ) {
				return max( 0, (int) $value['ID'] );
			}

			if ( isset( $value[0] ) ) {
				return self::resolve_post_id( $value[0] );
			}
		}

		return 0;
	}

	private static function resolve_image_url( int $ad_id ): string {
		$item = ImporterBridge::format_ad( $ad_id );
		if ( $item['image'] !== '' ) {
			return (string) $item['image'];
		}

		$thumb = get_the_post_thumbnail_url( $ad_id, 'large' );
		if ( is_string( $thumb ) && $thumb !== '' ) {
			return $thumb;
		}

		if ( function_exists( 'get_field' ) ) {
			$image = get_field( 'ad_image', $ad_id );
			if ( is_numeric( $image ) ) {
				$url = wp_get_attachment_image_url( (int) $image, 'large' );

				return is_string( $url ) ? $url : '';
			}
		}

		return '';
	}

	/**
	 * @param array<string, mixed> $fields
	 */
	private static function resolve_interval( array $fields ): int {
		$value = $fields['rotation_interval'] ?? $fields['field_cinfo_ads_rotation_interval'] ?? 5;
		$interval = (int) round( (float) $value );

		return max( 3, min( 30, $interval ) );
	}
}
