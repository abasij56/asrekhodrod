<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve attachment alternative text for frontend images.
 */
final class MediaAlt {

	public static function from_attachment_id( int $attachment_id ): string {
		if ( $attachment_id <= 0 ) {
			return '';
		}

		$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

		return is_string( $alt ) ? trim( $alt ) : '';
	}

	public static function from_post_thumbnail( int $post_id ): string {
		return self::from_attachment_id( (int) get_post_thumbnail_id( $post_id ) );
	}

	/**
	 * @param mixed $image ACF image array or attachment ID.
	 */
	public static function from_acf_image( mixed $image ): string {
		if ( is_array( $image ) ) {
			$alt = trim( (string) ( $image['alt'] ?? '' ) );
			if ( $alt !== '' ) {
				return $alt;
			}

			if ( ! empty( $image['ID'] ) ) {
				return self::from_attachment_id( (int) $image['ID'] );
			}
		}

		if ( is_numeric( $image ) ) {
			return self::from_attachment_id( (int) $image );
		}

		return '';
	}

	public static function from_url( string $url ): string {
		$url = trim( $url );
		if ( $url === '' ) {
			return '';
		}

		return self::from_attachment_id( self::find_attachment_id_by_url( $url ) );
	}

	public static function find_attachment_id_by_url( string $url ): int {
		$url = trim( $url );
		if ( $url === '' ) {
			return 0;
		}

		$attachment_id = attachment_url_to_postid( $url );
		if ( $attachment_id > 0 ) {
			return $attachment_id;
		}

		global $wpdb;

		$attachment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid = %s LIMIT 1",
				$url
			)
		);
		if ( $attachment_id ) {
			return (int) $attachment_id;
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( $path !== '' ) {
			$attachment_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
					ltrim( $path, '/' )
				)
			);
			if ( $attachment_id ) {
				return (int) $attachment_id;
			}
		}

		return 0;
	}
}
