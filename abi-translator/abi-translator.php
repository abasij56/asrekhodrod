<?php
/**
 * Plugin Name:       ABI Translator
 * Plugin URI:        https://asrekhodro.com/
 * Description:       AI-powered multilingual + SEO translation plugin. Phases 1–4: /en/ routing, CPT translation, SEO, homepage blocks, taxonomy terms.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Asre Khodro
 * Text Domain:       abi-translator
 * Domain Path:       /languages
 *
 * @package ABI\Translator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ABI_TRANSLATOR_VERSION', '0.1.0' );
define( 'ABI_TRANSLATOR_DB_VERSION', '1' );
define( 'ABI_TRANSLATOR_FILE', __FILE__ );
define( 'ABI_TRANSLATOR_DIR', plugin_dir_path( __FILE__ ) );
define( 'ABI_TRANSLATOR_URL', plugin_dir_url( __FILE__ ) );
define( 'ABI_TRANSLATOR_OPTION', 'abi_translator_settings' );

/**
 * Minimal PSR-4 style autoloader for the ABI\Translator namespace.
 *
 * ABI\Translator\Core\Foo  ->  src/Core/Foo.php
 */
spl_autoload_register(
	static function ( string $class ): void {
		$prefix   = 'ABI\\Translator\\';
		$base_dir = ABI_TRANSLATOR_DIR . 'src/';

		if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

register_activation_hook(
	__FILE__,
	static function (): void {
		\ABI\Translator\Core\Installer::activate();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		\ABI\Translator\Core\Installer::deactivate();
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		\ABI\Translator\Core\Plugin::instance()->boot();
	}
);
