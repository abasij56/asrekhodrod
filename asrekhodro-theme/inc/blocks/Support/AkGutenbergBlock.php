<?php

namespace AsreKhodro\Theme\AcfBlocks\Support;

use AsreKhodro\Theme\Appearance;
use AsreKhodro\Theme\BlockRegistry;
use AsreKhodro\Theme\BlockRenderer;
use AsreKhodro\Theme\CinfoBlocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base for ak-* layout blocks also registered as ACF Gutenberg blocks.
 */
abstract class AkGutenbergBlock {

	public static function boot(): void {
		BlockRegistry::register_acf( static::class );
	}

	public static function name(): string {
		return (string) static::config()['name'];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function config(): array {
		static $configs = array();
		$class          = static::class;

		if ( ! isset( $configs[ $class ] ) ) {
			/** @var array<string, mixed> $config */
			$configs[ $class ] = require static::block_dir() . '/config.php';
		}

		return $configs[ $class ];
	}

	public static function template_file(): string {
		$template = static::block_dir() . '/template.twig';
		if ( file_exists( $template ) ) {
			return $template;
		}

		$partial = (string) ( static::config()['partial'] ?? '' );
		if ( $partial !== '' ) {
			return Appearance::resolve_template( $partial );
		}

		return '';
	}

	/**
	 * @param array<string, mixed> $fields
	 * @return array<string, mixed>
	 */
	abstract public static function build_context( array $fields, ?\Timber\Post $post = null ): array;

	public static function register_block_type(): void {
		if ( ! function_exists( 'acf_register_block_type' ) ) {
			return;
		}

		$config = static::config();
		$label  = (string) ( $config['title'] ?? $config['label'] ?? static::name() );

		acf_register_block_type(
			array(
				'name'            => static::name(),
				'title'           => __( $label, 'asrekhodro' ),
				'render_callback' => static function ( $block ) use ( $label ) {
					BlockRenderer::render_acf_block( $block, static::template_file(), $label, static::name() );
				},
				'category'        => CinfoBlocks::BLOCK_CATEGORY,
				'icon'            => (string) ( $config['icon'] ?? 'megaphone' ),
				'mode'            => (string) ( $config['mode'] ?? 'edit' ),
				'supports'        => (array) ( $config['supports'] ?? array(
					'align'  => array( 'wide', 'full' ),
					'anchor' => true,
				) ),
				'keywords'        => (array) ( $config['keywords'] ?? array( 'تبلیغ', 'ad' ) ),
			)
		);
	}

	abstract protected static function block_dir(): string;
}
