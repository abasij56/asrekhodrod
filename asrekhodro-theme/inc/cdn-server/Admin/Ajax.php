<?php

namespace AsreKhodro\Theme\CdnServer\Admin;

use AsreKhodro\Theme\CdnServer\Config;
use AsreKhodro\Theme\CdnServer\Uploader;
use AsreKhodro\Theme\ExternalMedia;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX + admin-post handlers for external media and CDN uploads.
 */
final class Ajax {

	public const UPLOAD_ACTION = 'ak_upload_cdn_media';
	public const UPLOAD_NONCE  = 'ak_cdn_upload';
	public const TEST_ACTION   = 'ak_cdn_test_connection';
	public const TEST_NONCE    = 'ak_cdn_test';

	public static function init(): void {
		add_action( 'wp_ajax_' . ExternalMedia::AJAX_ACTION, array( self::class, 'handle_url_ajax' ) );
		add_action( 'admin_post_' . ExternalMedia::AJAX_ACTION, array( self::class, 'handle_url_admin_post' ) );
		add_action( 'wp_ajax_' . self::UPLOAD_ACTION, array( self::class, 'handle_cdn_upload' ) );
		add_action( 'wp_ajax_' . self::TEST_ACTION, array( self::class, 'handle_test_connection' ) );
	}

	/**
	 * Register remote URL(s) — AJAX (media modal).
	 */
	public static function handle_url_ajax(): void {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'asrekhodro' ) ), 403 );
		}

		check_ajax_referer( ExternalMedia::NONCE_ACTION, 'nonce' );

		$info        = ExternalMedia::process_submission();
		$attachments = array();

		foreach ( $info['attachment_ids'] as $attachment_id ) {
			$attachment = wp_prepare_attachment_for_js( $attachment_id );
			if ( $attachment ) {
				$attachments[] = $attachment;
			} elseif ( ! isset( $info['error'] ) ) {
				$info['error'] = __( 'An attachment was created but could not be loaded for display.', 'asrekhodro' );
			}
		}

		$info['attachments'] = $attachments;
		wp_send_json_success( $info );
	}

	/**
	 * Register remote URL(s) — non-JS fallback.
	 */
	public static function handle_url_admin_post(): void {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You do not have permission to upload files.', 'asrekhodro' ) );
		}

		check_admin_referer( ExternalMedia::NONCE_ACTION );

		$info         = ExternalMedia::process_submission();
		$redirect_url = 'upload.php?page=add-external-media';
		$failed_urls  = (string) ( $info['urls'] ?? '' );

		if ( $failed_urls !== '' || ! empty( $info['error'] ) ) {
			$redirect_url = add_query_arg(
				array(
					'urls'      => $failed_urls,
					'error'     => (string) ( $info['error'] ?? '' ),
					'width'     => (string) ( $info['width'] ?? '' ),
					'height'    => (string) ( $info['height'] ?? '' ),
					'mime-type' => (string) ( $info['mime-type'] ?? '' ),
				),
				'upload.php?page=add-external-media'
			);
		} else {
			$redirect_url = 'upload.php';
		}

		wp_safe_redirect( admin_url( $redirect_url ) );
		exit;
	}

	/**
	 * Upload a local file to the CDN, then register its public URL.
	 */
	public static function handle_cdn_upload(): void {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'asrekhodro' ) ), 403 );
		}

		check_ajax_referer( self::UPLOAD_NONCE, 'nonce' );

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 );
		}

		if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'فایلی دریافت نشد.', 'asrekhodro' ) ) );
		}

		try {
			// $_FILES is validated inside Uploader (is_uploaded_file, size, mime).
			$file       = self::sanitize_file_array( $_FILES['file'] );
			$public_url = Uploader::handle( $file );

			if ( is_wp_error( $public_url ) ) {
				wp_send_json_error(
					array(
						'message'      => $public_url->get_error_message(),
						'upload_debug' => Uploader::format_upload_debug_rows(),
					)
				);
			}

			$attachment_id = ExternalMedia::register_url(
				$public_url,
				array(
					'allow_fallback'    => true,
					'skip_remote_probe' => true,
				)
			);

			if ( $attachment_id <= 0 ) {
				wp_send_json_error(
					array(
						'message' => __( 'فایل آپلود شد اما ثبت در کتابخانه رسانه ناموفق بود.', 'asrekhodro' ),
						'url'     => $public_url,
					)
				);
			}

			$attachment = wp_prepare_attachment_for_js( $attachment_id );

			wp_send_json_success(
				array(
					'url'          => $public_url,
					'attachments'  => $attachment ? array( $attachment ) : array(),
					'upload_debug' => Uploader::format_upload_debug_rows(),
				)
			);
		} catch ( \Throwable $e ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: exception message */
						__( 'خطای سرور هنگام آپلود CDN: %s', 'asrekhodro' ),
						$e->getMessage()
					),
					'upload_debug' => Uploader::format_upload_debug_rows(),
				)
			);
		}
	}

	/**
	 * Test the FTP/SFTP connection (settings page button).
	 */
	public static function handle_test_connection(): void {
		if ( ! \AsreKhodro\Theme\AuthorAccess::can_manage_theme_settings() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'asrekhodro' ) ), 403 );
		}

		check_ajax_referer( self::TEST_NONCE, 'nonce' );

		$input   = self::sanitize_test_input( $_POST );
		$bundle  = Config::connection_settings_for_test( $input );
		$settings = $bundle['settings'];
		$debug   = Config::format_debug_summary(
			$settings,
			(string) $bundle['source'],
			(bool) $bundle['pass_from_saved']
		);

		$result = Uploader::test_connection( $settings );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'debug'   => $debug,
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'اتصال با موفقیت برقرار شد.', 'asrekhodro' ),
				'debug'   => $debug,
			)
		);
	}

	/**
	 * @param array<string, mixed> $post
	 * @return array<string, string>
	 */
	private static function sanitize_test_input( array $post ): array {
		return array(
			'from_form'        => isset( $post['from_form'] ) ? '1' : '',
			'protocol'         => isset( $post['protocol'] ) ? sanitize_key( (string) wp_unslash( $post['protocol'] ) ) : '',
			'host'             => isset( $post['host'] ) ? sanitize_text_field( wp_unslash( (string) $post['host'] ) ) : '',
			'port'             => isset( $post['port'] ) ? sanitize_text_field( wp_unslash( (string) $post['port'] ) ) : '',
			'user'             => isset( $post['user'] ) ? sanitize_text_field( wp_unslash( (string) $post['user'] ) ) : '',
			'pass'             => isset( $post['pass'] ) ? (string) wp_unslash( $post['pass'] ) : '',
			'remote_base_path' => isset( $post['remote_base_path'] ) ? sanitize_text_field( wp_unslash( (string) $post['remote_base_path'] ) ) : '',
		);
	}

	/**
	 * Normalise a single-file $_FILES entry.
	 *
	 * @param array<string, mixed> $file
	 * @return array<string, mixed>
	 */
	private static function sanitize_file_array( array $file ): array {
		return array(
			'name'     => isset( $file['name'] ) ? sanitize_file_name( (string) $file['name'] ) : '',
			'type'     => isset( $file['type'] ) ? sanitize_mime_type( (string) $file['type'] ) : '',
			'tmp_name' => isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '',
			'error'    => isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE,
			'size'     => isset( $file['size'] ) ? (int) $file['size'] : 0,
		);
	}
}
