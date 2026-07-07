<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VideoSingle {

	/**
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>
	 */
	public static function extend_context( array $context ): array {
		$post = $context['post'] ?? null;
		if ( ! $post instanceof \Timber\Post || $post->post_type !== 'ak_video' ) {
			return $context;
		}

		$post_id = (int) $post->ID;
		$terms   = $post->terms( 'video_category' );
		$term_id = ! empty( $terms ) ? (int) $terms[0]->term_id : 0;

		$related_args = array(
			'post_type'      => 'ak_video',
			'posts_per_page' => 4,
			'post__not_in'   => array( $post_id ),
		);

		if ( $term_id > 0 ) {
			$related_args['tax_query'] = array(
				array(
					'taxonomy' => 'video_category',
					'field'    => 'term_id',
					'terms'    => array( $term_id ),
				),
			);
		}

		$split = self::split_video_content( $post );

		$context['related_videos']     = \Timber\Timber::get_posts( $related_args );
		$context['video_url']          = self::get_video_url( $post );
		$context['video_poster_url']   = self::get_poster_url( $post );
		$context['video_poster_alt']   = self::get_poster_alt( $post );
		$context['video_player_html']  = $split['player'];
		$context['video_body_html']    = $split['body'];

		return $context;
	}

	public static function get_poster_url( \Timber\Post $post ): string {
		if ( $post->thumbnail ) {
			$src = $post->thumbnail->src( 'large' );
			if ( is_string( $src ) && $src !== '' ) {
				return esc_url( $src );
			}
		}

		$from_bridge = ImporterBridge::get_post_image_url( $post );
		if ( $from_bridge !== '' && $from_bridge !== ImporterBridge::placeholder_image_url() ) {
			return $from_bridge;
		}

		return '';
	}

	public static function get_poster_alt( \Timber\Post $post ): string {
		$alt = MediaAlt::from_post_thumbnail( (int) $post->ID );
		if ( $alt !== '' ) {
			return $alt;
		}

		if ( $post->thumbnail ) {
			$thumb_alt = trim( (string) $post->thumbnail->alt() );
			if ( $thumb_alt !== '' ) {
				return $thumb_alt;
			}
		}

		$poster_url = self::get_poster_url( $post );

		return $poster_url !== '' ? MediaAlt::from_url( $poster_url ) : '';
	}

	public static function get_video_url( \Timber\Post $post ): string {
		if ( function_exists( 'get_field' ) ) {
			$url = get_field( 'video_url', (int) $post->ID );
			if ( is_string( $url ) && $url !== '' ) {
				return esc_url( $url );
			}
		}

		$meta_keys = array( 'video_url', '_asrekhodro_video_url' );
		foreach ( $meta_keys as $key ) {
			$url = get_post_meta( (int) $post->ID, $key, true );
			if ( is_string( $url ) && $url !== '' ) {
				$resolved = ImporterBridge::resolve_media_url( $url );

				return $resolved !== '' ? $resolved : esc_url( $url );
			}
		}

		return '';
	}

	public static function get_player_markup( int $post_id ): string {
		if ( $post_id <= 0 ) {
			return '';
		}

		$post = \Timber\Timber::get_post( $post_id );
		if ( ! $post instanceof \Timber\Post ) {
			return '';
		}

		$split = self::split_video_content( $post );

		return trim( $split['player'] );
	}

	/**
	 * @return array{player: string, body: string}
	 */
	private static function split_video_content( \Timber\Post $post ): array {
		$raw = (string) get_post_field( 'post_content', (int) $post->ID );
		$raw = trim( $raw );

		if ( $raw === '' ) {
			return array(
				'player' => self::native_player_markup( self::get_video_url( $post ) ),
				'body'   => '',
			);
		}

		$player = '';
		$rest   = $raw;

		if ( preg_match( '#^\s*(<div class="ak-video-player"[^>]*>.*?</div>)#is', $raw, $matches ) ) {
			$player = $matches[1];
			$rest   = (string) preg_replace( '#^\s*<div class="ak-video-player"[^>]*>.*?</div>#is', '', $raw, 1 );
		} elseif ( preg_match( '#^\s*(<iframe\b[^>]*>.*?</iframe>)#is', $raw, $matches ) ) {
			$player = $matches[1];
			$rest   = (string) preg_replace( '#^\s*<iframe\b[^>]*>.*?</iframe>#is', '', $raw, 1 );
		} elseif ( preg_match( '#^\s*(<video\b[^>]*>.*?</video>)#is', $raw, $matches ) ) {
			$player = '<div class="ak-video-player">' . $matches[1] . '</div>';
			$rest   = (string) preg_replace( '#^\s*<video\b[^>]*>.*?</video>#is', '', $raw, 1 );
		} else {
			$player = self::native_player_markup( self::get_video_url( $post ) );
		}

		if ( $player === '' ) {
			$extracted = self::extract_first_embed( $raw );
			if ( $extracted !== '' ) {
				$player = $extracted;
				$rest   = self::remove_first_embed( $raw, $extracted );
			}
		}

		$rest = self::strip_embedded_videos( trim( $rest ) );
		$body = $rest !== '' ? (string) apply_filters( 'the_content', $rest ) : '';

		return array(
			'player' => $player,
			'body'   => trim( $body ),
		);
	}

	private static function native_player_markup( string $url ): string {
		if ( $url === '' ) {
			return '';
		}

		return sprintf(
			'<div class="ak-video-player"><video controls preload="metadata" playsinline src="%s"></video></div>',
			esc_url( $url )
		);
	}

	private static function extract_first_embed( string $html ): string {
		if ( preg_match( '#<div class="ak-video-player"[^>]*>.*?</div>#is', $html, $matches ) ) {
			return $matches[0];
		}

		if ( preg_match( '#<iframe\b[^>]*>.*?</iframe>#is', $html, $matches ) ) {
			return $matches[0];
		}

		if ( preg_match( '#<video\b[^>]*>.*?</video>#is', $html, $matches ) ) {
			return '<div class="ak-video-player">' . $matches[0] . '</div>';
		}

		return '';
	}

	private static function remove_first_embed( string $html, string $embed ): string {
		if ( $embed === '' ) {
			return $html;
		}

		$pos = strpos( $html, $embed );
		if ( $pos === false ) {
			return $html;
		}

		return trim( substr( $html, 0, $pos ) . substr( $html, $pos + strlen( $embed ) ) );
	}

	private static function strip_embedded_videos( string $html ): string {
		if ( $html === '' ) {
			return '';
		}

		$patterns = array(
			'#<div class="ak-video-player"[^>]*>.*?</div>#is',
			'#<video\b[^>]*>.*?</video>#is',
			'#<iframe\b[^>]*>.*?</iframe>#is',
			'#<object\b[^>]*>.*?</object>#is',
			'#<embed\b[^>]*/?>#is',
			'#\[video[^\]]*/?\]#is',
		);

		foreach ( $patterns as $pattern ) {
			$result = preg_replace( $pattern, '', $html );
			if ( is_string( $result ) ) {
				$html = $result;
			}
		}

		return trim( $html );
	}
}
