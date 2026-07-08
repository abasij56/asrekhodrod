<?php

namespace ABI\Translator\Core\Filters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABI\Translator\Core\Language\LanguageDetector;
use ABI\Translator\Core\Translation\TranslationService;

/**
 * Translate taxonomy term names and descriptions when a secondary language is active.
 */
final class TermFilters {

	/** @var list<string> */
	private const DEFAULT_TAXONOMIES = array( 'category' );

	private TranslationService $service;

	public function __construct( TranslationService $service ) {
		$this->service = $service;
	}

	public function register(): void {
		add_filter( 'get_term', array( $this, 'filter_term' ), 10, 2 );
		add_filter( 'get_terms', array( $this, 'filter_terms' ), 10, 4 );
	}

	/**
	 * @return list<string>
	 */
	public static function taxonomies(): array {
		/** @var list<string> $taxonomies */
		$taxonomies = apply_filters( 'abi_translator_translatable_taxonomies', self::DEFAULT_TAXONOMIES );

		return array_values( array_unique( array_filter( array_map( 'strval', $taxonomies ) ) ) );
	}

	public static function is_translatable_taxonomy( string $taxonomy ): bool {
		return in_array( $taxonomy, self::taxonomies(), true );
	}

	/**
	 * @param mixed $term
	 * @param mixed $taxonomy
	 * @return mixed
	 */
	public function filter_term( $term, $taxonomy ) {
		if ( LanguageDetector::is_default() || is_admin() || ! $term instanceof \WP_Term ) {
			return $term;
		}

		$tax = is_string( $taxonomy ) && $taxonomy !== '' ? $taxonomy : $term->taxonomy;
		if ( ! in_array( $tax, self::taxonomies(), true ) ) {
			return $term;
		}

		return $this->translate_term( $term );
	}

	/**
	 * @param mixed $terms
	 * @param mixed $taxonomies
	 * @param mixed $args
	 * @param mixed $term_query
	 * @return mixed
	 */
	public function filter_terms( $terms, $taxonomies, $args, $term_query ) {
		if ( LanguageDetector::is_default() || is_admin() || ! is_array( $terms ) || $terms === array() ) {
			return $terms;
		}

		$allowed = self::taxonomies();
		foreach ( $terms as $index => $term ) {
			if ( $term instanceof \WP_Term && in_array( $term->taxonomy, $allowed, true ) ) {
				$terms[ $index ] = $this->translate_term( $term );
			}
		}

		return $terms;
	}

	private function translate_term( \WP_Term $term ): \WP_Term {
		$lang = LanguageDetector::current();

		if ( trim( $term->name ) !== '' ) {
			$term->name = $this->service->translate_field(
				'term',
				(int) $term->term_id,
				'name',
				$term->name,
				$lang,
				array( 'field' => 'name', 'taxonomy' => $term->taxonomy )
			);
		}

		if ( trim( (string) $term->description ) !== '' ) {
			$term->description = $this->service->translate_field(
				'term',
				(int) $term->term_id,
				'description',
				(string) $term->description,
				$lang,
				array( 'field' => 'description', 'taxonomy' => $term->taxonomy )
			);
		}

		return $term;
	}
}
