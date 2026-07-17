<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LegacyRedirects {

	public static function init(): void {
		add_action( 'template_redirect', array( self::class, 'maybe_redirect' ), 1 );
	}

	public static function maybe_redirect(): void {
		if ( is_admin() ) {
			return;
		}

		if ( is_singular( 'ak_magazine' ) || is_post_type_archive( 'ak_magazine' ) ) {
			return;
		}

		if ( is_singular( 'post' ) ) {
			return;
		}

		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$path = '/' . trim( $path, '/' );

		// Legacy mobile site used /Mobile/...; strip so existing redirects apply.
		if ( preg_match( '#^/mobile(/.*)?$#i', $path, $mobile_match ) ) {
			$path = ( isset( $mobile_match[1] ) && $mobile_match[1] !== '' ) ? $mobile_match[1] : '/';
		}

		$gallery_id = self::extract_gallery_content_id( $path );
		if ( $gallery_id > 0 ) {
			$target = self::find_video_url_by_content_id( $gallery_id );
			if ( $target && ! self::paths_match( $path, $target ) ) {
				wp_safe_redirect( $target, 301 );
				exit;
			}

			return;
		}

		$legacy_video_id = self::extract_legacy_video_slug_id( $path );
		if ( $legacy_video_id > 0 ) {
			$target = self::find_video_url_by_content_id( $legacy_video_id );
			if ( $target && ! self::paths_match( $path, $target ) ) {
				wp_safe_redirect( $target, 301 );
				exit;
			}
		}

		$content_id = self::extract_news_content_id( $path );
		if ( $content_id > 0 ) {
			$target = self::find_post_url_by_content_id( $content_id );
			if ( $target && ! self::paths_match( $path, $target ) ) {
				wp_safe_redirect( $target, 301 );
				exit;
			}
		}

		$kiosk_file_id = self::extract_kiosk_file_id( $path );
		if ( $kiosk_file_id > 0 ) {
			$target = self::find_magazine_url_by_file_id( $kiosk_file_id );
			if ( $target ) {
				wp_safe_redirect( $target, 301 );
				exit;
			}
		}

		$redirect = self::match_legacy_redirect_file( $path );
		if ( $redirect ) {
			wp_safe_redirect( $redirect, 301 );
			exit;
		}

		$legacy_target = self::match_stored_legacy_path( $path, $uri );
		if ( $legacy_target ) {
			wp_safe_redirect( $legacy_target, 301 );
			exit;
		}

		$legacy_category = self::match_legacy_category_slug( $path );
		if ( $legacy_category ) {
			wp_safe_redirect( $legacy_category, 301 );
			exit;
		}

		$legacy_home_category = self::match_legacy_home_category( $path );
		if ( $legacy_home_category ) {
			wp_safe_redirect( $legacy_home_category, 301 );
			exit;
		}
	}

	private static function extract_legacy_video_slug_id( string $path ): int {
		if ( preg_match( '#^/video/video-(\d+)/?$#i', $path, $matches ) ) {
			return (int) $matches[1];
		}

		return 0;
	}

	private static function extract_gallery_content_id( string $path ): int {
		if ( preg_match( '#^/Gallery/Content/(\d+)(?:/|$)#i', $path, $matches ) ) {
			return (int) $matches[1];
		}

		return 0;
	}

	private static function extract_news_content_id( string $path ): int {
		$patterns = array(
			'#^/News/(\d+)(?:/|$)#i',
			'#^/Home/News/(\d+)(?:/|$)#i',
			'#^/news/(\d+)(?:/|$)#i',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $path, $matches ) ) {
				return (int) $matches[1];
			}
		}

		return 0;
	}

	private static function extract_kiosk_file_id( string $path ): int {
		if ( preg_match( '#^/Home/Kiosk/(\d+)/?$#i', $path, $matches ) ) {
			return (int) $matches[1];
		}

		return 0;
	}

	private static function find_magazine_url_by_file_id( int $file_id ): ?string {
		$cache_key = 'ak_kiosk_' . $file_id;
		$cached    = wp_cache_get( $cache_key, 'asrekhodro' );
		if ( is_string( $cached ) && $cached !== '' ) {
			return $cached;
		}

		$posts = get_posts(
			array(
				'post_type'      => 'ak_magazine',
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'meta_key'       => '_asrekhodro_file_id',
				'meta_value'     => $file_id,
				'fields'         => 'ids',
			)
		);

		if ( empty( $posts ) ) {
			return null;
		}

		$url = get_permalink( $posts[0] );
		if ( ! $url ) {
			return null;
		}

		wp_cache_set( $cache_key, $url, 'asrekhodro', HOUR_IN_SECONDS );

		return $url;
	}

	private static function find_video_url_by_content_id( int $content_id ): ?string {
		$cache_key = 'ak_legacy_video_' . $content_id;
		$cached    = wp_cache_get( $cache_key, 'asrekhodro' );
		if ( is_string( $cached ) && $cached !== '' ) {
			return $cached;
		}

		$post_id = VideoPermalinks::find_post_id_by_content_id( $content_id );
		if ( $post_id <= 0 ) {
			return null;
		}

		$url = VideoPermalinks::build_url_for_post( $post_id );
		if ( ! $url ) {
			$url = get_permalink( $post_id );
		}

		if ( ! $url ) {
			return null;
		}

		wp_cache_set( $cache_key, $url, 'asrekhodro', HOUR_IN_SECONDS );

		return $url;
	}

	private static function find_post_url_by_content_id( int $content_id ): ?string {
		$cache_key = 'ak_legacy_' . $content_id;
		$cached    = wp_cache_get( $cache_key, 'asrekhodro' );
		if ( is_string( $cached ) && $cached !== '' ) {
			return $cached;
		}

		$posts = get_posts(
			array(
				'post_type'      => 'any',
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'meta_key'       => '_asrekhodro_content_id',
				'meta_value'     => $content_id,
				'fields'         => 'ids',
			)
		);

		if ( empty( $posts ) ) {
			return null;
		}

		$url = get_permalink( $posts[0] );
		if ( ! $url ) {
			return null;
		}

		wp_cache_set( $cache_key, $url, 'asrekhodro', HOUR_IN_SECONDS );

		return $url;
	}

	private static function match_legacy_redirect_file( string $path ): ?string {
		$map = self::load_legacy_redirect_map();
		if ( $map === array() ) {
			return null;
		}

		$key = self::redirect_lookup_key( $path );
		if ( ! isset( $map[ $key ] ) ) {
			return null;
		}

		return self::redirect_target_url( (string) $map[ $key ] );
	}

	/**
	 * @return array<string, string>
	 */
	private static function load_legacy_redirect_map(): array {
		static $loaded = false;
		static $map    = array();

		if ( $loaded ) {
			return $map;
		}

		$loaded = true;
		$path   = ASREKHODRO_THEME_DIR . '/legacy-redirects.json';
		if ( ! is_file( $path ) ) {
			return $map;
		}

		$data = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $data ) ) {
			return $map;
		}

		foreach ( $data as $old => $new ) {
			if ( ! is_string( $old ) || ! is_string( $new ) || $new === '' ) {
				continue;
			}

			$map[ self::redirect_lookup_key( $old ) ] = $new;
		}

		return $map;
	}

	private static function redirect_lookup_key( string $path ): string {
		$path = (string) wp_parse_url( $path, PHP_URL_PATH );
		$path = '/' . trim( $path, '/' );

		return strtolower( $path );
	}

	private static function redirect_target_url( string $target ): string {
		if ( preg_match( '#^https?://#i', $target ) ) {
			return $target;
		}

		return user_trailingslashit( home_url( $target ) );
	}

	private static function match_stored_legacy_path( string $path, string $full_uri ): ?string {
		$legacy_path = get_transient( 'ak_legacy_path_' . md5( $path ) );
		if ( is_string( $legacy_path ) && $legacy_path !== '' ) {
			return $legacy_path;
		}

		$posts = get_posts(
			array(
				'post_type'      => 'any',
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'meta_key'       => '_asrekhodro_legacy_path',
				'meta_value'     => $path,
				'fields'         => 'ids',
			)
		);

		if ( empty( $posts ) ) {
			$posts = get_posts(
				array(
					'post_type'      => 'any',
					'posts_per_page' => 1,
					'post_status'    => 'publish',
					'meta_key'       => '_asrekhodro_old_page_url',
					'meta_value'     => $full_uri,
					'fields'         => 'ids',
				)
			);
		}

		if ( empty( $posts ) ) {
			return null;
		}

		$url = get_permalink( $posts[0] );
		if ( $url ) {
			set_transient( 'ak_legacy_path_' . md5( $path ), $url, DAY_IN_SECONDS );
		}

		return $url ?: null;
	}

	private static function paths_match( string $request_path, string $target_url ): bool {
		$target_path = wp_parse_url( $target_url, PHP_URL_PATH );
		if ( ! is_string( $target_path ) || $target_path === '' ) {
			return false;
		}

		return untrailingslashit( $request_path ) === untrailingslashit( $target_path );
	}

	private static function match_legacy_category_slug( string $path ): ?string {
		if ( ! preg_match( '#^/category/cat-(\d+)/?$#i', $path, $matches ) ) {
			return null;
		}

		$old_id = (int) $matches[1];
		if ( $old_id <= 0 ) {
			return null;
		}

		$cache_key = 'ak_legacy_cat_' . $old_id;
		$cached    = wp_cache_get( $cache_key, 'asrekhodro' );
		if ( is_string( $cached ) && $cached !== '' ) {
			return $cached;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'number'     => 1,
				'meta_query' => array(
					array(
						'key'   => '_asrekhodro_category_id',
						'value' => $old_id,
					),
				),
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return null;
		}

		$link = get_term_link( $terms[0] );
		if ( is_wp_error( $link ) || ! is_string( $link ) || $link === '' ) {
			return null;
		}

		wp_cache_set( $cache_key, $link, 'asrekhodro', HOUR_IN_SECONDS );

		return $link;
	}

	private static function match_legacy_home_category( string $path ): ?string {
		if ( ! preg_match( '#^/home/category/(\d+)/?$#i', $path, $matches ) ) {
			return null;
		}

		$old_id = (int) $matches[1];
		if ( $old_id <= 0 ) {
			return null;
		}

		$map = self::load_legacy_redirect_map();
		$key = self::redirect_lookup_key( '/Home/Category/' . $old_id );
		if ( isset( $map[ $key ] ) ) {
			return self::redirect_target_url( (string) $map[ $key ] );
		}

		$cache_key = 'ak_legacy_home_cat_' . $old_id;
		$cached    = wp_cache_get( $cache_key, 'asrekhodro' );
		if ( is_string( $cached ) && $cached !== '' ) {
			return $cached;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'number'     => 1,
				'meta_query' => array(
					array(
						'key'   => '_asrekhodro_category_id',
						'value' => $old_id,
					),
				),
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return null;
		}

		$link = get_term_link( $terms[0] );
		if ( is_wp_error( $link ) || ! is_string( $link ) || $link === '' ) {
			return null;
		}

		wp_cache_set( $cache_key, $link, 'asrekhodro', HOUR_IN_SECONDS );

		return $link;
	}
}
