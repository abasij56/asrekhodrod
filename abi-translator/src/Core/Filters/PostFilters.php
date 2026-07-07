<?php

namespace ABI\Translator\Core\Filters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABI\Translator\Core\Language\LanguageDetector;
use ABI\Translator\Core\Translation\TranslationService;

/**
 * Translate title, content (single only), and excerpt (lists) for all configured
 * post types when a secondary language is active. Homepage blocks are warmed via
 * ListTitleWarmer; UI labels are handled by Compat/AsreKhodro.
 */
final class PostFilters {

	private TranslationService $service;

	public function __construct( TranslationService $service ) {
		$this->service = $service;
	}

	public function register(): void {
		add_filter( 'the_title', array( $this, 'filter_title' ), 10, 2 );
		add_filter( 'the_content', array( $this, 'filter_content' ), 10, 1 );
		add_filter( 'get_the_excerpt', array( $this, 'filter_excerpt' ), 10, 2 );
	}

	/**
	 * @param mixed $post_id
	 */
	public function filter_title( string $title, $post_id = 0 ): string {
		if ( LanguageDetector::is_default() ) {
			return $title;
		}

		$post_id = (int) $post_id;
		$post    = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post instanceof \WP_Post || ! $this->is_translatable_title( $post_id, $post ) ) {
			return $title;
		}

		/** @param bool $should */
		$should = apply_filters( 'abi_translator_should_translate_post', true, $post, LanguageDetector::current() );
		if ( ! $should ) {
			return $title;
		}

		return $this->service->translate_field(
			$post->post_type,
			$post_id,
			'title',
			$title,
			LanguageDetector::current(),
			array( 'field' => 'title', 'post_type' => $post->post_type )
		);
	}

	public function filter_content( string $content ): string {
		if ( LanguageDetector::is_default() ) {
			return $content;
		}

		// Timber applies the_content without setting the loop, so we key off the
		// main queried single post instead of in_the_loop()/get_the_ID().
		if ( is_admin() || ! is_singular() || ! is_main_query() ) {
			return $content;
		}

		$queried = get_queried_object();
		if ( ! $queried instanceof \WP_Post || ! TranslatablePostTypes::is_translatable( $queried->post_type ) ) {
			return $content;
		}

		$post_id = (int) get_queried_object_id();
		if ( $post_id <= 0 ) {
			return $content;
		}

		/** @param bool $should */
		$should = apply_filters( 'abi_translator_should_translate_post', true, $queried, LanguageDetector::current() );
		if ( ! $should ) {
			return $content;
		}

		return $this->service->translate_field(
			$queried->post_type,
			$post_id,
			'content',
			$content,
			LanguageDetector::current(),
			array( 'field' => 'content', 'post_type' => $queried->post_type )
		);
	}

	/**
	 * Translate a post excerpt in list/archive/search contexts. Body is never
	 * translated in lists — only the excerpt string.
	 *
	 * @param mixed $post
	 */
	public function filter_excerpt( string $excerpt, $post = null ): string {
		if ( LanguageDetector::is_default() || is_admin() || trim( $excerpt ) === '' ) {
			return $excerpt;
		}

		$post = $post instanceof \WP_Post ? $post : get_post( $post );
		if ( ! $post instanceof \WP_Post || ! TranslatablePostTypes::is_translatable( $post->post_type ) ) {
			return $excerpt;
		}

		/** @param bool $should */
		$should = apply_filters( 'abi_translator_should_translate_post', true, $post, LanguageDetector::current() );
		if ( ! $should ) {
			return $excerpt;
		}

		return $this->service->translate_field(
			$post->post_type,
			(int) $post->ID,
			'excerpt',
			$excerpt,
			LanguageDetector::current(),
			array( 'field' => 'excerpt', 'post_type' => $post->post_type )
		);
	}

	/**
	 * Translate the title when it is either the main single post, or a post that
	 * appears in a warmed listing context (homepage blocks, archives, related).
	 */
	private function is_translatable_title( int $post_id, \WP_Post $post ): bool {
		if ( is_admin() || $post_id <= 0 || ! TranslatablePostTypes::is_translatable( $post->post_type ) ) {
			return false;
		}

		if ( ListContext::has( $post_id ) ) {
			return true;
		}

		if ( ! is_singular() || ! is_main_query() ) {
			return false;
		}

		$queried = get_queried_object();
		if ( ! $queried instanceof \WP_Post || ! TranslatablePostTypes::is_translatable( $queried->post_type ) ) {
			return false;
		}

		return $post_id === (int) get_queried_object_id();
	}
}
