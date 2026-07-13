<?php

namespace AsreKhodro\Theme\AcfBlocks\AkUnderTitle;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class View {

	/**
	 * @param array<string, mixed> $fields
	 * @return array<string, mixed>
	 */
	public static function context( array $fields, ?\Timber\Post $post = null ): array {
		unset( $post );

		$text = trim( (string) ( $fields['text'] ?? '' ) );

		return array(
			'under_title_text'        => $text,
			'under_title_has_content' => $text !== '',
		);
	}
}
