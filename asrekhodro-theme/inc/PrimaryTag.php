<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * "Primary tag" for news, stored inside post_content as an invisible self-closing block:
 * <!-- wp:asrekhodro/primary-tag {"termId":123} /-->
 */
final class PrimaryTag {

	public const BLOCK = 'asrekhodro/primary-tag';

	public static function init(): void {
		add_action( 'init', array( self::class, 'register_block' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_editor_assets' ) );
		add_filter( 'the_content', array( self::class, 'strip_from_content' ), 8 );
	}

	/**
	 * Runs before do_blocks (9): otherwise this block alone makes has_blocks() true and
	 * WordPress skips wpautop on classic/imported HTML bodies.
	 */
	public static function strip_from_content( string $content ): string {
		if ( ! str_contains( $content, 'wp:' . self::BLOCK ) ) {
			return $content;
		}

		$stripped = preg_replace( '#\s*<!--\s+wp:' . preg_quote( self::BLOCK, '#' ) . '\b[^>]*?/-->\s*#', "\n", $content );

		return is_string( $stripped ) ? $stripped : $content;
	}

	public static function register_block(): void {
		register_block_type(
			self::BLOCK,
			array(
				'api_version'     => 2,
				'attributes'      => array(
					'termId' => array(
						'type'    => 'number',
						'default' => 0,
					),
				),
				'supports'        => array(
					'inserter'        => false,
					'html'            => false,
					'reusable'        => false,
					'multiple'        => false,
					'className'       => false,
					'customClassName' => false,
				),
				'render_callback' => static fn(): string => '',
			)
		);
	}

	public static function enqueue_editor_assets(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || $screen->base !== 'post' || $screen->post_type !== 'post' ) {
			return;
		}

		$js_path = ASREKHODRO_THEME_DIR . '/assets/admin/primary-tag-editor.js';
		if ( ! is_readable( $js_path ) ) {
			return;
		}

		wp_enqueue_script(
			'asrekhodro-primary-tag-editor',
			ASREKHODRO_THEME_URI . '/assets/admin/primary-tag-editor.js',
			array(
				'wp-blocks',
				'wp-block-editor',
				'wp-components',
				'wp-core-data',
				'wp-data',
				'wp-element',
				'wp-hooks',
			),
			(string) filemtime( $js_path ),
			true
		);

		wp_localize_script(
			'asrekhodro-primary-tag-editor',
			'akPrimaryTag',
			array(
				'blockName' => self::BLOCK,
				'label'     => 'تگ اول',
				'none'      => '— بدون تگ اول —',
				'help'      => 'این برچسب در صفحه خبر اول نمایش داده می‌شود و بقیه به ترتیب الفبا.',
			)
		);
	}

	/**
	 * Primary tag term ID from raw post_content; 0 when not set.
	 */
	public static function from_content( string $content ): int {
		if ( $content === '' || ! str_contains( $content, 'wp:' . self::BLOCK ) ) {
			return 0;
		}

		if ( ! preg_match( '#<!--\s+wp:' . preg_quote( self::BLOCK, '#' ) . '\s+\{[^}]*"termId"\s*:\s*(\d+)#', $content, $matches ) ) {
			return 0;
		}

		return (int) $matches[1];
	}
}
