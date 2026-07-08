<?php

namespace AsreKhodro\Theme\CdnServer;

use AsreKhodro\Theme\PersianDate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds remote upload paths and public URLs.
 *
 * Paths follow the legacy CMS layout, e.g.
 * Uploaded/Image/1404/07/08/140407081430521234567890.jpg
 *
 * Edit tokens in defaults.php or override helpers here.
 */
final class PathBuilder {

	/**
	 * Canonical web path below public_base_url.
	 * Always: Uploaded/{type}/{Y}/{m}/{d}/{basename}.{ext}
	 */
	public static function build_canonical_relative_path( string $original_filename, string $mime_type ): string {
		return self::build_path_from_pattern( $original_filename, $mime_type, Config::path_pattern() );
	}

	/**
	 * Path relative to remote_base_path for FTP upload.
	 *
	 * When remote_base_path already ends with "Uploaded" (e.g. /AsreKhodro/Uploaded),
	 * the Uploaded/ prefix is stripped to avoid Uploaded/Uploaded/...
	 */
	public static function build_ftp_relative_path( string $original_filename, string $mime_type ): string {
		$canonical = self::build_canonical_relative_path( $original_filename, $mime_type );

		return self::ftp_relative_from_canonical( $canonical );
	}

	/**
	 * @deprecated Use build_canonical_relative_path() or build_ftp_relative_path().
	 */
	public static function build_remote_relative_path( string $original_filename, string $mime_type ): string {
		return self::build_ftp_relative_path( $original_filename, $mime_type );
	}

	/**
	 * Absolute remote path on the server (remote_base_path + FTP-relative).
	 */
	public static function build_remote_absolute_path( string $ftp_relative ): string {
		$base = rtrim( Config::remote_base_path(), '/' );

		return $base . '/' . ltrim( $ftp_relative, '/' );
	}

	/**
	 * Public URL — always uses the canonical path under public_base_url.
	 */
	public static function build_public_url( string $canonical_relative ): string {
		return Config::get_public_base_url() . '/' . ltrim( $canonical_relative, '/' );
	}

	/**
	 * Strip a duplicate Uploaded/ prefix when FTP base already includes Uploaded.
	 */
	public static function ftp_relative_from_canonical( string $canonical ): string {
		$canonical = ltrim( $canonical, '/' );
		$base      = trim( Config::remote_base_path(), '/' );

		if ( $base === '' || ! str_starts_with( strtolower( $canonical ), 'uploaded/' ) ) {
			return $canonical;
		}

		$base_lower = strtolower( $base );
		if ( str_ends_with( $base_lower, '/uploaded' ) || $base_lower === 'uploaded' ) {
			return substr( $canonical, strlen( 'Uploaded/' ) );
		}

		return $canonical;
	}

	private static function build_path_from_pattern( string $original_filename, string $mime_type, string $pattern ): string {
		$ext      = self::extension( $original_filename, $mime_type );
		$jalali   = PersianDate::now_jalali_parts();
		$timezone = wp_timezone();
		$now      = new \DateTimeImmutable( 'now', $timezone );

		$year  = sprintf( '%04d', (int) $jalali['year'] );
		$month = sprintf( '%02d', (int) $jalali['month'] );
		$day   = sprintf( '%02d', (int) $jalali['day'] );

		$replacements = array(
			'{type}'     => self::type_folder( $mime_type ),
			'{Y}'        => $year,
			'{m}'        => $month,
			'{d}'        => $day,
			'{basename}' => self::legacy_basename( $year, $month, $day, $now ),
			'{name}'     => self::sanitized_name( $original_filename ),
			'{rand}'     => self::random_suffix(),
			'{ext}'      => $ext,
		);

		$path = strtr( $pattern, $replacements );
		$path = preg_replace( '#/+#', '/', $path ) ?? $path;

		return ltrim( $path, '/' );
	}

	/**
	 * Legacy-style unique filename (without extension).
	 *
	 * Example: 140103091703371832932063 → {Y}{m}{d}{His}{random10}
	 */
	private static function legacy_basename( string $year, string $month, string $day, \DateTimeImmutable $now ): string {
		$time_part   = $now->format( 'His' );
		$random_part = (string) wp_rand( 1_000_000_000, 9_999_999_999 );

		return $year . $month . $day . $time_part . $random_part;
	}

	private static function type_folder( string $mime_type ): string {
		$mime_type = strtolower( $mime_type );

		if ( str_starts_with( $mime_type, 'image/' ) ) {
			return 'Image';
		}
		if ( str_starts_with( $mime_type, 'video/' ) ) {
			return 'Video';
		}
		if ( str_starts_with( $mime_type, 'audio/' ) ) {
			return 'Audio';
		}

		return 'File';
	}

	private static function sanitized_name( string $filename ): string {
		$base = pathinfo( $filename, PATHINFO_FILENAME );
		$base = sanitize_title( $base );

		return $base !== '' ? $base : 'file';
	}

	private static function extension( string $filename, string $mime_type ): string {
		$ext = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( $ext !== '' ) {
			return preg_replace( '/[^a-z0-9]/', '', $ext ) ?? $ext;
		}

		return self::extension_from_mime( $mime_type );
	}

	private static function extension_from_mime( string $mime_type ): string {
		return match ( strtolower( $mime_type ) ) {
			'image/jpeg'    => 'jpg',
			'image/png'     => 'png',
			'image/gif'     => 'gif',
			'image/webp'    => 'webp',
			'image/svg+xml' => 'svg',
			'video/mp4'     => 'mp4',
			'video/webm'    => 'webm',
			'audio/mpeg'    => 'mp3',
			default         => 'bin',
		};
	}

	private static function random_suffix(): string {
		$length = Config::rand_length();

		if ( function_exists( 'wp_generate_password' ) ) {
			return strtolower( wp_generate_password( $length, false, false ) );
		}

		return substr( md5( uniqid( '', true ) ), 0, $length );
	}
}
