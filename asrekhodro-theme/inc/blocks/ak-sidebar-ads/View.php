<?php

namespace AsreKhodro\Theme\AcfBlocks\AkSidebarAds;

use AsreKhodro\Theme\AcfBlocks\Support\LayoutQueryView;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class View {

	/**
	 * @param array<string, mixed> $fields
	 * @return array<string, mixed>
	 */
	public static function context( array $fields, ?\Timber\Post $post = null ): array {
		return LayoutQueryView::context( Block::name(), $fields, $post );
	}
}
