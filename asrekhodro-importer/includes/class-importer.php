<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AsreKhodro_Importer {

	/** @var array<string, string> */
	private const FRONT_SECTION_FIELDS = array(
		'main-slider'     => 'home_main_slider',
		'main-ticker'     => 'home_main_ticker',
		'main-top-hits'   => 'home_main_top_hits',
		'parsik'          => 'home_parsik',
		'special-events'  => 'home_special_events',
		'top-hits'        => 'home_top_hits',
	);

	private string $import_dir;

	private array $category_map = array();

	private array $post_map = array();

	/** @var array<int, bool> */
	private array $prefetched_post_content_ids = array();

	private array $comment_map = array();

	private array $ad_map = array();

	private array $video_map = array();

	private array $review_map = array();

	private array $magazine_map = array();

	/** @var array<int, int> */
	private array $video_category_map = array();

	private array $log = array();

	private int $external_media_count = 0;

	public function __construct( ?string $import_dir = null ) {
		$this->import_dir = $import_dir ?: ASREKHODRO_IMPORTER_DEFAULT_IMPORT_DIR;
	}

	public function get_import_dir(): string {
		return $this->import_dir;
	}

	/**
	 * Resolve a legacy contentId to a WordPress post ID (uses in-memory cache after prefetch).
	 */
	public function get_post_id_for_content_id( int $content_id, string $post_type = 'post' ): int {
		if ( $post_type !== 'post' ) {
			return $this->find_post_id_by_content_id( $post_type, $content_id );
		}

		return $this->lookup_post_id_by_content_id( $content_id );
	}

	/**
	 * Warm the post_map cache for a batch of legacy content IDs.
	 *
	 * @param array<int, int> $content_ids
	 */
	public function prefetch_content_ids( array $content_ids ): void {
		$this->prefetch_post_map_for_content_ids( $content_ids );
	}

	public function get_log(): array {
		return $this->log;
	}

	/**
	 * @return array<string, int>
	 */
	public static function get_file_totals( string $import_dir ): array {
		$from_manifest = AsreKhodro_Import_Chunks::totals_from_manifest( $import_dir );
		if ( $from_manifest !== null ) {
			return $from_manifest;
		}

		$importer = new self( $import_dir );

		$categories      = $importer->read_categories_sorted();
		$posts           = $importer->read_json( 'posts.json' );
		$post_categories = $importer->read_json( 'post-categories.json' );
		$tags            = $importer->read_json( 'tags.json' );
		$post_relations  = $importer->read_json_optional( 'post-relations.json' );
		$comments        = $importer->read_comments_sorted();
		$ads             = $importer->read_ads_sorted();
		$videos          = $importer->read_videos_sorted();
		$reviews         = $importer->read_reviews_sorted();
		$magazines       = $importer->read_magazines_sorted();

		return array(
			'categories'       => count( $categories ),
			'posts'            => count( $posts ),
			'post_categories'  => self::count_unique_content_ids( $post_categories ),
			'tags'             => self::count_unique_content_ids( $tags ),
			'post_relations'   => self::count_unique_parent_content_ids( $importer->read_json_optional( 'post-relations.json' ) ),
			'front_sections'   => self::count_front_section_steps( $import_dir ),
			'videos'           => count( $videos ),
			'video_categories' => self::count_unique_content_ids( $importer->read_json_optional( 'video-categories.json' ) ),
			'reviews'          => count( $reviews ),
			'magazines'        => count( $magazines ),
			'ads'              => count( $ads ),
			'comments'         => count( $comments ),
		);
	}

	/**
	 * @param array<string, mixed> $state
	 */
	public function hydrate_state( array $state ): void {
		$this->category_map         = is_array( $state['category_map'] ?? null ) ? $state['category_map'] : array();
		$this->post_map             = is_array( $state['post_map'] ?? null ) ? $state['post_map'] : array();
		$this->comment_map          = is_array( $state['comment_map'] ?? null ) ? $state['comment_map'] : array();
		$this->ad_map               = is_array( $state['ad_map'] ?? null ) ? $state['ad_map'] : array();
		$this->video_map            = is_array( $state['video_map'] ?? null ) ? $state['video_map'] : array();
		$this->review_map           = is_array( $state['review_map'] ?? null ) ? $state['review_map'] : array();
		$this->magazine_map         = is_array( $state['magazine_map'] ?? null ) ? $state['magazine_map'] : array();
		$this->video_category_map   = is_array( $state['video_category_map'] ?? null ) ? $state['video_category_map'] : array();
		$this->log                  = is_array( $state['log'] ?? null ) ? $state['log'] : array();
		$this->external_media_count = (int) ( $state['external_media_count'] ?? 0 );
	}

	/**
	 * @param array<string, mixed> $state
	 */
	public function sync_state( array &$state ): void {
		$state['category_map']         = $this->category_map;
		$state['post_map']             = $this->post_map;
		$state['comment_map']          = $this->comment_map;
		$state['ad_map']               = $this->ad_map;
		$state['video_map']            = $this->video_map;
		$state['review_map']           = $this->review_map;
		$state['magazine_map']         = $this->magazine_map;
		$state['video_category_map']   = $this->video_category_map;
		$state['log']                  = $this->log;
		$state['external_media_count'] = $this->external_media_count;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function read_json( string $filename ): array {
		return $this->read_json_file( $filename );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function read_json_optional( string $filename ): array {
		return $this->read_json_file_optional( $filename );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function read_categories_sorted(): array {
		$categories = $this->read_json_file( 'categories.json' );
		usort(
			$categories,
			static function ( $a, $b ) {
				$a_parent = isset( $a['parentId'] ) ? (int) $a['parentId'] : 0;
				$b_parent = isset( $b['parentId'] ) ? (int) $b['parentId'] : 0;
				return $a_parent <=> $b_parent;
			}
		);

		return $categories;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function read_comments_sorted(): array {
		$comments = $this->read_json_file( 'comments.json' );
		usort(
			$comments,
			static function ( $a, $b ) {
				return (int) ( $a['commentId'] ?? 0 ) <=> (int) ( $b['commentId'] ?? 0 );
			}
		);

		return $comments;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function read_ads_sorted(): array {
		$ads = $this->read_json_file_optional( 'ads.json' );
		usort(
			$ads,
			static function ( $a, $b ) {
				$pos = (int) ( $a['menuPositionId'] ?? 0 ) <=> (int) ( $b['menuPositionId'] ?? 0 );
				if ( $pos !== 0 ) {
					return $pos;
				}

				$priority = (string) ( $a['priority'] ?? '' );
				$b_priority = (string) ( $b['priority'] ?? '' );
				$prio_cmp   = strcmp( $priority, $b_priority );
				if ( $prio_cmp !== 0 ) {
					return $prio_cmp;
				}

				return (int) ( $a['id'] ?? 0 ) <=> (int) ( $b['id'] ?? 0 );
			}
		);

		return $ads;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function read_videos_sorted(): array {
		$videos = $this->read_json_file_optional( 'videos.json' );
		usort(
			$videos,
			static function ( $a, $b ) {
				$a_time = isset( $a['publishTime'] ) ? strtotime( (string) $a['publishTime'] ) : 0;
				$b_time = isset( $b['publishTime'] ) ? strtotime( (string) $b['publishTime'] ) : 0;

				return $b_time <=> $a_time;
			}
		);

		return $videos;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function read_reviews_sorted(): array {
		$reviews = $this->read_json_file_optional( 'reviews.json' );
		usort(
			$reviews,
			static function ( $a, $b ) {
				$a_time = isset( $a['publishTime'] ) ? strtotime( (string) $a['publishTime'] ) : 0;
				$b_time = isset( $b['publishTime'] ) ? strtotime( (string) $b['publishTime'] ) : 0;

				return $b_time <=> $a_time;
			}
		);

		return $reviews;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function read_magazines_sorted(): array {
		$magazines = $this->read_json_file_optional( 'magazines.json' );
		usort(
			$magazines,
			static function ( $a, $b ) {
				$a_time = isset( $a['publishTime'] ) ? strtotime( (string) $a['publishTime'] ) : 0;
				$b_time = isset( $b['publishTime'] ) ? strtotime( (string) $b['publishTime'] ) : 0;

				if ( $a_time === $b_time ) {
					return (int) ( $b['fileId'] ?? 0 ) <=> (int) ( $a['fileId'] ?? 0 );
				}

				return $b_time <=> $a_time;
			}
		);

		return $magazines;
	}

	public function run(): array {
		$this->log( 'Starting import from ' . $this->import_dir );

		if ( ! is_dir( $this->import_dir ) ) {
			throw new RuntimeException( 'Import directory not found: ' . $this->import_dir );
		}

		$manifest = $this->read_json_file( 'manifest.json' );
		$categories = $this->read_categories_sorted();
		$posts = $this->read_json_file( 'posts.json' );
		$post_categories = $this->read_json_file( 'post-categories.json' );
		$tags = $this->read_json_file( 'tags.json' );
		$post_relations = $this->read_json_file_optional( 'post-relations.json' );
		$comments = $this->read_comments_sorted();
		$ads      = $this->read_ads_sorted();
		$videos   = $this->read_videos_sorted();
		$video_categories = $this->read_json_file_optional( 'video-categories.json' );
		$reviews  = $this->read_reviews_sorted();
		$magazines = $this->read_magazines_sorted();

		$this->import_categories( $categories );
		$this->import_posts( $posts );
		$this->import_post_categories( $post_categories );
		$this->import_tags( $tags );
		AsreKhodro_Legacy_Redirects::regenerate_from_tags( $this->import_dir, $this );
		$this->import_post_relations( $this->read_json_file_optional( 'post-relations.json' ) );
		$this->import_front_sections();
		$this->import_videos( $videos );
		$this->import_video_categories( $video_categories );
		$this->import_reviews( $reviews );
		$this->import_magazines( $magazines );
		$this->import_ads( $ads );
		$this->import_comments( $comments );

		$mapping = array(
			'importedAt'   => gmdate( 'c' ),
			'categoryMap'  => $this->category_map,
			'postMap'      => $this->post_map,
			'videoMap'     => $this->video_map,
			'reviewMap'    => $this->review_map,
			'magazineMap'  => $this->magazine_map,
			'adMap'        => $this->ad_map,
			'commentMap'   => $this->comment_map,
		);

		file_put_contents(
			$this->import_dir . '/mapping.json',
			wp_json_encode( $mapping, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE )
		);

		$this->log( 'Import finished.' );

		return array(
			'manifest'  => $manifest,
			'log'       => $this->log,
			'counts'    => array(
				'categories'     => count( $this->category_map ),
				'posts'          => count( $this->post_map ),
				'videos'         => count( $this->video_map ),
				'reviews'        => count( $this->review_map ),
				'magazines'      => count( $this->magazine_map ),
				'ads'            => count( $this->ad_map ),
				'comments'       => count( $this->comment_map ),
				'external_media' => $this->external_media_count,
			),
		);
	}

	private function read_json_file( string $filename ): array {
		$path = $this->import_dir . '/' . $filename;
		if ( ! file_exists( $path ) ) {
			throw new RuntimeException( 'Missing file: ' . $path );
		}

		$data = json_decode( file_get_contents( $path ), true );
		if ( ! is_array( $data ) ) {
			throw new RuntimeException( 'Invalid JSON: ' . $path );
		}

		return $data;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function read_json_file_optional( string $filename ): array {
		$path = $this->import_dir . '/' . $filename;
		if ( ! file_exists( $path ) ) {
			return array();
		}

		return $this->read_json_file( $filename );
	}

	private function import_categories( array $categories ): void {
		foreach ( $categories as $category ) {
			$this->import_one_category( $category );
		}

		$this->log( 'Categories imported: ' . count( $this->category_map ) );
	}

	/**
	 * @param array<string, mixed> $category
	 */
	public function import_one_category( array $category ): string {
		$old_id = (int) $category['id'];
		if ( isset( $this->category_map[ $old_id ] ) ) {
			return $this->category_title( $category, $old_id );
		}

		$existing = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'meta_query' => array(
					array(
						'key'   => '_asrekhodro_category_id',
						'value' => $old_id,
					),
				),
			)
		);

		if ( ! empty( $existing ) && ! is_wp_error( $existing ) ) {
			$term_id = (int) $existing[0]->term_id;
			$this->category_map[ $old_id ] = $term_id;
			$this->update_category_term( $term_id, $category );
			return $this->category_title( $category, $old_id );
		}

		$parent_id = 0;
		if ( ! empty( $category['parentId'] ) ) {
			$parent_old = (int) $category['parentId'];
			$parent_id  = $this->category_map[ $parent_old ] ?? 0;
		}

		$result = wp_insert_term(
			$this->category_title( $category, $old_id ),
			'category',
			array(
				'description' => $this->decode_text( $category['description'] ?? '' ),
				'parent'      => $parent_id,
				'slug'        => $this->category_slug( $category, $old_id ),
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->log( 'Category error (' . $old_id . '): ' . $result->get_error_message() );
			return 'Category ' . $old_id;
		}

		$term_id = (int) $result['term_id'];
		update_term_meta( $term_id, '_asrekhodro_category_id', $old_id );
		$this->category_map[ $old_id ] = $term_id;

		return $this->category_title( $category, $old_id );
	}

	/**
	 * @param array<string, mixed> $category
	 */
	private function update_category_term( int $term_id, array $category ): void {
		$old_id = (int) ( $category['id'] ?? 0 );
		$title  = $this->category_title( $category, $old_id );

		$parent_id = 0;
		if ( ! empty( $category['parentId'] ) ) {
			$parent_old = (int) $category['parentId'];
			$parent_id  = $this->category_map[ $parent_old ] ?? 0;
		}

		wp_update_term(
			$term_id,
			'category',
			array(
				'name'        => $title,
				'description' => $this->decode_text( $category['description'] ?? '' ),
				'parent'      => $parent_id,
				'slug'        => $this->category_slug( $category, $old_id, $term_id ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $category
	 */
	private function category_slug( array $category, int $old_id, int $term_id = 0 ): string {
		$title = $this->category_title( $category, $old_id );

		if ( class_exists( '\AsreKhodro\Theme\NewsPermalinks' ) ) {
			return \AsreKhodro\Theme\NewsPermalinks::unique_category_slug( $title, $term_id, $old_id );
		}

		$slug = sanitize_title( $title );

		return $slug !== '' ? $slug : 'cat-' . $old_id;
	}

	/**
	 * @param array<string, mixed> $category
	 */
	private function category_title( array $category, int $old_id ): string {
		$title = $this->decode_text( $category['title'] ?? '' );
		if ( $title === '' || $this->is_corrupted_text( $title ) ) {
			$this->log( 'Category title encoding issue for id ' . $old_id . ' — re-export categories.json as UTF-8 from SQL Server.' );
			return 'دسته ' . $old_id;
		}

		return $title;
	}

	private function is_corrupted_text( string $text ): bool {
		$stripped = preg_replace( '/[\s\?\x{061F}]+/u', '', $text );
		return $stripped === '';
	}

	private function import_posts( array $posts ): void {
		foreach ( $posts as $post ) {
			$this->import_one_post( $post );
		}

		$this->log( 'Posts imported: ' . count( $this->post_map ) );
		$this->log( 'External media thumbnails registered: ' . $this->external_media_count );
	}

	/**
	 * @param array<string, mixed> $post
	 */
	public function import_one_post( array $post ): string {
		$content_id = (int) $post['contentId'];
		$title      = $this->decode_text( $post['title'] ?? ( 'Post ' . $content_id ) );
		$content_type_id = (int) ( $post['contentTypeId'] ?? 0 );

		if ( in_array( $content_type_id, array( 8, 16 ), true ) ) {
			return $title;
		}

		$post_id = (int) ( $this->post_map[ $content_id ] ?? 0 );
		if ( ! $post_id && ! isset( $this->prefetched_post_content_ids[ $content_id ] ) ) {
			$post_id = $this->lookup_post_id_by_content_id( $content_id );
		}

		$status  = $this->map_post_status( (int) ( $post['statusId'] ?? 3 ) );
		$publish = $this->normalize_datetime( $post['publishTime'] ?? null, $post['contentTime'] ?? null );

		if ( $post_id > 0 ) {
			wp_update_post(
				array(
					'ID'             => $post_id,
					'post_title'     => $title,
					'post_excerpt'   => $this->decode_text( $post['excerpt'] ?? '' ),
					'post_status'    => $status,
					'post_date'      => $publish,
					'post_date_gmt'  => get_gmt_from_date( $publish ),
				)
			);
			$this->save_imported_post_content( $post_id, $post, true );
			$this->save_post_meta( $post_id, $post );
			update_post_meta( $post_id, '_asrekhodro_content_id', $content_id );
			$this->sync_news_post_slug( $post_id, $content_id, $title );
			$this->post_map[ $content_id ] = $post_id;
			return $title;
		}

		$slug = $this->build_news_post_slug( $title, $content_id );

		$post_id = wp_insert_post(
			array(
				'post_title'     => $title,
				'post_excerpt'   => $this->decode_text( $post['excerpt'] ?? '' ),
				'post_status'    => $status,
				'post_type'      => 'post',
				'post_name'      => $slug,
				'post_date'      => $publish,
				'post_date_gmt'  => get_gmt_from_date( $publish ),
				'comment_status' => 'open',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			$this->log( 'Post error (' . $content_id . '): ' . $post_id->get_error_message() );
			return $title;
		}

		$this->save_imported_post_content( (int) $post_id, $post, false );
		update_post_meta( (int) $post_id, '_asrekhodro_content_id', $content_id );
		$this->save_post_meta( (int) $post_id, $post );
		$this->sync_news_post_slug( (int) $post_id, $content_id, $title );

		$this->post_map[ $content_id ] = (int) $post_id;

		return $title;
	}

	/**
	 * @param array<int, array<string, mixed>> $posts
	 * @return string Last imported post title.
	 */
	public function import_posts_batch( array $posts ): string {
		$this->prefetch_post_map_for_posts( $posts );

		$label = '';
		foreach ( $posts as $post ) {
			$label = $this->import_one_post( $post );
		}

		return $label;
	}

	/**
	 * Rewrite post_content from export JSON for posts that already exist in WordPress.
	 * Preserves an existing ak-gallery marker block when present.
	 *
	 * @param array<int, array<string, mixed>> $posts
	 * @return array{updated:int,skipped_not_found:int,skipped_no_embed:int,skipped_other:int,errors:int,log:array<int,string>}
	 */
	public function reimport_post_content_batch( array $posts ): array {
		$stats = array(
			'updated'           => 0,
			'skipped_not_found' => 0,
			'skipped_no_embed'  => 0,
			'skipped_other'     => 0,
			'errors'            => 0,
		);

		$this->prefetch_post_map_for_posts( $posts );

		foreach ( $posts as $post ) {
			$result = $this->reimport_one_post_content( $post );
			if ( isset( $stats[ $result ] ) ) {
				++$stats[ $result ];
			}
		}

		$stats['log'] = $this->log;

		return $stats;
	}

	/**
	 * @param array<string, mixed> $post
	 * @return 'updated'|'skipped_not_found'|'skipped_no_embed'|'skipped_other'|'errors'
	 */
	public function reimport_one_post_content( array $post ): string {
		$content_id = (int) ( $post['contentId'] ?? 0 );
		if ( $content_id <= 0 ) {
			return 'skipped_other';
		}

		$content_type_id = (int) ( $post['contentTypeId'] ?? 0 );
		if ( in_array( $content_type_id, array( 8, 16 ), true ) ) {
			return 'skipped_other';
		}

		if ( ! $this->export_post_has_embed_markup( $post ) ) {
			return 'skipped_no_embed';
		}

		$post_id = $this->get_post_id_for_content_id( $content_id );
		if ( $post_id <= 0 ) {
			return 'skipped_not_found';
		}

		if ( ! $this->save_imported_post_content( $post_id, $post, true ) ) {
			$this->log[] = sprintf(
				'Content reimport error contentId %d (post %d)',
				$content_id,
				$post_id
			);
			return 'errors';
		}

		$this->log[] = sprintf(
			'Content reimport contentId %d → post %d (embed restored)',
			$content_id,
			$post_id
		);

		return 'updated';
	}

	/**
	 * True when export JSON still contains embed markup that wp_kses typically strips.
	 *
	 * @param array<string, mixed> $post
	 */
	public function export_post_has_embed_markup( array $post ): bool {
		$fields = array( 'body', 'footer', 'overTitle' );

		foreach ( $fields as $field ) {
			if ( empty( $post[ $field ] ) || ! is_string( $post[ $field ] ) ) {
				continue;
			}

			$html = $this->decode_text( $post[ $field ] );
			if ( $html === '' ) {
				continue;
			}

			if ( preg_match( '/<iframe\b/i', $html ) ) {
				return true;
			}

			if ( preg_match( '/<script\b/i', $html ) ) {
				return true;
			}

			if ( preg_match( '/<(?:embed|object)\b/i', $html ) ) {
				return true;
			}

			if ( preg_match( '/aparat\.com/i', $html ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<string, mixed> $post
	 */
	private function build_imported_post_content( array $post, int $post_id, bool $preserve_gallery ): string {
		$body = AsreKhodro_Gallery_Importer::strip_existing_gallery( $this->build_post_content( $post ) );
		if ( ! $preserve_gallery || $post_id <= 0 ) {
			return $body;
		}

		$gallery = AsreKhodro_Gallery_Importer::extract_existing_gallery(
			(string) get_post_field( 'post_content', $post_id )
		);
		if ( $gallery === '' ) {
			return $body;
		}

		return $gallery . "\n\n" . ltrim( $body );
	}

	/**
	 * @param array<string, mixed> $post
	 */
	private function save_imported_post_content( int $post_id, array $post, bool $preserve_gallery ): bool {
		$content = $this->build_imported_post_content( $post, $post_id, $preserve_gallery );

		return $this->write_post_content_raw( $post_id, $content );
	}

	/**
	 * Write post_content directly, bypassing wp_kses so iframe/script embeds survive.
	 */
	private function write_post_content_raw( int $post_id, string $content ): bool {
		global $wpdb;

		$updated = $wpdb->update(
			$wpdb->posts,
			array( 'post_content' => $content ),
			array( 'ID' => $post_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( $updated === false ) {
			return false;
		}

		clean_post_cache( $post_id );

		return true;
	}

	/**
	 * @param array<int, array<string, mixed>> $posts
	 */
	private function prefetch_post_map_for_posts( array $posts ): void {
		$content_ids = array();
		foreach ( $posts as $post ) {
			$content_id = (int) ( $post['contentId'] ?? 0 );
			if ( $content_id > 0 ) {
				$content_ids[] = $content_id;
			}
		}

		$this->prefetch_post_map_for_content_ids( $content_ids );
	}

	/**
	 * @param array<int, int> $content_ids
	 */
	private function prefetch_post_map_for_content_ids( array $content_ids ): void {
		global $wpdb;

		$content_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $content_ids ),
					static fn( int $content_id ): bool => $content_id > 0
				)
			)
		);

		if ( $content_ids === array() ) {
			return;
		}

		foreach ( $content_ids as $content_id ) {
			$this->prefetched_post_content_ids[ $content_id ] = true;
		}

		$missing = array_values(
			array_filter(
				$content_ids,
				fn( int $content_id ): bool => empty( $this->post_map[ $content_id ] )
			)
		);

		if ( $missing === array() ) {
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $missing ), '%d' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value, pm.post_id
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s
					AND p.post_type = 'post'
					AND pm.meta_value IN ($placeholders)",
				array_merge( array( '_asrekhodro_content_id' ), $missing )
			),
			ARRAY_A
		);

		foreach ( $rows as $row ) {
			$content_id = (int) ( $row['meta_value'] ?? 0 );
			$post_id    = (int) ( $row['post_id'] ?? 0 );
			if ( $content_id > 0 && $post_id > 0 ) {
				$this->post_map[ $content_id ] = $post_id;
			}
		}
	}

	private function lookup_post_id_by_content_id( int $content_id ): int {
		$this->prefetch_post_map_for_content_ids( array( $content_id ) );
		return (int) ( $this->post_map[ $content_id ] ?? 0 );
	}

	private function find_post_id_by_content_id( string $post_type, int $content_id ): int {
		if ( $content_id <= 0 ) {
			return 0;
		}

		global $wpdb;

		$post_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT pm.post_id
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE p.post_type = %s
					AND pm.meta_key = %s
					AND pm.meta_value = %s
				LIMIT 1",
				$post_type,
				'_asrekhodro_content_id',
				(string) $content_id
			)
		);

		return $post_id > 0 ? $post_id : 0;
	}

	private function save_post_meta( int $post_id, array $post ): void {
		update_post_meta( $post_id, '_asrekhodro_domain_id', (int) ( $post['domainId'] ?? 0 ) );
		update_post_meta( $post_id, '_asrekhodro_status_id', (int) ( $post['statusId'] ?? 0 ) );
		update_post_meta( $post_id, '_asrekhodro_content_type_id', (int) ( $post['contentTypeId'] ?? 0 ) );

		if ( ! empty( $post['overTitle'] ) ) {
			$over = $this->decode_text( $post['overTitle'] );
			update_post_meta( $post_id, '_asrekhodro_over_title', $over );
		}
		if ( ! empty( $post['underTitle'] ) ) {
			$under = $this->decode_text( $post['underTitle'] );
			update_post_meta( $post_id, '_asrekhodro_under_title', $under );
		}

		$image_path = $this->resolve_best_image_path( $post );
		if ( $image_path !== '' ) {
			$this->save_image_meta( $post_id, $image_path );
		}
		if ( ! empty( $post['pageUrl'] ) ) {
			$page_url = esc_url_raw( $post['pageUrl'] );
			update_post_meta( $post_id, '_asrekhodro_old_page_url', $page_url );
		}
		if ( ! empty( $post['author'] ) ) {
			update_post_meta( $post_id, '_asrekhodro_author', sanitize_text_field( $post['author'] ) );
		}
	}

	private function build_news_post_slug( string $title, int $content_id ): string {
		if ( class_exists( '\AsreKhodro\Theme\NewsPermalinks' ) ) {
			$slug = \AsreKhodro\Theme\NewsPermalinks::slug_from_title( $title );
		} else {
			$slug = sanitize_title( $title );
		}

		return $slug !== '' ? $slug : 'content-' . $content_id;
	}

	private function sync_news_post_slug( int $post_id, int $content_id, string $title ): void {
		if ( class_exists( '\AsreKhodro\Theme\NewsPermalinks' ) ) {
			\AsreKhodro\Theme\NewsPermalinks::sync_post( $post_id, $content_id, $title );
			return;
		}

		$slug = $this->build_news_post_slug( $title, $content_id );
		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => $slug,
			)
		);
		update_post_meta( $post_id, '_asrekhodro_legacy_path', '/News/' . $content_id . '/' . $slug );
	}

	private function sync_video_post_slug( int $post_id, int $content_id, string $title ): void {
		if ( class_exists( '\AsreKhodro\Theme\VideoPermalinks' ) ) {
			\AsreKhodro\Theme\VideoPermalinks::sync_post( $post_id, $content_id, $title );
			return;
		}

		$slug = $this->build_news_post_slug( $title, $content_id );
		if ( $slug === '' ) {
			$slug = 'video-' . $content_id;
		}

		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => $slug,
			)
		);
		update_post_meta( $post_id, '_asrekhodro_legacy_path', '/Gallery/Content/' . $content_id . '/' . $slug );
	}

	private function save_image_meta( int $post_id, string $image_path ): void {
		$absolute = $this->absolutize_media_url( $image_path );
		if ( $absolute === '' ) {
			return;
		}

		$url = esc_url_raw( $absolute );
		if ( $url === '' ) {
			return;
		}

		$previous_url = (string) get_post_meta( $post_id, '_asrekhodro_image_url', true );
		update_post_meta( $post_id, '_asrekhodro_image_url', $url );

		if ( $previous_url === $url && has_post_thumbnail( $post_id ) ) {
			return;
		}

		$attachment_id = $this->register_external_thumbnail( $post_id, $url );
		if ( $attachment_id > 0 ) {
			set_post_thumbnail( $post_id, $attachment_id );
			update_post_meta( $post_id, '_asrekhodro_external_attachment_id', $attachment_id );
			++$this->external_media_count;
		}
	}

	/**
	 * Prefer the largest / highest-quality image among export fields and HTML body.
	 *
	 * @param array<string, mixed> $post
	 */
	private function resolve_best_image_path( array $post ): string {
		if ( ! empty( $post['imageUrl'] ) ) {
			return trim( (string) $post['imageUrl'] );
		}

		$candidates = array();

		if ( ! empty( $post['body'] ) ) {
			$candidates = $this->extract_all_images_from_body( (string) $post['body'] );
		}

		$best   = '';
		$best_score = -1;

		foreach ( array_unique( $candidates ) as $candidate ) {
			$candidate = trim( $candidate );
			if ( $candidate === '' ) {
				continue;
			}

			$score = $this->score_image_path( $candidate );
			if ( $score > $best_score ) {
				$best_score = $score;
				$best       = $candidate;
			}
		}

		return $best;
	}

	/**
	 * @return array<int, string>
	 */
	private function extract_all_images_from_body( string $body ): array {
		$html = $this->decode_text( $body );
		if ( $html === '' ) {
			return array();
		}

		$found = array();

		if ( preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches ) ) {
			foreach ( $matches[1] as $src ) {
				$found[] = $src;
			}
		}

		if ( preg_match_all( '#(/Uploaded/Image/[^\s"\'<>&]+\.(?:jpe?g|png|gif|webp))#i', $html, $path_matches ) ) {
			foreach ( $path_matches[1] as $path ) {
				$found[] = $path;
			}
		}

		return $found;
	}

	private function score_image_path( string $path ): int {
		$normalized = strtolower( $path );

		if ( preg_match( '#/(thumb|thumbnail|thumbs|small|mini|list|preview)/#', $normalized ) ) {
			return 1;
		}

		if ( preg_match( '#/(large|original|full|max)/#', $normalized ) ) {
			return 100000 + strlen( $path );
		}

		if ( preg_match( '#(\d{2,4})[xX](\d{2,4})#', $normalized, $dimensions ) ) {
			return (int) $dimensions[1] * (int) $dimensions[2];
		}

		return 1000 + strlen( $path );
	}

	private function register_external_thumbnail( int $post_id, string $url ): int {
		if ( ! class_exists( '\AsreKhodro\Theme\ExternalMedia' ) ) {
			$this->log( 'External media skipped (activate Asre Khodro theme): ' . $url );
			return 0;
		}

		$attachment_id = \AsreKhodro\Theme\ExternalMedia::register_url(
			$url,
			array(
				'post_parent'       => $post_id,
				'allow_fallback'    => true,
				'skip_remote_probe' => true,
			)
		);

		if ( $attachment_id <= 0 ) {
			$this->log( 'External media registration failed for post ' . $post_id . ': ' . $url );
		}

		return $attachment_id;
	}

	private function extract_image_from_body( string $body ): string {
		$html = $this->decode_text( $body );

		if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches ) ) {
			return $matches[1];
		}

		if ( preg_match( '#(/Uploaded/Image/[^\s"\'<>&]+\.(?:jpe?g|png|gif|webp))#i', $html, $matches ) ) {
			return $matches[1];
		}

		return '';
	}

	private function import_post_categories( array $relations ): void {
		$queue = $this->build_post_category_queue( $relations );
		foreach ( $queue as $entry ) {
			$this->assign_post_categories( (int) $entry['post_id'], $entry['term_ids'] );
		}

		$this->log( 'Posts with categories assigned: ' . count( $queue ) );
	}

	/**
	 * @param array<int, array<string, mixed>> $relations
	 * @return array<int, array{post_id:int,term_ids:array<int,int>}>
	 */
	public function build_post_category_queue( array $relations ): array {
		$groups = array();
		$this->prefetch_post_map_for_content_ids(
			array_map(
				static fn( array $relation ): int => (int) ( $relation['contentId'] ?? 0 ),
				$relations
			)
		);

		foreach ( $relations as $relation ) {
			$content_id  = (int) $relation['contentId'];
			$category_id = (int) $relation['categoryId'];
			$post_id     = $this->post_map[ $content_id ] ?? 0;
			$term_id     = $this->category_map[ $category_id ] ?? 0;

			if ( ! $post_id || ! $term_id ) {
				continue;
			}

			$groups[ $post_id ][] = $term_id;
		}

		$queue = array();
		foreach ( $groups as $post_id => $term_ids ) {
			$queue[] = array(
				'post_id'  => (int) $post_id,
				'term_ids' => array_values( array_unique( array_map( 'intval', $term_ids ) ) ),
			);
		}

		return $queue;
	}

	/**
	 * @param array<int, int> $term_ids
	 */
	public function assign_post_categories( int $post_id, array $term_ids, bool $append = false ): void {
		if ( $append ) {
			$existing = wp_get_post_terms( $post_id, 'category', array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $existing ) && ! empty( $existing ) ) {
				$term_ids = array_values(
					array_unique(
						array_merge(
							array_map( 'intval', $existing ),
							array_map( 'intval', $term_ids )
						)
					)
				);
			}
		}

		wp_set_post_terms( $post_id, $term_ids, 'category', false );
	}

	private function import_tags( array $tags ): void {
		$queue = $this->build_tag_queue( $tags );
		foreach ( $queue as $entry ) {
			$this->assign_post_tags( (int) $entry['post_id'], $entry['tags'] );
		}

		$this->log( 'Posts tagged: ' . count( $queue ) );
	}

	/**
	 * @param array<int, array<string, mixed>> $tags
	 * @return array<int, array{post_id:int,tags:array<int,string>}>
	 */
	public function build_tag_queue( array $tags ): array {
		$groups = array();

		foreach ( $tags as $row ) {
			$content_id = (int) $row['contentId'];
			$tag        = trim( $this->decode_text( $row['tag'] ?? '' ) );
			if ( $tag === '' ) {
				continue;
			}
			$groups[ $content_id ][] = $tag;
		}

		$queue = array();
		$this->prefetch_post_map_for_content_ids( array_keys( $groups ) );

		foreach ( $groups as $content_id => $tag_names ) {
			$post_id = $this->post_map[ $content_id ] ?? 0;
			if ( ! $post_id ) {
				continue;
			}

			$queue[] = array(
				'post_id' => (int) $post_id,
				'tags'    => array_values( array_unique( $tag_names ) ),
			);
		}

		return $queue;
	}

	/**
	 * @param array<int, string> $tag_names
	 */
	public function assign_post_tags( int $post_id, array $tag_names, bool $append = false ): void {
		if ( $append ) {
			$existing = wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $existing ) && ! empty( $existing ) ) {
				$tag_names = array_values(
					array_unique(
						array_merge(
							array_map( 'strval', $existing ),
							array_map( 'strval', $tag_names )
						)
					)
				);
			}
		}

		wp_set_post_terms( $post_id, $tag_names, 'post_tag', false );
	}

	private function import_post_relations( array $relations ): void {
		if ( empty( $relations ) ) {
			$this->log( 'No post relations to import (post-relations.json missing or empty).' );
			return;
		}

		$queue = $this->build_post_relation_queue( $relations );
		foreach ( $queue as $entry ) {
			$this->assign_post_relations( (int) $entry['post_id'], $entry['related_post_ids'] );
		}

		$this->log( 'Posts with related content assigned: ' . count( $queue ) );
	}

	/**
	 * @param array<int, array<string, mixed>> $relations
	 * @return array<int, array{post_id:int,related_post_ids:array<int,int>}>
	 */
	public function build_post_relation_queue( array $relations ): array {
		$groups = array();

		$content_ids = array();
		foreach ( $relations as $relation ) {
			$parent_id = (int) ( $relation['parentContentId'] ?? 0 );
			$child_id  = (int) ( $relation['childContentId'] ?? 0 );
			if ( $parent_id > 0 ) {
				$content_ids[] = $parent_id;
			}
			if ( $child_id > 0 ) {
				$content_ids[] = $child_id;
			}
		}

		$this->prefetch_post_map_for_content_ids( $content_ids );

		foreach ( $relations as $relation ) {
			$parent_content_id = (int) ( $relation['parentContentId'] ?? 0 );
			$child_content_id  = (int) ( $relation['childContentId'] ?? 0 );
			$parent_post_id    = (int) ( $this->post_map[ $parent_content_id ] ?? 0 );
			$child_post_id     = (int) ( $this->post_map[ $child_content_id ] ?? 0 );

			if ( ! $parent_post_id || ! $child_post_id || $parent_post_id === $child_post_id ) {
				continue;
			}

			$groups[ $parent_post_id ][] = $child_post_id;
		}

		$queue = array();
		foreach ( $groups as $post_id => $related_post_ids ) {
			$queue[] = array(
				'post_id'           => (int) $post_id,
				'related_post_ids'  => array_values(
					array_unique(
						array_map( 'intval', $related_post_ids )
					)
				),
			);
		}

		return $queue;
	}

	/**
	 * @param array<int, int> $related_post_ids
	 */
	public function assign_post_relations( int $post_id, array $related_post_ids, bool $append = false ): void {
		$related_post_ids = array_values(
			array_filter(
				array_map( 'intval', $related_post_ids ),
				static fn( int $related_post_id ): bool => $related_post_id > 0 && $related_post_id !== $post_id
			)
		);

		if ( $append && ! empty( $related_post_ids ) ) {
			$existing = function_exists( 'get_field' ) ? get_field( 'related_posts', $post_id ) : null;
			if ( is_array( $existing ) && ! empty( $existing ) ) {
				$related_post_ids = array_values(
					array_unique(
						array_merge(
							array_map( 'intval', $existing ),
							$related_post_ids
						)
					)
				);
			}
		}

		if ( function_exists( 'update_field' ) ) {
			update_field( 'related_posts', $related_post_ids, $post_id );
		}

		delete_post_meta( $post_id, '_asrekhodro_related_content_ids' );
		if ( ! empty( $related_post_ids ) ) {
			$legacy_ids = array();
			foreach ( $related_post_ids as $related_post_id ) {
				$legacy_id = (int) get_post_meta( $related_post_id, '_asrekhodro_content_id', true );
				if ( $legacy_id > 0 ) {
					$legacy_ids[] = $legacy_id;
				}
			}

			if ( ! empty( $legacy_ids ) ) {
				update_post_meta( $post_id, '_asrekhodro_related_content_ids', $legacy_ids );
			}
		}
	}

	public function import_front_sections(): void {
		$assigned = 0;

		foreach ( self::FRONT_SECTION_FIELDS as $file => $field_name ) {
			$rows = $this->read_front_section_json( $file );
			if ( empty( $rows ) ) {
				continue;
			}

			$post_ids = $this->map_section_rows_to_post_ids( $rows );
			$this->assign_front_section_posts( $field_name, $post_ids, $rows );
			if ( ! empty( $post_ids ) ) {
				++$assigned;
			}
		}

		$this->log( 'Front homepage sections assigned: ' . $assigned );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function read_front_section_json( string $file ): array {
		$path = trailingslashit( $this->import_dir ) . 'front-sections/' . $file . '.json';
		if ( ! is_file( $path ) ) {
			return array();
		}

		$data = json_decode( (string) file_get_contents( $path ), true );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 * @return array<int, int>
	 */
	public function map_section_rows_to_post_ids( array $rows ): array {
		$content_ids = array();
		foreach ( $rows as $row ) {
			$content_id = (int) ( $row['contentId'] ?? 0 );
			if ( $content_id > 0 ) {
				$content_ids[] = $content_id;
			}
		}

		$this->prefetch_post_map_for_content_ids( $content_ids );

		$post_ids = array();
		foreach ( $rows as $row ) {
			$content_id = (int) ( $row['contentId'] ?? 0 );
			$post_id    = (int) ( $this->post_map[ $content_id ] ?? 0 );
			if ( $post_id > 0 ) {
				$post_ids[] = $post_id;
			}
		}

		return array_values( array_unique( $post_ids ) );
	}

	/**
	 * @param array<int, int>              $post_ids
	 * @param array<int, array<string, mixed>> $rows
	 */
	public function assign_front_section_posts( string $field_name, array $post_ids, array $rows = array() ): void {
		if ( function_exists( 'update_field' ) ) {
			update_field( $field_name, $post_ids, 'option' );
		}

		$legacy_ids = array();
		foreach ( $rows as $row ) {
			$content_id = (int) ( $row['contentId'] ?? 0 );
			if ( $content_id > 0 ) {
				$legacy_ids[] = $content_id;
			}
		}

		update_option( '_asrekhodro_' . $field_name . '_content_ids', $legacy_ids, false );
	}

	/**
	 * @return array<int, string>
	 */
	public static function get_front_section_field_map(): array {
		return self::FRONT_SECTION_FIELDS;
	}

	private function import_videos( array $videos ): void {
		if ( empty( $videos ) ) {
			$this->log( 'No videos to import (videos.json missing or empty).' );
			return;
		}

		foreach ( $videos as $video ) {
			$this->import_one_video( $video );
		}

		$this->log( 'Videos imported: ' . count( $this->video_map ) );
	}

	/**
	 * @param array<int, array<string, mixed>> $videos
	 * @return string Last imported video title.
	 */
	public function import_videos_batch( array $videos ): string {
		$label = '';
		foreach ( $videos as $video ) {
			$label = $this->import_one_video( $video );
		}

		return $label;
	}

	/**
	 * @param array<string, mixed> $video
	 */
	public function import_one_video( array $video ): string {
		$content_id = (int) ( $video['contentId'] ?? 0 );
		$title      = $this->decode_text( (string) ( $video['title'] ?? 'Video ' . $content_id ) );

		$post_id = (int) ( $this->video_map[ $content_id ] ?? 0 );
		if ( ! $post_id ) {
			$post_id = $this->find_post_id_by_content_id( 'ak_video', $content_id );
		}

		$body    = $this->build_video_content( $video );
		$status  = $this->map_post_status( (int) ( $video['statusId'] ?? 3 ) );
		$publish = $this->normalize_datetime( $video['publishTime'] ?? null, $video['contentTime'] ?? null );

		if ( $post_id > 0 ) {
			wp_update_post(
				array(
					'ID'             => $post_id,
					'post_title'     => $title,
					'post_content'   => $body,
					'post_excerpt'   => $this->decode_text( $video['excerpt'] ?? '' ),
					'post_status'    => $status,
					'post_date'      => $publish,
					'post_date_gmt'  => get_gmt_from_date( $publish ),
				)
			);
			update_post_meta( $post_id, '_asrekhodro_content_id', $content_id );
			$this->save_video_meta( $post_id, $video );
			$this->sync_video_post_slug( $post_id, $content_id, $title );
			$this->video_map[ $content_id ] = $post_id;
			return $title;
		}

		$post_id = wp_insert_post(
			array(
				'post_title'     => $title,
				'post_content'   => $body,
				'post_excerpt'   => $this->decode_text( $video['excerpt'] ?? '' ),
				'post_status'    => $status,
				'post_type'      => 'ak_video',
				'post_date'      => $publish,
				'post_date_gmt'  => get_gmt_from_date( $publish ),
				'comment_status' => 'closed',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			$this->log( 'Video error (' . $content_id . '): ' . $post_id->get_error_message() );
			return $title;
		}

		update_post_meta( (int) $post_id, '_asrekhodro_content_id', $content_id );
		$this->save_video_meta( (int) $post_id, $video );
		$this->sync_video_post_slug( (int) $post_id, $content_id, $title );
		$this->video_map[ $content_id ] = (int) $post_id;

		return $title;
	}

	/**
	 * @param array<string, mixed> $video
	 */
	private function save_video_meta( int $post_id, array $video ): void {
		update_post_meta( $post_id, '_asrekhodro_domain_id', (int) ( $video['domainId'] ?? 0 ) );
		update_post_meta( $post_id, '_asrekhodro_status_id', (int) ( $video['statusId'] ?? 0 ) );
		update_post_meta( $post_id, '_asrekhodro_content_type_id', (int) ( $video['contentTypeId'] ?? 16 ) );

		$video_url = $this->resolve_video_url( $video );
		if ( $video_url !== '' ) {
			update_post_meta( $post_id, '_asrekhodro_video_url', esc_url_raw( $video_url ) );
			if ( function_exists( 'update_field' ) ) {
				update_field( 'video_url', $video_url, $post_id );
			} else {
				update_post_meta( $post_id, 'video_url', $video_url );
			}
		}

		if ( ! empty( $video['imageUrl'] ) ) {
			$this->save_image_meta( $post_id, (string) $video['imageUrl'] );
		}

		if ( ! empty( $video['author'] ) ) {
			update_post_meta( $post_id, '_asrekhodro_author', sanitize_text_field( $video['author'] ) );
		}

		if ( ! empty( $video['pageUrl'] ) ) {
			$page_url = esc_url_raw( (string) $video['pageUrl'] );
			if ( $page_url !== '' ) {
				update_post_meta( $post_id, '_asrekhodro_old_page_url', $page_url );
			}
		}
	}

	/**
	 * @param array<string, mixed> $video
	 */
	private function build_video_content( array $video ): string {
		$parts = array();
		$url   = $this->resolve_video_url( $video );

		if ( $url !== '' ) {
			$parts[] = sprintf(
				'<div class="ak-video-player"><video controls preload="metadata" playsinline src="%s"></video></div>',
				esc_url( $url )
			);
		}

		$body = $this->rewrite_body_media_urls( $this->decode_text( $video['body'] ?? '' ) );
		if ( $body !== '' && ! $this->is_placeholder_body( $body ) ) {
			if ( $url !== '' ) {
				$body = $this->strip_video_embeds_from_html( $body );
			}
			$parts[] = $body;
		}

		if ( ! empty( $video['footer'] ) ) {
			$parts[] = '<div class="asrekhodro-footer">' . $this->rewrite_body_media_urls( $this->decode_text( $video['footer'] ) ) . '</div>';
		}

		return implode( "\n\n", array_filter( $parts ) );
	}

	/**
	 * @param array<string, mixed> $video
	 */
	private function resolve_video_url( array $video ): string {
		if ( ! empty( $video['videoUrl'] ) ) {
			$absolute = $this->absolutize_media_url( (string) $video['videoUrl'] );
			if ( $absolute !== '' ) {
				return esc_url_raw( $absolute ) ?: '';
			}
		}

		$from_body = $this->extract_video_from_body( (string) ( $video['body'] ?? '' ) );
		if ( $from_body !== '' ) {
			$absolute = $this->absolutize_media_url( $from_body );
			if ( $absolute !== '' ) {
				return esc_url_raw( $absolute ) ?: '';
			}
		}

		return '';
	}

	private function extract_video_from_body( string $body ): string {
		$html = $this->decode_text( $body );

		if ( preg_match( '#(?:src|href)\s*=\s*["\']([^"\']*/Uploaded/Video/[^"\']+)["\']#i', $html, $matches ) ) {
			return $matches[1];
		}

		if ( preg_match( '#(/Uploaded/Video/[^\s"\'<>&]+\.(?:mp4|flv|webm|m4v))#i', $html, $matches ) ) {
			return $matches[1];
		}

		return '';
	}

	private function is_placeholder_body( string $body ): bool {
		$text = trim( wp_strip_all_tags( $body ) );
		return $text === '' || $text === '.';
	}

	private function strip_video_embeds_from_html( string $html ): string {
		if ( $html === '' ) {
			return '';
		}

		$patterns = array(
			'#<div class="ak-video-player"[^>]*>.*?</div>#is',
			'#<video\b[^>]*>.*?</video>#is',
			'#<iframe\b[^>]*>.*?</iframe>#is',
			'#<object\b[^>]*>.*?</object>#is',
			'#<embed\b[^>]*/?>#is',
			'#\[video[^\]]*/?\]#is',
		);

		foreach ( $patterns as $pattern ) {
			$result = preg_replace( $pattern, '', $html );
			if ( is_string( $result ) ) {
				$html = $result;
			}
		}

		return trim( $html );
	}

	private function import_video_categories( array $relations ): void {
		$queue = $this->build_video_category_queue( $relations );
		foreach ( $queue as $entry ) {
			$this->assign_video_categories( (int) $entry['post_id'], $entry['term_ids'] );
		}

		$this->log( 'Videos with categories assigned: ' . count( $queue ) );
	}

	/**
	 * @param array<int, array<string, mixed>> $relations
	 * @return array<int, array{post_id:int,term_ids:array<int,int>}>
	 */
	public function build_video_category_queue( array $relations ): array {
		$groups = array();

		foreach ( $relations as $relation ) {
			$content_id  = (int) $relation['contentId'];
			$category_id = (int) $relation['categoryId'];
			$post_id     = $this->video_map[ $content_id ] ?? 0;
			$term_id     = $this->resolve_video_category_term_id( $category_id );

			if ( ! $post_id || ! $term_id ) {
				continue;
			}

			$groups[ $post_id ][] = $term_id;
		}

		$queue = array();
		foreach ( $groups as $post_id => $term_ids ) {
			$queue[] = array(
				'post_id'  => (int) $post_id,
				'term_ids' => array_values( array_unique( array_map( 'intval', $term_ids ) ) ),
			);
		}

		return $queue;
	}

	/**
	 * @param array<int, int> $term_ids
	 */
	public function assign_video_categories( int $post_id, array $term_ids, bool $append = false ): void {
		if ( $append ) {
			$existing = wp_get_post_terms( $post_id, 'video_category', array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $existing ) && ! empty( $existing ) ) {
				$term_ids = array_values(
					array_unique(
						array_merge(
							array_map( 'intval', $existing ),
							array_map( 'intval', $term_ids )
						)
					)
				);
			}
		}

		wp_set_post_terms( $post_id, $term_ids, 'video_category', false );
	}

	private function resolve_video_category_term_id( int $legacy_category_id ): int {
		if ( $legacy_category_id <= 0 ) {
			return 0;
		}

		if ( isset( $this->video_category_map[ $legacy_category_id ] ) ) {
			return (int) $this->video_category_map[ $legacy_category_id ];
		}

		$existing = get_terms(
			array(
				'taxonomy'   => 'video_category',
				'hide_empty' => false,
				'meta_query' => array(
					array(
						'key'   => '_asrekhodro_category_id',
						'value' => $legacy_category_id,
					),
				),
				'number'     => 1,
			)
		);

		if ( ! empty( $existing ) && ! is_wp_error( $existing ) ) {
			$term_id = (int) $existing[0]->term_id;
			$this->video_category_map[ $legacy_category_id ] = $term_id;
			return $term_id;
		}

		$news_term_id = $this->category_map[ $legacy_category_id ] ?? 0;
		if ( $news_term_id > 0 ) {
			$news_term = get_term( $news_term_id, 'category' );
			if ( $news_term && ! is_wp_error( $news_term ) ) {
				$by_slug = get_term_by( 'slug', $news_term->slug, 'video_category' );
				if ( $by_slug ) {
					$term_id = (int) $by_slug->term_id;
					update_term_meta( $term_id, '_asrekhodro_category_id', $legacy_category_id );
					$this->video_category_map[ $legacy_category_id ] = $term_id;
					return $term_id;
				}

				$result = wp_insert_term(
					$news_term->name,
					'video_category',
					array(
						'slug'        => $news_term->slug,
						'description' => $news_term->description,
					)
				);

				if ( ! is_wp_error( $result ) ) {
					$term_id = (int) $result['term_id'];
					update_term_meta( $term_id, '_asrekhodro_category_id', $legacy_category_id );
					$this->video_category_map[ $legacy_category_id ] = $term_id;
					return $term_id;
				}
			}
		}

		return 0;
	}

	private function import_reviews( array $reviews ): void {
		if ( empty( $reviews ) ) {
			$this->log( 'No reviews to import (reviews.json missing or empty).' );
			return;
		}

		foreach ( $reviews as $review ) {
			$this->import_one_review( $review );
		}

		$this->log( 'Reviews imported: ' . count( $this->review_map ) );
	}

	/**
	 * @param array<int, array<string, mixed>> $reviews
	 * @return string Last imported review title.
	 */
	public function import_reviews_batch( array $reviews ): string {
		$label = '';
		foreach ( $reviews as $review ) {
			$label = $this->import_one_review( $review );
		}

		return $label;
	}

	/**
	 * @param array<string, mixed> $review
	 */
	public function import_one_review( array $review ): string {
		$content_id = (int) ( $review['contentId'] ?? 0 );
		$title      = $this->decode_text( (string) ( $review['title'] ?? 'Review ' . $content_id ) );

		$post_id = (int) ( $this->review_map[ $content_id ] ?? 0 );
		if ( ! $post_id ) {
			$post_id = $this->find_post_id_by_content_id( 'ak_review', $content_id );
		}
		if ( ! $post_id ) {
			$post_id = $this->find_post_id_by_content_id( 'post', $content_id );
		}

		$body    = $this->build_post_content( $review );
		$status  = $this->map_post_status( (int) ( $review['statusId'] ?? 3 ) );
		$publish = $this->normalize_datetime( $review['publishTime'] ?? null, $review['contentTime'] ?? null );

		if ( $post_id > 0 ) {
			wp_update_post(
				array(
					'ID'             => $post_id,
					'post_type'      => 'ak_review',
					'post_title'     => $title,
					'post_content'   => $body,
					'post_excerpt'   => $this->decode_text( $review['excerpt'] ?? '' ),
					'post_status'    => $status,
					'post_name'      => 'review-' . $content_id,
					'post_date'      => $publish,
					'post_date_gmt'  => get_gmt_from_date( $publish ),
					'comment_status' => 'closed',
				)
			);
			update_post_meta( $post_id, '_asrekhodro_content_id', $content_id );
			$this->save_review_meta( $post_id, $review );
			$this->review_map[ $content_id ] = $post_id;
			return $title;
		}

		$post_id = wp_insert_post(
			array(
				'post_title'     => $title,
				'post_content'   => $body,
				'post_excerpt'   => $this->decode_text( $review['excerpt'] ?? '' ),
				'post_status'    => $status,
				'post_type'      => 'ak_review',
				'post_name'      => 'review-' . $content_id,
				'post_date'      => $publish,
				'post_date_gmt'  => get_gmt_from_date( $publish ),
				'comment_status' => 'closed',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			$this->log( 'Review error (' . $content_id . '): ' . $post_id->get_error_message() );
			return $title;
		}

		update_post_meta( (int) $post_id, '_asrekhodro_content_id', $content_id );
		$this->save_review_meta( (int) $post_id, $review );
		$this->review_map[ $content_id ] = (int) $post_id;

		return $title;
	}

	/**
	 * @param array<string, mixed> $review
	 */
	private function save_review_meta( int $post_id, array $review ): void {
		update_post_meta( $post_id, '_asrekhodro_content_id', (int) ( $review['contentId'] ?? 0 ) );
		update_post_meta( $post_id, '_asrekhodro_domain_id', (int) ( $review['domainId'] ?? 0 ) );
		update_post_meta( $post_id, '_asrekhodro_status_id', (int) ( $review['statusId'] ?? 0 ) );
		update_post_meta( $post_id, '_asrekhodro_content_type_id', (int) ( $review['contentTypeId'] ?? 8 ) );

		if ( ! empty( $review['overTitle'] ) ) {
			update_post_meta( $post_id, '_asrekhodro_over_title', $this->decode_text( (string) $review['overTitle'] ) );
		}
		if ( ! empty( $review['underTitle'] ) ) {
			update_post_meta( $post_id, '_asrekhodro_under_title', $this->decode_text( (string) $review['underTitle'] ) );
		}
		$image_path = $this->resolve_best_image_path( $review );
		if ( $image_path !== '' ) {
			$this->save_image_meta( $post_id, $image_path );
		}
		if ( ! empty( $review['author'] ) ) {
			update_post_meta( $post_id, '_asrekhodro_author', sanitize_text_field( $review['author'] ) );
		}
	}

	private function import_magazines( array $magazines ): void {
		if ( empty( $magazines ) ) {
			$this->log( 'No magazines to import (magazines.json missing or empty).' );
			return;
		}

		foreach ( $magazines as $magazine ) {
			$this->import_one_magazine( $magazine );
		}

		$this->log( 'Magazines imported: ' . count( $this->magazine_map ) );
	}

	/**
	 * @param array<int, array<string, mixed>> $magazines
	 * @return string Last imported magazine title.
	 */
	public function import_magazines_batch( array $magazines ): string {
		$label = '';
		foreach ( $magazines as $magazine ) {
			$label = $this->import_one_magazine( $magazine );
		}

		return $label;
	}

	/**
	 * @param array<string, mixed> $magazine
	 */
	public function import_one_magazine( array $magazine ): string {
		$file_id = (int) ( $magazine['fileId'] ?? 0 );
		$title   = $this->decode_text( (string) ( $magazine['title'] ?? 'Magazine ' . $file_id ) );

		if ( $file_id > 0 && isset( $this->magazine_map[ $file_id ] ) ) {
			return $title;
		}

		$existing = get_posts(
			array(
				'post_type'      => 'ak_magazine',
				'posts_per_page' => 1,
				'meta_key'       => '_asrekhodro_file_id',
				'meta_value'     => $file_id,
				'post_status'    => 'any',
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $existing ) ) {
			$post_id = (int) $existing[0];
			$this->save_magazine_meta( $post_id, $magazine );
			$this->magazine_map[ $file_id ] = $post_id;
			return $title;
		}

		$status  = $this->map_post_status( (int) ( $magazine['statusId'] ?? 3 ) );
		$publish = $this->normalize_datetime( $magazine['publishTime'] ?? null, null );

		$post_id = wp_insert_post(
			array(
				'post_title'     => $title,
				'post_content'   => '',
				'post_excerpt'   => $this->decode_text( $magazine['description'] ?? '' ),
				'post_status'    => $status,
				'post_type'      => 'ak_magazine',
				'post_name'      => $file_id > 0 ? (string) $file_id : sanitize_title( $title ),
				'post_date'      => $publish,
				'post_date_gmt'  => get_gmt_from_date( $publish ),
				'comment_status' => 'closed',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			$this->log( 'Magazine error (' . $file_id . '): ' . $post_id->get_error_message() );
			return $title;
		}

		$this->save_magazine_meta( (int) $post_id, $magazine );
		$this->magazine_map[ $file_id ] = (int) $post_id;

		return $title;
	}

	/**
	 * @param array<string, mixed> $magazine
	 */
	private function save_magazine_meta( int $post_id, array $magazine ): void {
		$file_id = (int) ( $magazine['fileId'] ?? 0 );

		update_post_meta( $post_id, '_asrekhodro_file_id', $file_id );
		update_post_meta( $post_id, '_asrekhodro_domain_id', (int) ( $magazine['domainId'] ?? 0 ) );
		update_post_meta( $post_id, '_asrekhodro_status_id', (int) ( $magazine['statusId'] ?? 0 ) );
		update_post_meta( $post_id, '_asrekhodro_kiosk_category_id', (int) ( $magazine['categoryId'] ?? 43 ) );

		if ( $file_id > 0 ) {
			update_post_meta( $post_id, '_asrekhodro_legacy_path', '/Home/Kiosk/' . $file_id );
		}

		if ( ! empty( $magazine['imageUrl'] ) ) {
			$this->save_image_meta( $post_id, (string) $magazine['imageUrl'] );
		}
	}

	private function import_ads( array $ads ): void {
		if ( empty( $ads ) ) {
			$this->log( 'No ads to import (ads.json missing or empty).' );
			return;
		}

		$this->ensure_ad_positions();

		foreach ( $ads as $ad ) {
			$this->import_one_ad( $ad );
		}

		$this->log( 'Ads imported: ' . count( $this->ad_map ) );
	}

	/**
	 * @param array<int, array<string, mixed>> $ads
	 * @return string Last imported ad title.
	 */
	public function import_ads_batch( array $ads ): string {
		$this->ensure_ad_positions();

		$label = '';
		foreach ( $ads as $ad ) {
			$label = $this->import_one_ad( $ad );
		}

		return $label;
	}

	/**
	 * @param array<string, mixed> $ad
	 */
	public function import_one_ad( array $ad ): string {
		AsreKhodro_Importer_Post_Types::register();

		$old_id = (int) ( $ad['id'] ?? 0 );
		$title  = $this->decode_text( (string) ( $ad['title'] ?? 'Ad ' . $old_id ) );

		if ( $old_id > 0 && isset( $this->ad_map[ $old_id ] ) ) {
			return $title;
		}

		$existing_id = $this->find_existing_ad_id( $old_id );
		if ( $existing_id > 0 ) {
			$this->ad_map[ $old_id ] = $existing_id;
			return $title;
		}

		$position_slug = $this->map_ad_position( (int) ( $ad['menuPositionId'] ?? 0 ) );
		$menu_order    = $this->parse_ad_priority( (string) ( $ad['priority'] ?? '' ) );

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'ad_slot',
				'post_title'   => $title,
				'post_status'  => ! empty( $ad['isActive'] ) ? 'publish' : 'draft',
				'menu_order'   => $menu_order,
				'post_content' => '',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			$this->log( 'Failed to import ad ' . $old_id . ': ' . $post_id->get_error_message() );
			return $title;
		}

		if ( ! $post_id ) {
			$this->log( 'Failed to import ad ' . $old_id . ': wp_insert_post returned empty.' );
			return $title;
		}

		$post_id = (int) $post_id;
		update_post_meta( $post_id, '_asrekhodro_ad_id', $old_id );
		update_post_meta( $post_id, '_asrekhodro_menu_position_id', (int) ( $ad['menuPositionId'] ?? 0 ) );

		if ( ! empty( $ad['menuPositionName'] ) ) {
			update_post_meta( $post_id, '_asrekhodro_menu_position_name', sanitize_text_field( $this->decode_text( (string) $ad['menuPositionName'] ) ) );
		}

		$term = get_term_by( 'slug', $position_slug, 'ad_position' );
		if ( $term ) {
			wp_set_post_terms( $post_id, array( (int) $term->term_id ), 'ad_position', false );
		}

		$this->save_ad_fields( $post_id, $ad, $title );
		$this->ad_map[ $old_id ] = $post_id;

		return $title;
	}

	private function find_existing_ad_id( int $old_id ): int {
		if ( $old_id <= 0 ) {
			return 0;
		}

		$posts = get_posts(
			array(
				'post_type'      => 'ad_slot',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_asrekhodro_ad_id',
						'value' => $old_id,
					),
				),
			)
		);

		return ! empty( $posts ) ? (int) $posts[0] : 0;
	}

	/**
	 * @param array<string, mixed> $ad
	 */
	private function save_ad_fields( int $post_id, array $ad, string $title ): void {
		$label = $title;
		if ( ! empty( $ad['html'] ) ) {
			$html_label = trim( wp_strip_all_tags( $this->decode_text( (string) $ad['html'] ) ) );
			if ( $html_label !== '' ) {
				$label = $html_label;
			}
		}

		$link = $this->normalize_ad_link( (string) ( $ad['link'] ?? '' ) );
		$active = ! empty( $ad['isActive'] );

		if ( function_exists( 'update_field' ) ) {
			update_field( 'ad_label', $label, $post_id );
			update_field( 'ad_link', $link, $post_id );
			update_field( 'ad_active', $active ? 1 : 0, $post_id );
		} else {
			update_post_meta( $post_id, 'ad_label', $label );
			update_post_meta( $post_id, 'ad_link', $link );
			update_post_meta( $post_id, 'ad_active', $active ? '1' : '0' );
		}

		if ( empty( $ad['imageUrl'] ) ) {
			return;
		}

		$absolute = $this->absolutize_media_url( (string) $ad['imageUrl'] );
		if ( $absolute === '' ) {
			return;
		}

		$url = esc_url_raw( $absolute );
		if ( $url === '' ) {
			return;
		}

		update_post_meta( $post_id, '_asrekhodro_image_url', $url );

		$attachment_id = $this->register_external_thumbnail( $post_id, $url );
		if ( $attachment_id <= 0 ) {
			return;
		}

		if ( function_exists( 'update_field' ) ) {
			update_field( 'ad_image', $attachment_id, $post_id );
		} else {
			update_post_meta( $post_id, 'ad_image', $attachment_id );
		}
	}

	private function normalize_ad_link( string $link ): string {
		$link = trim( $this->decode_text( $link ) );
		if ( $link === '' || $link === 'http://' || $link === 'https://' ) {
			return '#';
		}

		$url = esc_url_raw( $link );

		return $url !== '' ? $url : '#';
	}

	private function parse_ad_priority( string $priority ): int {
		$digits = preg_replace( '/\D+/', '', $priority );
		if ( ! is_string( $digits ) || $digits === '' ) {
			return 0;
		}

		$value = (int) $digits;
		if ( $value > 99999 ) {
			return (int) substr( $digits, -5 );
		}

		return $value;
	}

	private function map_ad_position( int $menu_position_id ): string {
		return match ( $menu_position_id ) {
			4, 5, 6, 12, 13, 16 => 'menu_strip',
			7, 11                => 'sidebar_left',
			9, 10, 15            => 'content_row',
			8                    => 'kiosk',
			default              => 'sidebar_left',
		};
	}

	private function ensure_ad_positions(): void {
		if ( class_exists( '\AsreKhodro\Theme\Setup' ) ) {
			\AsreKhodro\Theme\Setup::ensure_ad_positions();
			return;
		}

		$positions = array(
			'menu_strip'   => 'Menu strip (below nav)',
			'sidebar_left' => 'Sidebar left',
			'content_row'  => 'Content row banner',
			'kiosk'        => 'Kiosk / magazine carousel',
		);

		foreach ( $positions as $slug => $name ) {
			if ( ! term_exists( $slug, 'ad_position' ) ) {
				wp_insert_term( $name, 'ad_position', array( 'slug' => $slug ) );
			}
		}
	}

	private function import_comments( array $comments ): void {
		foreach ( $comments as $comment ) {
			$this->import_one_comment( $comment );
		}

		$this->log( 'Comments imported: ' . count( $this->comment_map ) );
	}

	/**
	 * @param array<string, mixed> $comment
	 */
	public function import_one_comment( array $comment ): string {
		$old_id     = (int) ( $comment['commentId'] ?? 0 );
		$content_id = (int) ( $comment['contentId'] ?? 0 );

		if ( $old_id <= 0 || $content_id <= 0 ) {
			return 'Comment skipped (missing ids)';
		}

		$existing_id = $this->find_existing_comment_id( $old_id );
		if ( $existing_id > 0 ) {
			$this->comment_map[ $old_id ] = $existing_id;
			return 'Comment ' . $old_id;
		}

		$post_id = (int) ( $this->post_map[ $content_id ] ?? 0 );
		if ( ! $post_id ) {
			$post_id = $this->lookup_post_id_by_content_id( $content_id );
		}

		if ( ! $post_id ) {
			return 'Comment ' . $old_id;
		}

		$parent_old = (int) ( $comment['parentId'] ?? 0 );
		$parent_new = 0;
		if ( $parent_old > 0 ) {
			$parent_new = (int) ( $this->comment_map[ $parent_old ] ?? 0 );
			if ( ! $parent_new ) {
				$parent_new = $this->find_existing_comment_id( $parent_old );
			}
		}

		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $post_id,
				'comment_parent'       => $parent_new,
				'comment_content'      => $this->decode_text( $comment['content'] ?? '' ),
				'comment_author'       => $this->decode_text( $comment['authorName'] ?? 'Guest' ),
				'comment_author_email' => sanitize_email( $comment['authorEmail'] ?? '' ),
				'comment_date'         => $this->normalize_datetime( $comment['createdAt'] ?? null ),
				'comment_approved'     => 1,
			)
		);

		if ( ! $comment_id ) {
			$this->log( 'Failed to import comment ' . $old_id . ' for post ' . $content_id );
			return 'Comment ' . $old_id;
		}

		update_comment_meta( $comment_id, '_asrekhodro_comment_id', $old_id );
		update_comment_meta( $comment_id, '_asrekhodro_content_id', $content_id );
		$this->comment_map[ $old_id ] = (int) $comment_id;

		return $this->decode_text( $comment['authorName'] ?? 'Guest' ) . ' — comment ' . $old_id;
	}

	/**
	 * @param array<int, array<string, mixed>> $comments
	 */
	public function import_comments_batch( array $comments ): void {
		usort(
			$comments,
			static function ( array $a, array $b ): int {
				$a_reply = (int) ( $a['parentId'] ?? 0 ) > 0 ? 1 : 0;
				$b_reply = (int) ( $b['parentId'] ?? 0 ) > 0 ? 1 : 0;
				if ( $a_reply !== $b_reply ) {
					return $a_reply <=> $b_reply;
				}

				return (int) ( $a['commentId'] ?? 0 ) <=> (int) ( $b['commentId'] ?? 0 );
			}
		);

		$this->prefetch_post_map_for_content_ids(
			array_map(
				static fn( array $comment ): int => (int) ( $comment['contentId'] ?? 0 ),
				$comments
			)
		);

		$legacy_ids = array();
		foreach ( $comments as $comment ) {
			$legacy_ids[] = (int) ( $comment['commentId'] ?? 0 );
			$parent_id    = (int) ( $comment['parentId'] ?? 0 );
			if ( $parent_id > 0 ) {
				$legacy_ids[] = $parent_id;
			}
		}
		$this->prefetch_comment_map_for_legacy_ids( $legacy_ids );

		$imported = 0;
		$skipped  = 0;

		foreach ( $comments as $comment ) {
			$before = count( $this->comment_map );
			$this->import_one_comment( $comment );
			if ( count( $this->comment_map ) > $before ) {
				++$imported;
			} else {
				++$skipped;
			}
		}

		if ( $skipped > 0 ) {
			$this->log( 'Comments batch: imported ' . $imported . ', skipped ' . $skipped . ' (post missing or duplicate).' );
		}
	}

	/**
	 * @param array<int, int> $legacy_ids
	 */
	private function prefetch_comment_map_for_legacy_ids( array $legacy_ids ): void {
		global $wpdb;

		$legacy_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $legacy_ids ),
					fn( int $legacy_id ): bool => $legacy_id > 0 && empty( $this->comment_map[ $legacy_id ] )
				)
			)
		);

		if ( $legacy_ids === array() ) {
			return;
		}

		foreach ( array_chunk( $legacy_ids, 200 ) as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
			$rows         = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT meta_value, comment_id
					FROM {$wpdb->commentmeta}
					WHERE meta_key = %s
						AND meta_value IN ($placeholders)",
					array_merge( array( '_asrekhodro_comment_id' ), array_map( 'strval', $chunk ) )
				),
				ARRAY_A
			);

			foreach ( $rows as $row ) {
				$legacy_id  = (int) ( $row['meta_value'] ?? 0 );
				$comment_id = (int) ( $row['comment_id'] ?? 0 );
				if ( $legacy_id > 0 && $comment_id > 0 ) {
					$this->comment_map[ $legacy_id ] = $comment_id;
				}
			}
		}
	}

	private function find_existing_comment_id( int $old_id ): int {
		if ( $old_id <= 0 ) {
			return 0;
		}

		if ( isset( $this->comment_map[ $old_id ] ) ) {
			return (int) $this->comment_map[ $old_id ];
		}

		global $wpdb;

		$comment_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT comment_id
				FROM {$wpdb->commentmeta}
				WHERE meta_key = %s
					AND meta_value = %s
				LIMIT 1",
				'_asrekhodro_comment_id',
				(string) $old_id
			)
		);

		if ( $comment_id > 0 ) {
			$this->comment_map[ $old_id ] = $comment_id;
		}

		return $comment_id;
	}

	private function build_post_content( array $post ): string {
		$parts = array();

		if ( ! empty( $post['overTitle'] ) ) {
			$parts[] = '<p class="asrekhodro-over-title"><strong>' . esc_html( $this->decode_text( $post['overTitle'] ) ) . '</strong></p>';
		}

		$body = $this->rewrite_body_media_urls( $this->decode_text( $post['body'] ?? '' ) );
		if ( $body !== '' ) {
			$parts[] = $body;
		}

		if ( ! empty( $post['footer'] ) ) {
			$parts[] = '<div class="asrekhodro-footer">' . $this->rewrite_body_media_urls( $this->decode_text( $post['footer'] ) ) . '</div>';
		}

		return implode( "\n\n", array_filter( $parts ) );
	}

	private function rewrite_body_media_urls( string $html ): string {
		if ( $html === '' ) {
			return $html;
		}

		if ( class_exists( '\AsreKhodro\Theme\ImporterBridge' ) ) {
			return \AsreKhodro\Theme\ImporterBridge::rewrite_content_media_urls( $html );
		}

		$pattern = '#(?<attr>src|href|data-src|data-lazy-src|poster)\s*=\s*(["\'])(?!https?://|//|data:)(/?Uploaded/[^"\']+)\2#i';

		$result = preg_replace_callback(
			$pattern,
			function ( array $matches ): string {
				$absolute = $this->absolutize_media_url( $matches[3] );

				return $matches['attr'] . '=' . $matches[2] . esc_url( $absolute ) . $matches[2];
			},
			$html
		);

		return is_string( $result ) ? $result : $html;
	}

	private function map_post_status( int $status_id ): string {
		return match ( $status_id ) {
			1, 3 => 'publish',
			4    => 'trash',
			default => 'draft',
		};
	}

	private function normalize_datetime( ?string $value, ?string $fallback = null ): string {
		$candidate = $this->pick_valid_datetime( $value );

		if ( null === $candidate && $fallback ) {
			$candidate = $this->pick_valid_datetime( $fallback );
		}

		if ( null === $candidate ) {
			return current_time( 'mysql' );
		}

		return wp_date( 'Y-m-d H:i:s', $candidate );
	}

	private function pick_valid_datetime( ?string $value ): ?int {
		if ( empty( $value ) ) {
			return null;
		}

		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return null;
		}

		$year = (int) wp_date( 'Y', $timestamp );
		if ( $year < 1990 || $year > 2100 ) {
			return null;
		}

		return $timestamp;
	}

	private function absolutize_media_url( string $url ): string {
		if ( preg_match( '#^https?://#i', $url ) ) {
			return $url;
		}

		$base = defined( 'ASREKHODRO_MEDIA_BASE_URL' ) ? ASREKHODRO_MEDIA_BASE_URL : '';
		if ( '' === $base ) {
			return $url;
		}

		return rtrim( $base, '/' ) . '/' . ltrim( $url, '/' );
	}

	private function decode_text( string $value ): string {
		return html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	public function log( string $message ): void {
		$this->log[] = $message;
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 */
	private static function count_unique_content_ids( array $rows ): int {
		$ids = array();
		foreach ( $rows as $row ) {
			if ( isset( $row['contentId'] ) ) {
				$ids[ (int) $row['contentId'] ] = true;
			}
		}

		return count( $ids );
	}

	/**
	 * @param array<int, array<string, mixed>> $rows
	 */
	private static function count_unique_parent_content_ids( array $rows ): int {
		$ids = array();
		foreach ( $rows as $row ) {
			if ( isset( $row['parentContentId'] ) ) {
				$ids[ (int) $row['parentContentId'] ] = true;
			}
		}

		return count( $ids );
	}

	private static function count_front_section_steps( string $import_dir ): int {
		$dir = trailingslashit( $import_dir ) . 'front-sections';
		if ( ! is_dir( $dir ) ) {
			return 0;
		}

		foreach ( array_keys( self::FRONT_SECTION_FIELDS ) as $file ) {
			$path = $dir . '/' . $file . '.json';
			if ( ! is_file( $path ) ) {
				continue;
			}

			$data = json_decode( (string) file_get_contents( $path ), true );
			if ( is_array( $data ) && $data !== array() ) {
				return count( self::FRONT_SECTION_FIELDS );
			}
		}

		return 0;
	}
}
