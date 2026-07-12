<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carsinfo single template with fixed 3D background (page-carinfo3d).
 */
final class CarInfo3d {

	public const TEMPLATE = 'page-carinfo3d.php';

	public const TEMPLATE_3D2 = 'page-carinfo3d2.php';

	public const TEMPLATE_2D = 'page-carinfo2d.php';

	public static function init(): void {
		add_filter( 'body_class', array( self::class, 'body_class' ) );
		add_filter( 'timber/context', array( self::class, 'filter_timber_context' ) );
		add_filter( 'default_page_template', array( self::class, 'default_page_template' ), 10, 3 );
		add_action( 'wp_insert_post', array( self::class, 'assign_default_template_on_create' ), 10, 3 );
		add_action( 'wp_head', array( self::class, 'print_import_map' ), 1 );
		add_filter( 'script_loader_tag', array( self::class, 'script_loader_tag' ), 10, 3 );
		add_action( 'wp_ajax_ak_carinfo3d_ad_image', array( self::class, 'proxy_ad_image' ) );
		add_action( 'wp_ajax_nopriv_ak_carinfo3d_ad_image', array( self::class, 'proxy_ad_image' ) );
	}

	/**
	 * Same-origin proxy so WebGL can sample external ad images (e.g. media.asrekhodro.com).
	 */
	public static function texture_image_url( string $url ): string {
		$url = esc_url_raw( $url );
		if ( $url === '' ) {
			return '';
		}

		if ( self::is_same_origin_url( $url ) ) {
			return $url;
		}

		if ( ! self::is_allowed_ad_image_url( $url ) ) {
			return $url;
		}

		return add_query_arg(
			array(
				'action' => 'ak_carinfo3d_ad_image',
				'src'    => $url,
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	public static function proxy_ad_image(): void {
		$raw = isset( $_GET['src'] ) ? wp_unslash( $_GET['src'] ) : '';
		$url = is_string( $raw ) ? esc_url_raw( $raw ) : '';

		if ( $url === '' || ! self::is_allowed_ad_image_url( $url ) ) {
			status_header( 403 );
			exit;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 20,
				'user-agent' => 'AsreKhodroTheme/1.0',
			)
		);

		if ( is_wp_error( $response ) ) {
			status_header( 502 );
			exit;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			status_header( $code > 0 ? $code : 502 );
			exit;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( $body === '' ) {
			status_header( 404 );
			exit;
		}

		$type = wp_remote_retrieve_header( $response, 'content-type' );
		if ( ! is_string( $type ) || $type === '' ) {
			$type = 'image/jpeg';
		}

		$type = strtok( $type, ';' ) ?: 'image/jpeg';

		header( 'Content-Type: ' . $type );
		header( 'Cache-Control: public, max-age=86400' );
		header( 'Access-Control-Allow-Origin: *' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $body;
		exit;
	}

	public static function is_allowed_ad_image_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return false;
		}

		$host = strtolower( (string) $parts['host'] );

		if ( self::is_same_origin_url( $url ) ) {
			return true;
		}

		if ( str_ends_with( $host, '.asrekhodro.com' ) || $host === 'asrekhodro.com' ) {
			return true;
		}

		return false;
	}

	private static function is_same_origin_url( string $url ): bool {
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$url_host  = wp_parse_url( $url, PHP_URL_HOST );

		if ( ! is_string( $site_host ) || $site_host === '' || ! is_string( $url_host ) || $url_host === '' ) {
			return false;
		}

		return strtolower( $url_host ) === strtolower( $site_host );
	}

	/**
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>
	 */
	public static function filter_timber_context( array $context ): array {
		$post = $context['post'] ?? null;
		if ( ! $post instanceof \Timber\Post ) {
			return $context;
		}

		if ( self::is_2d_template( (int) $post->ID ) ) {
			$context['carsinfo_content'] = self::render_content_without_3dmodel( $post );

			return $context;
		}

		if ( ! self::is_3d_template() ) {
			return $context;
		}

		$context['carinfo3d_ads_json'] = self::collect_ads_json_from_post( $post );

		return $context;
	}

	public static function template_slug( ?int $post_id = null ): string {
		if ( $post_id === null ) {
			$post_id = get_queried_object_id();
		}

		if ( $post_id <= 0 ) {
			return '';
		}

		$slug = (string) get_page_template_slug( $post_id );
		if ( $slug === '' ) {
			return '';
		}

		return basename( str_replace( '\\', '/', $slug ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function context_for_2d( ?\Timber\Post $post = null ): array {
		$post = $post ?? \Timber\Timber::get_post();
		if ( ! $post instanceof \Timber\Post ) {
			return \Timber\Timber::context();
		}

		$context                     = \Timber\Timber::context();
		$context['post']             = $post;
		$context['carsinfo_content'] = self::render_content_without_3dmodel( $post );

		return array_merge( $context, PageLayout::for_page( 'carsinfo_2d' ) );
	}

	public static function is_2d_template( ?int $post_id = null ): bool {
		return self::template_slug( $post_id ) === self::TEMPLATE_2D;
	}

	/**
	 * Default carsinfo template on new post screen only (auto-draft).
	 */
	public static function default_page_template( string $template, string $post_type, $post = null ): string {
		if ( $post_type !== 'carsinfo' ) {
			return $template;
		}

		if ( ! $post instanceof \WP_Post || $post->post_status !== 'auto-draft' ) {
			return $template;
		}

		return self::TEMPLATE_2D;
	}

	/**
	 * Persist 2D template when WordPress creates a new carsinfo auto-draft.
	 */
	public static function assign_default_template_on_create( int $post_id, \WP_Post $post, bool $update ): void {
		if ( $update || $post->post_type !== 'carsinfo' ) {
			return;
		}

		if ( get_post_meta( $post_id, '_wp_page_template', true ) !== '' ) {
			return;
		}

		update_post_meta( $post_id, '_wp_page_template', self::TEMPLATE_2D );
	}

	public static function is_3d_template( ?int $post_id = null ): bool {
		if ( ! is_singular( 'carsinfo' ) && $post_id === null ) {
			return false;
		}

		return in_array(
			self::template_slug( $post_id ),
			array( self::TEMPLATE, self::TEMPLATE_3D2 ),
			true
		);
	}

	public static function is_3d2( ?int $post_id = null ): bool {
		return self::template_slug( $post_id ) === self::TEMPLATE_3D2;
	}

	public static function is_immersive( ?int $post_id = null ): bool {
		return self::template_slug( $post_id ) === self::TEMPLATE;
	}

	public static function collect_ads_json_from_post( \Timber\Post $post ): string {
		$content = (string) $post->post_content;
		if ( $content === '' || ! has_blocks( $content ) ) {
			return '';
		}

		$all_items = array();
		$interval  = 5;

		foreach ( parse_blocks( $content ) as $block ) {
			if ( (string) ( $block['blockName'] ?? '' ) !== 'acf/cinfo-ads' ) {
				continue;
			}

			$fields = self::acf_block_fields_from_parsed( $block );
			$ctx    = \AsreKhodro\Theme\AcfBlocks\CinfoAds\View::context( $fields );

			if ( empty( $ctx['ads_items'] ) || ! is_array( $ctx['ads_items'] ) ) {
				continue;
			}

			$all_items = array_merge( $all_items, $ctx['ads_items'] );
			$interval  = (int) ( $ctx['ads_rotation_interval'] ?? $interval );
		}

		if ( $all_items === array() ) {
			return '';
		}

		return (string) wp_json_encode(
			array(
				'items'    => $all_items,
				'interval' => $interval,
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
	}

	/**
	 * @param array<string, mixed> $block
	 * @return array<string, mixed>
	 */
	private static function acf_block_fields_from_parsed( array $block ): array {
		if ( ! function_exists( 'get_fields' ) || ! function_exists( 'acf_setup_meta' ) ) {
			return array();
		}

		$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		$data  = is_array( $attrs['data'] ?? null ) ? $attrs['data'] : array();
		$id    = (string) ( $attrs['id'] ?? 'cinfo-ads-block' );

		if ( $data === array() ) {
			return array();
		}

		acf_setup_meta( $data, $id, true );
		$fields = get_fields() ?: array();
		acf_reset_meta( $id );

		return is_array( $fields ) ? $fields : array();
	}

	public static function is_active(): bool {
		return self::is_3d_template();
	}

	/**
	 * @param array<int, string> $classes
	 * @return array<int, string>
	 */
	public static function body_class( array $classes ): array {
		if ( self::is_3d_template() ) {
			$classes[] = 'carinfo3d-page';
		}

		if ( self::is_3d2() ) {
			$classes[] = 'carinfo3d2-page';

			if ( self::layout_has_sidebar( 'carsinfo_3d2' ) ) {
				$classes[] = 'carinfo3d2-page--has-sidebar';
			}
		}

		if ( self::is_2d_template() ) {
			$classes[] = 'carinfo2d-page';

			if ( self::layout_has_sidebar( 'carsinfo_2d' ) ) {
				$classes[] = 'carinfo2d-page--has-sidebar';
			}
		}

		return $classes;
	}

	public static function layout_has_sidebar( ?string $page_key = null ): bool {
		if ( $page_key === null ) {
			$page_key = self::is_2d_template() ? 'carsinfo_2d' : 'carsinfo_3d2';
		}

		$sidebar = LayoutResolver::zones_for_page( $page_key )['sidebar'] ?? array();

		foreach ( $sidebar as $placement ) {
			if ( is_array( $placement ) && ! empty( $placement['partial'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Render post content without the cinfo-3dmodel block (for 2D template).
	 */
	public static function render_content_without_3dmodel( \Timber\Post $post ): string {
		$content = (string) $post->post_content;
		if ( $content === '' ) {
			return '';
		}

		if ( ! has_blocks( $content ) ) {
			return (string) apply_filters( 'the_content', $content );
		}

		$blocks   = parse_blocks( $content );
		$filtered = array();

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			if ( (string) ( $block['blockName'] ?? '' ) === 'acf/cinfo-3dmodel' ) {
				continue;
			}

			$filtered[] = $block;
		}

		$serialized = serialize_blocks( $filtered );
		if ( trim( $serialized ) === '' ) {
			return '';
		}

		$wp_post = get_post( (int) $post->ID );
		if ( ! $wp_post instanceof \WP_Post ) {
			return (string) do_blocks( $serialized );
		}

		global $post;
		$previous = $post;
		$post     = $wp_post;
		setup_postdata( $post );
		$html = (string) apply_filters( 'the_content', $serialized );
		wp_reset_postdata();
		$post = $previous;

		return $html;
	}

	public static function uses_3d_scene(): bool {
		return self::is_3d_template();
	}

	public static function print_import_map(): void {
		if ( ! self::is_3d_template() ) {
			return;
		}

		$map = array(
			'imports' => array(
				'three'         => Appearance::asset_url( 'vendor/three/build/three.module.js' ),
				'three/addons/' => trailingslashit( Appearance::asset_url( 'vendor/three/examples/jsm' ) ),
			),
		);

		echo '<script type="importmap">' . wp_json_encode( $map, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
	}

	/**
	 * @param string $tag    Script tag.
	 * @param string $handle Script handle.
	 * @param string $src    Script src.
	 */
	public static function script_loader_tag( string $tag, string $handle, string $src ): string {
		unset( $src );

		if ( $handle !== 'asrekhodro-carinfo3d-scene' ) {
			return $tag;
		}

		if ( str_contains( $tag, 'type=' ) ) {
			return $tag;
		}

		return str_replace( '<script ', '<script type="module" ', $tag );
	}
}
