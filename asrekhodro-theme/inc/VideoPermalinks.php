<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VideoPermalinks {

	public const CPT         = 'ak_video';
	public const BASE_SLUG   = 'video';
	public const LEGACY_BASE = 'Gallery/Content';

	public static function init(): void {
		add_action( 'init', array( self::class, 'register_rewrites' ) );
		add_filter( 'query_vars', array( self::class, 'register_query_vars' ) );
		add_filter( 'post_type_link', array( self::class, 'filter_permalink' ), 10, 2 );
		add_action( 'parse_request', array( self::class, 'parse_request' ), 0 );
		add_filter( 'pre_handle_404', array( self::class, 'pre_handle_404' ), 10, 2 );
		add_filter( 'redirect_canonical', array( self::class, 'filter_redirect_canonical' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( self::class, 'filter_insert_post_data' ), 50, 2 );
		add_action( 'init', array( self::class, 'maybe_repair_video_slugs' ), 20 );
		add_action( 'template_redirect', array( self::class, 'maybe_redirect_canonical' ), 2 );
	}

	/**
	 * @param array<int, string> $vars
	 * @return array<int, string>
	 */
	public static function register_query_vars( array $vars ): array {
		$vars[] = 'ak_video_content_id';

		return $vars;
	}

	public static function register_rewrites(): void {
		add_rewrite_tag( '%ak_video_content_id%', '([0-9]+)' );

		add_rewrite_rule(
			'^Gallery/Content/([0-9]+)(?:/.*)?$',
			'index.php?ak_video_content_id=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			'^video/([0-9]+)(?:/.*)?$',
			'index.php?ak_video_content_id=$matches[1]',
			'top'
		);

		if ( ! get_option( 'ak_video_rewrite_long_slug_v2' ) ) {
			flush_rewrite_rules( false );
			update_option( 'ak_video_rewrite_long_slug_v2', 1, false );
		}
	}

	public static function parse_request( \WP $wp ): void {
		$route_id = 0;

		if ( ! empty( $wp->query_vars['ak_video_content_id'] ) ) {
			$route_id = (int) $wp->query_vars['ak_video_content_id'];
		} else {
			$route_id = self::content_id_from_request_uri();
			if ( $route_id > 0 ) {
				$wp->query_vars['ak_video_content_id'] = $route_id;
			}
		}

		if ( $route_id <= 0 ) {
			return;
		}

		$post_id = self::resolve_post_id_by_route_id( $route_id );
		if ( $post_id <= 0 ) {
			return;
		}

		$wp->query_vars['p']         = $post_id;
		$wp->query_vars['post_type'] = self::CPT;
		$wp->query_vars['name']      = '';
		$wp->query_vars['pagename']  = '';
		$wp->query_vars['ak_video']  = '';
		$wp->query_vars['error']     = '';
		unset( $wp->query_vars['page_id'], $wp->query_vars['attachment'], $wp->query_vars['attachment_id'] );
	}

	/**
	 * @param mixed     $preempt
	 * @param \WP_Query $query
	 * @return mixed
	 */
	public static function pre_handle_404( $preempt, $query ) {
		if ( $preempt || ! $query instanceof \WP_Query || ! $query->is_main_query() ) {
			return $preempt;
		}

		$route_id = self::content_id_from_request_uri();
		if ( $route_id <= 0 ) {
			return $preempt;
		}

		$post_id = self::resolve_post_id_by_route_id( $route_id );
		$post    = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post instanceof \WP_Post || $post->post_type !== self::CPT || $post->post_status === 'trash' ) {
			return $preempt;
		}

		$query->posts             = array( $post );
		$query->post_count        = 1;
		$query->found_posts       = 1;
		$query->post              = $post;
		$query->queried_object    = $post;
		$query->queried_object_id = (int) $post->ID;
		$query->is_404            = false;
		$query->is_single         = true;
		$query->is_singular       = true;
		$query->is_page           = false;
		$query->is_archive        = false;
		$query->is_home           = false;

		status_header( 200 );
		nocache_headers();

		return true;
	}

	private static function content_id_from_request_uri(): int {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( $uri === '' ) {
			return 0;
		}

		$path = '/' . ltrim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' );

		if ( preg_match( '#/(?:Gallery/Content|video)/(\d+)(?:/|$)#i', $path, $matches ) ) {
			return (int) $matches[1];
		}

		if ( preg_match( '#/(?:Gallery/Content|video)/(\d+)#i', $path, $matches ) ) {
			return (int) $matches[1];
		}

		return 0;
	}

	/**
	 * @param mixed $redirect_url
	 */
	public static function filter_redirect_canonical( $redirect_url, string $requested_url ) {
		if ( preg_match( '#/(?:Gallery/Content|video)/\d+#i', $requested_url ) ) {
			return false;
		}

		return $redirect_url;
	}

	public static function filter_permalink( string $permalink, \WP_Post $post ): string {
		if ( $post->post_type !== self::CPT ) {
			return $permalink;
		}

		$url = self::build_url_for_post( (int) $post->ID );

		return $url ?? $permalink;
	}

	/**
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $postarr
	 * @return array<string, mixed>
	 */
	public static function filter_insert_post_data( array $data, array $postarr ): array {
		if ( ( $data['post_type'] ?? '' ) !== self::CPT ) {
			return $data;
		}

		$status = (string) ( $data['post_status'] ?? '' );
		if ( $status === 'auto-draft' ) {
			return $data;
		}

		$title = trim( (string) ( $data['post_title'] ?? '' ) );
		if ( $title === '' ) {
			return $data;
		}

		$desired = NewsPermalinks::slug_from_title( $title );
		if ( $desired === '' ) {
			$post_id = (int) ( $postarr['ID'] ?? 0 );
			$desired = $post_id > 0 ? 'video-' . $post_id : 'video';
		}

		if ( $desired === '' ) {
			return $data;
		}

		$post_id           = (int) ( $postarr['ID'] ?? 0 );
		$data['post_name'] = NewsPermalinks::unique_post_slug( $desired, $post_id, self::CPT );

		return $data;
	}

	public static function build_legacy_path( int $content_id, string $slug ): string {
		return '/' . self::LEGACY_BASE . '/' . $content_id . '/' . trim( $slug, '/' );
	}

	public static function route_id_for_post( int $post_id ): int {
		$content_id = (int) get_post_meta( $post_id, '_asrekhodro_content_id', true );

		return $content_id > 0 ? $content_id : max( 0, $post_id );
	}

	public static function resolve_post_id_by_route_id( int $route_id ): int {
		if ( $route_id <= 0 ) {
			return 0;
		}

		$by_meta = self::find_post_id_by_content_id( $route_id );
		if ( $by_meta > 0 ) {
			return $by_meta;
		}

		$post = get_post( $route_id );
		if ( $post instanceof \WP_Post && $post->post_type === self::CPT && $post->post_status !== 'trash' ) {
			return (int) $post->ID;
		}

		return 0;
	}

	public static function build_url( int $route_id, string $slug ): string {
		return home_url( user_trailingslashit( self::BASE_SLUG . '/' . max( 0, $route_id ) . '/' . $slug ) );
	}

	public static function build_url_for_post( int $post_id ): ?string {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || $post->post_type !== self::CPT ) {
			return null;
		}

		$slug = self::resolve_slug( $post );
		if ( $slug === '' ) {
			return null;
		}

		$route_id = self::route_id_for_post( $post_id );
		if ( $route_id <= 0 ) {
			return null;
		}

		return self::build_url( $route_id, $slug );
	}

	public static function resolve_slug( \WP_Post $post ): string {
		$from_title = NewsPermalinks::slug_from_title( $post->post_title );
		$slug       = (string) $post->post_name;

		if ( $from_title !== '' ) {
			if (
				$slug === ''
				|| preg_match( '/^video-\d+$/', $slug )
				|| str_contains( $slug, '%' )
				|| ( $slug !== $from_title && str_starts_with( $from_title, $slug ) )
			) {
				return $from_title;
			}
		}

		if ( $slug !== '' ) {
			return $slug;
		}

		return $from_title !== '' ? $from_title : 'video-' . $post->ID;
	}

	public static function unique_video_slug( string $title, int $post_id, int $content_id ): string {
		$slug = NewsPermalinks::slug_from_title( $title );
		if ( $slug === '' ) {
			$slug = 'video-' . $content_id;
		}

		return NewsPermalinks::unique_post_slug( $slug, $post_id, self::CPT );
	}

	public static function sync_post( int $post_id, int $content_id, string $title ): string {
		$slug = self::unique_video_slug( $title, $post_id, $content_id );
		NewsPermalinks::write_post_name( $post_id, $slug );
		update_post_meta( $post_id, '_asrekhodro_legacy_path', self::build_legacy_path( $content_id, $slug ) );
		clean_post_cache( $post_id );

		return $slug;
	}

	public static function maybe_repair_video_slugs(): void {
		if ( get_option( 'ak_video_slugs_repaired_v1' ) ) {
			return;
		}

		$count = self::repair_imported_video_slugs();
		update_option( 'ak_video_slugs_repaired_v1', 1, false );

		if ( $count > 0 ) {
			flush_rewrite_rules( false );
		}
	}

	public static function repair_imported_video_slugs(): int {
		$posts = get_posts(
			array(
				'post_type'      => self::CPT,
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'meta_query'     => array(
					array(
						'key'     => '_asrekhodro_content_id',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		if ( $posts === array() ) {
			return 0;
		}

		$count = 0;

		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$content_id = (int) get_post_meta( $post->ID, '_asrekhodro_content_id', true );
			if ( $content_id <= 0 ) {
				continue;
			}

			if ( ! preg_match( '/^video-' . $content_id . '$/', $post->post_name ) ) {
				continue;
			}

			self::sync_post( (int) $post->ID, $content_id, $post->post_title );
			++$count;
		}

		return $count;
	}

	public static function find_post_id_by_content_id( int $content_id ): int {
		if ( $content_id <= 0 ) {
			return 0;
		}

		$posts = get_posts(
			array(
				'post_type'      => self::CPT,
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'meta_key'       => '_asrekhodro_content_id',
				'meta_value'     => $content_id,
				'fields'         => 'ids',
			)
		);

		return ! empty( $posts ) ? (int) $posts[0] : 0;
	}

	public static function maybe_redirect_canonical(): void {
		if ( ! is_singular( self::CPT ) ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$target = self::build_url_for_post( (int) $post->ID );
		if ( ! $target ) {
			return;
		}

		$request_path = self::current_request_path();
		$target_path  = wp_parse_url( $target, PHP_URL_PATH );
		if ( ! is_string( $target_path ) || $target_path === '' ) {
			return;
		}

		// Accept both new /video/{id}/... and legacy /Gallery/Content/{id}/... as valid.
		$route_id = self::route_id_for_post( (int) $post->ID );
		if ( $route_id > 0 ) {
			$content_id    = (int) get_post_meta( (int) $post->ID, '_asrekhodro_content_id', true );
			$legacy_prefix = $content_id > 0 ? '/' . self::LEGACY_BASE . '/' . $content_id : '';
			$new_prefix    = '/' . self::BASE_SLUG . '/' . $route_id;
			$req           = untrailingslashit( $request_path );
			if (
				( $legacy_prefix !== '' && ( $req === untrailingslashit( $legacy_prefix ) || str_starts_with( $req . '/', $legacy_prefix . '/' ) ) )
				|| $req === untrailingslashit( $new_prefix )
				|| str_starts_with( $req . '/', $new_prefix . '/' )
			) {
				if ( $legacy_prefix !== '' && str_starts_with( strtolower( $req . '/' ), strtolower( $legacy_prefix . '/' ) ) ) {
					if ( untrailingslashit( $request_path ) !== untrailingslashit( $target_path ) ) {
						wp_safe_redirect( $target, 301 );
						exit;
					}
				}

				return;
			}
		}

		if ( untrailingslashit( $request_path ) === untrailingslashit( $target_path ) ) {
			return;
		}

		wp_safe_redirect( $target, 301 );
		exit;
	}

	private static function current_request_path(): string {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );

		return '/' . trim( $path, '/' );
	}
}
