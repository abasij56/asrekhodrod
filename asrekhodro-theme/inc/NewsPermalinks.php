<?php

namespace AsreKhodro\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NewsPermalinks {

	public const BASE_PATH          = 'News';
	public const POST_NAME_MAX_LEN  = 1024;

	public static function init(): void {
		add_action( 'init', array( self::class, 'register_rewrites' ) );
		add_action( 'init', array( self::class, 'maybe_repair_category_slugs' ), 20 );
		add_filter( 'query_vars', array( self::class, 'register_query_vars' ) );
		add_filter( 'post_link', array( self::class, 'filter_permalink' ), 10, 2 );
		add_filter( 'post_type_link', array( self::class, 'filter_permalink' ), 10, 2 );
		add_action( 'parse_request', array( self::class, 'parse_request' ) );
		add_filter( 'redirect_canonical', array( self::class, 'filter_redirect_canonical' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( self::class, 'filter_insert_post_data' ), 50, 2 );
		add_filter( 'dbdelta_create_queries', array( self::class, 'filter_dbdelta_create_queries' ) );
		add_filter( 'dbdelta_queries', array( self::class, 'filter_dbdelta_queries' ) );
	}

	/**
	 * @param array<int, string> $vars
	 * @return array<int, string>
	 */
	public static function register_query_vars( array $vars ): array {
		$vars[] = 'ak_news_content_id';

		return $vars;
	}

	public static function register_rewrites(): void {
		add_rewrite_tag( '%ak_news_content_id%', '([0-9]+)' );

		add_rewrite_rule(
			'^News/([0-9]+)/([^/]+)/?$',
			'index.php?ak_news_content_id=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			'^News/([0-9]+)/?$',
			'index.php?ak_news_content_id=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			'^Home/News/([0-9]+)/([^/]+)/?$',
			'index.php?ak_news_content_id=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			'^Home/News/([0-9]+)/?$',
			'index.php?ak_news_content_id=$matches[1]',
			'top'
		);
	}

	public static function parse_request( \WP $wp ): void {
		if ( empty( $wp->query_vars['ak_news_content_id'] ) ) {
			return;
		}

		$content_id = (int) $wp->query_vars['ak_news_content_id'];
		$post_id    = self::find_post_id_by_content_id( $content_id );

		if ( $post_id <= 0 ) {
			return;
		}

		$wp->query_vars['p'] = $post_id;
	}

	/**
	 * Keep /News/{id}/{slug} URLs instead of redirecting to the default post permalink structure.
	 *
	 * @param mixed $redirect_url
	 */
	public static function filter_redirect_canonical( $redirect_url, string $requested_url ) {
		if ( preg_match( '#/(?:Home/)?News/\d+#i', $requested_url ) ) {
			return false;
		}

		return $redirect_url;
	}

	public static function filter_permalink( string $permalink, \WP_Post $post ): string {
		if ( $post->post_type !== 'post' ) {
			return $permalink;
		}

		$legacy = self::build_url_for_post( (int) $post->ID );
		if ( $legacy === null ) {
			return $permalink;
		}

		return $legacy;
	}

	/**
	 * Keep full Unicode slugs for news posts; bypass utf8_uri_encode(..., 200) truncation.
	 *
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $postarr
	 * @return array<string, mixed>
	 */
	public static function filter_insert_post_data( array $data, array $postarr ): array {
		if ( ( $data['post_type'] ?? '' ) !== 'post' ) {
			return $data;
		}

		$status = (string) ( $data['post_status'] ?? '' );
		if ( $status === 'auto-draft' ) {
			return $data;
		}

		$title = trim( (string) ( $data['post_title'] ?? '' ) );
		if ( $title === '' ) {
			return $data;
		}

		$desired = self::slug_from_title( $title );
		if ( $desired === '' ) {
			$post_id    = (int) ( $postarr['ID'] ?? 0 );
			$content_id = $post_id > 0 ? (int) get_post_meta( $post_id, '_asrekhodro_content_id', true ) : 0;
			if ( $content_id <= 0 && isset( $postarr['meta_input']['_asrekhodro_content_id'] ) ) {
				$content_id = (int) $postarr['meta_input']['_asrekhodro_content_id'];
			}
			$desired = $content_id > 0 ? 'content-' . $content_id : '';
		}

		if ( $desired === '' ) {
			return $data;
		}

		$post_id             = (int) ( $postarr['ID'] ?? 0 );
		$data['post_name']   = self::unique_post_slug( $desired, $post_id );

		return $data;
	}

	/**
	 * @param array<string, string> $queries
	 * @return array<string, string>
	 */
	public static function filter_dbdelta_create_queries( array $queries ): array {
		return self::widen_post_name_in_queries( $queries );
	}

	/**
	 * @param array<int|string, string>|string $queries
	 * @return array<int|string, string>|string
	 */
	public static function filter_dbdelta_queries( $queries ) {
		if ( is_string( $queries ) ) {
			return self::widen_post_name_sql( $queries );
		}

		if ( ! is_array( $queries ) ) {
			return $queries;
		}

		return self::widen_post_name_in_queries( $queries );
	}

	/**
	 * @param array<int|string, string> $queries
	 * @return array<int|string, string>
	 */
	private static function widen_post_name_in_queries( array $queries ): array {
		foreach ( $queries as $key => $query ) {
			if ( ! is_string( $query ) ) {
				continue;
			}
			$queries[ $key ] = self::widen_post_name_sql( $query );
		}

		return $queries;
	}

	private static function widen_post_name_sql( string $sql ): string {
		if ( ! preg_match( '/\bpost_name\b/i', $sql ) ) {
			return $sql;
		}

		$replaced = preg_replace(
			'/\bpost_name\s+varchar\(\d+\)/i',
			'post_name varchar(' . self::POST_NAME_MAX_LEN . ')',
			$sql
		);

		return is_string( $replaced ) ? $replaced : $sql;
	}

	public static function ensure_post_name_column(): bool {
		global $wpdb;

		$row = $wpdb->get_row( "SHOW COLUMNS FROM {$wpdb->posts} LIKE 'post_name'", ARRAY_A );
		if ( ! is_array( $row ) || empty( $row['Type'] ) ) {
			return false;
		}

		if ( ! preg_match( '/varchar\((\d+)\)/i', (string) $row['Type'], $matches ) ) {
			return false;
		}

		if ( (int) $matches[1] >= self::POST_NAME_MAX_LEN ) {
			return true;
		}

		$wpdb->query( "SET SESSION sql_mode = ''" );
		$result = $wpdb->query(
			"ALTER TABLE {$wpdb->posts} MODIFY post_name VARCHAR(" . self::POST_NAME_MAX_LEN . ") NOT NULL DEFAULT ''"
		);

		return false !== $result;
	}

	public static function slug_from_title( string $title ): string {
		$title = trim( wp_strip_all_tags( $title ) );
		if ( $title === '' ) {
			return '';
		}

		$slug = preg_replace( '/[^\p{L}\p{N}]+/u', '-', $title );
		if ( ! is_string( $slug ) ) {
			return '';
		}

		$slug = trim( $slug, '-' );
		$slug = preg_replace( '/-+/', '-', $slug );

		if ( ! is_string( $slug ) || $slug === '' ) {
			return '';
		}

		if ( function_exists( 'mb_substr' ) ) {
			$slug = mb_substr( $slug, 0, self::POST_NAME_MAX_LEN, 'UTF-8' );
		} else {
			$slug = substr( $slug, 0, self::POST_NAME_MAX_LEN );
		}

		return trim( $slug, '-' );
	}

	public static function unique_post_slug( string $slug, int $post_id = 0 ): string {
		$slug = trim( $slug, '-' );
		if ( $slug === '' ) {
			return $slug;
		}

		$base   = $slug;
		$suffix = 2;

		while ( self::post_slug_exists( $slug, $post_id ) ) {
			$slug = $base . '-' . $suffix;
			++$suffix;
		}

		return $slug;
	}

	private static function post_slug_exists( string $slug, int $exclude_post_id = 0 ): bool {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'post' AND ID != %d LIMIT 1",
			$slug,
			$exclude_post_id
		);

		return (int) $wpdb->get_var( $sql ) > 0;
	}

	public static function unique_category_slug( string $title, int $term_id = 0, int $fallback_id = 0 ): string {
		$slug = self::slug_from_title( $title );
		if ( $slug === '' ) {
			return $fallback_id > 0 ? 'cat-' . $fallback_id : 'category-' . max( 0, $term_id );
		}

		return wp_unique_term_slug(
			$slug,
			(object) array(
				'term_id'  => $term_id,
				'taxonomy' => 'category',
			)
		);
	}

	public static function maybe_repair_category_slugs(): void {
		if ( get_option( 'ak_category_slugs_repaired_v3' ) ) {
			return;
		}

		$count = self::repair_imported_category_slugs();
		update_option( 'ak_category_slugs_repaired_v3', 1, false );

		if ( $count > 0 ) {
			flush_rewrite_rules( false );
		}
	}

	public static function repair_imported_category_slugs(): int {
		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'meta_query' => array(
					array(
						'key'     => '_asrekhodro_category_id',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		if ( is_wp_error( $terms ) || $terms === array() ) {
			return 0;
		}

		$count = 0;

		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$old_id = (int) get_term_meta( $term->term_id, '_asrekhodro_category_id', true );
			if ( $old_id <= 0 ) {
				continue;
			}

			if ( ! preg_match( '/^cat-\d+$/i', $term->slug ) ) {
				continue;
			}

			$slug   = self::unique_category_slug( $term->name, $term->term_id, $old_id );
			$result = wp_update_term(
				$term->term_id,
				'category',
				array(
					'slug' => $slug,
				)
			);

			if ( ! is_wp_error( $result ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Rebuild truncated post_name values for imported news from their titles.
	 *
	 * @return array{checked: int, updated: int, skipped: int, errors: int, column_ok: bool}
	 */
	public static function repair_imported_news_slugs(): array {
		$stats = array(
			'checked'   => 0,
			'updated'   => 0,
			'skipped'   => 0,
			'errors'    => 0,
			'column_ok' => self::ensure_post_name_column(),
		);

		$paged = 1;

		while ( true ) {
			$posts = get_posts(
				array(
					'post_type'              => 'post',
					'post_status'            => 'any',
					'posts_per_page'         => 100,
					'paged'                  => $paged,
					'fields'                 => 'ids',
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'meta_key'               => '_asrekhodro_content_id',
					'meta_compare'           => 'EXISTS',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);

			if ( $posts === array() ) {
				break;
			}

			foreach ( $posts as $post_id ) {
				++$stats['checked'];
				$post_id = (int) $post_id;
				$result  = self::repair_single_news_slug( $post_id );

				if ( $result === 'updated' ) {
					++$stats['updated'];
				} elseif ( $result === 'skipped' ) {
					++$stats['skipped'];
				} else {
					++$stats['errors'];
				}
			}

			++$paged;
		}

		return $stats;
	}

	public static function count_imported_news_posts(): int {
		global $wpdb;

		$sql = "
			SELECT COUNT(DISTINCT p.ID)
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm
				ON pm.post_id = p.ID
				AND pm.meta_key = '_asrekhodro_content_id'
			WHERE p.post_type = 'post'
		";

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Repair a small slice so the admin UI can show progress and stop safely.
	 *
	 * @return array{total: int, offset: int, next_offset: int, checked: int, updated: int, skipped: int, errors: int, done: bool, column_ok: bool}
	 */
	public static function repair_imported_news_slugs_batch( int $offset = 0, int $limit = 50 ): array {
		$offset = max( 0, $offset );
		$limit  = max( 1, min( 200, $limit ) );
		$total  = self::count_imported_news_posts();
		$stats  = array(
			'total'       => $total,
			'offset'      => $offset,
			'next_offset' => $offset,
			'checked'     => 0,
			'updated'     => 0,
			'skipped'     => 0,
			'errors'      => 0,
			'done'        => true,
			'column_ok'   => self::ensure_post_name_column(),
		);

		if ( $total <= 0 || $offset >= $total ) {
			return $stats;
		}

		$posts = get_posts(
			array(
				'post_type'              => 'post',
				'post_status'            => 'any',
				'posts_per_page'         => $limit,
				'offset'                 => $offset,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'meta_key'               => '_asrekhodro_content_id',
				'meta_compare'           => 'EXISTS',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $posts as $post_id ) {
			++$stats['checked'];
			$result = self::repair_single_news_slug( (int) $post_id );

			if ( $result === 'updated' ) {
				++$stats['updated'];
			} elseif ( $result === 'skipped' ) {
				++$stats['skipped'];
			} else {
				++$stats['errors'];
			}
		}

		$stats['next_offset'] = $offset + $stats['checked'];
		$stats['done']        = $stats['checked'] === 0 || $stats['next_offset'] >= $total;

		return $stats;
	}

	/**
	 * @return 'updated'|'skipped'|'error'
	 */
	public static function repair_single_news_slug( int $post_id ): string {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || $post->post_type !== 'post' ) {
			return 'error';
		}

		$content_id = (int) get_post_meta( $post_id, '_asrekhodro_content_id', true );
		if ( $content_id <= 0 ) {
			return 'skipped';
		}

		$desired = self::slug_from_title( $post->post_title );
		if ( $desired === '' ) {
			$desired = 'content-' . $content_id;
		}

		$desired = self::unique_post_slug( $desired, $post_id );
		$current = (string) $post->post_name;

		if ( $current === $desired ) {
			$legacy = self::build_legacy_path( $content_id, $desired );
			if ( (string) get_post_meta( $post_id, '_asrekhodro_legacy_path', true ) !== $legacy ) {
				update_post_meta( $post_id, '_asrekhodro_legacy_path', $legacy );
			}

			return 'skipped';
		}

		$saved = self::write_post_name( $post_id, $desired );
		if ( ! $saved ) {
			return 'error';
		}

		update_post_meta( $post_id, '_asrekhodro_legacy_path', self::build_legacy_path( $content_id, $desired ) );
		clean_post_cache( $post_id );

		return 'updated';
	}

	/**
	 * Persist post_name without going through sanitize_title truncation again.
	 */
	public static function write_post_name( int $post_id, string $slug ): bool {
		global $wpdb;

		$slug = trim( $slug, '-' );
		if ( $post_id <= 0 || $slug === '' ) {
			return false;
		}

		$updated = $wpdb->update(
			$wpdb->posts,
			array( 'post_name' => $slug ),
			array( 'ID' => $post_id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	public static function build_legacy_path( int $content_id, string $slug ): string {
		return '/' . self::BASE_PATH . '/' . $content_id . '/' . trim( $slug, '/' );
	}

	public static function build_url( int $content_id, string $slug ): string {
		return home_url( user_trailingslashit( self::BASE_PATH . '/' . $content_id . '/' . $slug ) );
	}

	public static function build_url_for_post( int $post_id ): ?string {
		$content_id = (int) get_post_meta( $post_id, '_asrekhodro_content_id', true );
		if ( $content_id <= 0 ) {
			return null;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}

		$slug = self::resolve_slug( $post );

		return self::build_url( $content_id, $slug );
	}

	public static function resolve_slug( \WP_Post $post ): string {
		$from_title = self::slug_from_title( $post->post_title );
		$slug       = (string) $post->post_name;

		if ( $from_title !== '' ) {
			if (
				$slug === ''
				|| str_starts_with( $slug, 'content-' )
				|| str_contains( $slug, '%' )
				|| ( $slug !== $from_title && str_starts_with( $from_title, $slug ) )
			) {
				return $from_title;
			}
		}

		if ( $slug !== '' ) {
			return $slug;
		}

		return $from_title !== '' ? $from_title : 'post-' . $post->ID;
	}

	public static function sync_post( int $post_id, int $content_id, string $title ): string {
		$slug = self::slug_from_title( $title );
		if ( $slug === '' ) {
			$slug = 'content-' . $content_id;
		}

		$slug = self::unique_post_slug( $slug, $post_id );
		self::write_post_name( $post_id, $slug );
		update_post_meta( $post_id, '_asrekhodro_legacy_path', self::build_legacy_path( $content_id, $slug ) );
		clean_post_cache( $post_id );

		return $slug;
	}

	public static function find_post_id_by_content_id( int $content_id ): int {
		if ( $content_id <= 0 ) {
			return 0;
		}

		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'meta_key'       => '_asrekhodro_content_id',
				'meta_value'     => $content_id,
				'fields'         => 'ids',
			)
		);

		return ! empty( $posts ) ? (int) $posts[0] : 0;
	}
}
