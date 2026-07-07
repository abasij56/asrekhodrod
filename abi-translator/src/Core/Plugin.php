<?php

namespace ABI\Translator\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABI\Translator\Compat\AsreKhodro\Bootstrap as AsreKhodroCompat;
use ABI\Translator\Core\Admin\SettingsPage;
use ABI\Translator\Core\Filters\ListTitleWarmer;
use ABI\Translator\Core\Filters\PostFilters;
use ABI\Translator\Core\Frontend\LanguageSwitcher;
use ABI\Translator\Core\Language\LanguageRouter;
use ABI\Translator\Core\SEO\Canonical;
use ABI\Translator\Core\SEO\Hreflang;
use ABI\Translator\Core\Support\Logger;
use ABI\Translator\Core\Translation\TranslationCache;
use ABI\Translator\Core\Translation\TranslationRepository;
use ABI\Translator\Core\Translation\TranslationService;

/**
 * Composition root: wires the pieces together on plugins_loaded.
 * All work is wrapped so a failure here can never take down the site.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	private bool $booted = false;

	public static function instance(): Plugin {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		try {
			load_plugin_textdomain( 'abi-translator', false, dirname( plugin_basename( ABI_TRANSLATOR_FILE ) ) . '/languages' );

			Installer::maybe_upgrade();

			// Detect the /en/ prefix now (still on plugins_loaded, before parse_request).
			$router = new LanguageRouter();
			$router->detect_and_strip();
			$router->register();

			// Admin settings screen.
			if ( is_admin() ) {
				( new SettingsPage() )->register();
			}

			// Front-end translation filters (guarded internally to fa = no-op).
			$repository = new TranslationRepository();
			$cache      = new TranslationCache();
			$service    = new TranslationService( $repository, $cache );

			( new PostFilters( $service ) )->register();

			// Phase 2 + 3 front-end features.
			if ( ! is_admin() ) {
				// Phase 2: SEO + language switcher.
				( new Hreflang() )->register();
				( new Canonical() )->register();
				( new LanguageSwitcher() )->register();

				// Phase 3: batch-warm list titles + site-specific Compat bridges.
				( new ListTitleWarmer( $service ) )->register();
				( new AsreKhodroCompat( $service ) )->register();
			}
		} catch ( \Throwable $e ) {
			Logger::error( 'Boot failed', array( 'reason' => $e->getMessage() ) );
			// Swallow: the site must keep working even if the plugin can't boot.
		}
	}
}
