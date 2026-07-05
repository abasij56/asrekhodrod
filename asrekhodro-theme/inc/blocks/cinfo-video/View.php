<?php

namespace AsreKhodro\Theme\AcfBlocks\CinfoVideo;

use AsreKhodro\Theme\AcfBlocks\Support\EmbedSanitizer;
use AsreKhodro\Theme\VideoSingle;

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

		$title = trim( (string) ( $fields['title'] ?? $fields['field_cinfo_video_title'] ?? '' ) );
		$embed = self::resolve_embed( $fields );
		$default_anchor = (string) ( Block::config()['default_anchor'] ?? 'video' );

		return array(
			'video_title'          => $title,
			'video_embed'          => $embed,
			'video_default_anchor' => $default_anchor,
			'video_has_content'    => $title !== '' || $embed !== '',
		);
	}

	/**
	 * @param array<string, mixed> $fields
	 */
	private static function resolve_embed( array $fields ): string {
		$embed_code = trim( (string) ( $fields['embed_code'] ?? $fields['field_cinfo_video_embed_code'] ?? '' ) );
		if ( $embed_code !== '' ) {
			return EmbedSanitizer::video_embed( $embed_code );
		}

		$video_id = self::resolve_post_id(
			$fields['selected_video'] ?? $fields['field_cinfo_video_selected_video'] ?? null
		);
		if ( $video_id > 0 ) {
			$markup = VideoSingle::get_player_markup( $video_id );
			if ( $markup !== '' ) {
				return EmbedSanitizer::video_embed( $markup );
			}
		}

		$review_id = self::resolve_post_id(
			$fields['selected_review'] ?? $fields['field_cinfo_video_selected_review'] ?? null
		);
		if ( $review_id > 0 ) {
			$markup = VideoSingle::get_player_markup( $review_id );
			if ( $markup !== '' ) {
				return EmbedSanitizer::video_embed( $markup );
			}
		}

		return '';
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
}
