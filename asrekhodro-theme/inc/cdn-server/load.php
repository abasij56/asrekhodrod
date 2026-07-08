<?php
/**
 * CDN Server module bootstrap.
 *
 * Requires all module files. Call \AsreKhodro\Theme\CdnServer\Module::init()
 * after this file to activate hooks. Remove this require from Theme.php to
 * disable the whole feature.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cdn_dir = ASREKHODRO_THEME_DIR . '/inc/cdn-server';

require_once $cdn_dir . '/Config.php';
require_once $cdn_dir . '/PathBuilder.php';
require_once $cdn_dir . '/Connection/ConnectionInterface.php';
require_once $cdn_dir . '/Connection/FtpConnection.php';
require_once $cdn_dir . '/Connection/SftpConnection.php';
require_once $cdn_dir . '/Uploader.php';
require_once $cdn_dir . '/ExternalMedia.php';
require_once $cdn_dir . '/Admin/UploadUi.php';
require_once $cdn_dir . '/Admin/Ajax.php';
require_once $cdn_dir . '/Settings.php';
require_once $cdn_dir . '/Module.php';
