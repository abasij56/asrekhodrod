<?php

namespace AsreKhodro\Theme\AcfBlocks\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitize video embed markup for cinfo-video blocks.
 */
final class EmbedSanitizer {

	public static function video_embed( string $html ): string {
		$html = trim( $html );
		if ( $html === '' ) {
			return '';
		}

		$html = do_shortcode( $html );

		/**
		 * Filters sanitized video embed HTML before output.
		 *
		 * @param string $html Raw embed HTML from the block field.
		 */
		$html = (string) apply_filters( 'asrekhodro/cinfo_video_embed_raw', $html );

		return wp_kses( $html, self::allowed_tags() );
	}

	/**
	 * @return array<string, array<string, bool>>
	 */
	private static function allowed_tags(): array {
		return array(
			'iframe' => array(
				'src'                   => true,
				'width'                 => true,
				'height'                => true,
				'frameborder'           => true,
				'allow'                 => true,
				'allowfullscreen'       => true,
				'webkitallowfullscreen' => true,
				'mozallowfullscreen'    => true,
				'title'                 => true,
				'loading'               => true,
				'class'                 => true,
				'id'                    => true,
				'style'                 => true,
				'name'                  => true,
				'scrolling'             => true,
			),
			'script' => array(
				'src'     => true,
				'async'   => true,
				'defer'   => true,
				'type'    => true,
				'charset' => true,
			),
			'div'    => array(
				'class' => true,
				'id'    => true,
				'style' => true,
			),
			'video'  => array(
				'src'         => true,
				'controls'    => true,
				'preload'     => true,
				'playsinline' => true,
				'poster'      => true,
				'width'       => true,
				'height'      => true,
				'class'       => true,
			),
			'source' => array(
				'src'  => true,
				'type' => true,
			),
		);
	}
}
