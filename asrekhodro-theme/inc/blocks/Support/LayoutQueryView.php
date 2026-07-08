<?php

namespace AsreKhodro\Theme\AcfBlocks\Support;

use AsreKhodro\Theme\BlockDataResolver;
use AsreKhodro\Theme\BlockRegistry;
use AsreKhodro\Theme\LayoutSchema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LayoutQueryView {

	/**
	 * @param array<string, mixed> $fields
	 * @return array<string, mixed>
	 */
	public static function context( string $block_name, array $fields, ?\Timber\Post $post = null ): array {
		unset( $post );

		$placement = LayoutQueryFields::placement_from_fields( $fields );
		$placement = LayoutSchema::merge_placement_defaults( $block_name, $placement );
		$placement['block']            = $block_name;
		$placement['visibility_class'] = LayoutSchema::device_visibility_class( $placement );
		$placement['partial']          = BlockRegistry::resolve_partial( $block_name );

		$resolved  = BlockDataResolver::resolve( $block_name, $placement );
		$placement = array_merge( $placement, $resolved );

		return array_merge( $resolved, array( 'placement' => $placement ) );
	}
}
