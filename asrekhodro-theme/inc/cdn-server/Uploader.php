<?php

namespace AsreKhodro\Theme\CdnServer;

use AsreKhodro\Theme\CdnServer\Connection\ConnectionInterface;
use AsreKhodro\Theme\CdnServer\Connection\FtpConnection;
use AsreKhodro\Theme\CdnServer\Connection\SftpConnection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates an uploaded file and pushes it to the CDN server,
 * returning the resulting public URL.
 */
final class Uploader {

	/** @var array<string, mixed> */
	private static array $last_upload_debug = array();

	/**
	 * @return array<string, mixed>
	 */
	public static function get_last_upload_debug(): array {
		return self::$last_upload_debug;
	}

	/**
	 * @param array<string, mixed> $file A single entry from $_FILES.
	 * @return string|\WP_Error Public URL on success.
	 */
	public static function handle( array $file ): string|\WP_Error {
		self::$last_upload_debug = array();

		if ( ! Config::is_configured() ) {
			return new \WP_Error( 'ak_cdn_not_configured', __( 'سرور CDN پیکربندی نشده است.', 'asrekhodro' ) );
		}

		$validated = self::validate( $file );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$tmp_path      = (string) $file['tmp_name'];
		$original_name = (string) $file['name'];
		$mime_type     = $validated['mime'];
		$upload_path   = $tmp_path;
		$temp_wm_path  = '';

		if ( Watermark::should_apply( $mime_type ) ) {
			$watermarked = Watermark::apply( $tmp_path, $mime_type );
			if ( is_wp_error( $watermarked ) ) {
				if ( apply_filters( 'ak_cdn_watermark_fail_upload', false, $watermarked, $file ) ) {
					return $watermarked;
				}
			} elseif ( is_string( $watermarked ) && $watermarked !== '' && $watermarked !== $tmp_path ) {
				$upload_path  = $watermarked;
				$temp_wm_path = $watermarked;
			}
		}

		$canonical    = PathBuilder::build_canonical_relative_path( $original_name, $mime_type );
		$ftp_relative = PathBuilder::ftp_relative_from_canonical( $canonical );
		$absolute     = PathBuilder::build_remote_absolute_path( $ftp_relative );
		$public_url   = PathBuilder::build_public_url( $canonical );

		self::$last_upload_debug = array(
			'canonical_relative' => $canonical,
			'ftp_relative'       => $ftp_relative,
			'logical_absolute'   => $absolute,
			'public_url'         => $public_url,
			'configured_base'    => Config::remote_base_path(),
			'watermark_applied' => $temp_wm_path !== '',
		);

		$connection = self::make_connection();
		if ( is_wp_error( $connection ) ) {
			self::cleanup_watermark_temp( $temp_wm_path );
			return $connection;
		}

		$result = $connection->upload( $upload_path, $absolute );
		self::merge_connection_debug( $connection );
		self::cleanup_watermark_temp( $temp_wm_path );

		if ( is_wp_error( $result ) ) {
			self::merge_error_debug( $result );
			return $result;
		}

		if ( ! self::verify_public_url( $public_url ) ) {
			$ftp_path = (string) ( self::$last_upload_debug['ftp_file_path'] ?? '' );
			$error    = new \WP_Error(
				'ak_cdn_http_verify_failed',
				sprintf(
					/* translators: 1: public URL, 2: FTP path */
					__( 'فایل روی FTP قرار گرفت اما از URL عمومی در دسترس نیست. URL: %1$s — مسیر FTP واقعی: %2$s', 'asrekhodro' ),
					$public_url,
					$ftp_path !== '' ? $ftp_path : __( '(نامشخص)', 'asrekhodro' )
				),
				array( 'upload_debug' => self::$last_upload_debug )
			);

			return $error;
		}

		/**
		 * Fires after a file has been uploaded to the CDN.
		 *
		 * @param string $public_url Resulting public URL.
		 * @param string $relative   Canonical relative path.
		 * @param string $mime_type  Detected MIME type.
		 */
		do_action( 'ak_cdn_uploaded', $public_url, $canonical, $mime_type );

		return $public_url;
	}

	/**
	 * @param array<string, mixed>|null $settings Optional connection override (test form).
	 * @return true|\WP_Error
	 */
	public static function test_connection( ?array $settings = null ): bool|\WP_Error {
		$connection = self::make_connection( $settings );
		if ( is_wp_error( $connection ) ) {
			return $connection;
		}

		return $connection->test();
	}

	/**
	 * @param array<string, mixed> $file
	 * @return array{mime:string}|\WP_Error
	 */
	private static function validate( array $file ): array|\WP_Error {
		$error_code = (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE );
		if ( $error_code !== UPLOAD_ERR_OK ) {
			return new \WP_Error( 'ak_cdn_upload_error', self::php_upload_error_message( $error_code ) );
		}

		$tmp_path = (string) ( $file['tmp_name'] ?? '' );
		if ( $tmp_path === '' || ! is_uploaded_file( $tmp_path ) ) {
			return new \WP_Error( 'ak_cdn_invalid_upload', __( 'فایل معتبری دریافت نشد.', 'asrekhodro' ) );
		}

		$size = (int) ( $file['size'] ?? 0 );
		$max  = Config::max_upload_bytes();
		if ( $size <= 0 ) {
			return new \WP_Error( 'ak_cdn_empty_file', __( 'فایل خالی است.', 'asrekhodro' ) );
		}
		if ( $size > $max ) {
			return new \WP_Error(
				'ak_cdn_too_large',
				sprintf(
					/* translators: %s: max size in MB */
					__( 'حجم فایل بیش از حد مجاز است (حداکثر %s مگابایت).', 'asrekhodro' ),
					number_format_i18n( $max / ( 1024 * 1024 ) )
				)
			);
		}

		$original_name = (string) ( $file['name'] ?? '' );
		$check         = wp_check_filetype_and_ext( $tmp_path, $original_name );
		$mime          = (string) ( $check['type'] ?? '' );

		if ( $mime === '' ) {
			// Fall back to the client-reported type only for allow-list comparison.
			$mime = strtolower( (string) ( $file['type'] ?? '' ) );
		}

		if ( $mime === '' ) {
			return new \WP_Error( 'ak_cdn_unknown_type', __( 'نوع فایل قابل تشخیص نیست.', 'asrekhodro' ) );
		}

		if ( ! self::is_allowed_mime( $mime ) ) {
			return new \WP_Error(
				'ak_cdn_type_not_allowed',
				sprintf(
					/* translators: %s: mime type */
					__( 'این نوع فایل مجاز نیست: %s', 'asrekhodro' ),
					$mime
				)
			);
		}

		return array( 'mime' => $mime );
	}

	private static function is_allowed_mime( string $mime ): bool {
		$mime    = strtolower( $mime );
		$allowed = Config::allowed_types();

		foreach ( $allowed as $pattern ) {
			if ( $pattern === $mime ) {
				return true;
			}
			// Support wildcard entries like "image/*".
			if ( str_ends_with( $pattern, '/*' ) ) {
				$prefix = substr( $pattern, 0, -1 ); // keep "image/"
				if ( str_starts_with( $mime, $prefix ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @param array<string, mixed>|null $settings
	 * @return ConnectionInterface|\WP_Error
	 */
	private static function make_connection( ?array $settings = null ): ConnectionInterface|\WP_Error {
		$settings = $settings ?? Config::connection_settings();
		$protocol = strtolower( (string) ( $settings['protocol'] ?? 'ftp' ) );

		return $protocol === 'sftp'
			? new SftpConnection( $settings )
			: new FtpConnection( $settings );
	}

	/**
	 * @return array<int, array{label: string, value: string}>
	 */
	public static function format_upload_debug_rows(): array {
		$debug = self::$last_upload_debug;
		$rows  = array();

		$ftp_file = (string) ( $debug['ftp_file_path'] ?? '' );
		$base     = trim( (string) ( $debug['configured_base'] ?? Config::remote_base_path() ), '/' );
		if ( $ftp_file !== '' && $base !== '' ) {
			$normalized_ftp_file = '/' . ltrim( str_replace( '\\', '/', $ftp_file ), '/' );
			$normalized_base     = '/' . $base;

			$debug['filezilla_full_path'] = str_starts_with( $normalized_ftp_file, $normalized_base . '/' )
				? $normalized_ftp_file
				: $normalized_base . $normalized_ftp_file;
		}

		$map = array(
			'configured_base'    => __( 'مسیر پایه (تنظیمات)', 'asrekhodro' ),
			'login_pwd'          => __( 'pwd بعد از ورود FTP', 'asrekhodro' ),
			'base_mode'          => __( 'نحوه ورود به پایه', 'asrekhodro' ),
			'pwd_after_base'     => __( 'pwd بعد از ورود به پایه', 'asrekhodro' ),
			'ftp_relative'       => __( 'مسیر نسبی FTP', 'asrekhodro' ),
			'pwd_after_mkdir'    => __( 'pwd بعد از ساخت پوشه', 'asrekhodro' ),
			'ftp_file_path'      => __( 'مسیر واقعی فایل روی FTP (pwd)', 'asrekhodro' ),
			'filezilla_full_path' => __( 'مسیر کامل برای FileZilla', 'asrekhodro' ),
			'canonical_relative' => __( 'مسیر وب (canonical)', 'asrekhodro' ),
			'public_url'         => __( 'URL عمومی', 'asrekhodro' ),
			'logical_absolute'   => __( 'مسیر منطقی مطلق', 'asrekhodro' ),
		);

		foreach ( $map as $key => $label ) {
			if ( isset( $debug[ $key ] ) && (string) $debug[ $key ] !== '' ) {
				$rows[] = array(
					'label' => $label,
					'value' => (string) $debug[ $key ],
				);
			}
		}

		return $rows;
	}

	private static function cleanup_watermark_temp( string $path ): void {
		if ( $path !== '' && is_file( $path ) ) {
			wp_delete_file( $path );
		}
	}

	private static function merge_connection_debug( ConnectionInterface $connection ): void {
		if ( $connection instanceof FtpConnection ) {
			self::$last_upload_debug = array_merge( self::$last_upload_debug, $connection->get_last_debug() );
		}
	}

	private static function merge_error_debug( \WP_Error $error ): void {
		$data = $error->get_error_data();
		if ( is_array( $data ) && isset( $data['upload_debug'] ) && is_array( $data['upload_debug'] ) ) {
			self::$last_upload_debug = array_merge( self::$last_upload_debug, $data['upload_debug'] );
		}
	}

	private static function verify_public_url( string $url ): bool {
		/**
		 * Whether to HTTP-check the public URL after FTP upload.
		 *
		 * @param bool   $verify Default true.
		 * @param string $url    Public CDN URL.
		 */
		if ( ! apply_filters( 'ak_cdn_verify_public_url', true, $url ) ) {
			return true;
		}

		$response = wp_remote_head(
			$url,
			array(
				'timeout'     => 15,
				'redirection' => 3,
			)
		);

		if ( ! is_wp_error( $response ) ) {
			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( $code >= 200 && $code < 300 ) {
				return true;
			}
		}

		// Some hosts block HEAD; try a tiny GET for images.
		$get = wp_remote_get(
			$url,
			array(
				'timeout'     => 15,
				'redirection' => 3,
				'headers'     => array(
					'Range' => 'bytes=0-1023',
				),
			)
		);

		if ( is_wp_error( $get ) ) {
			return false;
		}

		$get_code = (int) wp_remote_retrieve_response_code( $get );

		return ( $get_code >= 200 && $get_code < 300 ) || $get_code === 206;
	}

	private static function php_upload_error_message( int $code ): string {
		return match ( $code ) {
			UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => __( 'حجم فایل بیش از حد مجاز سرور است.', 'asrekhodro' ),
			UPLOAD_ERR_PARTIAL                         => __( 'فایل به‌طور کامل آپلود نشد.', 'asrekhodro' ),
			UPLOAD_ERR_NO_FILE                         => __( 'فایلی انتخاب نشده است.', 'asrekhodro' ),
			UPLOAD_ERR_NO_TMP_DIR                      => __( 'پوشه موقت سرور در دسترس نیست.', 'asrekhodro' ),
			UPLOAD_ERR_CANT_WRITE                      => __( 'نوشتن فایل روی دیسک ناموفق بود.', 'asrekhodro' ),
			default                                    => __( 'خطای نامشخص در آپلود.', 'asrekhodro' ),
		};
	}
}
