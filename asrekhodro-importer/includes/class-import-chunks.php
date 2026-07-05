<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read numbered chunk files (posts/posts-001.json) with fallback to legacy single JSON files.
 */
final class AsreKhodro_Import_Chunks {

	/** @var array<string, string> */
	private const PHASE_COLLECTIONS = array(
		'posts'            => 'posts',
		'post_categories'  => 'post-categories',
		'tags'             => 'tags',
		'post_relations'   => 'post-relations',
		'front_sections'   => 'front-sections',
		'comments'         => 'comments',
		'ads'              => 'ads',
		'videos'           => 'videos',
		'video_categories' => 'video-categories',
		'reviews'          => 'reviews',
		'magazines'        => 'magazines',
	);

	/** @var array<string, string> */
	private const LEGACY_FILENAMES = array(
		'posts'            => 'posts.json',
		'post-categories'  => 'post-categories.json',
		'tags'             => 'tags.json',
		'post-relations'   => 'post-relations.json',
		'front-sections'   => 'front-sections',
		'comments'         => 'comments.json',
		'ads'              => 'ads.json',
		'videos'           => 'videos.json',
		'video-categories' => 'video-categories.json',
		'reviews'          => 'reviews.json',
		'magazines'        => 'magazines.json',
	);

	public static function collection_for_phase( string $phase ): string {
		return self::PHASE_COLLECTIONS[ $phase ] ?? str_replace( '_', '-', $phase );
	}

	public static function legacy_filename( string $collection ): string {
		return self::LEGACY_FILENAMES[ $collection ] ?? ( $collection . '.json' );
	}

	public static function has_chunks( string $import_dir, string $collection ): bool {
		return self::list_chunk_files( $import_dir, $collection ) !== array();
	}

	/**
	 * @return array<int, string> Sorted absolute paths.
	 */
	public static function list_chunk_files( string $import_dir, string $collection ): array {
		$dir = trailingslashit( $import_dir ) . $collection;
		if ( ! is_dir( $dir ) ) {
			return array();
		}

		$pattern = trailingslashit( $dir ) . $collection . '-*.json';
		$files   = glob( $pattern );
		if ( ! is_array( $files ) || $files === array() ) {
			return array();
		}

		natsort( $files );

		return array_values( $files );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function read_chunk_file( string $path ): array {
		if ( ! is_file( $path ) ) {
			throw new RuntimeException( 'Chunk file not found: ' . $path );
		}

		return self::decode_json_file( $path );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function read_legacy( string $import_dir, string $collection ): array {
		$path = trailingslashit( $import_dir ) . self::legacy_filename( $collection );
		if ( ! is_file( $path ) ) {
			return array();
		}

		return self::decode_json_file( $path );
	}

	/**
	 * @return array<string, int>|null
	 */
	public static function totals_from_manifest( string $import_dir ): ?array {
		$path = trailingslashit( $import_dir ) . 'manifest.json';
		if ( ! is_file( $path ) ) {
			return null;
		}

		$manifest = self::decode_json_file( $path );
		if ( ! is_array( $manifest ) || ! isset( $manifest['counts'] ) || ! is_array( $manifest['counts'] ) ) {
			return null;
		}

		$counts = $manifest['counts'];
		$chunks = is_array( $manifest['chunks'] ?? null ) ? $manifest['chunks'] : array();

		$post_category_posts = (int) ( $counts['postCategoryPosts'] ?? $chunks['post-categories']['uniqueContentIds'] ?? $counts['postCategories'] ?? 0 );
		$tag_posts           = (int) ( $counts['tagPosts'] ?? $chunks['tags']['uniqueContentIds'] ?? $counts['tags'] ?? 0 );
		$post_relation_posts = (int) ( $counts['postRelationPosts'] ?? $chunks['post-relations']['uniqueContentIds'] ?? $counts['postRelations'] ?? 0 );
		$front_section_posts   = (int) ( $counts['frontSectionPosts'] ?? 0 );
		$video_category_posts = (int) ( $counts['videoCategoryPosts'] ?? $chunks['video-categories']['uniqueContentIds'] ?? $counts['videoCategories'] ?? 0 );

		return array(
			'categories'       => (int) ( $counts['categories'] ?? 0 ),
			'posts'            => (int) ( $counts['posts'] ?? 0 ),
			'post_categories'  => $post_category_posts,
			'tags'             => $tag_posts,
			'post_relations'   => $post_relation_posts,
			'front_sections'   => $front_section_posts > 0 ? 6 : 0,
			'videos'           => (int) ( $counts['videos'] ?? 0 ),
			'video_categories' => $video_category_posts,
			'reviews'          => (int) ( $counts['reviews'] ?? 0 ),
			'magazines'        => (int) ( $counts['magazines'] ?? 0 ),
			'ads'              => (int) ( $counts['ads'] ?? 0 ),
			'comments'         => (int) ( $counts['comments'] ?? 0 ),
		);
	}

	/**
	 * @return array{slice: array<int, array<string, mixed>>, total: int, chunk_item_count: int, chunk_file_count: int}
	 */
	public static function slice_items(
		string $import_dir,
		string $collection,
		int $chunk_index,
		int $offset,
		int $limit,
		?array &$cache
	): array {
		$files = self::list_chunk_files( $import_dir, $collection );

		if ( $files === array() ) {
			if ( $cache === null ) {
				$cache = self::read_legacy( $import_dir, $collection );
			}

			$items = $cache;
			$total = count( $items );
			$slice = array_slice( $items, $offset, $limit );

			return array(
				'slice'             => $slice,
				'total'             => $total,
				'chunk_item_count'  => $total,
				'chunk_file_count'  => 1,
			);
		}

		$total = self::total_rows_for_collection( $import_dir, $collection, count( $files ) );

		if ( $chunk_index >= count( $files ) ) {
			return array(
				'slice'             => array(),
				'total'             => $total,
				'chunk_item_count'  => 0,
				'chunk_file_count'  => count( $files ),
			);
		}

		if ( ! is_array( $cache ) || (int) ( $cache['chunk_index'] ?? -1 ) !== $chunk_index ) {
			$cache = array(
				'chunk_index' => $chunk_index,
				'items'       => self::read_chunk_file( $files[ $chunk_index ] ),
			);
		}

		$items = $cache['items'];
		$slice = array_slice( $items, $offset, $limit );

		return array(
			'slice'             => $slice,
			'total'             => $total,
			'chunk_item_count'  => count( $items ),
			'chunk_file_count'  => count( $files ),
		);
	}

	public static function advance_position(
		int $chunk_index,
		int $offset,
		int $processed,
		int $chunk_item_count,
		int $chunk_file_count
	): array {
		$offset += $processed;

		if ( $offset >= $chunk_item_count && $chunk_index + 1 < $chunk_file_count ) {
			return array(
				'chunk_index' => $chunk_index + 1,
				'offset'      => 0,
				'clear_cache' => true,
			);
		}

		return array(
			'chunk_index' => $chunk_index,
			'offset'      => $offset,
			'clear_cache' => false,
		);
	}

	private static function total_rows_for_collection( string $import_dir, string $collection, int $file_count ): int {
		$manifest_totals = self::totals_from_manifest( $import_dir );
		if ( $manifest_totals !== null ) {
			$map = array(
				'posts'            => 'posts',
				'post-categories'  => 'post_categories',
				'tags'             => 'tags',
				'post-relations'   => 'post_relations',
				'front-sections'   => 'front_sections',
				'comments'         => 'comments',
				'ads'              => 'ads',
				'videos'           => 'videos',
				'video-categories' => 'video_categories',
				'reviews'          => 'reviews',
				'magazines'        => 'magazines',
			);

			if ( isset( $map[ $collection ], $manifest_totals[ $map[ $collection ] ] ) ) {
				return (int) $manifest_totals[ $map[ $collection ] ];
			}
		}

		if ( $file_count <= 0 ) {
			return 0;
		}

		$files = self::list_chunk_files( $import_dir, $collection );
		$total = 0;
		foreach ( $files as $file ) {
			$rows   = self::decode_json_file( $file );
			$total += count( $rows );
		}

		return $total;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function decode_json_file( string $path ): array {
		$raw = file_get_contents( $path );
		if ( $raw === false ) {
			throw new RuntimeException( 'Failed to read: ' . $path );
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			throw new RuntimeException( 'Invalid JSON in ' . basename( $path ) );
		}

		return $data;
	}
}
