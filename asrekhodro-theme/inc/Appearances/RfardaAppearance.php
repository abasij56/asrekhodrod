<?php

namespace AsreKhodro\Theme\Appearances;

use AsreKhodro\Theme\Appearance;
use AsreKhodro\Theme\CinfoBlocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RfardaAppearance extends ClassicAppearance {

	public static function init(): void {
		add_filter( 'body_class', array( self::class, 'filter_body_class' ) );
	}

	/**
	 * @param array<int, string> $classes
	 * @return array<int, string>
	 */
	public static function filter_body_class( array $classes ): array {
		if ( is_front_page() ) {
			$classes[] = 'rf-page';
		}

		return $classes;
	}

	public static function enqueue_assets(): void {
		parent::enqueue_assets();

		if ( file_exists( Appearance::dir( 'rfarda' ) . '/assets/css/rfarda.css' ) ) {
			wp_enqueue_style(
				'asrekhodro-rfarda',
				Appearance::asset_url( 'css/rfarda.css', 'rfarda' ),
				array( 'asrekhodro-style' ),
				ASREKHODRO_THEME_VERSION
			);
		}

		if ( is_front_page() ) {
			wp_dequeue_style( 'asrekhodro-rf' );

			if ( file_exists( Appearance::dir( 'rfarda' ) . '/assets/css/rfarda-rf.css' ) ) {
				wp_enqueue_style(
					'asrekhodro-rfarda-rf',
					Appearance::asset_url( 'css/rfarda-rf.css', 'rfarda' ),
					array( 'asrekhodro-rfarda' ),
					ASREKHODRO_THEME_VERSION
				);
			}

			if ( file_exists( Appearance::dir( 'rfarda' ) . '/assets/js/rfarda-home.js' ) ) {
				wp_enqueue_script(
					'asrekhodro-rfarda-home',
					Appearance::asset_url( 'js/rfarda-home.js', 'rfarda' ),
					array(),
					ASREKHODRO_THEME_VERSION,
					array(
						'strategy'  => 'defer',
						'in_footer' => true,
					)
				);
			}
		}

		self::enqueue_cinfo_hero_assets();
	}

	private static function enqueue_cinfo_hero_assets(): void {
		if ( ! is_page_template( 'page-car.php' ) && ! is_singular( 'carsinfo' ) && ! CinfoBlocks::page_has_cinfo_blocks() ) {
			return;
		}

		$path = Appearance::dir( 'rfarda' ) . '/assets/css/cinfo-hero.css';
		if ( ! file_exists( $path ) ) {
			return;
		}

		wp_enqueue_style(
			'asrekhodro-rfarda-cinfo-hero',
			Appearance::asset_url( 'css/cinfo-hero.css', 'rfarda' ),
			array( 'asrekhodro-car-page' ),
			ASREKHODRO_THEME_VERSION
		);
	}
}
