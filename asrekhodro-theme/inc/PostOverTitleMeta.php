<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Edit importer rotiter meta (_asrekhodro_over_title) from the post sidebar.
 */
final class PostOverTitleMeta {

	public const META_KEY = '_asrekhodro_over_title';

	private const NONCE_ACTION = 'ak_post_over_title_meta';
	private const NONCE_FIELD  = 'ak_post_over_title_nonce';
	private const INPUT_FIELD  = 'ak_post_over_title';

	public static function init(): void {
		add_action( 'add_meta_boxes', array( self::class, 'register_meta_box' ) );
		add_action( 'save_post_post', array( self::class, 'save_meta' ), 10, 2 );
	}

	public static function register_meta_box(): void {
		add_meta_box(
			'ak-post-over-title',
			'روتیتر',
			array( self::class, 'render_meta_box' ),
			'post',
			'side',
			'default'
		);
	}

	public static function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$value = get_post_meta( $post->ID, self::META_KEY, true );
		if ( ! is_string( $value ) ) {
			$value = '';
		}

		echo '<p>';
		echo '<label class="screen-reader-text" for="' . esc_attr( self::INPUT_FIELD ) . '">روتیتر</label>';
		printf(
			'<input type="text" id="%1$s" name="%1$s" value="%2$s" class="widefat" placeholder="%3$s" />',
			esc_attr( self::INPUT_FIELD ),
			esc_attr( $value ),
			esc_attr( 'متن بالای تیتر خبر' )
		);
		echo '</p>';
		echo '<p class="description">همان فیلدی که از ایمپورت قدیمی آمده و در لیست و صفحه خبر نمایش داده می‌شود. برای حذف، خالی بگذارید و ذخیره کنید.</p>';
	}

	public static function save_meta( int $post_id, \WP_Post $post ): void {
		unset( $post );

		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_FIELD ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST[ self::INPUT_FIELD ] ) ) {
			return;
		}

		$value = sanitize_text_field( wp_unslash( (string) $_POST[ self::INPUT_FIELD ] ) );
		$value = trim( $value );

		if ( $value === '' ) {
			delete_post_meta( $post_id, self::META_KEY );
			return;
		}

		update_post_meta( $post_id, self::META_KEY, $value );
	}
}
