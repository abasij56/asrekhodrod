<?php

namespace AsreKhodro\Theme\CdnServer\Connection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FTP transport for CDN uploads (uses PHP ext-ftp).
 */
final class FtpConnection implements ConnectionInterface {

	/** @var array<string, mixed> */
	private array $settings;

	/** @var array<string, mixed> */
	private array $last_debug = array();

	/**
	 * @param array<string, mixed> $settings
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_last_debug(): array {
		return $this->last_debug;
	}

	public function upload( string $local_path, string $remote_absolute_path ): bool|\WP_Error {
		$this->last_debug = array(
			'configured_base'  => (string) ( $this->settings['remote_base_path'] ?? '' ),
			'logical_absolute' => $remote_absolute_path,
		);

		$conn = $this->connect();
		if ( is_wp_error( $conn ) ) {
			return $conn;
		}

		$this->last_debug['login_pwd'] = $this->pwd_or_unknown( $conn );

		$relative = $this->absolute_to_relative_upload_path( $remote_absolute_path );
		if ( is_wp_error( $relative ) ) {
			ftp_close( $conn );
			return $this->attach_debug( $relative );
		}

		$this->last_debug['ftp_relative'] = $relative;

		$base_entered = $this->enter_base_directory( $conn );
		if ( is_wp_error( $base_entered ) ) {
			ftp_close( $conn );
			return $this->attach_debug( $base_entered );
		}

		$this->last_debug['pwd_after_base'] = $this->pwd_or_unknown( $conn );

		$relative_dir = dirname( $relative );
		$filename     = basename( $relative );
		$this->last_debug['filename']       = $filename;

		if ( $relative_dir !== '.' && $relative_dir !== '' ) {
			$mkdir = $this->ensure_dir_relative( $conn, $relative_dir );
			if ( is_wp_error( $mkdir ) ) {
				ftp_close( $conn );
				return $this->attach_debug( $mkdir );
			}
		}

		$this->last_debug['pwd_after_mkdir'] = $this->pwd_or_unknown( $conn );

		$local_size = filesize( $local_path );
		if ( ! is_int( $local_size ) || $local_size <= 0 ) {
			ftp_close( $conn );
			return $this->attach_debug(
				new \WP_Error( 'ak_cdn_ftp_local', __( 'فایل موقت خالی یا نامعتبر است.', 'asrekhodro' ) )
			);
		}

		$this->last_debug['local_size'] = $local_size;

		$ok = @ftp_put( $conn, $filename, $local_path, FTP_BINARY );
		if ( ! $ok ) {
			$this->last_debug['pwd_after_upload'] = $this->pwd_or_unknown( $conn );
			ftp_close( $conn );
			return $this->attach_debug(
				new \WP_Error(
					'ak_cdn_ftp_put_failed',
					sprintf(
						__( 'آپلود فایل ناموفق بود (%1$s) در مسیر FTP: %2$s', 'asrekhodro' ),
						$filename,
						(string) $this->last_debug['pwd_after_upload']
					)
				)
			);
		}

		$remote_size = @ftp_size( $conn, $filename );
		$listed      = $this->file_listed( $conn, $filename );
		$pwd         = $this->pwd_or_unknown( $conn );

		$this->last_debug['pwd_after_upload'] = $pwd;
		$this->last_debug['remote_size']      = $remote_size;
		$this->last_debug['file_listed']      = $listed;
		$this->last_debug['ftp_file_path']    = self::join_ftp_path( $pwd, $filename );

		ftp_close( $conn );

		if ( $remote_size < 0 || $remote_size !== $local_size || ! $listed ) {
			return $this->attach_debug(
				new \WP_Error(
					'ak_cdn_ftp_verify_failed',
					sprintf(
						__( 'فایل روی سرور FTP تأیید نشد. مسیر FTP: %1$s — اندازه ریموت: %2$d', 'asrekhodro' ),
						(string) $this->last_debug['ftp_file_path'],
						max( 0, $remote_size )
					)
				)
			);
		}

		return true;
	}

	public function test(): bool|\WP_Error {
		$conn = $this->connect();
		if ( is_wp_error( $conn ) ) {
			return $conn;
		}

		$this->last_debug['login_pwd'] = $this->pwd_or_unknown( $conn );

		$entered = $this->enter_base_directory( $conn );
		if ( is_wp_error( $entered ) ) {
			ftp_close( $conn );
			return $entered;
		}

		$this->last_debug['pwd_after_base'] = $this->pwd_or_unknown( $conn );
		$listing                            = @ftp_nlist( $conn, '.' );
		ftp_close( $conn );

		if ( $listing === false ) {
			return new \WP_Error(
				'ak_cdn_ftp_path',
				sprintf(
					__( 'اتصال برقرار شد اما لیست پوشه ممکن نیست (pwd: %s).', 'asrekhodro' ),
					(string) $this->last_debug['pwd_after_base']
				)
			);
		}

		return true;
	}

	/**
	 * @return \FTP\Connection|resource|\WP_Error
	 */
	private function connect() {
		if ( ! function_exists( 'ftp_connect' ) ) {
			return new \WP_Error(
				'ak_cdn_ftp_ext',
				__( 'افزونه FTP در PHP فعال نیست (ext-ftp).', 'asrekhodro' )
			);
		}

		$host    = (string) ( $this->settings['host'] ?? '' );
		$port    = (int) ( $this->settings['port'] ?? 21 );
		$timeout = (int) ( $this->settings['timeout'] ?? 20 );
		$port    = $port > 0 ? $port : 21;
		$timeout = $timeout > 0 ? $timeout : 20;
		$use_tls = strtolower( (string) ( $this->settings['protocol'] ?? 'ftp' ) ) === 'ftps';

		if ( $use_tls ) {
			if ( ! function_exists( 'ftp_ssl_connect' ) ) {
				return new \WP_Error(
					'ak_cdn_ftps_ext',
					__( 'FTPS پشتیبانی نمی‌شود (ext-ftp با OpenSSL لازم است).', 'asrekhodro' )
				);
			}
			$conn = @ftp_ssl_connect( $host, $port, $timeout );
		} else {
			$conn = @ftp_connect( $host, $port, $timeout );
		}

		if ( ! $conn ) {
			return new \WP_Error(
				'ak_cdn_ftp_connect',
				sprintf(
					__( 'اتصال به سرور FTP ناموفق بود (%1$s:%2$d).', 'asrekhodro' ),
					$host,
					$port
				)
			);
		}

		$user = (string) ( $this->settings['user'] ?? '' );
		$pass = (string) ( $this->settings['pass'] ?? '' );

		if ( ! @ftp_login( $conn, $user, $pass ) ) {
			ftp_close( $conn );
			return new \WP_Error(
				'ak_cdn_ftp_login',
				$use_tls
					? __( 'ورود به سرور FTPS ناموفق بود (نام کاربری/رمز).', 'asrekhodro' )
					: __( 'ورود به سرور FTP ناموفق بود (نام کاربری/رمز).', 'asrekhodro' )
			);
		}

		@ftp_pasv( $conn, true );

		return $conn;
	}

	/**
	 * @return string|\WP_Error
	 */
	private function absolute_to_relative_upload_path( string $remote_absolute_path ): string|\WP_Error {
		$absolute = self::normalize_ftp_path( $remote_absolute_path );
		$base     = trim( (string) ( $this->settings['remote_base_path'] ?? '' ), '/' );

		if ( $base !== '' ) {
			$prefix = '/' . $base . '/';
			if ( str_starts_with( $absolute, $prefix ) ) {
				return ltrim( substr( $absolute, strlen( $prefix ) ), '/' );
			}
		}

		return ltrim( $absolute, '/' );
	}

	/**
	 * @param \FTP\Connection|resource $conn
	 * @return true|\WP_Error
	 */
	private function enter_base_directory( $conn ): bool|\WP_Error {
		$configured = trim( (string) ( $this->settings['remote_base_path'] ?? '' ), '/' );
		$login_pwd  = $this->pwd_or_unknown( $conn );

		if ( $configured === '' ) {
			$this->last_debug['base_mode'] = 'no_base_configured';
			return true;
		}

		$login_norm = rtrim( str_replace( '\\', '/', $login_pwd ), '/' );

		if ( $login_norm === $configured || str_ends_with( $login_norm, '/' . $configured ) ) {
			$this->last_debug['base_mode'] = 'already_at_base';
			return true;
		}

		// Try the configured path from the FTP login directory first.
		$segments = array_values( array_filter( explode( '/', $configured ), static fn ( $s ) => $s !== '' ) );
		$all_ok   = $segments !== array();

		foreach ( $segments as $segment ) {
			if ( ! @ftp_chdir( $conn, $segment ) ) {
				$all_ok = false;
				break;
			}
		}

		if ( $all_ok ) {
			$this->last_debug['base_mode'] = 'segments:' . implode( '/', $segments );
			return true;
		}

		// Could not navigate — stay at login pwd (likely chroot root).
		$this->restore_pwd( $conn, $login_pwd );
		$this->last_debug['base_mode'] = 'login_pwd_as_base (chroot?)';

		return true;
	}

	/**
	 * @param \FTP\Connection|resource $conn
	 * @return true|\WP_Error
	 */
	private function ensure_dir_relative( $conn, string $relative_dir ): bool|\WP_Error {
		$relative_dir = trim( str_replace( '\\', '/', $relative_dir ), '/' );
		if ( $relative_dir === '' ) {
			return true;
		}

		foreach ( explode( '/', $relative_dir ) as $segment ) {
			if ( $segment === '' ) {
				continue;
			}

			if ( @ftp_chdir( $conn, $segment ) ) {
				continue;
			}

			if ( ! @ftp_mkdir( $conn, $segment ) || ! @ftp_chdir( $conn, $segment ) ) {
				return new \WP_Error(
					'ak_cdn_ftp_mkdir_failed',
					sprintf(
						__( 'ساخت پوشه «%1$s» ناموفق بود (pwd: %2$s).', 'asrekhodro' ),
						$segment,
						$this->pwd_or_unknown( $conn )
					)
				);
			}
		}

		return true;
	}

	/**
	 * @param \FTP\Connection|resource $conn
	 */
	private function file_listed( $conn, string $filename ): bool {
		$list = @ftp_nlist( $conn, '.' );
		if ( ! is_array( $list ) ) {
			return false;
		}

		foreach ( $list as $entry ) {
			$entry_name = basename( str_replace( '\\', '/', (string) $entry ) );
			if ( $entry_name === $filename ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param \FTP\Connection|resource $conn
	 */
	private function restore_pwd( $conn, string $pwd ): void {
		if ( $pwd !== '' && $pwd !== '?' ) {
			@ftp_chdir( $conn, $pwd );
		}
	}

	/**
	 * @param \FTP\Connection|resource $conn
	 */
	private function pwd_or_unknown( $conn ): string {
		$pwd = @ftp_pwd( $conn );

		return is_string( $pwd ) && $pwd !== '' ? $pwd : '?';
	}

	private static function join_ftp_path( string $pwd, string $filename ): string {
		$pwd = rtrim( str_replace( '\\', '/', $pwd ), '/' );

		return ( $pwd === '' || $pwd === '?' ? '' : $pwd ) . '/' . ltrim( $filename, '/' );
	}

	private static function normalize_ftp_path( string $path ): string {
		$path = str_replace( '\\', '/', trim( $path ) );
		$path = preg_replace( '#/+#', '/', $path ) ?? $path;

		if ( $path !== '' && ! str_starts_with( $path, '/' ) ) {
			$path = '/' . $path;
		}

		return $path;
	}

	private function attach_debug( \WP_Error $error ): \WP_Error {
		$error->add_data( array( 'upload_debug' => $this->last_debug ) );

		return $error;
	}
}
