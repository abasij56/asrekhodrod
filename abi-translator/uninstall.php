<?php
/**
 * Uninstall routine: drop the translations table and remove options.
 *
 * @package ABI\Translator
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$table = $wpdb->prefix . 'abi_translations';

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name cannot be a bound parameter.
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

delete_option( 'abi_translator_settings' );
delete_option( 'abi_translator_db_version' );
