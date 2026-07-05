<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CarsInfo {

	public static function init(): void {
		add_filter( 'body_class', array( self::class, 'body_class' ) );
		add_filter( 'register_post_type_args', array( self::class, 'register_post_type_args' ), 10, 2 );
		add_action( 'save_post_carsinfo', array( self::class, 'seed_default_blocks' ), 10, 3 );
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
}
