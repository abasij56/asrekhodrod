<?php

namespace AsreKhodro\Theme\AcfBlocks\Cinfo3dmodel;

use AsreKhodro\Theme\Appearance;
use AsreKhodro\Theme\ThemeModels;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class View {

	/**
	 * @return list<array{hex: string, label: string}>
	 */
	private static function default_colors(): array {
		return array(
			array(
				'hex'   => '#c41e2a',
				'label' => 'قرمز متالیک',
			),
			array(
				'hex'   => '#1a3568',
				'label' => 'آبی نیمه‌شب',
			),
			array(
				'hex'   => '#9aa3ad',
				'label' => 'نقره‌ای متالیک',
			),
			array(
				'hex'   => '#0d5c48',
				'label' => 'سبز زمردی',
			),
			array(
				'hex'   => '#c4a35a',
				'label' => 'شامپاین طلایی',
			),
		);
	}

	/**
	 * @param array<string, mixed> $fields
	 * @return array<string, mixed>
	 */
	public static function context( array $fields, ?\Timber\Post $post = null ): array {
		$title = '';
		if ( $post ) {
			$title = trim( (string) $post->title() );
		}

		$model_url = self::normalize_model_url( $fields );
		$image     = self::resolve_image( $fields, $post );
		$colors    = self::resolve_colors( $fields );
		$default   = self::resolve_default_color_index( $fields );
		$rotation  = self::resolve_initial_rotation( $fields );
		$extra     = self::format_extra_info_html(
			(string) ( $fields['extra_info'] ?? $fields['field_cinfo_3dmodel_extra_info'] ?? '' )
		);

		return array(
			'model_title'               => $title,
			'model_url'                 => $model_url,
			'model_extra_info_html'     => $extra,
			'model_has_extra_info'      => $extra !== '',
			'model_image_url'           => $image['url'],
			'model_image_alt'           => $image['alt'],
			'model_image_width'         => $image['width'],
			'model_image_height'        => $image['height'],
			'model_draco_path'          => trailingslashit( Appearance::asset_url( 'vendor/three/examples/jsm/libs/draco/gltf' ) ),
			'model_has_visual'          => $model_url !== '' || $image['url'] !== '',
			'model_colors'              => $colors,
			'model_default_color'       => $colors[ $default - 1 ]['hex'] ?? ( $colors[0]['hex'] ?? '#c41e2a' ),
			'model_default_color_index' => $default,
			'model_initial_rotation'    => $rotation,
		);
	}

	private static function format_extra_info_html( string $text ): string {
		$text = trim( $text );
		if ( $text === '' ) {
			return '';
		}

		// ACF may inject literal <br> when new_lines is "br"; Twig auto-escape then shows them as text.
		$text = preg_replace( '/<br\s*\/?>/i', "\n", $text );
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( '/\r\n|\r/', "\n", $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", trim( $text ) );

		if ( $text === '' ) {
			return '';
		}

		return nl2br( esc_html( $text ), false );
	}

	/**
	 * @param array<string, mixed> $fields
	 */
	private static function resolve_initial_rotation( array $fields ): int {
		$value = $fields['initial_rotation'] ?? $fields['field_cinfo_3dmodel_initial_rotation'] ?? 32;
		$angle = (int) round( (float) $value );
		$angle %= 360;

		if ( $angle < 0 ) {
			$angle += 360;
		}

		return $angle;
	}

	/**
	 * @param array<string, mixed> $fields
	 * @return list<array{hex: string, label: string}>
	 */
	private static function resolve_colors( array $fields ): array {
		$defaults = self::default_colors();
		$colors   = array();

		for ( $index = 1; $index <= 5; $index++ ) {
			$hex_key   = "color_{$index}";
			$label_key = "color_{$index}_label";
			$hex       = self::normalize_hex(
				(string) ( $fields[ $hex_key ] ?? $fields[ "field_cinfo_3dmodel_color_{$index}" ] ?? '' )
			);
			$label     = trim(
				(string) ( $fields[ $label_key ] ?? $fields[ "field_cinfo_3dmodel_color_{$index}_label" ] ?? '' )
			);

			if ( $hex === '' ) {
				$hex = $defaults[ $index - 1 ]['hex'];
			}

			if ( $label === '' ) {
				$label = $defaults[ $index - 1 ]['label'];
			}

			$colors[] = array(
				'hex'   => $hex,
				'label' => $label,
			);
		}

		return $colors;
	}

	/**
	 * @param array<string, mixed> $fields
	 */
	private static function resolve_default_color_index( array $fields ): int {
		$value = (string) ( $fields['default_color'] ?? $fields['field_cinfo_3dmodel_default_color'] ?? '1' );
		$index = (int) $value;

		if ( $index < 1 || $index > 5 ) {
			return 1;
		}

		return $index;
	}

	private static function normalize_hex( string $value ): string {
		$value = trim( $value );
		if ( $value === '' ) {
			return '';
		}

		if ( $value[0] !== '#' ) {
			$value = '#' . $value;
		}

		if ( ! preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ) {
			return '';
		}

		return strtolower( $value );
	}

	private static function normalize_model_url( array $fields ): string {
		$model_url = trim( (string) ( $fields['model_url'] ?? $fields['field_cinfo_3dmodel_url'] ?? '' ) );
		if ( $model_url === '' ) {
			return '';
		}

		return ThemeModels::resolve_url( $model_url );
	}

	/**
	 * @param array<string, mixed> $fields
	 * @return array{url: string, alt: string, width: int, height: int}
	 */
	private static function resolve_image( array $fields, ?\Timber\Post $post ): array {
		$image_field = $fields['image'] ?? $fields['field_cinfo_3dmodel_image'] ?? null;

		if ( ( $image_field === null || $image_field === '' || $image_field === false ) && function_exists( 'get_field' ) ) {
			$image_field = get_field( 'image' );
		}

		if ( is_numeric( $image_field ) ) {
			return self::attachment_image( (int) $image_field, $post );
		}

		if ( is_array( $image_field ) ) {
			if ( ! empty( $image_field['url'] ) ) {
				return array(
					'url'    => (string) $image_field['url'],
					'alt'    => (string) ( $image_field['alt'] ?? '' ),
					'width'  => (int) ( $image_field['width'] ?? 960 ),
					'height' => (int) ( $image_field['height'] ?? 600 ),
				);
			}

			if ( ! empty( $image_field['ID'] ) ) {
				return self::attachment_image( (int) $image_field['ID'], $post );
			}
		}

		if ( $post && $post->thumbnail() ) {
			$thumb = $post->thumbnail();

			return array(
				'url'    => (string) $thumb->src( 'large' ),
				'alt'    => (string) ( $thumb->alt() ?: $post->title() ),
				'width'  => (int) ( $thumb->width() ?: 960 ),
				'height' => (int) ( $thumb->height() ?: 600 ),
			);
		}

		return array(
			'url'    => '',
			'alt'    => '',
			'width'  => 960,
			'height' => 600,
		);
	}

	/**
	 * @return array{url: string, alt: string, width: int, height: int}
	 */
	private static function attachment_image( int $attachment_id, ?\Timber\Post $post ): array {
		$url = wp_get_attachment_image_url( $attachment_id, 'large' );
		if ( ! is_string( $url ) || $url === '' ) {
			return array(
				'url'    => '',
				'alt'    => '',
				'width'  => 960,
				'height' => 600,
			);
		}

		$meta = wp_get_attachment_metadata( $attachment_id );

		return array(
			'url'    => $url,
			'alt'    => (string) ( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: ( $post ? $post->title() : '' ) ),
			'width'  => (int) ( $meta['width'] ?? 960 ),
			'height' => (int) ( $meta['height'] ?? 600 ),
		);
	}
}
