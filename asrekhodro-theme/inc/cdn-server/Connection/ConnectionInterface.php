<?php

namespace AsreKhodro\Theme\CdnServer\Connection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface ConnectionInterface {

	/**
	 * Upload a local file to an absolute remote path, creating directories as needed.
	 *
	 * @return true|\WP_Error
	 */
	public function upload( string $local_path, string $remote_absolute_path ): bool|\WP_Error;

	/**
	 * Verify credentials / connectivity.
	 *
	 * @return true|\WP_Error
	 */
	public function test(): bool|\WP_Error;
}
