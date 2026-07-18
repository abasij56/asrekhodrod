<?php

namespace AsreKhodro\Theme\CdnServer\Connection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Copy files on the same machine when remote_base_path is a real local directory.
 *
 * Common on Parspack/DirectAdmin: WordPress and media share one account, but
 * outbound FTP to the public IP is refused (hairpin/firewall). FileZilla from
 * a desktop still works because it is an external client.
 */
final class LocalFilesystemConnection implements ConnectionInterface {

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

	/**
	 * @param array<string, mixed> $settings
	 */
	public static function is_available( array $settings ): bool {
		return self::resolve_local_base( $settings ) !== '';
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	public static function resolve_local_base( array $settings ): string {
		$configured = str_replace( '\\', '/', trim( (string) ( $settings['remote_base_path'] ?? '' ) ) );
		if ( $configured === '' || $configured === '/' ) {
			return '';
		}

		foreach ( self::candidate_bases( $configured ) as $candidate ) {
			if ( is_dir( $candidate ) && is_writable( $candidate ) ) {
				return $candidate;
			}
		}

		return '';
	}

	public function upload( string $local_path, string $remote_absolute_path ): bool|\WP_Error {
		$base = self::resolve_local_base( $this->settings );
		$this->last_debug = array(
			'configured_base'  => (string) ( $this->settings['remote_base_path'] ?? '' ),
			'logical_absolute' => $remote_absolute_path,
			'upload_mode'      => 'local',
			'local_base'       => $base,
		);

		if ( $base === '' ) {
			return $this->attach_debug(
				new \WP_Error(
					'ak_cdn_local_missing',
					__( 'مسیر پایه به‌صورت پوشه محلی قابل نوشتن پیدا نشد.', 'asrekhodro' )
				)
			);
		}

		$relative = $this->absolute_to_relative( $remote_absolute_path );
		$dest     = rtrim( $base, '/' ) . '/' . ltrim( $relative, '/' );
		$dest_dir = dirname( $dest );

		$this->last_debug['ftp_relative']    = $relative;
		$this->last_debug['upload_remote']   = $dest;
		$this->last_debug['ftp_file_path']   = $dest;
		$this->last_debug['pwd_after_base']  = $base;
		$this->last_debug['pwd_after_mkdir'] = $dest_dir;

		if ( ! wp_mkdir_p( $dest_dir ) ) {
			return $this->attach_debug(
				new \WP_Error(
					'ak_cdn_local_mkdir',
					sprintf(
						__( 'ساخت پوشه محلی ناموفق بود: %s', 'asrekhodro' ),
						$dest_dir
					)
				)
			);
		}

		$local_size = filesize( $local_path );
		if ( ! is_int( $local_size ) || $local_size <= 0 ) {
			return $this->attach_debug(
				new \WP_Error( 'ak_cdn_ftp_local', __( 'فایل موقت خالی یا نامعتبر است.', 'asrekhodro' ) )
			);
		}

		if ( ! @copy( $local_path, $dest ) ) {
			return $this->attach_debug(
				new \WP_Error(
					'ak_cdn_local_copy',
					sprintf(
						__( 'کپی محلی فایل ناموفق بود: %s', 'asrekhodro' ),
						$dest
					)
				)
			);
		}

		@chmod( $dest, 0644 );

		$remote_size = filesize( $dest );
		$this->last_debug['local_size']  = $local_size;
		$this->last_debug['remote_size'] = is_int( $remote_size ) ? $remote_size : -1;

		if ( ! is_int( $remote_size ) || $remote_size !== $local_size ) {
			return $this->attach_debug(
				new \WP_Error(
					'ak_cdn_local_verify',
					sprintf(
						__( 'فایل محلی تأیید نشد. مسیر: %1$s — اندازه: %2$d', 'asrekhodro' ),
						$dest,
						is_int( $remote_size ) ? $remote_size : 0
					)
				)
			);
		}

		return true;
	}

	public function test(): bool|\WP_Error {
		$base = self::resolve_local_base( $this->settings );
		$this->last_debug = array(
			'upload_mode' => 'local',
			'local_base'  => $base,
		);

		if ( $base === '' ) {
			return new \WP_Error(
				'ak_cdn_local_missing',
				__( 'مسیر پایه روی دیسک این سرور پیدا نشد یا قابل نوشتن نیست. برای هاست مشترک (وردپرس + CDN روی یک اکانت) مسیر کامل مثل /domains/.../AsreKhodro/Uploaded را وارد کنید.', 'asrekhodro' )
			);
		}

		$probe = trailingslashit( $base ) . '.ak-cdn-local-probe-' . wp_generate_password( 6, false, false );
		$bytes = @file_put_contents( $probe, 'ok' );
		if ( $bytes === false ) {
			return new \WP_Error(
				'ak_cdn_local_write',
				sprintf(
					__( 'پوشه محلی پیدا شد اما قابل نوشتن نیست: %s', 'asrekhodro' ),
					$base
				)
			);
		}
		@unlink( $probe );

		$this->last_debug['probe_ok'] = true;

		return true;
	}

	/**
	 * @return list<string>
	 */
	private static function candidate_bases( string $configured ): array {
		$configured = rtrim( $configured, '/' );
		$candidates = array( $configured );

		if ( ! str_starts_with( $configured, '/' ) ) {
			$candidates[] = '/' . $configured;
		}

		$docroot = '';
		if ( ! empty( $_SERVER['DOCUMENT_ROOT'] ) && is_string( $_SERVER['DOCUMENT_ROOT'] ) ) {
			$docroot = rtrim( str_replace( '\\', '/', $_SERVER['DOCUMENT_ROOT'] ), '/' );
		} elseif ( defined( 'ABSPATH' ) ) {
			$docroot = rtrim( str_replace( '\\', '/', ABSPATH ), '/' );
		}

		if ( $docroot !== '' ) {
			// .../public_html + /AsreKhodro/Uploaded
			if ( preg_match( '#(/AsreKhodro(?:/Uploaded)?)$#', $configured, $match ) ) {
				$candidates[] = $docroot . $match[1];
				$parent       = dirname( $docroot );
				if ( $parent !== '' && $parent !== '.' && $parent !== '/' ) {
					$candidates[] = $parent . $match[1];
				}
			}

			if ( preg_match( '#(/public_html/.+)$#', $configured, $match ) ) {
				$parent = dirname( $docroot );
				if ( str_ends_with( $docroot, '/public_html' ) && $parent !== '/' ) {
					$candidates[] = $parent . $match[1];
				}
			}
		}

		return array_values( array_unique( array_filter( $candidates ) ) );
	}

	private function absolute_to_relative( string $remote_absolute_path ): string {
		$absolute = str_replace( '\\', '/', $remote_absolute_path );
		$base     = trim( (string) ( $this->settings['remote_base_path'] ?? '' ), '/' );

		if ( $base !== '' ) {
			$prefix = '/' . $base . '/';
			$norm   = str_starts_with( $absolute, '/' ) ? $absolute : '/' . $absolute;
			if ( str_starts_with( $norm, $prefix ) ) {
				return ltrim( substr( $norm, strlen( $prefix ) ), '/' );
			}
		}

		// Fall back: path relative to resolved local base suffix.
		$local_base = self::resolve_local_base( $this->settings );
		if ( $local_base !== '' && str_starts_with( $absolute, rtrim( $local_base, '/' ) . '/' ) ) {
			return ltrim( substr( $absolute, strlen( rtrim( $local_base, '/' ) ) ), '/' );
		}

		return ltrim( $absolute, '/' );
	}

	private function attach_debug( \WP_Error $error ): \WP_Error {
		$error->add_data( array( 'upload_debug' => $this->last_debug ) );

		return $error;
	}
}
