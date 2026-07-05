<?php

namespace AsreKhodro\Theme\AcfBlocks\CarsinfoDirectory;

use AsreKhodro\Theme\CarsInfoDirectory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class View {

	/**
	 * @param array<string, mixed> $fields
	 * @return array<string, mixed>
	 */
	public static function context( array $fields ): array {
		return CarsInfoDirectory::block_context( $fields );
	}
}
