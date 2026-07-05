<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ArchiveHero {

	public const DEFAULT_ASPECT = '21 / 9';

	public const DEFAULT_IMAGE = 'images/kiosk-hero-bg.jpg';

	/**
	 * @return array{url: string, aspect: string, aspect_fallback: bool}
	 */
	public static function from_acf_option( string $field_name, ?string $default_asset = null ): array {
		$default_asset   = $default_asset ?? self::DEFAULT_IMAGE;
		$fallback_aspect = self::aspect_from_theme_asset( $default_asset );
		$aspect_fallback = false;

		if ( function_exists( 'get_field' ) ) {
			$hero = get_field( $field_name, 'option' );
			if ( is_array( $hero ) && ! empty( $hero['url'] ) ) {
				$aspect = self::aspect_from_acf_image( $hero, '' );

				return array(
					'url'             => esc_url( (string) $hero['url'] ),
					'aspect'          => $aspect !== '' ? $aspect : $fallback_aspect,
					'aspect_fallback' => $aspect === '',
				);
			}
		}

		return array(
			'url'             => esc_url( Appearance::asset_url( $default_asset ) ),
			'aspect'          => $fallback_aspect,
			'aspect_fallback' => $fallback_aspect === self::DEFAULT_ASPECT,
		);
	}

	/**
	 * @param array<string, mixed> $image
	 */
	public static function aspect_from_acf_image( array $image, string $fallback = '' ): string {
		$width  = isset( $image['width'] ) ? (int) $image['width'] : 0;
		$height = isset( $image['height'] ) ? (int) $image['height'] : 0;

		if ( $width > 0 && $height > 0 ) {
			return self::format_aspect( $width, $height );
		}

		$attachment_id = isset( $image['ID'] ) ? (int) $image['ID'] : 0;
		if ( $attachment_id > 0 ) {
			$meta = wp_get_attachment_metadata( $attachment_id );
			if ( is_array( $meta ) && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
				return self::format_aspect( (int) $meta['width'], (int) $meta['height'] );
			}
		}

		return $fallback;
	}

	private static function format_aspect( int $width, int $height ): string {
		if ( $width <= 0 || $height <= 0 ) {
			return self::DEFAULT_ASPECT;
		}

		return $width . ' / ' . $height;
	}

	private static function aspect_from_theme_asset( string $relative ): string {
		$relative = ltrim( $relative, '/' );
		$searched = array();

		foreach ( array_merge( array( Appearance::id(), Appearance::FALLBACK_ID ), Appearance::registered_ids() ) as $search_id ) {
			if ( isset( $searched[ $search_id ] ) ) {
				continue;
			}

			$searched[ $search_id ] = true;
			$path                     = Appearance::dir( $search_id ) . '/assets/' . $relative;

			if ( ! file_exists( $path ) ) {
				continue;
			}

			$size = @getimagesize( $path );
			if ( is_array( $size ) && isset( $size[0], $size[1] ) && $size[0] > 0 && $size[1] > 0 ) {
				return self::format_aspect( (int) $size[0], (int) $size[1] );
			}

			break;
		}

		return self::DEFAULT_ASPECT;
	}
}
