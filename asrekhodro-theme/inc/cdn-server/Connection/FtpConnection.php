<?php

namespace AsreKhodro\Theme\CdnServer\Connection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FTP transport for CDN uploads (uses PHP ext-ftp, with optional cURL fallback).
 *
 * FileZilla from a desktop works because it negotiates Passive once and streams.
 * PHP on the WordPress host is different: slow/blocked data ports + many retries
 * used to hang until admin-ajax.php returned HTTP 500. Keep attempts few and fast.
 */
final class FtpConnection implements ConnectionInterface {

	private const TRANSFER_TIMEOUT = 25;

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

		$local_size = filesize( $local_path );
		if ( ! is_int( $local_size ) || $local_size <= 0 ) {
			return $this->attach_debug(
				new \WP_Error( 'ak_cdn_ftp_local', __( 'فایل موقت خالی یا نامعتبر است.', 'asrekhodro' ) )
			);
		}
		$this->last_debug['local_size'] = $local_size;

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
		$this->last_debug['filename'] = $filename;

		if ( $relative_dir !== '.' && $relative_dir !== '' ) {
			$mkdir = $this->ensure_dir_relative( $conn, $relative_dir );
			if ( is_wp_error( $mkdir ) ) {
				ftp_close( $conn );
				return $this->attach_debug( $mkdir );
			}
		}
		$this->last_debug['pwd_after_mkdir'] = $this->pwd_or_unknown( $conn );

		// Active FTP (host support): ftp_pasv(false). Do not stack many hanging fallbacks.
		$this->enable_active_mode( $conn );
		$ok = @ftp_put( $conn, $filename, $local_path, FTP_BINARY );
		$this->last_debug['ftp_last_message'] = $this->ftp_message( $conn );

		if ( $ok ) {
			$this->last_debug['upload_mode']   = 'active';
			$this->last_debug['upload_remote'] = $filename;
		} else {
			// Fast cURL fallback (often works when ext-ftp data channel hangs/fails).
			ftp_close( $conn );
			$conn = null;

			$curl_ok = $this->upload_via_curl( $local_path, $remote_absolute_path );
			if ( ! $curl_ok ) {
				return $this->attach_debug(
					new \WP_Error(
						'ak_cdn_ftp_put_failed',
						sprintf(
							__( 'آپلود فایل ناموفق بود (%1$s). سرور وردپرس به کانال داده FTP دسترسی ندارد (FileZilla از سیستم شما ممکن است کار کند). پیام: %2$s', 'asrekhodro' ),
							$filename,
							(string) ( $this->last_debug['ftp_last_message'] ?: $this->last_debug['curl_error'] ?? __( 'نامشخص', 'asrekhodro' ) )
						)
					)
				);
			}

			$this->last_debug['upload_mode']   = 'curl';
			$this->last_debug['upload_remote'] = $remote_absolute_path;
			$this->last_debug['ftp_file_path'] = $remote_absolute_path;
			$this->last_debug['remote_size']   = $local_size;

			return true;
		}

		// SIZE is control-channel only (cheap). Skip NLST — it often hangs on these hosts.
		$remote_size = @ftp_size( $conn, $filename );
		$pwd         = $this->pwd_or_unknown( $conn );
		$this->last_debug['pwd_after_upload'] = $pwd;
		$this->last_debug['remote_size']      = $remote_size;
		$this->last_debug['ftp_file_path']    = self::join_ftp_path( $pwd, $filename );
		ftp_close( $conn );

		if ( $remote_size >= 0 && $remote_size !== $local_size ) {
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

		// SIZE unsupported (-1) but STOR returned true — accept.
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

		$pwd                                = $this->pwd_or_unknown( $conn );
		$this->last_debug['pwd_after_base'] = $pwd;

		$this->enable_active_mode( $conn );
		$listing = @ftp_nlist( $conn, '.' );
		if ( is_array( $listing ) ) {
			$this->last_debug['list_ok'] = true;
			ftp_close( $conn );

			return true;
		}

		$this->last_debug['list_ok'] = false;

		$probe = $this->probe_write_delete( $conn );
		ftp_close( $conn );

		if ( is_wp_error( $probe ) ) {
			// Control channel works; data channel may still work via cURL for real uploads.
			$curl_ok = $this->probe_via_curl();
			if ( $curl_ok ) {
				$this->last_debug['probe_ok'] = 'curl';

				return true;
			}

			return new \WP_Error(
				'ak_cdn_ftp_path',
				sprintf(
					__( 'ورود FTP موفق بود اما انتقال داده از سرور وردپرس ممکن نیست (pwd: %s). FileZilla از سیستم شما مسیر دیگری است.', 'asrekhodro' ),
					$pwd
				)
			);
		}

		$this->last_debug['probe_ok'] = true;

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
		$timeout = $timeout > 0 ? min( $timeout, self::TRANSFER_TIMEOUT ) : self::TRANSFER_TIMEOUT;
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

		$this->configure_transfer_options( $conn );
		$this->enable_active_mode( $conn );

		return $conn;
	}

	/**
	 * @param \FTP\Connection|resource $conn
	 */
	private function configure_transfer_options( $conn ): void {
		if ( ! function_exists( 'ftp_set_option' ) ) {
			return;
		}

		if ( defined( 'FTP_TIMEOUT_SEC' ) ) {
			@ftp_set_option( $conn, FTP_TIMEOUT_SEC, self::TRANSFER_TIMEOUT );
			$this->last_debug['ftp_timeout'] = self::TRANSFER_TIMEOUT;
		}
	}

	/**
	 * Host support requires Active FTP (PORT), not Passive (PASV).
	 *
	 * @param \FTP\Connection|resource $conn
	 */
	private function enable_active_mode( $conn ): void {
		@ftp_pasv( $conn, false );
		$this->last_debug['data_mode'] = 'active';
	}

	/**
	 * Upload via cURL FTP — often more reliable than ext-ftp from restricted hosts.
	 */
	private function upload_via_curl( string $local_path, string $remote_absolute_path ): bool {
		if ( ! function_exists( 'curl_init' ) ) {
			$this->last_debug['curl_error'] = 'curl missing';
			return false;
		}

		$host = (string) ( $this->settings['host'] ?? '' );
		$port = (int) ( $this->settings['port'] ?? 21 );
		$user = (string) ( $this->settings['user'] ?? '' );
		$pass = (string) ( $this->settings['pass'] ?? '' );
		$port = $port > 0 ? $port : 21;

		$path_parts = array_map(
			'rawurlencode',
			array_values( array_filter( explode( '/', $remote_absolute_path ), static fn ( $s ) => $s !== '' ) )
		);
		$url = sprintf( 'ftp://%s:%d/%s', $host, $port, implode( '/', $path_parts ) );

		$handle = @fopen( $local_path, 'rb' );
		if ( ! $handle ) {
			$this->last_debug['curl_error'] = 'cannot open local file';
			return false;
		}

		$ch = curl_init();
		$opts = array(
			CURLOPT_URL            => $url,
			CURLOPT_USERPWD        => $user . ':' . $pass,
			CURLOPT_UPLOAD         => true,
			CURLOPT_INFILE         => $handle,
			CURLOPT_INFILESIZE     => (int) filesize( $local_path ),
			CURLOPT_FTP_USE_EPSV   => false,
			CURLOPT_FTPPORT        => '-', // Active FTP (PORT); matches ftp_pasv(false).
			CURLOPT_TIMEOUT        => self::TRANSFER_TIMEOUT,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_RETURNTRANSFER => true,
		);

		if ( defined( 'CURLFTP_CREATE_DIR_RETRY' ) ) {
			$opts[ CURLOPT_FTP_CREATE_MISSING_DIRS ] = CURLFTP_CREATE_DIR_RETRY;
		} elseif ( defined( 'CURLFTP_CREATE_DIR' ) ) {
			$opts[ CURLOPT_FTP_CREATE_MISSING_DIRS ] = CURLFTP_CREATE_DIR;
		}

		curl_setopt_array( $ch, $opts );
		$result = curl_exec( $ch );
		$error  = curl_error( $ch );
		$code   = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );
		fclose( $handle );

		$this->last_debug['curl_url']  = preg_replace( '#://([^:]+):[^@]+@#', '://$1:***@', $url ) ?? $url;
		$this->last_debug['curl_code'] = $code;

		if ( $result === false ) {
			$this->last_debug['curl_error'] = $error !== '' ? $error : 'curl_exec failed';
			return false;
		}

		return true;
	}

	private function probe_via_curl(): bool {
		$tmp = wp_tempnam( 'ak-cdn-curl-probe' );
		if ( ! is_string( $tmp ) || $tmp === '' ) {
			return false;
		}

		file_put_contents( $tmp, 'ak-cdn-probe' );
		$base = rtrim( (string) ( $this->settings['remote_base_path'] ?? '' ), '/' );
		$name = '.ak-cdn-probe-' . wp_generate_password( 8, false, false ) . '.txt';
		$path = ( $base === '' ? '' : $base ) . '/' . $name;

		$ok = $this->upload_via_curl( $tmp, $path );
		@unlink( $tmp );

		return $ok;
	}

	/**
	 * @param \FTP\Connection|resource $conn
	 * @return true|\WP_Error
	 */
	private function probe_write_delete( $conn ): bool|\WP_Error {
		$tmp = wp_tempnam( 'ak-cdn-ftp-probe' );
		if ( ! is_string( $tmp ) || $tmp === '' ) {
			return new \WP_Error( 'ak_cdn_ftp_probe_tmp', __( 'ساخت فایل موقت تست ناموفق بود.', 'asrekhodro' ) );
		}

		$payload = 'ak-cdn-probe';
		if ( false === file_put_contents( $tmp, $payload ) ) {
			@unlink( $tmp );
			return new \WP_Error( 'ak_cdn_ftp_probe_tmp', __( 'نوشتن فایل موقت تست ناموفق بود.', 'asrekhodro' ) );
		}

		$remote = '.ak-cdn-probe-' . wp_generate_password( 8, false, false ) . '.txt';
		$this->enable_active_mode( $conn );
		$ok = @ftp_put( $conn, $remote, $tmp, FTP_BINARY );
		@unlink( $tmp );

		if ( ! $ok ) {
			return new \WP_Error( 'ak_cdn_ftp_probe_put', __( 'آپلود تستی روی FTP ناموفق بود.', 'asrekhodro' ) );
		}

		$size = @ftp_size( $conn, $remote );
		@ftp_delete( $conn, $remote );

		if ( $size >= 0 && $size !== strlen( $payload ) ) {
			return new \WP_Error( 'ak_cdn_ftp_probe_size', __( 'تأیید اندازه فایل تستی روی FTP ناموفق بود.', 'asrekhodro' ) );
		}

		return true;
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

	/**
	 * @param \FTP\Connection|resource $conn
	 */
	private function ftp_message( $conn ): string {
		if ( ! function_exists( 'ftp_last_message' ) ) {
			return '';
		}

		$msg = @ftp_last_message( $conn );

		return is_string( $msg ) ? trim( $msg ) : '';
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
