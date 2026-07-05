<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VideoPermalinks {

	public const CPT          = 'ak_video';
	public const BASE_SLUG    = 'video';
	public const LEGACY_BASE  = 'Gallery/Content';

	public static function init(): void {
		add_filter( 'post_type_link', array( self::class, 'filter_permalink' ), 10, 2 );
		add_action( 'init', array( self::class, 'maybe_repair_video_slugs' ), 20 );
		add_action( 'template_redirect', array( self::class, 'maybe_redirect_canonical' ), 2 );
	}

	public static function filter_permalink( string $permalink, \WP_Post $post ): string {
		if ( $post->post_type !== self::CPT ) {
			return $permalink;
		}

		$url = self::build_url_for_post( (int) $post->ID );

		return $url ?? $permalink;
	}

	public static function build_legacy_path( int $content_id, string $slug ): string {
		return '/' . self::LEGACY_BASE . '/' . $content_id . '/' . trim( $slug, '/' );
	}

	public static function build_url( string $slug ): string {
		return home_url( user_trailingslashit( self::BASE_SLUG . '/' . $slug ) );
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

		return self::build_url( $slug );
	}

	public static function resolve_slug( \WP_Post $post ): string {
		$slug = (string) $post->post_name;

		if ( $slug === '' || preg_match( '/^video-\d+$/', $slug ) ) {
			$from_title = NewsPermalinks::slug_from_title( $post->post_title );
			if ( $from_title !== '' ) {
				return $from_title;
			}
		}

		return $slug !== '' ? $slug : 'video-' . $post->ID;
	}

	public static function unique_video_slug( string $title, int $post_id, int $content_id ): string {
		$slug = NewsPermalinks::slug_from_title( $title );
		if ( $slug === '' ) {
			$slug = 'video-' . $content_id;
		}

		return wp_unique_post_slug( $slug, $post_id, 'publish', self::CPT, 0 );
	}

	public static function sync_post( int $post_id, int $content_id, string $title ): string {
		$slug = self::unique_video_slug( $title, $post_id, $content_id );

		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => $slug,
			)
		);

		update_post_meta( $post_id, '_asrekhodro_legacy_path', self::build_legacy_path( $content_id, $slug ) );

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

		if ( untrailingslashit( $request_path ) === untrailingslashit( $target_path ) ) {
			return;
		}

		wp_safe_redirect( $target, 301 );
		exit;
	}

	private static function current_request_path(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );

		return '/' . trim( $path, '/' );
	}
}
