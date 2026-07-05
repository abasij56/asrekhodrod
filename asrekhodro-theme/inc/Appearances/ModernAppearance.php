<?php

namespace AsreKhodro\Theme\Appearances;

use AsreKhodro\Theme\Appearance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ModernAppearance extends ClassicAppearance {

	public static function enqueue_assets(): void {
		parent::enqueue_assets();

		if ( file_exists( Appearance::dir( 'modern' ) . '/assets/css/modern-overrides.css' ) ) {
			wp_enqueue_style(
				'asrekhodro-modern',
				Appearance::asset_url( 'css/modern-overrides.css', 'modern' ),
				array( 'asrekhodro-style' ),
				ASREKHODRO_THEME_VERSION
			);
		}
	}
}
