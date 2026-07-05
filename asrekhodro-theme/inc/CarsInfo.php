<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CarsInfo {

	public static function init(): void {
		add_filter( 'body_class', array( self::class, 'body_class' ) );
		add_filter( 'register_post_type_args', array( self::class, 'register_post_type_args' ), 10, 2 );
		add_filter( 'default_comment_status', array( self::class, 'default_comment_status' ), 10, 2 );
		add_action( 'save_post_carsinfo', array( self::class, 'seed_default_blocks' ), 10, 3 );
	}

	public static function default_comment_status( string $status, string $post_type ): string {
		if ( $post_type === 'carsinfo' ) {
			return 'open';
		}

		return $status;
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	public static function register_post_type_args( array $args, string $post_type ): array {
		if ( $post_type !== 'carsinfo' ) {
			return $args;
		}

		$args['template'] = CinfoBlocks::carsinfo_editor_template();

		return $args;
	}

	/**
	 * @param array<int, string> $classes
	 * @return array<int, string>
	 */
	public static function body_class( array $classes ): array {
		if ( is_singular( 'carsinfo' ) ) {
			$classes[] = 'car-page';
			$classes[] = 'carsinfo-page';

			if ( CinfoToc::show_for_post( (int) get_queried_object_id() ) ) {
				$classes[] = 'carsinfo-page--has-toc';
			}
		}

		return $classes;
	}

	public static function seed_default_blocks( int $post_id, \WP_Post $post, bool $update ): void {
		unset( $update );

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( trim( (string) $post->post_content ) !== '' ) {
			return;
		}

		$content = self::default_block_content();
		if ( $content === '' ) {
			return;
		}

		remove_action( 'save_post_carsinfo', array( self::class, 'seed_default_blocks' ), 10 );

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $content,
			)
		);

		add_action( 'save_post_carsinfo', array( self::class, 'seed_default_blocks' ), 10, 3 );
	}

	public static function default_block_content(): string {
		return BlockRegistry::default_block_content( 'carsinfo' );
	}

	public static function country_category_id(): int {
		return self::option_category_id( 'carsinfo_country_category' );
	}

	public static function brand_category_id(): int {
		return self::option_category_id( 'carsinfo_brand_category' );
	}

	public static function country_category(): ?\WP_Term {
		return self::option_category_term( 'carsinfo_country_category' );
	}

	public static function brand_category(): ?\WP_Term {
		return self::option_category_term( 'carsinfo_brand_category' );
	}

	private static function option_category_id( string $field_name ): int {
		if ( ! function_exists( 'get_field' ) ) {
			return 0;
		}

		$value = get_field( $field_name, 'option' );

		return is_numeric( $value ) ? (int) $value : 0;
	}

	private static function option_category_term( string $field_name ): ?\WP_Term {
		$term_id = self::option_category_id( $field_name );
		if ( $term_id <= 0 ) {
			return null;
		}

		$term = get_term( $term_id, 'category' );

		return ( $term instanceof \WP_Term && ! is_wp_error( $term ) ) ? $term : null;
	}
}
