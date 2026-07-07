<?php

namespace ABI\Translator\Core\Filters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABI\Translator\Core\Language\LanguageDetector;
use ABI\Translator\Core\Translation\TranslationService;

/**
 * Hooks `the_posts` (fired by every WP_Query, including theme block queries)
 * to batch-warm the translation cache for post titles and mark those posts as
 * translatable list items. Titles then translate through the standard the_title
 * filter (Timber's {{ post.title }}), with one DB read instead of N.
 */
final class ListTitleWarmer {

	private TranslationService $service;

	public function __construct( TranslationService $service ) {
		$this->service = $service;
	}

	public function register(): void {
		add_filter( 'the_posts', array( $this, 'warm' ), 10, 1 );
	}

	/**
	 * @param mixed $posts
	 * @return mixed
	 */
	public function warm( $posts ) {
		if ( is_admin() || LanguageDetector::is_default() || ! is_array( $posts ) || $posts === array() ) {
			return $posts;
		}

		/** @var array<string, array<int, string>> $by_type */
		$by_type = array();

		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post || ! TranslatablePostTypes::is_translatable( $post->post_type ) ) {
				continue;
			}

			$id = (int) $post->ID;
			ListContext::add( $id );
			$by_type[ $post->post_type ][ $id ] = (string) $post->post_title;
		}

		$lang = LanguageDetector::current();
		foreach ( $by_type as $object_type => $id_to_text ) {
			$this->service->warm( $object_type, $id_to_text, 'title', $lang );
		}

		return $posts;
	}
}
