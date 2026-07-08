<?php

namespace AsreKhodro\Theme\AcfBlocks\AkAdStrip;

use AsreKhodro\Theme\AcfBlocks\Support\AkGutenbergBlock;
use AsreKhodro\Theme\AcfBlocks\Support\LayoutQueryFields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Block extends AkGutenbergBlock {

	protected static function block_dir(): string {
		return __DIR__;
	}

	public static function register_fields(): void {
		$config   = self::config();
		$defaults = is_array( $config['defaults'] ?? null ) ? $config['defaults'] : array();

		LayoutQueryFields::register(
			'group_ak_ad_strip_gutenberg',
			self::name(),
			'field_ak_ad_strip_',
			$defaults
		);
	}

	/**
	 * @param array<string, mixed> $fields
	 * @return array<string, mixed>
	 */
	public static function build_context( array $fields, ?\Timber\Post $post = null ): array {
		return View::context( $fields, $post );
	}
}
