<?php

namespace AsreKhodro\Theme\CdnServer;

use AsreKhodro\Theme\CdnServer\Admin\Ajax;
use AsreKhodro\Theme\CdnServer\Admin\UploadUi;
use AsreKhodro\Theme\ExternalMedia;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstraps the CDN Server module: external media + FTP/SFTP upload.
 *
 * Everything for this feature lives under inc/cdn-server/. The theme only needs
 * to require load.php and call Module::init().
 */
final class Module {

	public static function init(): void {
		// Core external-media attachment behaviour (works with or without CDN).
		add_filter( 'get_attached_file', array( ExternalMedia::class, 'filter_attached_file' ), 10, 2 );

		// Admin UI + AJAX.
		UploadUi::init();
		Ajax::init();

		// Settings tab (appended to the main theme-options group) + its assets.
		add_filter( 'ak_theme_options_fields', array( Settings::class, 'append_option_fields' ) );
		add_action( 'admin_enqueue_scripts', array( Settings::class, 'enqueue' ) );

		// Bridge: let ImporterBridge resolve media through the CDN base URL.
		add_filter( 'ak_media_base_url', array( self::class, 'filter_media_base_url' ) );
	}

	/**
	 * Prefer the configured public CDN base URL over the legacy constant.
	 */
	public static function filter_media_base_url( mixed $base ): string {
		$configured = Config::get_public_base_url();

		return $configured !== '' ? $configured : (string) $base;
	}
}
