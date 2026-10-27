<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pick an already-generated attachment size for a CSS container.
 *
 * Read-only: never writes post/attachment meta and never creates files.
 */
final class PostImage {

	/** @var array<string, array{src: string, srcset: string, sizes: string}> */
	private static array $cache = array();

	/**
	 * @param \Timber\Post|\WP_Post|int|mixed $post
	 * @return array{src: string, srcset: string, sizes: string}
	 */
	public static function attrs( $post, int $width, int $height = 0, string $sizes = '' ): array {
		$width   = max( 0, $width );
		$height  = max( 0, $height );
		$post_id = self::resolve_post_id( $post );
		$cache_key = $post_id . '|' . $width . 'x' . $height . '|' . $sizes;

		if ( isset( self::$cache[ $cache_key ] ) ) {
			return self::$cache[ $cache_key ];
		}

		$attrs = self::resolve( $post, $post_id, $width, $sizes );
		self::$cache[ $cache_key ] = $attrs;

		return $attrs;
	}

	/**
	 * @param array<string, mixed>            $item
	 * @param \Timber\Post|\WP_Post|int|mixed $post
	 * @return array<string, mixed>
	 */
	public static function merge( array $item, $post, int $width, int $height = 0, string $sizes = '' ): array {
		$attrs                 = self::attrs( $post, $width, $height, $sizes );
		$item['image']         = $attrs['src'];
		$item['image_srcset']  = $attrs['srcset'];
		$item['image_sizes']   = $attrs['sizes'];

		return $item;
	}

	/**
	 * @param \Timber\Post|\WP_Post|int|mixed $post
	 */
	public static function url( $post, int $width = 1024 ): string {
		return self::attrs( $post, $width )['src'];
	}

	/**
	 * @param \Timber\Post|\WP_Post|int|mixed $post
	 * @return array{src: string, srcset: string, sizes: string}
	 */
	private static function resolve( $post, int $post_id, int $width, string $sizes ): array {
		$fallback_sizes = self::sizes_attr( $width, $sizes );

		if ( $post_id > 0 && has_post_thumbnail( $post_id ) ) {
			$attachment_id = (int) get_post_thumbnail_id( $post_id );
			$from_file     = self::from_attachment( $attachment_id, $width, $fallback_sizes );
			if ( $from_file['src'] !== '' ) {
				return $from_file;
			}
		}

		if ( $post_id > 0 ) {
			$from_meta = ImporterBridge::resolve_any_image_url(
				(string) get_post_meta( $post_id, '_asrekhodro_image_url', true )
			);
			if ( $from_meta !== '' ) {
				return self::single( $from_meta, $fallback_sizes );
			}
		}

		$content   = self::raw_content( $post, $post_id );
		$from_body = ImporterBridge::extract_image_from_html( $content );
		if ( $from_body !== '' ) {
			$resolved = ImporterBridge::resolve_any_image_url( $from_body );
			if ( $resolved !== '' ) {
				return self::single( $resolved, $fallback_sizes );
			}
		}

		return self::single( ImporterBridge::placeholder_image_url(), '' );
	}

	/**
	 * @return array{src: string, srcset: string, sizes: string}
	 */
	private static function from_attachment( int $attachment_id, int $width, string $sizes ): array {
		if ( $attachment_id <= 0 ) {
			return self::single( '', $sizes );
		}

		if ( class_exists( ExternalMedia::class ) ) {
			$external = ExternalMedia::get_attachment_url( $attachment_id );
			if ( $external !== '' ) {
				return self::single( $external, $sizes );
			}
		}

		$sources = self::existing_sources( $attachment_id );
		if ( $sources === array() ) {
			$url = wp_get_attachment_url( $attachment_id );

			return is_string( $url ) && $url !== ''
				? self::single( esc_url( $url ), $sizes )
				: self::single( '', $sizes );
		}

		$src_width = self::best_width( array_keys( $sources ), $width );
		$src       = $sources[ $src_width ] ?? '';
		if ( $src === '' ) {
			return self::single( '', $sizes );
		}

		return array(
			'src'    => $src,
			'srcset' => self::srcset( $sources, $width, $src_width ),
			'sizes'  => $sizes,
		);
	}

	/**
	 * Existing generated files only, via WP so CDN filters still apply.
	 *
	 * @return array<int, string> width => url
	 */
	private static function existing_sources( int $attachment_id ): array {
		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}

		$names = array( 'full' );
		if ( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( array_keys( $meta['sizes'] ) as $name ) {
				if ( is_string( $name ) && $name !== '' ) {
					$names[] = $name;
				}
			}
		}

		$sources = array();
		foreach ( $names as $name ) {
			$src = wp_get_attachment_image_src( $attachment_id, $name );
			if ( ! is_array( $src ) || empty( $src[0] ) ) {
				continue;
			}

			$file_width = (int) ( $src[1] ?? 0 );
			if ( $file_width <= 0 ) {
				continue;
			}

			$url = esc_url( (string) $src[0] );
			if ( $url === '' ) {
				continue;
			}

			if ( ! isset( $sources[ $file_width ] ) ) {
				$sources[ $file_width ] = $url;
			}
		}

		ksort( $sources, SORT_NUMERIC );

		return $sources;
	}

	/**
	 * @param list<int> $widths
	 */
	private static function best_width( array $widths, int $container ): int {
		sort( $widths, SORT_NUMERIC );

		if ( $widths === array() ) {
			return 0;
		}

		$target = $container > 0 ? $container : 1024;
		foreach ( $widths as $width ) {
			if ( $width >= $target ) {
				return $width;
			}
		}

		return (int) end( $widths );
	}

	/**
	 * Keep 1x + ~2x candidates. Skip the 1024/1200 files for a 163px thumb.
	 *
	 * @param array<int, string> $sources
	 */
	private static function srcset( array $sources, int $container, int $src_width ): string {
		if ( count( $sources ) < 2 ) {
			return '';
		}

		$container = $container > 0 ? $container : $src_width;
		$needed_2x = (int) ceil( $container * 2.5 );
		$cap       = max( $src_width, $container * 2 );

		$include_widths = array();
		foreach ( array_keys( $sources ) as $width ) {
			if ( $width <= $cap ) {
				$include_widths[] = $width;
			}
		}

		$retina = 0;
		foreach ( array_keys( $sources ) as $width ) {
			if ( $width >= $needed_2x ) {
				$retina = $width;
				break;
			}
		}
		if ( $retina > 0 ) {
			$include_widths[] = $retina;
		}

		$include_widths = array_values( array_unique( $include_widths ) );
		sort( $include_widths, SORT_NUMERIC );

		if ( count( $include_widths ) < 2 ) {
			return '';
		}

		$parts = array();
		foreach ( $include_widths as $width ) {
			$parts[] = $sources[ $width ] . ' ' . $width . 'w';
		}

		return implode( ', ', $parts );
	}

	private static function sizes_attr( int $width, string $sizes ): string {
		$sizes = trim( $sizes );
		if ( $sizes !== '' ) {
			return $sizes;
		}

		if ( $width <= 0 ) {
			return '100vw';
		}

		if ( $width <= 200 ) {
			return '(max-width: 767px) 120px, ' . $width . 'px';
		}

		if ( $width <= 360 ) {
			return '(max-width: 767px) 45vw, ' . $width . 'px';
		}

		if ( $width <= 640 ) {
			return '(max-width: 767px) 100vw, ' . $width . 'px';
		}

		return '(max-width: 1100px) 100vw, ' . min( $width, 884 ) . 'px';
	}

	/**
	 * @return array{src: string, srcset: string, sizes: string}
	 */
	private static function single( string $src, string $sizes ): array {
		return array(
			'src'    => $src,
			'srcset' => '',
			'sizes'  => $src !== '' ? $sizes : '',
		);
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
	 * @param \Timber\Post|\WP_Post|int|mixed $post
	 */
	private static function raw_content( $post, int $post_id ): string {
		$content = '';

		if ( $post instanceof \Timber\Post ) {
			$content = (string) ( $post->post_content ?? '' );
		}

		if ( $content === '' && $post_id > 0 ) {
			$content = (string) get_post_field( 'post_content', $post_id );
		}

		return $content;
	}
}
