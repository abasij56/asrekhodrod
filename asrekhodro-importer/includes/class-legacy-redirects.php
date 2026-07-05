<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build theme legacy-redirects.json from import tag + category data (old URL → new URL only).
 */
final class AsreKhodro_Legacy_Redirects {

	public const FILENAME = 'legacy-redirects.json';

	public static function default_path(): string {
		return trailingslashit( get_template_directory() ) . self::FILENAME;
	}

	/**
	 * @return array{written: bool, path: string, count: int, tags: int, categories: int, missing_tags: int, missing_categories: int}
	 */
	public static function regenerate_from_tags( ?string $import_dir = null, ?AsreKhodro_Importer $importer = null ): array {
		return self::regenerate( $import_dir, $importer );
	}

	/**
	 * @return array{written: bool, path: string, count: int, tags: int, categories: int, missing_tags: int, missing_categories: int}
	 */
	public static function regenerate( ?string $import_dir = null, ?AsreKhodro_Importer $importer = null ): array {
		$import_dir = $import_dir ?: ASREKHODRO_IMPORTER_DEFAULT_IMPORT_DIR;
		$path       = self::default_path();

		$redirects          = array();
		$missing_tags       = 0;
		$missing_categories = 0;
		$tag_count          = 0;
		$category_count     = 0;

		foreach ( self::collect_keyword_tag_names( $import_dir ) as $keyword_id => $tag_name ) {
			if ( $keyword_id <= 0 || $tag_name === '' ) {
				continue;
			}

			$target_path = self::resolve_tag_path( $tag_name );
			if ( $target_path === '' ) {
				++$missing_tags;
				continue;
			}

			$redirects[ '/Home/Keyword/' . $keyword_id ] = $target_path;
			++$tag_count;
		}

		foreach ( self::collect_category_ids( $import_dir ) as $category_id ) {
			if ( $category_id <= 0 ) {
				continue;
			}

			$target_path = self::resolve_category_path( $category_id );
			if ( $target_path === '' ) {
				++$missing_categories;
				continue;
			}

			$redirects[ '/Home/Category/' . $category_id ] = $target_path;
			++$category_count;
		}

		ksort( $redirects, SORT_STRING );

		$written = false !== file_put_contents(
			$path,
			wp_json_encode( $redirects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		);

		if ( $importer ) {
			$importer->log(
				sprintf(
					'Legacy redirects: %d entries written to %s (%d tags, %d categories; skipped %d keywords, %d categories without WP term).',
					count( $redirects ),
					basename( $path ),
					$tag_count,
					$category_count,
					$missing_tags,
					$missing_categories
				)
			);
		}

		return array(
			'written'            => $written,
			'path'               => $path,
			'count'              => count( $redirects ),
			'tags'               => $tag_count,
			'categories'         => $category_count,
			'missing_tags'       => $missing_tags,
			'missing_categories' => $missing_categories,
		);
	}

	/**
	 * @return array<int, string>
	 */
	private static function collect_keyword_tag_names( string $import_dir ): array {
		/** @var array<int, array<string, int>> $counts */
		$counts = array();

		self::foreach_tag_row(
			$import_dir,
			static function ( array $row ) use ( &$counts ): void {
				$keyword_id = (int) ( $row['keywordId'] ?? 0 );
				$tag        = trim( html_entity_decode( (string) ( $row['tag'] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
				if ( $keyword_id <= 0 || $tag === '' ) {
					return;
				}

				if ( ! isset( $counts[ $keyword_id ] ) ) {
					$counts[ $keyword_id ] = array();
				}

				if ( ! isset( $counts[ $keyword_id ][ $tag ] ) ) {
					$counts[ $keyword_id ][ $tag ] = 0;
				}

				++$counts[ $keyword_id ][ $tag ];
			}
		);

		$result = array();
		foreach ( $counts as $keyword_id => $tag_counts ) {
			arsort( $tag_counts, SORT_NUMERIC );
			$names = array_keys( $tag_counts );
			$result[ (int) $keyword_id ] = (string) ( $names[0] ?? '' );
		}

		return $result;
	}

	/**
	 * @return array<int, int>
	 */
	private static function collect_category_ids( string $import_dir ): array {
		$ids = array();

		foreach ( self::read_categories( $import_dir ) as $category ) {
			$category_id = (int) ( $category['id'] ?? 0 );
			if ( $category_id > 0 ) {
				$ids[] = $category_id;
			}
		}

		return $ids;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function read_categories( string $import_dir ): array {
		$file = trailingslashit( $import_dir ) . 'categories.json';
		if ( ! is_file( $file ) ) {
			return array();
		}

		$data = json_decode( (string) file_get_contents( $file ), true );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * @param callable(array<string, mixed>): void $callback
	 */
	private static function foreach_tag_row( string $import_dir, callable $callback ): void {
		$chunk_files = AsreKhodro_Import_Chunks::list_chunk_files( $import_dir, 'tags' );
		if ( $chunk_files !== array() ) {
			foreach ( $chunk_files as $chunk_file ) {
				foreach ( AsreKhodro_Import_Chunks::read_chunk_file( $chunk_file ) as $row ) {
					if ( is_array( $row ) ) {
						$callback( $row );
					}
				}
			}

			return;
		}

		foreach ( AsreKhodro_Import_Chunks::read_legacy( $import_dir, 'tags' ) as $row ) {
			if ( is_array( $row ) ) {
				$callback( $row );
			}
		}
	}

	private static function resolve_tag_path( string $tag_name ): string {
		$term = get_term_by( 'name', $tag_name, 'post_tag' );
		if ( ! $term || is_wp_error( $term ) ) {
			$term = get_term_by( 'slug', sanitize_title( $tag_name ), 'post_tag' );
		}

		if ( ! $term || is_wp_error( $term ) ) {
			return '';
		}

		return self::term_path( $term );
	}

	private static function resolve_category_path( int $old_id ): string {
		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'number'     => 1,
				'meta_query' => array(
					array(
						'key'   => '_asrekhodro_category_id',
						'value' => $old_id,
					),
				),
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}

		return self::term_path( $terms[0] );
	}

	/**
	 * @param \WP_Term $term
	 */
	private static function term_path( $term ): string {
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) || ! is_string( $link ) || $link === '' ) {
			return '';
		}

		$path = wp_parse_url( $link, PHP_URL_PATH );
		if ( ! is_string( $path ) || $path === '' ) {
			return '';
		}

		return user_trailingslashit( $path );
	}
}
