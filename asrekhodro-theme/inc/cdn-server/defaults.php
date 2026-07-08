<?php
/**
 * Editable defaults for the CDN Server module.
 *
 * Change upload rules, allowed types, size limits and the remote path pattern here.
 * Everything is overridable through the "CDN Server" tab in theme settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	// Feature flags / connection.
	'enabled'          => false,
	'public_base_url'  => '',
	'protocol'         => 'ftp', // ftp | sftp
	'host'             => '',
	'port'             => 21,
	'user'             => '',
	'pass'             => '',
	'remote_base_path' => '/',

	// Upload limits.
	'max_upload_mb'    => 50,

	// Allowed MIME types (one per line in settings; array here).
	'allowed_types'    => array(
		'image/jpeg',
		'image/png',
		'image/gif',
		'image/webp',
		'video/mp4',
		'video/webm',
	),

	/*
	 * Remote path pattern (relative to remote_base_path).
	 * Tokens: {type} {Y} {m} {d} {basename} {ext}
	 *   {type}     = Image | Video | File (derived from mime)
	 *   {Y}/{m}/{d} = Jalali (Shamsi) date parts, zero-padded
	 *   {basename} = legacy unique name, e.g. 140407081430521234567890
	 */
	'path_pattern'     => 'Uploaded/{type}/{Y}/{m}/{d}/{basename}.{ext}',

	// Length of the random suffix appended to filenames.
	'rand_length'      => 8,

	// FTP/SFTP connection timeout (seconds).
	'timeout'          => 20,
);
