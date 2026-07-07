<?php

namespace ABI\Translator\Core\Filters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-request registry of post IDs that appear in listing/query contexts
 * (homepage blocks, archives, related, etc.). PostFilters uses it to decide
 * whether a the_title call belongs to a translatable listing item.
 */
final class ListContext {

	/** @var array<int, true> */
	private static array $ids = array();

	public static function add( int $id ): void {
		if ( $id > 0 ) {
			self::$ids[ $id ] = true;
		}
	}

	/**
	 * @param array<int, int> $ids
	 */
	public static function add_many( array $ids ): void {
		foreach ( $ids as $id ) {
			self::add( (int) $id );
		}
	}

	public static function has( int $id ): bool {
		return isset( self::$ids[ $id ] );
	}
}
