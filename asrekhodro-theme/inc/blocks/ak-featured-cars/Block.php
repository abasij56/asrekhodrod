<?php

namespace AsreKhodro\Theme\AcfBlocks\AkFeaturedCars;

use AsreKhodro\Theme\AcfBlocks\Support\AkGutenbergBlock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Block extends AkGutenbergBlock {

	protected static function block_dir(): string {
		return __DIR__;
	}

	public static function register_fields(): void {
		Fields::register( self::name() );
	}

	/**
	 * @param array<string, mixed> $fields
	 * @return array<string, mixed>
	 */
	public static function build_context( array $fields, ?\Timber\Post $post = null ): array {
		return View::context( $fields, $post );
	}
}
