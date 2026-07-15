<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Edit importer rotiter meta (_asrekhodro_over_title) from the post sidebar.
 *
 * Block editor: registered post meta + Document settings panel (REST-safe).
 * Classic editor: fallback meta box + save_post.
 */
final class PostOverTitleMeta {

	public const META_KEY = '_asrekhodro_over_title';

	private const NONCE_ACTION = 'ak_post_over_title_meta';
	private const NONCE_FIELD  = 'ak_post_over_title_nonce';
	private const INPUT_FIELD  = 'ak_post_over_title';

	public static function init(): void {
		add_action( 'init', array( self::class, 'register_meta' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_editor_assets' ) );
		add_action( 'add_meta_boxes', array( self::class, 'register_meta_box' ) );
		add_action( 'save_post_post', array( self::class, 'save_meta' ), 10, 2 );
	}

	public static function register_meta(): void {
		register_post_meta(
			'post',
			self::META_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
					unset( $allowed, $meta_key );

					return current_user_can( 'edit_post', (int) $post_id );
				},
			)
		);
	}

	public static function enqueue_editor_assets(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || $screen->base !== 'post' || $screen->post_type !== 'post' ) {
			return;
		}

		$js_path = ASREKHODRO_THEME_DIR . '/assets/admin/post-over-title-editor.js';
		if ( ! is_readable( $js_path ) ) {
			return;
		}

		wp_enqueue_script(
			'asrekhodro-post-over-title-editor',
			ASREKHODRO_THEME_URI . '/assets/admin/post-over-title-editor.js',
			array(
				'wp-plugins',
				'wp-edit-post',
				'wp-element',
				'wp-components',
				'wp-data',
				'wp-core-data',
				'wp-i18n',
			),
			(string) filemtime( $js_path ),
			true
		);

		wp_localize_script(
			'asrekhodro-post-over-title-editor',
			'akPostOverTitle',
			array(
				'metaKey'     => self::META_KEY,
				'panelTitle'  => 'روتیتر',
				'fieldLabel'  => 'روتیتر',
				'placeholder' => 'متن بالای تیتر خبر',
				'help'        => 'همان فیلدی که از ایمپورت قدیمی آمده و در لیست و صفحه خبر بالای تیتر نمایش داده می‌شود. برای حذف، خالی بگذارید و به‌روزرسانی کنید.',
			)
		);
	}

	public static function register_meta_box(): void {
		// Block editor uses the Document settings panel (REST meta).
		if ( function_exists( 'use_block_editor_for_post_type' ) && use_block_editor_for_post_type( 'post' ) ) {
			return;
		}

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

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
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
