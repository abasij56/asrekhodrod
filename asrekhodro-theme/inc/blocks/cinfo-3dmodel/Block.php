<?php

namespace AsreKhodro\Theme\AcfBlocks\Cinfo3dmodel;

use AsreKhodro\Theme\BlockRegistry;
use AsreKhodro\Theme\Appearance;
use AsreKhodro\Theme\BlockRenderer;
use AsreKhodro\Theme\CinfoBlocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Block {

	private const DIR = __DIR__;

	/** @var array<string, mixed>|null */
	private static ?array $config = null;

	public static function boot(): void {
		BlockRegistry::register_acf( self::class );
	}

	public static function name(): string {
		return (string) self::config()['name'];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function config(): array {
		if ( self::$config === null ) {
			/** @var array<string, mixed> $config */
			$config       = require self::DIR . '/config.php';
			self::$config = $config;
		}

		return self::$config;
	}

	public static function register_fields(): void {
		Fields::register();
	}

	public static function template_file(): string {
		$manifest_template = Appearance::block_template( self::name() );
		if ( $manifest_template !== '' ) {
			$resolved = Appearance::resolve_template( $manifest_template );
			if ( $resolved !== '' ) {
				return $resolved;
			}
		}

		return self::name() . '/template.twig';
	}

	/**
	 * @param array<string, mixed> $fields
	 * @return array<string, mixed>
	 */
	public static function build_context( array $fields, ?\Timber\Post $post = null ): array {
		return View::context( $fields, $post );
	}

	public static function register_block_type(): void {
		if ( ! function_exists( 'acf_register_block_type' ) ) {
			return;
		}

		$config      = self::config();
		$block_title = __( (string) $config['title'], 'asrekhodro' );

		acf_register_block_type(
			array(
				'name'            => self::name(),
				'title'           => $block_title,
				'render_callback' => static function ( $block ) use ( $block_title ) {
					BlockRenderer::render_acf_block( $block, self::template_file(), $block_title, self::name() );
				},
				'category'        => CinfoBlocks::BLOCK_CATEGORY,
				'icon'            => (string) ( $config['icon'] ?? 'block-default' ),
				'mode'            => (string) ( $config['mode'] ?? 'edit' ),
				'supports'        => (array) ( $config['supports'] ?? array() ),
				'keywords'        => (array) ( $config['keywords'] ?? array() ),
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function manifest_entry(): array {
		return (array) ( self::config()['manifest'] ?? array() );
	}

	public static function seed_carsinfo(): bool {
		return ! empty( self::config()['seed_carsinfo'] );
	}
}
