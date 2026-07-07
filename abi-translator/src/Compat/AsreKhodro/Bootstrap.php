<?php

namespace ABI\Translator\Compat\AsreKhodro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABI\Translator\Core\Translation\TranslationService;

/**
 * Site-specific glue for the Asre Khodro theme. Loads only when that theme is
 * active, so Core stays sellable/standalone. Wires the Timber + block bridges.
 */
final class Bootstrap {

	private TranslationService $service;

	public function __construct( TranslationService $service ) {
		$this->service = $service;
	}

	/**
	 * Whether the Asre Khodro theme (or a child of it) is active.
	 */
	public static function is_theme_active(): bool {
		if ( class_exists( 'AsreKhodro\\Theme\\Theme' ) ) {
			return true;
		}

		$slugs = array( get_template(), get_stylesheet() );

		return in_array( 'asrekhodro-theme', $slugs, true );
	}

	public function register(): void {
		if ( ! self::is_theme_active() ) {
			return;
		}

		$labels = new BlockLabelsBridge( $this->service );
		$labels->register();

		( new TimberBridge( $this->service, $labels ) )->register();
	}
}
