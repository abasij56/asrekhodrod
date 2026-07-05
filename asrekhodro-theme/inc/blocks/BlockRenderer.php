<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders ACF blocks using appearance manifest templates and granular data sections.
 */
final class BlockRenderer {

	/**
	 * @param array<string, mixed> $block
	 */
	public static function render_homepage_block( array $block, string $block_name ): void {
		$config   = Appearance::block_config( $block_name );
		$template = (string) ( $config['template'] ?? '' );

		if ( $template === '' ) {
			return;
		}

		$placement = array( 'block' => $block_name );
		if ( $block_name === 'ak-news-list' && function_exists( 'get_field' ) ) {
			$count = (int) get_field( 'news_count' );
			if ( $count > 0 ) {
				$placement['count'] = $count;
			}
		}

		$ctx = BlockDataResolver::resolve( $block_name, $placement );

		$ctx['block']      = $block;
		$ctx['is_preview'] = ! empty( $block['is_preview'] );

		\Timber\Timber::render( Appearance::resolve_template( $template ), $ctx );
	}

	/**
	 * Renders ACF blocks whose fields live on the block (cinfo-* and similar).
	 *
	 * @param array<string, mixed> $block
	 */
	public static function render_acf_block( array $block, string $template, string $block_title = '', string $block_name = '' ): void {
		$fields = self::acf_block_fields( $block );
		$post   = \Timber\Timber::get_post();

		$ctx = \Timber\Timber::context();
		$ctx['block']       = $block;
		$ctx['is_preview']  = ! empty( $block['is_preview'] );
		$ctx['fields']      = $fields;
		$ctx['block_title'] = $block_title;
		$ctx['post']        = $post;

		if ( $block_name !== '' ) {
			$ctx = array_merge( $ctx, BlockRegistry::build_acf_context( $block_name, $fields, $post ) );
		}

		if ( $template === '' && $block_name !== '' ) {
			$template = BlockRegistry::acf_template_file( $block_name );
		}

		if ( $template === '' ) {
			return;
		}

		\Timber\Timber::render( $template, $ctx );
	}

	/**
	 * @param array<string, mixed> $block
	 * @return array<string, mixed>
	 */
	private static function acf_block_fields( array $block ): array {
		if ( ! function_exists( 'get_fields' ) ) {
			return array();
		}

		if (
			! empty( $block['id'] )
			&& ! empty( $block['data'] )
			&& is_array( $block['data'] )
			&& function_exists( 'acf_setup_meta' )
		) {
			acf_setup_meta( $block['data'], $block['id'], true );
		}

		$fields = get_fields() ?: array();

		if ( ! empty( $block['id'] ) && function_exists( 'acf_reset_meta' ) ) {
			acf_reset_meta( $block['id'] );
		}

		return is_array( $fields ) ? $fields : array();
	}
}
