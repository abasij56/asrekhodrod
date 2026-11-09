<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Denormalized FULLTEXT search index (title + content + terms).
 *
 * Safety:
 * - Own table only; never writes to wp_posts / terms.
 * - Front-end search uses this table only when the enable flag is on.
 */
final class SearchIndex {

	public const DB_VERSION_OPTION = 'ak_search_index_db_version';
	public const DB_VERSION        = 1;
	public const ENABLED_OPTION    = 'ak_search_index_enabled';
	public const PROGRESS_OPTION   = 'ak_search_index_reindex_progress';

	public const SEARCH_POST_TYPES = array( 'post', 'ak_video', 'ak_review' );

	public static function init(): void {
		add_action( 'init', array( self::class, 'maybe_install' ), 5 );
		add_action( 'save_post', array( self::class, 'on_save_post' ), 30, 2 );
		add_action( 'before_delete_post', array( self::class, 'on_before_delete_post' ), 10, 1 );
		add_action( 'transition_post_status', array( self::class, 'on_transition_post_status' ), 10, 3 );
		add_action( 'set_object_terms', array( self::class, 'on_set_object_terms' ), 10, 6 );
	}

	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'ak_search_index';
	}

	public static function is_enabled(): bool {
		return (string) get_option( self::ENABLED_OPTION, '0' ) === '1';
	}

	public static function set_enabled( bool $enabled ): void {
		update_option( self::ENABLED_OPTION, $enabled ? '1' : '0', false );
	}

	public static function maybe_install(): void {
		$installed = (int) get_option( self::DB_VERSION_OPTION, 0 );
		if ( $installed >= self::DB_VERSION ) {
			return;
		}

		self::install_table();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	public static function install_table(): void {
		global $wpdb;

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table} (
			post_id bigint(20) unsigned NOT NULL,
			post_type varchar(20) NOT NULL DEFAULT '',
			post_status varchar(20) NOT NULL DEFAULT '',
			post_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			search_text longtext NOT NULL,
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (post_id),
			KEY type_status_date (post_type, post_status, post_date),
			FULLTEXT KEY ft_search (search_text)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	public static function table_exists(): bool {
		global $wpdb;

		$table  = self::table_name();
		$found  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		return is_string( $found ) && $found === $table;
	}

	/**
	 * @param int          $post_id Post ID.
	 * @param \WP_Post|null $post    Post object when available.
	 */
	public static function on_save_post( int $post_id, $post = null ): void {
		if ( $post_id <= 0 || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! $post instanceof \WP_Post ) {
			$post = get_post( $post_id );
		}
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( ! in_array( $post->post_type, self::SEARCH_POST_TYPES, true ) ) {
			return;
		}

		self::upsert( $post_id );
	}

	public static function on_before_delete_post( int $post_id ): void {
		if ( $post_id <= 0 ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || ! in_array( $post->post_type, self::SEARCH_POST_TYPES, true ) ) {
			return;
		}

		self::delete( $post_id );
	}

	/**
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 */
	public static function on_transition_post_status( string $new_status, string $old_status, $post ): void {
		if ( ! $post instanceof \WP_Post || ! in_array( $post->post_type, self::SEARCH_POST_TYPES, true ) ) {
			return;
		}

		if ( $new_status === $old_status ) {
			return;
		}

		self::upsert( (int) $post->ID );
	}

	/**
	 * @param int    $object_id  Object ID.
	 * @param mixed  $terms      Terms.
	 * @param array  $tt_ids     Term taxonomy IDs.
	 * @param string $taxonomy   Taxonomy.
	 * @param bool   $append     Append.
	 * @param array  $old_tt_ids Old term taxonomy IDs.
	 */
	public static function on_set_object_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ): void {
		$post_id = (int) $object_id;
		if ( $post_id <= 0 ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || ! in_array( $post->post_type, self::SEARCH_POST_TYPES, true ) ) {
			return;
		}

		self::upsert( $post_id );
	}

	public static function upsert( int $post_id ): bool {
		if ( $post_id <= 0 || ! self::table_exists() ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			self::delete( $post_id );
			return false;
		}

		if ( ! in_array( $post->post_type, self::SEARCH_POST_TYPES, true ) ) {
			self::delete( $post_id );
			return false;
		}

		// Keep only published content searchable; remove drafts/trash/etc from index.
		if ( $post->post_status !== 'publish' ) {
			self::delete( $post_id );
			return true;
		}

		global $wpdb;

		$now = current_time( 'mysql' );
		$row = array(
			'post_id'     => $post_id,
			'post_type'   => $post->post_type,
			'post_status' => $post->post_status,
			'post_date'   => $post->post_date,
			'search_text' => self::build_search_text( $post ),
			'updated_at'  => $now,
		);

		$result = $wpdb->replace(
			self::table_name(),
			$row,
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return $result !== false;
	}

	public static function delete( int $post_id ): void {
		if ( $post_id <= 0 || ! self::table_exists() ) {
			return;
		}

		global $wpdb;
		$wpdb->delete( self::table_name(), array( 'post_id' => $post_id ), array( '%d' ) );
	}

	/**
	 * @param \WP_Post $post Post.
	 */
	public static function build_search_text( \WP_Post $post ): string {
		$title   = html_entity_decode( wp_strip_all_tags( (string) $post->post_title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$content = (string) $post->post_content;
		$content = strip_shortcodes( $content );
		$content = html_entity_decode( wp_strip_all_tags( $content ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		$excerpt = html_entity_decode( wp_strip_all_tags( (string) $post->post_excerpt ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		$taxonomies = get_object_taxonomies( $post->post_type, 'names' );
		$term_names = array();
		if ( is_array( $taxonomies ) && $taxonomies !== array() ) {
			$terms = wp_get_object_terms( $post->ID, $taxonomies, array( 'fields' => 'names' ) );
			if ( is_array( $terms ) ) {
				foreach ( $terms as $name ) {
					$name = trim( (string) $name );
					if ( $name !== '' ) {
						$term_names[] = $name;
					}
				}
			}
		}

		$parts = array_filter(
			array(
				self::normalize_whitespace( $title ),
				self::normalize_whitespace( $excerpt ),
				self::normalize_whitespace( $content ),
				self::normalize_whitespace( implode( ' ', $term_names ) ),
			)
		);

		return implode( "\n", $parts );
	}

	private static function normalize_whitespace( string $text ): string {
		$text = preg_replace( '/\s+/u', ' ', $text );
		return is_string( $text ) ? trim( $text ) : '';
	}

	/**
	 * Convert a user query into a FULLTEXT BOOLEAN MODE expression (AND of tokens).
	 */
	public static function to_boolean_query( string $query ): string {
		$query = self::normalize_whitespace( $query );
		if ( $query === '' ) {
			return '';
		}

		// Strip InnoDB boolean operators so user input cannot change query logic.
		$query = str_replace( array( '+', '-', '>', '<', '(', ')', '~', '*', '"', "'" ), ' ', $query );
		$query = self::normalize_whitespace( $query );
		if ( $query === '' ) {
			return '';
		}

		$parts = preg_split( '/\s+/u', $query );
		if ( ! is_array( $parts ) ) {
			return '';
		}

		$tokens = array();
		foreach ( $parts as $part ) {
			$part = trim( (string) $part );
			if ( $part === '' || mb_strlen( $part ) < 2 ) {
				continue;
			}
			$tokens[] = '+' . $part;
		}

		return implode( ' ', $tokens );
	}

	/**
	 * @return list<int>
	 */
	public static function find_ids( string $query, int $limit = 20, int $offset = 0 ): array {
		$boolean = self::to_boolean_query( $query );
		if ( $boolean === '' || ! self::table_exists() ) {
			return array();
		}

		global $wpdb;
		$table = self::table_name();
		$types = self::SEARCH_POST_TYPES;
		$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );

		$limit  = max( 1, min( 100, $limit ) );
		$offset = max( 0, $offset );

		$params = array_merge( $types, array( $boolean, $limit, $offset ) );
		$sql    = $wpdb->prepare(
			"SELECT post_id FROM {$table}
			WHERE post_status = 'publish'
			  AND post_type IN ({$in})
			  AND MATCH(search_text) AGAINST (%s IN BOOLEAN MODE)
			ORDER BY post_date DESC
			LIMIT %d OFFSET %d",
			...$params
		);

		$ids = $wpdb->get_col( $sql );
		if ( ! is_array( $ids ) ) {
			return array();
		}

		return array_map( 'intval', $ids );
	}

	/**
	 * Whether this WP_Query should use the FULLTEXT index join.
	 */
	public static function should_filter_query( \WP_Query $query ): bool {
		if ( ! self::is_enabled() || ! self::table_exists() || ! $query->is_search() ) {
			return false;
		}

		$term = $query->get( 's' );
		if ( ! is_string( $term ) || self::to_boolean_query( $term ) === '' ) {
			return false;
		}

		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}

		if ( $query->is_main_query() && ! is_admin() ) {
			return true;
		}

		$post_types = $query->get( 'post_type' );
		if ( empty( $post_types ) ) {
			return false;
		}

		$types = is_array( $post_types ) ? $post_types : array( $post_types );
		sort( $types );
		$expected = self::SEARCH_POST_TYPES;
		sort( $expected );

		return $types === $expected;
	}

	/**
	 * @param string    $join  JOIN clause.
	 * @param \WP_Query $query Query.
	 */
	public static function posts_join( string $join, \WP_Query $query ): string {
		if ( ! self::should_filter_query( $query ) ) {
			return $join;
		}

		global $wpdb;
		$table = self::table_name();
		$join .= " INNER JOIN {$table} AS ak_si ON ak_si.post_id = {$wpdb->posts}.ID ";

		return $join;
	}

	/**
	 * Replace WP LIKE search with FULLTEXT MATCH.
	 *
	 * @param string    $search Search SQL.
	 * @param \WP_Query $query  Query.
	 */
	public static function posts_search( string $search, \WP_Query $query ): string {
		if ( ! self::should_filter_query( $query ) ) {
			return $search;
		}

		global $wpdb;
		$boolean = self::to_boolean_query( (string) $query->get( 's' ) );
		if ( $boolean === '' ) {
			return ' AND 1=0 ';
		}

		return $wpdb->prepare( ' AND MATCH(ak_si.search_text) AGAINST (%s IN BOOLEAN MODE) ', $boolean );
	}

	public static function count_publish_posts(): int {
		global $wpdb;

		$types = self::SEARCH_POST_TYPES;
		$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$sql = $wpdb->prepare(
			"SELECT COUNT(ID) FROM {$wpdb->posts}
			WHERE post_status = 'publish' AND post_type IN ({$in})",
			...$types
		);

		return (int) $wpdb->get_var( $sql );
	}

	public static function count_indexed(): int {
		if ( ! self::table_exists() ) {
			return 0;
		}

		global $wpdb;
		$table = self::table_name();
		$types = self::SEARCH_POST_TYPES;
		$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );

		$sql = $wpdb->prepare(
			"SELECT COUNT(post_id) FROM {$table}
			WHERE post_status = 'publish' AND post_type IN ({$in})",
			...$types
		);

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Reindex a batch using keyset pagination (after_id), safe for large archives.
	 *
	 * @return array{
	 *   done: bool,
	 *   after_id: int,
	 *   next_after_id: int,
	 *   total: int,
	 *   indexed: int,
	 *   deleted: int,
	 *   errors: int,
	 *   checked: int
	 * }
	 */
	public static function reindex_batch( int $after_id = 0, int $limit = 200 ): array {
		$after_id = max( 0, $after_id );
		$limit    = max( 1, min( 1000, $limit ) );

		if ( ! self::table_exists() ) {
			self::install_table();
		}

		$total = self::count_publish_posts();

		$result = array(
			'done'          => true,
			'after_id'      => $after_id,
			'next_after_id' => $after_id,
			'total'         => $total,
			'indexed'       => 0,
			'deleted'       => 0,
			'errors'        => 0,
			'checked'       => 0,
		);

		global $wpdb;
		$types = self::SEARCH_POST_TYPES;
		$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );

		$params = array_merge( $types, array( $after_id, $limit ) );
		$sql    = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			WHERE post_status = 'publish'
			  AND post_type IN ({$in})
			  AND ID > %d
			ORDER BY ID ASC
			LIMIT %d",
			...$params
		);

		$ids = $wpdb->get_col( $sql );
		if ( ! is_array( $ids ) || $ids === array() ) {
			update_option(
				self::PROGRESS_OPTION,
				array(
					'after_id' => $after_id,
					'done'     => true,
					'total'    => $total,
					'at'       => time(),
				),
				false
			);

			return $result;
		}

		$result['done'] = false;
		$last_id          = $after_id;

		foreach ( $ids as $id ) {
			$id      = (int) $id;
			$last_id = $id;
			$result['checked']++;

			$ok = self::upsert( $id );
			if ( $ok ) {
				$post = get_post( $id );
				if ( $post instanceof \WP_Post && $post->post_status === 'publish' ) {
					$result['indexed']++;
				} else {
					$result['deleted']++;
				}
			} else {
				$result['errors']++;
			}
		}

		$result['next_after_id'] = $last_id;

		// Peek whether more rows remain.
		$more_params = array_merge( $types, array( $last_id ) );
		$more_sql    = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			WHERE post_status = 'publish'
			  AND post_type IN ({$in})
			  AND ID > %d
			ORDER BY ID ASC
			LIMIT 1",
			...$more_params
		);
		$more = $wpdb->get_var( $more_sql );
		$result['done'] = empty( $more );

		update_option(
			self::PROGRESS_OPTION,
			array(
				'after_id' => $last_id,
				'done'     => $result['done'],
				'total'    => $total,
				'at'       => time(),
			),
			false
		);

		return $result;
	}

	/**
	 * @return array{after_id: int, done: bool, total: int, at: int}
	 */
	public static function get_progress(): array {
		$raw = get_option( self::PROGRESS_OPTION, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		return array(
			'after_id' => isset( $raw['after_id'] ) ? (int) $raw['after_id'] : 0,
			'done'     => ! empty( $raw['done'] ),
			'total'    => isset( $raw['total'] ) ? (int) $raw['total'] : 0,
			'at'       => isset( $raw['at'] ) ? (int) $raw['at'] : 0,
		);
	}
}
