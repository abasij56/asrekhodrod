<?php

namespace AsreKhodro\Theme\CdnServer\Connection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SFTP transport for CDN uploads (requires PHP ext-ssh2).
 *
 * If ext-ssh2 is not available the connection reports a clear error so the
 * admin can switch to FTP or install the extension.
 */
final class SftpConnection implements ConnectionInterface {

	/** @var array<string, mixed> */
	private array $settings;

	/**
	 * @param array<string, mixed> $settings
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	public static function is_supported(): bool {
		return function_exists( 'ssh2_connect' ) && function_exists( 'ssh2_sftp' );
	}

	public function upload( string $local_path, string $remote_absolute_path ): bool|\WP_Error {
		$sftp = $this->connect();
		if ( is_wp_error( $sftp ) ) {
			return $sftp;
		}

		$this->ensure_dir( $sftp, self::dirname( $remote_absolute_path ) );

		$stream_path = 'ssh2.sftp://' . intval( $sftp ) . $remote_absolute_path;

		$source = @fopen( $local_path, 'rb' );
		if ( ! $source ) {
			return new \WP_Error( 'ak_cdn_sftp_source', __( 'فایل موقت قابل خواندن نیست.', 'asrekhodro' ) );
		}

		$dest = @fopen( $stream_path, 'wb' );
		if ( ! $dest ) {
			fclose( $source );
			return new \WP_Error( 'ak_cdn_sftp_put_failed', __( 'آپلود فایل روی سرور SFTP ناموفق بود.', 'asrekhodro' ) );
		}

		$bytes = stream_copy_to_stream( $source, $dest );

		fclose( $source );
		fclose( $dest );

		if ( $bytes === false ) {
			return new \WP_Error( 'ak_cdn_sftp_copy', __( 'انتقال داده روی SFTP ناموفق بود.', 'asrekhodro' ) );
		}

		return true;
	}

	public function test(): bool|\WP_Error {
		$sftp = $this->connect();
		if ( is_wp_error( $sftp ) ) {
			return $sftp;
		}

		$base       = rtrim( (string) ( $this->settings['remote_base_path'] ?? '/' ), '/' ) ?: '/';
		$stat_path  = 'ssh2.sftp://' . intval( $sftp ) . $base;
		$exists     = @file_exists( $stat_path );

		if ( ! $exists ) {
			return new \WP_Error(
				'ak_cdn_sftp_path',
				__( 'اتصال برقرار شد اما مسیر پایه در دسترس نیست.', 'asrekhodro' )
			);
		}

		return true;
	}

	/**
	 * @return resource|\WP_Error SFTP resource
	 */
	private function connect() {
		if ( ! self::is_supported() ) {
			return new \WP_Error(
				'ak_cdn_sftp_ext',
				__( 'SFTP نیازمند افزونه ssh2 در PHP است. لطفاً FTP را انتخاب کنید یا ext-ssh2 نصب شود.', 'asrekhodro' )
			);
		}

		$host    = (string) ( $this->settings['host'] ?? '' );
		$port    = (int) ( $this->settings['port'] ?? 22 );

		$session = @ssh2_connect( $host, $port > 0 ? $port : 22 );
		if ( ! $session ) {
			return new \WP_Error(
				'ak_cdn_sftp_connect',
				sprintf(
					/* translators: 1: host, 2: port */
					__( 'اتصال به سرور SFTP ناموفق بود (%1$s:%2$d).', 'asrekhodro' ),
					$host,
					$port
				)
			);
		}

		$user = (string) ( $this->settings['user'] ?? '' );
		$pass = (string) ( $this->settings['pass'] ?? '' );

		if ( ! @ssh2_auth_password( $session, $user, $pass ) ) {
			return new \WP_Error(
				'ak_cdn_sftp_auth',
				__( 'احراز هویت SFTP ناموفق بود (نام کاربری/رمز).', 'asrekhodro' )
			);
		}

		$sftp = @ssh2_sftp( $session );
		if ( ! $sftp ) {
			return new \WP_Error( 'ak_cdn_sftp_init', __( 'راه‌اندازی زیرسیستم SFTP ناموفق بود.', 'asrekhodro' ) );
		}

		return $sftp;
	}

	/**
	 * @param resource $sftp
	 */
	private function ensure_dir( $sftp, string $dir ): void {
		$dir = trim( $dir );
		if ( $dir === '' || $dir === '/' || $dir === '.' ) {
			return;
		}

		$stream_dir = 'ssh2.sftp://' . intval( $sftp ) . $dir;
		if ( @is_dir( $stream_dir ) ) {
			return;
		}

		// mkdir with recursive works over the ssh2 stream wrapper.
		@ssh2_sftp_mkdir( $sftp, $dir, 0755, true );
	}

	private static function dirname( string $path ): string {
		$dir = str_replace( '\\', '/', dirname( $path ) );

		return $dir === '.' ? '' : $dir;
	}
}
