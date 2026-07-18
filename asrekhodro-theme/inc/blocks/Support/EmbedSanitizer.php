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
		$html = self::normalize_aparat_responsive_iframe( $html );

		return wp_kses( $html, self::allowed_tags() );
	}

	/**
	 * Aparat's responsive iframe snippet relies on an inline <style> block and
	 * absolute positioning. Theme CSS (aspect-ratio / height:auto on
	 * .ci-video__player iframe) breaks that layout, and wp_kses may strip
	 * display:block from the spacer span. Collapse it to a plain iframe that
	 * the player CSS already sizes correctly. Script-based Aparat embeds are left alone.
	 */
	private static function normalize_aparat_responsive_iframe( string $html ): string {
		if ( ! preg_match( '/aparat\.com/i', $html ) ) {
			return $html;
		}

		// Script embeds (e.g. aparat.com/embed/...) already work.
		if ( preg_match( '/<script\b/i', $html ) ) {
			return $html;
		}

		$is_responsive_wrapper = (bool) preg_match( '/h_iframe-aparat_embed_frame/i', $html );
		if ( ! $is_responsive_wrapper ) {
			return $html;
		}

		if ( ! preg_match( '/<iframe\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1[^>]*>/i', $html, $match ) ) {
			return $html;
		}

		$src = esc_url( $match[2] );
		if ( $src === '' ) {
			return $html;
		}

		return sprintf(
			'<iframe src="%s" allowfullscreen="true" webkitallowfullscreen="true" mozallowfullscreen="true" loading="lazy"></iframe>',
			esc_attr( $src )
		);
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
			'span'   => array(
				'class' => true,
				'style' => true,
			),
			'style'  => array(
				'type' => true,
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
