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

		if ( is_singular( array( 'post', 'ak_magazine', 'ak_video', 'ak_review', 'carsinfo' ) ) ) {
			return;
		}

		if ( is_post_type_archive( 'ak_magazine' ) ) {
			return;
		}

		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$path = '/' . trim( $path, '/' );

		// Legacy mobile site used /Mobile/...; strip so existing redirects apply.
		if ( preg_match( '#^/mobile(/.*)?$#i', $path, $mobile_match ) ) {
			$path = ( isset( $mobile_match[1] ) && $mobile_match[1] !== '' ) ? $mobile_match[1] : '/';
		}

		$legacy_search = self::match_legacy_search( $path, $uri );
		if ( $legacy_search ) {
			wp_safe_redirect( $legacy_search, 301 );
			exit;
		}

		$gallery_id = self::extract_gallery_content_id( $path );
		if ( $gallery_id > 0 ) {
			self::redirect_or_404_by_content_id( $gallery_id, $path, array( 'ak_video', 'post' ) );
			return;
		}

		$legacy_video_id = self::extract_video_route_id( $path );
		if ( $legacy_video_id > 0 ) {
			self::redirect_or_404_by_content_id( $legacy_video_id, $path, array( 'ak_video', 'post' ) );
			return;
		}

		$content_id = self::extract_news_content_id( $path );
		if ( $content_id > 0 ) {
			self::redirect_or_404_by_content_id( $content_id, $path, array( 'post' ) );
			return;
		}

		$kiosk_file_id = self::extract_kiosk_file_id( $path );
		if ( $kiosk_file_id > 0 ) {
			$target = self::find_magazine_url_by_file_id( $kiosk_file_id );
			if ( ! $target ) {
				$target = self::find_url_by_content_id( $kiosk_file_id, array( 'ak_magazine', 'post' ) );
			}
			if ( $target && ! self::paths_match( $path, $target ) ) {
				wp_safe_redirect( $target, 301 );
				exit;
			}
			if ( ! $target ) {
				self::force_not_found();
			}

			return;
		}

		// Cheap pattern redirects only — skip heavy meta probes on normal archives/home.
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

		if ( self::is_normal_wp_front_path( $path ) ) {
			return;
		}

		$redirect = self::match_legacy_redirect_file( $path );
		if ( $redirect ) {
			wp_safe_redirect( $redirect, 301 );
			exit;
		}

		if ( ! self::should_probe_stored_legacy_path( $path ) ) {
			return;
		}

		$legacy_target = self::match_stored_legacy_path( $path, $uri );
		if ( $legacy_target ) {
			wp_safe_redirect( $legacy_target, 301 );
			exit;
		}
	}

	/**
	 * Home, blog index, category/tag/author archives — no stored-legacy meta lookup.
	 */
	private static function is_normal_wp_front_path( string $path ): bool {
		if ( $path === '/' || $path === '' ) {
			return true;
		}

		if ( preg_match( '#^/page/\d+/?$#i', $path ) ) {
			return true;
		}

		if ( preg_match( '#^/(category|tag|author|search|comments|feed)(/|$)#i', $path ) ) {
			return true;
		}

		if ( is_home() || is_front_page() || is_category() || is_tag() || is_author() || is_date() || is_search() ) {
			return true;
		}

		return false;
	}

	/**
	 * Only probe _asrekhodro_legacy_path meta for paths that look legacy-shaped.
	 */
	private static function should_probe_stored_legacy_path( string $path ): bool {
		if ( self::is_normal_wp_front_path( $path ) ) {
			return false;
		}

		// Known modern ID routes already handled above.
		if ( preg_match( '#^/(News|video|review|carsinfo|Home/Kiosk|Home/News|Gallery/Content)/\d+#i', $path ) ) {
			return false;
		}

		return (bool) preg_match( '#^/(Home|News|Gallery|video|Mobile)/#i', $path );
	}

	private static function extract_video_route_id( string $path ): int {
		if ( preg_match( '#^/video/video-(\d+)/?$#i', $path, $matches ) ) {
			return (int) $matches[1];
		}

		if ( preg_match( '#^/video/(\d+)(?:/|$)#i', $path, $matches ) ) {
			return (int) $matches[1];
		}

		return 0;
	}

	private static function extract_gallery_content_id( string $path ): int {
		if ( preg_match( '#^/Gallery/Content/(\d+)(?:/|$)#i', $path, $matches ) ) {
			return (int) $matches[1];
		}

		// ID still present even if path was cut mid-slug.
		if ( preg_match( '#^/Gallery/Content/(\d+)#i', $path, $matches ) ) {
			return (int) $matches[1];
		}

		return 0;
	}

	private static function extract_news_content_id( string $path ): int {
		$patterns = array(
			'#^/News/(\d+)(?:/|$)#i',
			'#^/Home/News/(\d+)(?:/|$)#i',
			'#^/news/(\d+)(?:/|$)#i',
			'#^/News/(\d+)#i',
			'#^/Home/News/(\d+)#i',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $path, $matches ) ) {
				return (int) $matches[1];
			}
		}

		return 0;
	}

	private static function extract_kiosk_file_id( string $path ): int {
		if ( preg_match( '#^/Home/Kiosk/(\d+)(?:/|$)#i', $path, $matches ) ) {
			return (int) $matches[1];
		}

		return 0;
	}

	/**
	 * Prefer typed matches, then any post with the same legacy content id. 301 or real 404.
	 *
	 * @param list<string> $preferred_types Post types to try first, in order.
	 */
	private static function redirect_or_404_by_content_id( int $content_id, string $path, array $preferred_types ): void {
		$target = self::find_url_by_content_id( $content_id, $preferred_types );
		if ( $target ) {
			if ( ! self::paths_match( $path, $target ) ) {
				wp_safe_redirect( $target, 301 );
				exit;
			}

			return;
		}

		self::force_not_found();
	}

	/**
	 * @param list<string> $preferred_types
	 */
	private static function find_url_by_content_id( int $content_id, array $preferred_types = array() ): ?string {
		if ( $content_id <= 0 ) {
			return null;
		}

		$cache_key = 'ak_legacy_cid_v2_' . $content_id . '_' . md5( implode( ',', $preferred_types ) );
		$cached    = wp_cache_get( $cache_key, 'asrekhodro' );
		if ( is_string( $cached ) && $cached !== '' ) {
			return $cached;
		}
		if ( $cached === 0 ) {
			return null;
		}

		$candidates = self::find_posts_by_content_id( $content_id );
		if ( $candidates === array() ) {
			wp_cache_set( $cache_key, 0, 'asrekhodro', HOUR_IN_SECONDS );

			return null;
		}

		$tried = array();
		foreach ( $preferred_types as $type ) {
			foreach ( $candidates as $candidate ) {
				if ( $candidate['type'] !== $type ) {
					continue;
				}
				$tried[ $candidate['id'] ] = true;
				$url = self::url_for_content_post( $candidate['id'] );
				if ( $url ) {
					wp_cache_set( $cache_key, $url, 'asrekhodro', HOUR_IN_SECONDS );

					return $url;
				}
			}
		}

		foreach ( $candidates as $candidate ) {
			if ( isset( $tried[ $candidate['id'] ] ) ) {
				continue;
			}
			$url = self::url_for_content_post( $candidate['id'] );
			if ( $url ) {
				wp_cache_set( $cache_key, $url, 'asrekhodro', HOUR_IN_SECONDS );

				return $url;
			}
		}

		wp_cache_set( $cache_key, 0, 'asrekhodro', HOUR_IN_SECONDS );

		return null;
	}

	/**
	 * @return list<array{id: int, type: string}>
	 */
	private static function find_posts_by_content_id( int $content_id ): array {
		$posts = get_posts(
			array(
				'post_type'              => array( 'post', 'ak_video', 'ak_review', 'ak_magazine', 'carsinfo' ),
				'posts_per_page'         => 20,
				'post_status'            => 'publish',
				'meta_key'               => '_asrekhodro_content_id',
				'meta_value'             => $content_id,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$out = array();
		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post || $post->post_status === 'trash' ) {
				continue;
			}
			$out[] = array(
				'id'   => (int) $post->ID,
				'type' => (string) $post->post_type,
			);
		}

		return $out;
	}

	private static function url_for_content_post( int $post_id ): ?string {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || $post->post_status === 'trash' ) {
			return null;
		}

		$url = null;
		switch ( $post->post_type ) {
			case 'ak_video':
				$url = VideoPermalinks::build_url_for_post( $post_id );
				break;
			case 'post':
				$url = NewsPermalinks::build_url_for_post( $post_id );
				break;
			case 'ak_review':
				$url = ReviewPermalinks::build_url_for_post( $post_id );
				break;
			case 'ak_magazine':
				$url = MagazinePermalinks::build_url_for_post( $post_id );
				break;
			case 'carsinfo':
				$url = CarsinfoPermalinks::build_url_for_post( $post_id );
				break;
		}

		if ( ! is_string( $url ) || $url === '' ) {
			$permalink = get_permalink( $post_id );
			$url       = is_string( $permalink ) && $permalink !== '' ? $permalink : null;
		}

		return $url;
	}

	private static function force_not_found(): void {
		global $wp_query;

		if ( $wp_query instanceof \WP_Query ) {
			$wp_query->set_404();
			$wp_query->posts             = array();
			$wp_query->post_count        = 0;
			$wp_query->found_posts       = 0;
			$wp_query->is_home           = false;
			$wp_query->is_front_page     = false;
			$wp_query->is_singular       = false;
			$wp_query->is_single         = false;
			$wp_query->is_page           = false;
			$wp_query->is_archive        = false;
			$wp_query->queried_object    = null;
			$wp_query->queried_object_id = 0;
		}

		status_header( 404 );
		nocache_headers();
	}

	private static function find_magazine_url_by_file_id( int $file_id ): ?string {
		$cache_key = 'ak_kiosk_' . $file_id;
		$cached    = wp_cache_get( $cache_key, 'asrekhodro' );
		if ( is_string( $cached ) && $cached !== '' ) {
			return $cached;
		}

		$post_id = MagazinePermalinks::resolve_post_id_by_route_id( $file_id );
		if ( $post_id <= 0 ) {
			return null;
		}

		$url = MagazinePermalinks::build_url_for_post( $post_id );
		if ( ! $url ) {
			$url = get_permalink( $post_id );
		}
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

	/**
	 * Old ASP.NET search: /Home/Search?query=... or /Mobile/Home/Search?query=...
	 * → WordPress /?s=...
	 */
	private static function match_legacy_search( string $path, string $full_uri ): ?string {
		if ( ! preg_match( '#^/home/search/?$#i', $path ) ) {
			return null;
		}

		$query = '';
		$qs    = (string) wp_parse_url( $full_uri, PHP_URL_QUERY );
		if ( $qs !== '' ) {
			parse_str( $qs, $params );
			if ( isset( $params['query'] ) && is_string( $params['query'] ) ) {
				$query = trim( wp_unslash( $params['query'] ) );
			} elseif ( isset( $params['q'] ) && is_string( $params['q'] ) ) {
				$query = trim( wp_unslash( $params['q'] ) );
			}
		}

		if ( $query === '' ) {
			return home_url( '/' );
		}

		return add_query_arg( 's', $query, home_url( '/' ) );
	}

	private static function match_legacy_home_category( string $path ): ?string {
		if ( ! preg_match( '#^/home/category/(\d+)(?:/|$)#i', $path, $matches ) ) {
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
