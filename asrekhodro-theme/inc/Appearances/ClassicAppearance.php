<?php

namespace AsreKhodro\Theme\Appearances;

use AsreKhodro\Theme\AboutPage;
use AsreKhodro\Theme\Appearance;
use AsreKhodro\Theme\CarInfo3d;
use AsreKhodro\Theme\CarsInfoDirectory;
use AsreKhodro\Theme\CinfoBlocks;
use AsreKhodro\Theme\Comments;
use AsreKhodro\Theme\Setup;
use AsreKhodro\Theme\SinglePost;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ClassicAppearance {

	public static function enqueue_assets(): void {
		wp_enqueue_style(
			'asrekhodro-fonts',
			Appearance::asset_url( 'css/fonts.css' ),
			array(),
			ASREKHODRO_THEME_VERSION
		);

		wp_enqueue_style(
			'asrekhodro-style',
			Appearance::asset_url( 'css/style.css' ),
			array( 'asrekhodro-fonts' ),
			ASREKHODRO_THEME_VERSION
		);

		if ( is_page_template( 'page-contact.php' ) ) {
			wp_enqueue_style(
				'asrekhodro-contact',
				Appearance::asset_url( 'css/contact.css' ),
				array( 'asrekhodro-style' ),
				ASREKHODRO_THEME_VERSION
			);
		}

		if ( AboutPage::is_about_page() ) {
			wp_enqueue_style(
				'asrekhodro-about',
				Appearance::asset_url( 'css/about.css' ),
				array( 'asrekhodro-style' ),
				ASREKHODRO_THEME_VERSION
			);
		}

		if ( SinglePost::uses_main_backdrop() ) {
			wp_enqueue_style(
				'asrekhodro-single-backdrop',
				Appearance::asset_url( 'css/single-backdrop.css' ),
				array( 'asrekhodro-style' ),
				ASREKHODRO_THEME_VERSION
			);
		}

		if ( is_404() ) {
			wp_enqueue_style(
				'asrekhodro-not-found',
				Appearance::asset_url( 'css/not-found.css' ),
				array( 'asrekhodro-style' ),
				ASREKHODRO_THEME_VERSION
			);
		}

		if ( CarsInfoDirectory::needs_assets() ) {
			wp_enqueue_style(
				'asrekhodro-carsinfo-directory',
				Appearance::asset_url( 'css/carsinfo-directory.css' ),
				array( 'asrekhodro-style' ),
				ASREKHODRO_THEME_VERSION
			);

			wp_enqueue_script(
				'asrekhodro-carsinfo-directory',
				Appearance::asset_url( 'js/carsinfo-directory.js' ),
				array(),
				ASREKHODRO_THEME_VERSION,
				array(
					'strategy'  => 'defer',
					'in_footer' => true,
				)
			);
		}

		if ( Comments::needs_assets() ) {
			wp_enqueue_style(
				'asrekhodro-comments',
				Appearance::asset_url( 'css/comments.css' ),
				array( 'asrekhodro-style' ),
				ASREKHODRO_THEME_VERSION
			);

			wp_enqueue_script(
				'asrekhodro-comments',
				Appearance::asset_url( 'js/comments.js' ),
				array(),
				ASREKHODRO_THEME_VERSION,
				array(
					'strategy'  => 'defer',
					'in_footer' => true,
				)
			);

			wp_localize_script(
				'asrekhodro-comments',
				'akComments',
				Comments::script_config()
			);
		}

		if ( is_page_template( 'page-car.php' ) || is_singular( 'carsinfo' ) || CinfoBlocks::page_has_cinfo_blocks() ) {
			if ( ! CarInfo3d::is_3d_template() ) {
				wp_enqueue_style(
					'asrekhodro-car-page',
					Appearance::asset_url( 'css/car-page.css' ),
					array( 'asrekhodro-style' ),
					ASREKHODRO_THEME_VERSION
				);

				wp_enqueue_script(
					'asrekhodro-car-page',
					Appearance::asset_url( 'js/car-page.js' ),
					array(),
					ASREKHODRO_THEME_VERSION,
					array(
						'strategy'  => 'defer',
						'in_footer' => true,
					)
				);
			}

			if ( CarInfo3d::is_2d_template() ) {
				wp_enqueue_style(
					'asrekhodro-carinfo2d',
					Appearance::asset_url( 'css/carinfo2d.css' ),
					array( 'asrekhodro-car-page' ),
					ASREKHODRO_THEME_VERSION
				);

				wp_enqueue_script(
					'asrekhodro-carinfo2d',
					Appearance::asset_url( 'js/carinfo2d.js' ),
					array(),
					ASREKHODRO_THEME_VERSION,
					array(
						'strategy'  => 'defer',
						'in_footer' => true,
					)
				);
			}
		}

		if ( CarInfo3d::is_3d_template() ) {
			wp_enqueue_style(
				'asrekhodro-carinfo3d',
				Appearance::asset_url( 'css/carinfo3d.css' ),
				array( 'asrekhodro-style' ),
				ASREKHODRO_THEME_VERSION
			);

			if ( CarInfo3d::is_3d2() ) {
				wp_enqueue_style(
					'asrekhodro-car-page',
					Appearance::asset_url( 'css/car-page.css' ),
					array( 'asrekhodro-style' ),
					ASREKHODRO_THEME_VERSION
				);

				wp_enqueue_style(
					'asrekhodro-carinfo3d2',
					Appearance::asset_url( 'css/carinfo3d2.css' ),
					array( 'asrekhodro-carinfo3d', 'asrekhodro-car-page' ),
					ASREKHODRO_THEME_VERSION
				);
			}

			wp_enqueue_script(
				'asrekhodro-car-page',
				Appearance::asset_url( 'js/car-page.js' ),
				array(),
				ASREKHODRO_THEME_VERSION,
				array(
					'strategy'  => 'defer',
					'in_footer' => true,
				)
			);

			wp_enqueue_script(
				'asrekhodro-carinfo3d-scene',
				Appearance::asset_url( 'js/carinfo3d-scene.js' ),
				array(),
				ASREKHODRO_THEME_VERSION,
				array(
					'strategy'  => 'defer',
					'in_footer' => true,
				)
			);
			wp_script_add_data( 'asrekhodro-carinfo3d-scene', 'type', 'module' );
		}

		if ( is_front_page() ) {
			wp_enqueue_style(
				'st-page-flip',
				Appearance::asset_url( 'vendor/stPageFlip.css' ),
				array( 'asrekhodro-style' ),
				'2.0.7'
			);

			wp_enqueue_script(
				'page-flip',
				Appearance::asset_url( 'vendor/page-flip.browser.js' ),
				array(),
				'2.0.7',
				array(
					'strategy'  => 'defer',
					'in_footer' => true,
				)
			);

			wp_enqueue_script(
				'asrekhodro-featured-magazine-flip',
				Appearance::asset_url( 'js/featured-magazine-flip.js' ),
				array( 'page-flip' ),
				ASREKHODRO_THEME_VERSION,
				array(
					'strategy'  => 'defer',
					'in_footer' => true,
				)
			);
		}

		wp_enqueue_script(
			'asrekhodro-app',
			Appearance::asset_url( 'js/app.js' ),
			array(),
			ASREKHODRO_THEME_VERSION,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);

		wp_localize_script(
			'asrekhodro-app',
			'akTheme',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'searchNonce' => wp_create_nonce( 'ak_search' ),
				'homeUrl'     => home_url( '/' ),
			)
		);
	}

	public static function preload_font(): void {
		$url = Appearance::asset_url( 'fonts/Vazirmatn-Variable.woff2' );
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( $url )
		);
	}
}
