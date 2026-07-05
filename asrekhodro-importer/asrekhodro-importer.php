<?php
/**
 * Plugin Name: AsreKhodro Importer
 * Description: Import sample JSON export from SQL Server into WordPress (posts, categories, tags, comments). No media files — external URLs only.
 * Version: 0.3.0
 * Author: AsreKhodro Migration
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ASREKHODRO_IMPORTER_VERSION', '0.3.0' );
define( 'ASREKHODRO_IMPORTER_DIR', plugin_dir_path( __FILE__ ) );
define( 'ASREKHODRO_IMPORTER_DEFAULT_IMPORT_DIR', WP_CONTENT_DIR . '/asrekhodro-import' );

require_once ASREKHODRO_IMPORTER_DIR . 'includes/class-import-chunks.php';
require_once ASREKHODRO_IMPORTER_DIR . 'includes/class-legacy-redirects.php';
require_once ASREKHODRO_IMPORTER_DIR . 'includes/class-import-reset.php';
require_once ASREKHODRO_IMPORTER_DIR . 'includes/class-post-types.php';
require_once ASREKHODRO_IMPORTER_DIR . 'includes/class-importer.php';
require_once ASREKHODRO_IMPORTER_DIR . 'includes/class-import-session.php';
require_once ASREKHODRO_IMPORTER_DIR . 'includes/class-admin.php';
require_once ASREKHODRO_IMPORTER_DIR . 'includes/class-cli.php';

AsreKhodro_Importer_Post_Types::init();
AsreKhodro_Importer_Admin::init();
AsreKhodro_Import_Session::init();
