<?php

namespace ABI\Translator\Core\Filters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central registry of post types that participate in on-demand translation.
 */
final class TranslatablePostTypes {

	/** @var list<string> */
	private const DEFAULT_TYPES = array(
		'post',
		'ak_video',
		'ak_review',
		'ak_magazine',
		'carsinfo',
	);

	/**
	 * @return list<string>
	 */
	public static function all(): array {
		/** @var list<string> $types */
		$types = apply_filters( 'abi_translator_translatable_post_types', self::DEFAULT_TYPES );

		return array_values( array_unique( array_filter( array_map( 'strval', $types ) ) ) );
	}

	public static function is_translatable( string $post_type ): bool {
		return in_array( $post_type, self::all(), true );
	}
}
