<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Backward-compatible facade for full homepage context.
 */
final class Homepage {

	/**
	 * @return array<string, mixed>
	 */
	public static function get_context(): array {
		return LayoutEngine::page_context( 'front_page' );
	}
}
