<?php

namespace AsreKhodro\Theme\Appearances;

use AsreKhodro\Theme\Appearance;
use AsreKhodro\Theme\CinfoBlocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ModernBlueAppearance extends ClassicAppearance {

	public static function enqueue_assets(): void {
		parent::enqueue_assets();

		if ( file_exists( Appearance::dir( 'modern-blue' ) . '/assets/css/modern-blue.css' ) ) {
			wp_enqueue_style(
				'asrekhodro-modern-blue',
				Appearance::asset_url( 'css/modern-blue.css', 'modern-blue' ),
				array( 'asrekhodro-style' ),
				ASREKHODRO_THEME_VERSION
			);
		}

		self::enqueue_cinfo_hero_assets();
	}

	private static function enqueue_cinfo_hero_assets(): void {
		if ( ! is_page_template( 'page-car.php' ) && ! is_singular( 'carsinfo' ) && ! CinfoBlocks::page_has_cinfo_blocks() ) {
			return;
		}

		$path = Appearance::dir( 'modern-blue' ) . '/assets/css/cinfo-hero.css';
		if ( ! file_exists( $path ) ) {
			return;
		}

		wp_enqueue_style(
			'asrekhodro-modern-blue-cinfo-hero',
			Appearance::asset_url( 'css/cinfo-hero.css', 'modern-blue' ),
			array( 'asrekhodro-car-page' ),
			ASREKHODRO_THEME_VERSION
		);
	}
}
