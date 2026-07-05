<?php

namespace AsreKhodro\Theme\AcfBlocks\CinfoFaq;

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

		$title      = trim( (string) ( $fields['title'] ?? '' ) );
		$open_first = ! empty( $fields['open_first'] );
		$items      = array();

		foreach ( (array) ( $fields['faq_items'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$question = trim( (string) ( $row['question'] ?? '' ) );
			$answer   = trim( (string) ( $row['answer'] ?? '' ) );

			if ( $question === '' && $answer === '' ) {
				continue;
			}

			$items[] = array(
				'question' => $question,
				'answer'   => $answer,
			);
		}

		$default_anchor = (string) ( Block::config()['default_anchor'] ?? 'faq' );

		return array(
			'faq_title'          => $title,
			'faq_items'          => $items,
			'faq_open_first'     => $open_first,
			'faq_default_anchor' => $default_anchor,
			'faq_has_content'    => $title !== '' || $items !== array(),
		);
	}
}
