<?php

namespace ABI\Translator\Core\Translation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Produces a stable sha256 hash of the source (Persian) text so that edits to
 * the original invalidate the cached translation on the next request.
 */
final class ContentHasher {

	/**
	 * Normalise whitespace so trivial formatting changes don't invalidate cache.
	 */
	public static function hash( string $text ): string {
		$normalized = trim( preg_replace( '/\s+/u', ' ', $text ) ?? $text );

		return hash( 'sha256', $normalized );
	}
}
