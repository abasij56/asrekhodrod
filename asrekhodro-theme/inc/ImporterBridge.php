<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ImporterBridge {

	/** @var array<int, array<int, string>> */
	private static array $content_images_cache = array();

	public static function init(): void {
		add_filter( 'timber/twig/functions', array( self::class, 'register_twig_functions' ) );
		add_filter( 'the_content', array( self::class, 'filter_content_media_urls' ), 20 );
		add_filter( 'the_content', array( self::class, 'filter_content_external_links' ), 21 );
		add_filter( 'the_content', array( self::class, 'strip_lead_image_from_content' ), 22 );
		add_filter( 'the_content', array( self::class, 'transform_inline_content_galleries' ), 23 );
		add_action( 'save_post', array( self::class, 'sync_post_image_meta' ), 20, 2 );
	}

	/**
	 * @param array<string, mixed> $functions
	 * @return array<string, mixed>
	 */
	public static function register_twig_functions( array $functions ): array {
		$functions['ak_media_url']      = array(
			'callable' => array( self::class, 'resolve_media_url' ),
		);
		$functions['ak_post_image_url'] = array(
			'callable' => array( self::class, 'get_post_image_url' ),
		);
		$functions['ak_post_date']      = array(
			'callable' => array( self::class, 'format_post_date' ),
		);
		$functions['ak_post_datetime']  = array(
			'callable' => array( self::class, 'format_post_datetime' ),
		);
		$functions['ak_post_time']      = array(
			'callable' => array( self::class, 'format_post_time' ),
		);
		$functions['ak_post_has_image'] = array(
			'callable' => array( self::class, 'post_has_real_image' ),
		);
		$functions['ak_persian_date'] = array(
			'callable' => array( PersianDate::class, 'format_object_date' ),
		);
		$functions['ak_persian_datetime'] = array(
			'callable' => array( PersianDate::class, 'format_object_datetime' ),
		);
		$functions['ak_post_hero_image_url'] = array(
			'callable' => array( self::class, 'get_post_hero_image_url' ),
		);
		$functions['ak_post_content_images'] = array(
			'callable' => array( self::class, 'get_post_content_images' ),
		);

		return $functions;
	}

	/**
	 * Turn stored path or URL into a full media URL.
	 */
	public static function resolve_media_url( mixed $url ): string {
		if ( ! is_string( $url ) ) {
			return '';
		}

		$url = trim( $url );
		if ( $url === '' ) {
			return '';
		}

		if ( preg_match( '#^https?://#i', $url ) ) {
			return esc_url( $url );
		}

		if ( str_starts_with( $url, '//' ) ) {
			return esc_url( 'https:' . $url );
		}

		if ( str_starts_with( $url, '/wp-content/' ) || str_starts_with( $url, '/uploads/' ) ) {
			return esc_url( home_url( $url ) );
		}

		if ( str_starts_with( $url, 'wp-content/' ) ) {
			return esc_url( content_url( $url ) );
		}

		$base = self::media_base_url();
		if ( $base === '' ) {
			return '';
		}

		$full = rtrim( $base, '/' ) . '/' . ltrim( $url, '/' );

		return esc_url( $full );
	}

	/**
	 * Rewrite relative /Uploaded/... paths in HTML content to full media URLs.
	 */
	public static function filter_content_media_urls( string $content ): string {
		return self::rewrite_content_media_urls( $content );
	}

	/**
	 * Media base URL: CDN Server option (via filter) with the legacy constant as fallback.
	 */
	public static function media_base_url(): string {
		$base = defined( 'ASREKHODRO_MEDIA_BASE_URL' ) ? ASREKHODRO_MEDIA_BASE_URL : '';

		return (string) apply_filters( 'ak_media_base_url', $base );
	}

	public static function rewrite_content_media_urls( string $content ): string {
		if ( $content === '' || self::media_base_url() === '' ) {
			return $content;
		}

		$pattern = '#(?<attr>src|href|data-src|data-lazy-src|poster)\s*=\s*(["\'])(?!https?://|//|data:)(/?Uploaded/[^"\']+)\2#i';

		$result = preg_replace_callback(
			$pattern,
			static function ( array $matches ): string {
				$resolved = self::resolve_media_url( $matches[3] );
				if ( $resolved === '' ) {
					return $matches[0];
				}

				return $matches['attr'] . '=' . $matches[2] . $resolved . $matches[2];
			},
			$content
		);

		return is_string( $result ) ? $result : $content;
	}

	/**
	 * Open external editorial links in a new tab (UX + security).
	 * Does not add nofollow — normal outbound links are fine for news SEO.
	 */
	public static function filter_content_external_links( string $content ): string {
		return self::process_external_links( $content );
	}

	public static function process_external_links( string $content ): string {
		if ( $content === '' || stripos( $content, '<a ' ) === false ) {
			return $content;
		}

		$result = preg_replace_callback(
			'/<a\s+([^>]+)>/i',
			static function ( array $matches ): string {
				$attrs = $matches[1];

				if ( ! preg_match( '/\bhref\s*=\s*(["\'])([^"\']+)\1/i', $attrs, $href_match ) ) {
					return $matches[0];
				}

				if ( ! self::is_external_url( $href_match[2] ) ) {
					return $matches[0];
				}

				return '<a ' . self::apply_external_link_attrs( $attrs ) . '>';
			},
			$content
		);

		return is_string( $result ) ? $result : $content;
	}

	public static function is_external_url( string $url ): bool {
		$url = trim( $url );

		if ( $url === '' || str_starts_with( $url, '#' ) ) {
			return false;
		}

		if ( str_starts_with( $url, 'mailto:' ) || str_starts_with( $url, 'tel:' ) || str_starts_with( $url, 'javascript:' ) ) {
			return false;
		}

		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return false;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || $host === '' ) {
			return false;
		}

		$host = strtolower( $host );

		foreach ( self::internal_link_hosts() as $internal ) {
			if ( $host === $internal || str_ends_with( $host, '.' . $internal ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @return array<int, string>
	 */
	private static function internal_link_hosts(): array {
		$hosts = array( 'asrekhodro.com', 'media.asrekhodro.com' );

		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( is_string( $site_host ) && $site_host !== '' ) {
			$hosts[] = strtolower( $site_host );
		}

		return array_values( array_unique( $hosts ) );
	}

	private static function apply_external_link_attrs( string $attrs ): string {
		if ( preg_match( '/\btarget\s*=/i', $attrs ) ) {
			$attrs = preg_replace( '/\btarget\s*=\s*["\'][^"\']*["\']/i', 'target="_blank"', $attrs ) ?? $attrs;
		} else {
			$attrs .= ' target="_blank"';
		}

		$rels = array( 'noopener', 'noreferrer' );

		if ( preg_match( '/\brel=(["\'])([^"\']*)\1/i', $attrs, $rel_match ) ) {
			$existing = preg_split( '/\s+/', strtolower( trim( $rel_match[2] ) ), -1, PREG_SPLIT_NO_EMPTY ) ?: array();
			$rels     = array_values( array_unique( array_merge( $existing, $rels ) ) );
			$attrs    = preg_replace(
				'/\brel=(["\'])[^"\']*\1/i',
				'rel="' . esc_attr( implode( ' ', $rels ) ) . '"',
				$attrs
			) ?? $attrs;
		} else {
			$attrs .= ' rel="noopener noreferrer"';
		}

		return trim( $attrs );
	}

	public static function get_external_image_url( int $post_id ): string {
		return self::get_post_image_url( $post_id );
	}

	/**
	 * Hero/lead image URL (no placeholder).
	 *
	 * @param \Timber\Post|int $post
	 */
	public static function get_post_hero_image_url( $post ): string {
		$url = self::get_post_image_url( $post );
		if ( $url === '' || $url === self::placeholder_image_url() ) {
			return '';
		}

		return $url;
	}

	/**
	 * @param \Timber\Post|int $post
	 */
	private static function get_post_raw_content( $post ): string {
		$post_id = self::resolve_post_id( $post );
		$content = '';

		if ( $post instanceof \Timber\Post ) {
			$content = (string) ( $post->post_content ?? '' );
		}

		if ( $content === '' && $post_id > 0 ) {
			$content = (string) get_post_field( 'post_content', $post_id );
		}

		return $content;
	}

	/**
	 * @param \Timber\Post|\WP_Post|int|mixed $post
	 */
	private static function resolve_post_id( $post ): int {
		if ( $post instanceof \Timber\Post ) {
			return (int) $post->ID;
		}

		if ( $post instanceof \WP_Post ) {
			return (int) $post->ID;
		}

		if ( is_numeric( $post ) ) {
			return (int) $post;
		}

		if ( is_object( $post ) ) {
			if ( isset( $post->ID ) && is_numeric( $post->ID ) ) {
				return (int) $post->ID;
			}

			if ( isset( $post->id ) && is_numeric( $post->id ) ) {
				return (int) $post->id;
			}
		}

		return 0;
	}

	/**
	 * Resolve image URLs from HTTP(S), site uploads, or legacy media paths.
	 */
	public static function resolve_any_image_url( string $url ): string {
		$url = trim( html_entity_decode( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		if ( $url === '' ) {
			return '';
		}

		return self::resolve_media_url( $url );
	}

	/**
	 * Resolve image URL: featured image → meta → body HTML → placeholder.
	 *
	 * @param \Timber\Post|\WP_Post|int $post
	 */
	public static function get_post_image_url( $post ): string {
		$post_id = self::resolve_post_id( $post );

		if ( $post_id <= 0 ) {
			return self::placeholder_image_url();
		}

		$content = self::get_post_raw_content( $post );

		if ( has_post_thumbnail( $post_id ) ) {
			$src = self::get_featured_image_url( $post_id, 'large' );
			if ( $src !== '' ) {
				if ( (string) get_post_meta( $post_id, '_asrekhodro_image_url', true ) === '' ) {
					update_post_meta( $post_id, '_asrekhodro_image_url', $src );
				}

				return $src;
			}
		}

		$from_meta = self::resolve_any_image_url( (string) get_post_meta( $post_id, '_asrekhodro_image_url', true ) );
		if ( $from_meta !== '' ) {
			return $from_meta;
		}

		$from_body = self::extract_image_from_html( $content );
		if ( $from_body !== '' ) {
			$resolved = self::resolve_any_image_url( $from_body );
			if ( $resolved !== '' ) {
				return $resolved;
			}
		}

		return self::placeholder_image_url();
	}

	public static function extract_image_from_html( string $html ): string {
		$items = self::extract_image_items_from_html( $html );

		return $items[0]['url'] ?? '';
	}

	/**
	 * @return array<int, string>
	 */
	public static function extract_images_from_html( string $html ): array {
		return array_column( self::extract_image_items_from_html( $html ), 'url' );
	}

	/**
	 * @return list<array{url: string, alt: string}>
	 */
	public static function extract_image_items_from_html( string $html ): array {
		if ( $html === '' ) {
			return array();
		}

		$html = html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$html = self::rewrite_content_media_urls( $html );

		$items = array();
		$seen  = array();

		if ( preg_match_all( '/<img\b[^>]*>/i', $html, $img_tags ) ) {
			foreach ( $img_tags[0] as $tag ) {
				$item = self::image_item_from_img_tag( (string) $tag );
				if ( $item === null ) {
					continue;
				}

				$key = self::normalize_image_url_key( $item['url'] );
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}

				$seen[ $key ]  = true;
				$items[]         = $item;
			}
		}

		$patterns = array(
			'#(?:src|href|data-src)\s*=\s*["\']([^"\']*/Uploaded/Image/[^"\']+\.(?:jpe?g|png|gif|webp))["\']#i',
			'#(https?://media\.asrekhodro\.com/[^\s"\'<>&]+\.(?:jpe?g|png|gif|webp))#i',
		);

		foreach ( $patterns as $pattern ) {
			if ( ! preg_match_all( $pattern, $html, $matches ) ) {
				continue;
			}

			foreach ( $matches[1] as $url ) {
				$item = self::image_item_from_raw_url( (string) $url );
				if ( $item === null ) {
					continue;
				}

				$key = self::normalize_image_url_key( $item['url'] );
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}

				$seen[ $key ] = true;
				$items[]        = $item;
			}
		}

		return $items;
	}

	/**
	 * @return array{url: string, alt: string}|null
	 */
	private static function image_item_from_img_tag( string $tag ): ?array {
		if ( ! preg_match( '/(?:src|data-src|data-lazy-src)\s*=\s*["\']([^"\']+)["\']/i', $tag, $src_match ) ) {
			return null;
		}

		$url = html_entity_decode( (string) $src_match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$full = self::resolve_any_image_url( $url );
		if ( $full === '' ) {
			return null;
		}

		$alt = '';
		if ( preg_match( '/\balt\s*=\s*["\']([^"\']*)["\']/i', $tag, $alt_match ) ) {
			$alt = trim( html_entity_decode( (string) $alt_match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		}

		if ( $alt === '' ) {
			$alt = MediaAlt::from_url( $full );
		}

		return array(
			'url' => $full,
			'alt' => $alt,
		);
	}

	/**
	 * @return array{url: string, alt: string}|null
	 */
	private static function image_item_from_raw_url( string $url ): ?array {
		$url = html_entity_decode( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( $url === '' ) {
			return null;
		}

		$full = self::resolve_any_image_url( $url );
		if ( $full === '' ) {
			return null;
		}

		return array(
			'url' => $full,
			'alt' => MediaAlt::from_url( $full ),
		);
	}

	/**
	 * @param array<int, string|array{url?: string, alt?: string}> $images
	 * @return list<array{url: string, alt: string}>
	 */
	private static function normalize_image_items( array $images ): array {
		$items = array();

		foreach ( $images as $image ) {
			if ( is_array( $image ) ) {
				$url = trim( (string) ( $image['url'] ?? '' ) );
				$alt = trim( (string) ( $image['alt'] ?? '' ) );
			} else {
				$url = trim( (string) $image );
				$alt = '';
			}

			if ( $url === '' ) {
				continue;
			}

			if ( $alt === '' ) {
				$alt = MediaAlt::from_url( $url );
			}

			$items[] = array(
				'url' => $url,
				'alt' => $alt,
			);
		}

		return $items;
	}

	/**
	 * Images embedded in post body (excluding lead/hero image).
	 *
	 * @param \Timber\Post|int $post
	 * @return array<int, string>
	 */
	public static function get_post_content_images( $post ): array {
		$post_id = 0;

		if ( $post instanceof \Timber\Post ) {
			$post_id = (int) $post->ID;
		} elseif ( is_numeric( $post ) ) {
			$post_id = (int) $post;
		}

		if ( $post_id <= 0 ) {
			return array();
		}

		if ( isset( self::$content_images_cache[ $post_id ] ) ) {
			return self::$content_images_cache[ $post_id ];
		}

		$content = self::get_post_raw_content( $post );
		$images  = self::extract_images_from_html( $content );
		$hero    = self::get_post_hero_image_url( $post );

		if ( $hero !== '' ) {
			$hero_key = self::normalize_image_url_key( $hero );
			$images   = array_values(
				array_filter(
					$images,
					static fn( string $url ): bool => self::normalize_image_url_key( $url ) !== $hero_key
				)
			);
		}

		self::$content_images_cache[ $post_id ] = $images;

		return $images;
	}

	private static function normalize_image_url_key( string $url ): string {
		$path = wp_parse_url( $url, PHP_URL_PATH );

		return is_string( $path ) && $path !== ''
			? strtolower( basename( $path ) )
			: strtolower( $url );
	}

	public static function strip_lead_image_from_content( string $content ): string {
		if ( ! is_singular( 'post' ) || $content === '' ) {
			return $content;
		}

		$post = \Timber\Timber::get_post();
		if ( ! $post instanceof \Timber\Post ) {
			return $content;
		}

		$hero = self::get_post_hero_image_url( $post );
		if ( $hero === '' ) {
			return $content;
		}

		$content_images = self::extract_images_from_html( $content );
		if ( $content_images === array() ) {
			return $content;
		}

		$hero_key  = self::normalize_image_url_key( $hero );
		$first_key = self::normalize_image_url_key( $content_images[0] );
		if ( $hero_key !== $first_key ) {
			return $content;
		}

		$patterns = array(
			'#<p[^>]*>\s*(?:<a[^>]*>\s*)?<img[^>]+>(?:\s*</a>)?\s*</p>#is',
			'#<figure[^>]*>\s*(?:<a[^>]*>\s*)?<img[^>]+>(?:\s*</a>)?.*?</figure>#is',
			'#<img[^>]+>#i',
		);

		foreach ( $patterns as $pattern ) {
			$result = preg_replace( $pattern, '', $content, 1 );
			if ( is_string( $result ) && $result !== $content ) {
				return $result;
			}
		}

		return $content;
	}

	/**
	 * Replace consecutive inline image blocks with centered solo media or mosaic galleries.
	 */
	public static function transform_inline_content_galleries( string $content ): string {
		if ( ! is_singular( 'post' ) || $content === '' ) {
			return $content;
		}

		$segments = self::split_content_into_segments( $content );
		if ( $segments === array() ) {
			return $content;
		}

		$output = '';
		$index  = 0;
		$total  = count( $segments );

		while ( $index < $total ) {
			if ( self::segment_is_image_only( $segments[ $index ] ) ) {
				$group   = self::collect_consecutive_image_segments( $segments, $index );
				$images  = self::extract_image_items_from_html( implode( '', $group['segments'] ) );
				$output .= self::render_inline_image_group( $images );
				$index   = $group['next_index'];
				continue;
			}

			$output .= $segments[ $index ];
			++$index;
		}

		return $output;
	}

	/**
	 * @return array<int, string>
	 */
	private static function split_content_into_segments( string $content ): array {
		$pattern = '#(<p[^>]*>.*?</p>|<figure[^>]*>.*?</figure>|<div[^>]*>.*?</div>|<h[1-6][^>]*>.*?</h[1-6]>|<blockquote[^>]*>.*?</blockquote>|<ul[^>]*>.*?</ul>|<ol[^>]*>.*?</ol>|<table[^>]*>.*?</table>|<a[^>]*>\s*<img[^>]+>\s*</a>|<img[^>]+>)#is';

		if ( ! preg_match_all( $pattern, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			return $content !== '' ? array( $content ) : array();
		}

		$segments = array();
		$offset   = 0;

		foreach ( $matches[0] as $match ) {
			$start = (int) $match[1];
			$block = (string) $match[0];

			if ( $start > $offset ) {
				$segments[] = substr( $content, $offset, $start - $offset );
			}

			$segments[] = $block;
			$offset     = $start + strlen( $block );
		}

		if ( $offset < strlen( $content ) ) {
			$segments[] = substr( $content, $offset );
		}

		return array_values(
			array_filter(
				$segments,
				static fn( string $segment ): bool => $segment !== ''
			)
		);
	}

	private static function segment_is_image_only( string $segment ): bool {
		if ( self::extract_images_from_html( $segment ) === array() ) {
			return false;
		}

		return self::segment_has_no_visible_text( $segment );
	}

	private static function segment_has_no_visible_text( string $segment ): bool {
		$text = html_entity_decode( wp_strip_all_tags( $segment ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/[\s\x{00A0}\x{200C}]+/u', '', (string) $text );

		return $text === '';
	}

	/**
	 * @param array<int, string> $segments
	 * @return array{segments: array<int, string>, next_index: int}
	 */
	private static function collect_consecutive_image_segments( array $segments, int $start ): array {
		$collected = array();
		$index     = $start;
		$total     = count( $segments );

		while ( $index < $total ) {
			$segment = $segments[ $index ];

			if ( self::segment_is_image_only( $segment ) ) {
				$collected[] = $segment;
				++$index;
				continue;
			}

			if ( self::segment_has_no_visible_text( $segment ) && ( $index + 1 ) < $total && self::segment_is_image_only( $segments[ $index + 1 ] ) ) {
				++$index;
				continue;
			}

			break;
		}

		return array(
			'segments'   => $collected,
			'next_index' => $index,
		);
	}

	/**
	 * @param array<int, string|array{url?: string, alt?: string}> $images
	 */
	private static function render_inline_image_group( array $images ): string {
		$image_items = self::normalize_image_items( $images );
		if ( $image_items === array() ) {
			return '';
		}

		$template = count( $image_items ) === 1
			? 'partials/single-inline-image.twig'
			: 'partials/single-gallery-inline.twig';

		return \Timber\Timber::compile(
			Appearance::resolve_template( $template ),
			array(
				'image_items' => $image_items,
			)
		);
	}

	public static function post_has_real_image( $post ): bool {
		return self::get_post_hero_image_url( $post ) !== '';
	}

	public static function find_first_post_with_image( array $args = array() ): ?\Timber\Post {
		$posts = self::query_posts( array_merge( array( 'posts_per_page' => 40 ), $args ) );
		foreach ( $posts as $item ) {
			if ( self::post_has_real_image( $item ) ) {
				return $item;
			}
		}

		return null;
	}

	public static function placeholder_image_url(): string {
		return esc_url( Appearance::asset_url( 'images/placeholder-news.svg' ) );
	}

	/**
	 * Featured image URL. External media always uses the full remote URL (guid).
	 */
	public static function get_featured_image_url( int $post_id, string $size = 'large' ): string {
		if ( ! has_post_thumbnail( $post_id ) ) {
			return '';
		}

		$attachment_id = (int) get_post_thumbnail_id( $post_id );
		if ( $attachment_id <= 0 ) {
			return '';
		}

		if ( class_exists( ExternalMedia::class ) ) {
			$external = ExternalMedia::get_attachment_url( $attachment_id );
			if ( $external !== '' ) {
				return $external;
			}
		}

		$sizes = array_values( array_unique( array( $size, 'large', 'full', 'medium', 'thumbnail' ) ) );
		foreach ( $sizes as $candidate_size ) {
			$src = wp_get_attachment_image_url( $attachment_id, $candidate_size );
			if ( is_string( $src ) && $src !== '' ) {
				return esc_url( $src );
			}
		}

		$url = wp_get_attachment_url( $attachment_id );

		return is_string( $url ) && $url !== '' ? esc_url( $url ) : '';
	}

	/**
	 * Keep legacy image meta in sync when editors set a featured image.
	 */
	public static function sync_post_image_meta( int $post_id, \WP_Post $post ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, array( 'post', 'ak_video', 'ak_review' ), true ) ) {
			return;
		}

		if ( ! has_post_thumbnail( $post_id ) ) {
			return;
		}

		$url = self::get_featured_image_url( $post_id, 'large' );
		if ( $url === '' ) {
			return;
		}

		update_post_meta( $post_id, '_asrekhodro_image_url', $url );
	}

	/**
	 * @param \Timber\Post|mixed $post
	 */
	public static function format_post_date( $post ): string {
		return PersianDate::format_object_date( $post );
	}

	/**
	 * Persian (Jalali) date + time.
	 *
	 * @param \Timber\Post|mixed $post
	 */
	public static function format_post_datetime( $post ): string {
		return PersianDate::format_object_datetime( $post );
	}

	/**
	 * Persian (Jalali) time only (HH:MM).
	 *
	 * @param \Timber\Post|mixed $post
	 */
	public static function format_post_time( $post ): string {
		return PersianDate::format_object_time( $post );
	}

	public static function get_list_excerpt( \Timber\Post $post, int $max = 160 ): string {
		$raw = $post->post_excerpt ?: $post->post_content;
		$text = wp_strip_all_tags( $raw );
		$text = preg_replace( '/^عصر خودرو[-–—\s]*/u', '', $text ) ?? $text;

		if ( mb_strlen( $text ) > $max ) {
			return mb_substr( $text, 0, $max ) . '…';
		}

		return $text;
	}

	public static function get_primary_category_name( \Timber\Post $post ): string {
		$terms = $post->categories();
		if ( empty( $terms ) ) {
			return '';
		}

		return $terms[0]->name ?? '';
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_ads_by_position( string $position_slug, int $limit = 3 ): array {
		$limit = max( 1, $limit );
		$term  = get_term_by( 'slug', $position_slug, 'ad_position' );
		if ( ! $term ) {
			return self::placeholder_ads( $position_slug, $limit );
		}

		$query = new \WP_Query(
			array(
				'post_type'              => 'ad_slot',
				'posts_per_page'         => $limit,
				'post_status'            => 'publish',
				'orderby'                => 'menu_order',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'suppress_filters'       => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => array(
					array(
						'taxonomy' => 'ad_position',
						'field'    => 'term_id',
						'terms'    => $term->term_id,
					),
				),
			)
		);

		$ads = array();
		foreach ( $query->posts as $ad_post ) {
			$ads[] = self::format_ad( $ad_post->ID );
		}

		if ( $ads === array() ) {
			return self::placeholder_ads( $position_slug, $limit );
		}

		return $ads;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function format_ad( int $post_id ): array {
		$image = function_exists( 'get_field' ) ? get_field( 'ad_image', $post_id ) : null;
		$link  = function_exists( 'get_field' ) ? (string) get_field( 'ad_link', $post_id ) : '#';
		$label = get_the_title( $post_id );
		$image_url = is_array( $image ) ? (string) ( $image['url'] ?? '' ) : ( is_string( $image ) ? $image : '' );
		$image_alt = MediaAlt::from_acf_image( $image );

		if ( $image_alt === '' ) {
			$image_alt = MediaAlt::from_post_thumbnail( $post_id );
		}

		if ( $image_url === '' && has_post_thumbnail( $post_id ) ) {
			$image_url = self::get_featured_image_url( $post_id, 'medium' );
		}

		return array(
			'post_id'     => $post_id,
			'title'       => $label,
			'link'        => $link ?: '#',
			'image'       => $image_url,
			'image_alt'   => $image_alt,
			'label'       => function_exists( 'get_field' ) ? (string) get_field( 'ad_label', $post_id ) : $label,
			'link_target' => '_blank',
			'link_rel'    => 'noopener noreferrer',
		);
	}

	/**
	 * Add mobile-sized image URLs for sticky bottom ads.
	 *
	 * @param list<array<string, mixed>> $items
	 * @return list<array<string, mixed>>
	 */
	public static function enrich_sticky_bottom_ads( array $items ): array {
		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$post_id = (int) ( $item['post_id'] ?? 0 );
			if ( $post_id <= 0 ) {
				continue;
			}

			$mobile_url = self::get_featured_image_url( $post_id, 'ak-sticky-bottom-ad-mobile' );
			if ( $mobile_url !== '' ) {
				$items[ $index ]['image_mobile'] = $mobile_url;
			}
		}

		return $items;
	}

	/**
	 * Ad-strip slot from a Timber post (featured image or ad_slot ACF fields).
	 *
	 * @return array{title: string, label: string, link: string, image: string}
	 */
	public static function ad_strip_item_from_post( \Timber\Post $post, string $image_size = 'ak-ad-strip' ): array {
		if ( $post->post_type === 'ad_slot' ) {
			$item = self::format_ad( (int) $post->ID );
			if ( $item['image'] === '' && $post->thumbnail ) {
				$thumb = $post->thumbnail( $image_size ) ?: $post->thumbnail();
				if ( $thumb ) {
					$item['image'] = (string) $thumb->src();
				}
			}

			return $item;
		}

		$image = '';
		if ( $post->thumbnail ) {
			$thumb = $post->thumbnail( $image_size ) ?: $post->thumbnail();
			if ( $thumb ) {
				$image = (string) $thumb->src();
			}
		}

		return array(
			'title'       => (string) $post->title(),
			'label'       => (string) $post->title(),
			'link'        => (string) $post->link(),
			'image'       => $image,
			'link_target' => '_blank',
			'link_rel'    => 'noopener noreferrer',
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function placeholder_ads( string $position, int $limit ): array {
		$defaults = array(
			'menu_strip'   => array(
				array( 'label' => 'ایران‌خودرو · پیش‌فروش پژو ۲۰۷ و تارا', 'link' => '#' ),
				array( 'label' => 'سایپا · خدمات پس از فروش سراسری', 'link' => '#' ),
				array( 'label' => 'بهمن موتور · دیگنیتی پرایم ۱۴۰۵', 'link' => '#' ),
			),
			'sidebar_left' => array(
				array( 'label' => 'بانک ملت · وام خودرو', 'link' => '#' ),
				array( 'label' => 'کاسترول · روغن موتور', 'link' => '#' ),
				array( 'label' => 'بیمه پارسیان', 'link' => '#' ),
				array( 'label' => 'لاستیک بارز', 'link' => '#' ),
				array( 'label' => 'هیوندای · آوان', 'link' => '#' ),
				array( 'label' => 'MVM · مدیران خودرو', 'link' => '#' ),
			),
			'content_row'  => array(
				array( 'label' => 'تبلیغات · عصر خودرو', 'link' => '#' ),
			),
			'sticky_bottom' => array(
				array( 'label' => 'تبلیغات · عصر خودرو', 'link' => '#' ),
			),
		);

		$items  = $defaults[ $position ] ?? array();
		$limit  = max( 1, $limit );
		$filled = array();

		if ( $items === array() ) {
			return array();
		}

		for ( $i = 0; $i < $limit; $i++ ) {
			$filled[] = $items[ $i % count( $items ) ];
		}

		return array_map(
			static fn( array $item ) => array(
				'title'       => $item['label'],
				'label'       => $item['label'],
				'link'        => $item['link'],
				'image'       => '',
				'link_target' => '_blank',
				'link_rel'    => 'noopener noreferrer',
			),
			$filled
		);
	}

	/**
	 * @param array<string, mixed> $args
	 * @return \Timber\PostQuery
	 */
	public static function query_posts( array $args = array() ): \Timber\PostQuery {
		$defaults = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		);

		return \Timber\Timber::get_posts( array_merge( $defaults, $args ) );
	}
}
