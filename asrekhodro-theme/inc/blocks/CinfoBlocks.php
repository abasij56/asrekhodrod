<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstraps cinfo-* ACF blocks and shared editor behavior.
 */
final class CinfoBlocks {

	public const BLOCK_CATEGORY = 'asrekhodro';

	public static function init(): void {
		add_filter( 'block_categories_all', array( self::class, 'register_block_category' ), 10, 2 );
		add_filter( 'body_class', array( self::class, 'body_class' ) );

		if ( ! function_exists( 'acf_register_block_type' ) ) {
			return;
		}

		BlockRegistry::register_acf_block_types();
	}

	/**
	 * @param array<int, array<string, mixed>> $categories
	 * @return array<int, array<string, mixed>>
	 */
	public static function register_block_category( array $categories, $editor_context ): array {
		unset( $editor_context );

		foreach ( $categories as $category ) {
			if ( ( $category['slug'] ?? '' ) === self::BLOCK_CATEGORY ) {
				return $categories;
			}
		}

		$categories[] = array(
			'slug'  => self::BLOCK_CATEGORY,
			'title' => __( 'عصر خودرو', 'asrekhodro' ),
			'icon'  => null,
		);

		return $categories;
	}

	public static function register_field_groups(): void {
		BlockRegistry::register_acf_field_groups();
	}

	/**
	 * @return array<int, array{0: string, 1?: array<string, mixed>}>
	 */
	public static function carsinfo_editor_template(): array {
		return BlockRegistry::carsinfo_editor_template();
	}

	/**
	 * @return list<string>
	 */
	public static function block_names(): array {
		return BlockRegistry::acf_block_names();
	}

	/**
	 * @param array<int, string> $classes
	 * @return array<int, string>
	 */
	public static function body_class( array $classes ): array {
		if ( self::page_has_cinfo_blocks() ) {
			$classes[] = 'car-page';
			$classes[] = 'carsinfo-page';
		}

		return $classes;
	}

	public static function page_has_cinfo_blocks(): bool {
		if ( ! is_singular() ) {
			return false;
		}

		$post = get_post();
		if ( ! $post || ! has_blocks( $post->post_content ) ) {
			return false;
		}

		foreach ( parse_blocks( $post->post_content ) as $block ) {
			$name = (string) ( $block['blockName'] ?? '' );
			if ( str_starts_with( $name, 'acf/cinfo-' ) ) {
				return true;
			}
		}

		return false;
	}
}
