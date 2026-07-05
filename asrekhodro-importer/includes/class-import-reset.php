<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fast bulk SQL reset for one-time migration re-imports.
 * Only runs for types that have export JSON data (count > 0).
 */
final class AsreKhodro_Import_Reset {

	private const POST_SQL_BATCH = 5000;

	/** @var array<string, array{label: string, totals_key: string}> */
	private const TYPES = array(
		'comments'         => array(
			'label'      => 'Comments',
			'totals_key' => 'comments',
		),
		'post_categories'  => array(
			'label'      => 'Post categories',
			'totals_key' => 'post_categories',
		),
		'tags'             => array(
			'label'      => 'Tags',
			'totals_key' => 'tags',
		),
		'post_relations'   => array(
			'label'      => 'Related posts',
			'totals_key' => 'post_relations',
		),
		'front_sections'   => array(
			'label'      => 'Homepage sections',
			'totals_key' => 'front_sections',
		),
		'ads'              => array(
			'label'      => 'Ads',
			'totals_key' => 'ads',
		),
		'magazines'        => array(
			'label'      => 'Magazines',
			'totals_key' => 'magazines',
		),
		'reviews'          => array(
			'label'      => 'Reviews',
			'totals_key' => 'reviews',
		),
		'videos'           => array(
			'label'      => 'Videos',
			'totals_key' => 'videos',
		),
		'video_categories' => array(
			'label'      => 'Video categories',
			'totals_key' => 'video_categories',
		),
		'posts'            => array(
			'label'      => 'Posts',
			'totals_key' => 'posts',
		),
		'categories'       => array(
			'label'      => 'Categories',
			'totals_key' => 'categories',
		),
	);

	/** @var array<int, string> */
	private const ORDER = array(
		'comments',
		'post_categories',
		'tags',
		'post_relations',
		'front_sections',
		'ads',
		'magazines',
		'reviews',
		'videos',
		'video_categories',
		'posts',
		'categories',
	);

	/** @var array<int, string> Types made redundant when a parent type is also reset. */
	private const REDUNDANT_WHEN = array(
		'comments'         => 'posts',
		'post_categories'  => 'posts',
		'tags'             => 'posts',
		'post_relations'   => 'posts',
		'front_sections'   => 'posts',
		'video_categories' => 'videos',
	);

	/**
	 * @return array<string, array{label: string, totals_key: string}>
	 */
	public static function get_types(): array {
		return self::TYPES;
	}

	/**
	 * @param array<string, bool> $flags
	 * @param array<string, int>  $totals
	 * @return array<int, string>
	 */
	public static function build_queue( array $flags, array $totals ): array {
		$selected = array();

		foreach ( self::ORDER as $type ) {
			if ( empty( $flags[ $type ] ) ) {
				continue;
			}

			$totals_key = self::TYPES[ $type ]['totals_key'] ?? $type;
			if ( (int) ( $totals[ $totals_key ] ?? 0 ) <= 0 ) {
				continue;
			}

			$selected[ $type ] = true;
		}

		$queue = array();
		foreach ( self::ORDER as $type ) {
			if ( empty( $selected[ $type ] ) ) {
				continue;
			}

			$parent = self::REDUNDANT_WHEN[ $type ] ?? null;
			if ( $parent !== null && ! empty( $selected[ $parent ] ) ) {
				continue;
			}

			$queue[] = $type;
		}

		return $queue;
	}

	/**
	 * @param array<string, bool> $raw
	 * @return array<string, bool>
	 */
	public static function parse_flags( array $raw ): array {
		$flags = array();

		foreach ( array_keys( self::TYPES ) as $type ) {
			if ( ! empty( $raw[ $type ] ) ) {
				$flags[ $type ] = true;
			}
		}

		return $flags;
	}

	/**
	 * Default reset flags for CLI/admin: every export type that has rows.
	 *
	 * @param array<string, int> $totals
	 * @return array<string, bool>
	 */
	public static function default_flags_for_totals( array $totals ): array {
		$flags = array();

		foreach ( self::TYPES as $type_id => $type_info ) {
			if ( (int) ( $totals[ $type_info['totals_key'] ] ?? 0 ) > 0 ) {
				$flags[ $type_id ] = true;
			}
		}

		return $flags;
	}

	public static function type_label( string $type ): string {
		return self::TYPES[ $type ]['label'] ?? $type;
	}

	/**
	 * Remove all items of the given type in one fast SQL pass.
	 */
	public static function reset_type( string $type ): int {
		@set_time_limit( 0 );
		wp_defer_term_counting( true );
		wp_defer_comment_counting( true );

		$deleted = match ( $type ) {
			'comments'         => self::reset_comments(),
			'post_categories'  => self::clear_taxonomy_links( 'category', 'post' ),
			'tags'             => self::clear_taxonomy_links( 'post_tag', 'post' ),
			'post_relations'   => self::clear_post_relations(),
			'front_sections'   => self::clear_front_sections(),
			'video_categories' => self::clear_taxonomy_links( 'video_category', 'ak_video' ),
			'ads'              => self::reset_posts_by_meta( 'ad_slot', '_asrekhodro_ad_id' ),
			'magazines'        => self::reset_posts_by_meta( 'ak_magazine', '_asrekhodro_file_id' ),
			'reviews'          => self::reset_posts_by_meta( 'ak_review', '_asrekhodro_content_id' ),
			'videos'           => self::reset_posts_by_meta( 'ak_video', '_asrekhodro_content_id' ),
			'posts'            => self::reset_posts_by_meta( 'post', '_asrekhodro_content_id', true ),
			'categories'       => self::reset_categories(),
			default            => 0,
		};

		wp_defer_term_counting( false );
		wp_defer_comment_counting( false );
		wp_cache_flush();

		return $deleted;
	}

	private static function reset_comments(): int {
		global $wpdb;

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT comment_id)
				FROM {$wpdb->commentmeta}
				WHERE meta_key = %s",
				'_asrekhodro_comment_id'
			)
		);

		if ( $count === 0 ) {
			return 0;
		}

		$wpdb->query(
			$wpdb->prepare(
				"DELETE c, cm
				FROM {$wpdb->comments} c
				INNER JOIN {$wpdb->commentmeta} marker
					ON c.comment_ID = marker.comment_id
					AND marker.meta_key = %s
				INNER JOIN {$wpdb->commentmeta} cm
					ON c.comment_ID = cm.comment_id",
				'_asrekhodro_comment_id'
			)
		);

		$wpdb->delete( $wpdb->commentmeta, array( 'meta_key' => '_asrekhodro_comment_id' ), array( '%s' ) );

		return $count;
	}

	private static function clear_post_relations(): int {
		global $wpdb;

		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
				WHERE p.post_type = %s",
				'_asrekhodro_content_id',
				'post'
			)
		);

		if ( empty( $post_ids ) ) {
			return 0;
		}

		$count = 0;
		foreach ( array_map( 'intval', $post_ids ) as $post_id ) {
			if ( $post_id <= 0 ) {
				continue;
			}

			if ( function_exists( 'update_field' ) ) {
				update_field( 'related_posts', array(), $post_id );
			}

			delete_post_meta( $post_id, 'related_posts' );
			delete_post_meta( $post_id, '_related_posts' );
			delete_post_meta( $post_id, '_asrekhodro_related_content_ids' );
			++$count;
		}

		return $count;
	}

	private static function clear_front_sections(): int {
		$fields = array(
			'home_main_slider',
			'home_main_ticker',
			'home_main_top_hits',
			'home_parsik',
			'home_special_events',
			'home_top_hits',
		);

		$count = 0;
		foreach ( $fields as $field_name ) {
			if ( function_exists( 'update_field' ) ) {
				update_field( $field_name, array(), 'option' );
			}

			delete_option( '_asrekhodro_' . $field_name . '_content_ids' );
			++$count;
		}

		return $count;
	}

	private static function clear_taxonomy_links( string $taxonomy, string $post_type ): int {
		global $wpdb;

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				WHERE p.post_type = %s
					AND tt.taxonomy = %s",
				'_asrekhodro_content_id',
				$post_type,
				$taxonomy
			)
		);

		if ( $count === 0 ) {
			return 0;
		}

		$wpdb->query(
			$wpdb->prepare(
				"DELETE tr
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				WHERE p.post_type = %s
					AND tt.taxonomy = %s",
				'_asrekhodro_content_id',
				$post_type,
				$taxonomy
			)
		);

		return $count;
	}

	private static function reset_posts_by_meta( string $post_type, string $meta_key, bool $delete_external_media = false ): int {
		global $wpdb;

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
				WHERE p.post_type = %s",
				$meta_key,
				$post_type
			)
		);

		if ( $total === 0 ) {
			return 0;
		}

		$deleted = 0;
		while ( true ) {
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT p.ID
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
					WHERE p.post_type = %s
					LIMIT %d",
					$meta_key,
					$post_type,
					self::POST_SQL_BATCH
				)
			);

			if ( ! is_array( $ids ) || $ids === array() ) {
				break;
			}

			if ( $delete_external_media ) {
				self::delete_external_attachments_for_posts( $ids );
			}

			$deleted += self::delete_posts_by_ids( $ids );

			if ( count( $ids ) < self::POST_SQL_BATCH ) {
				break;
			}
		}

		return $deleted > 0 ? $deleted : $total;
	}

	/**
	 * @param array<int, string|int> $post_ids
	 */
	private static function delete_external_attachments_for_posts( array $post_ids ): void {
		global $wpdb;

		foreach ( array_chunk( $post_ids, 500 ) as $chunk ) {
			$ids_sql = implode( ',', array_map( 'intval', $chunk ) );
			$attachment_ids = $wpdb->get_col(
				"SELECT DISTINCT meta_value
				FROM {$wpdb->postmeta}
				WHERE post_id IN ({$ids_sql})
					AND meta_key = '_asrekhodro_external_attachment_id'
					AND meta_value <> ''"
			);

			if ( is_array( $attachment_ids ) && $attachment_ids !== array() ) {
				self::delete_posts_by_ids( $attachment_ids );
			}
		}
	}

	/**
	 * @param array<int, string|int> $post_ids
	 */
	private static function delete_posts_by_ids( array $post_ids ): int {
		global $wpdb;

		$post_ids = array_values(
			array_unique(
				array_filter( array_map( 'intval', $post_ids ) )
			)
		);

		if ( $post_ids === array() ) {
			return 0;
		}

		$deleted = 0;

		foreach ( array_chunk( $post_ids, 500 ) as $chunk ) {
			$in = implode( ',', $chunk );

			$comment_ids = $wpdb->get_col( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_post_ID IN ({$in})" );
			if ( is_array( $comment_ids ) && $comment_ids !== array() ) {
				$comment_in = implode( ',', array_map( 'intval', $comment_ids ) );
				$wpdb->query( "DELETE FROM {$wpdb->commentmeta} WHERE comment_id IN ({$comment_in})" );
				$wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_ID IN ({$comment_in})" );
			}

			$wpdb->query( "DELETE FROM {$wpdb->term_relationships} WHERE object_id IN ({$in})" );
			$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$in})" );
			$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE ID IN ({$in})" );

			$deleted += count( $chunk );
		}

		return $deleted;
	}

	private static function reset_categories(): int {
		global $wpdb;

		$total = 0;

		foreach ( array( 'category', 'video_category' ) as $taxonomy ) {
			$term_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT tm.term_id
					FROM {$wpdb->termmeta} tm
					INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = tm.term_id
					WHERE tm.meta_key = %s
						AND tt.taxonomy = %s",
					'_asrekhodro_category_id',
					$taxonomy
				)
			);

			if ( ! is_array( $term_ids ) || $term_ids === array() ) {
				continue;
			}

			$term_ids = array_values( array_unique( array_map( 'intval', $term_ids ) ) );
			$total   += count( $term_ids );

			foreach ( array_chunk( $term_ids, 500 ) as $chunk ) {
				$in = implode( ',', $chunk );

				$wpdb->query(
					"DELETE tr
					FROM {$wpdb->term_relationships} tr
					INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
					WHERE tt.term_id IN ({$in})
						AND tt.taxonomy = '{$taxonomy}'"
				);

				$wpdb->query(
					"DELETE FROM {$wpdb->term_taxonomy}
					WHERE term_id IN ({$in})
						AND taxonomy = '{$taxonomy}'"
				);

				$wpdb->query( "DELETE FROM {$wpdb->termmeta} WHERE term_id IN ({$in})" );

				$wpdb->query(
					"DELETE t
					FROM {$wpdb->terms} t
					WHERE t.term_id IN ({$in})
						AND NOT EXISTS (
							SELECT 1 FROM {$wpdb->term_taxonomy} tt WHERE tt.term_id = t.term_id
						)"
				);
			}
		}

		$wpdb->delete( $wpdb->termmeta, array( 'meta_key' => '_asrekhodro_category_id' ), array( '%s' ) );

		return $total;
	}
}
