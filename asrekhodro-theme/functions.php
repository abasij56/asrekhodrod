<?php
/**
 * Asre Khodro Theme bootstrap.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ASREKHODRO_THEME_VERSION', '1.5.1' );
define( 'ASREKHODRO_THEME_DIR', get_template_directory() );
define( 'ASREKHODRO_THEME_URI', get_template_directory_uri() );

$autoload = ASREKHODRO_THEME_DIR . '/vendor/autoload.php';
if ( ! file_exists( $autoload ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Asre Khodro theme requires Composer. Run: composer install in the theme directory.', 'asrekhodro' );
			echo '</p></div>';
		}
	);
	return;
}

require_once $autoload;

require_once ASREKHODRO_THEME_DIR . '/inc/Appearance.php';

\Timber\Timber::init();
\Timber\Timber::$dirname = \AsreKhodro\Theme\Appearance::bootstrap_timber_locations();

require_once ASREKHODRO_THEME_DIR . '/inc/Theme.php';

AsreKhodro\Theme\Theme::init();
