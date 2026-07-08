<?php

namespace ABI\Translator\Core\Maintenance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABI\Translator\Core\Filters\TermFilters;
use ABI\Translator\Core\Filters\TranslatablePostTypes;
use ABI\Translator\Core\Support\Logger;
use ABI\Translator\Core\Translation\TranslationRepository;

/**
 * Invalidates cached translations when the source object changes.
 *
 * Why purge in addition to source_hash: the stored source_hash already makes a
 * translation stale automatically when the exact hashed Persian text changes, so
 * routine content edits self-refresh. Explicit purging is still valuable to
 *   1) drop orphaned rows when a post/term is deleted,
 *   2) allow a forced refresh even when the hashed text is unchanged (e.g. after
 *      switching provider/model, or edits that don't alter the exact stored field),
 *   3) give deterministic behaviour instead of relying only on the next hash diff.
 * The two mechanisms are complementary; we keep both.
 */
final class PurgeHooks {

	private TranslationRepository $repository;

	public function __construct( TranslationRepository $repository ) {
		$this->repository = $repository;
	}

	public function register(): void {
		add_action( 'save_post', array( $this, 'on_save_post' ), 10, 2 );
		add_action( 'deleted_post', array( $this, 'on_deleted_post' ), 10, 2 );
		add_action( 'edited_term', array( $this, 'on_edited_term' ), 10, 3 );
		add_action( 'delete_term', array( $this, 'on_delete_term' ), 10, 4 );
	}

	/**
	 * @param mixed $post
	 */
	public function on_save_post( int $post_id, $post ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! $post instanceof \WP_Post ) {
			$post = get_post( $post_id );
		}
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		// Ignore auto-draft / inherit states; only purge real, translatable content.
		if ( in_array( $post->post_status, array( 'auto-draft', 'inherit' ), true ) ) {
			return;
		}

		if ( ! TranslatablePostTypes::is_translatable( $post->post_type ) ) {
			return;
		}

		$this->purge( $post->post_type, $post_id );
	}

	/**
	 * @param mixed $post
	 */
	public function on_deleted_post( int $post_id, $post = null ): void {
		if ( ! $post instanceof \WP_Post ) {
			$post = get_post( $post_id );
		}

		// On delete the post type may still be resolvable; if not, purge best-effort
		// across all known translatable types so no orphan rows remain.
		if ( $post instanceof \WP_Post && TranslatablePostTypes::is_translatable( $post->post_type ) ) {
			$this->purge( $post->post_type, $post_id );
			return;
		}

		foreach ( TranslatablePostTypes::all() as $type ) {
			$this->purge( $type, $post_id );
		}
	}

	public function on_edited_term( int $term_id, int $tt_id, string $taxonomy ): void {
		if ( ! TermFilters::is_translatable_taxonomy( $taxonomy ) ) {
			return;
		}

		$this->purge( 'term', $term_id );
	}

	/**
	 * @param mixed $deleted_term
	 */
	public function on_delete_term( int $term_id, int $tt_id, string $taxonomy, $deleted_term = null ): void {
		if ( ! TermFilters::is_translatable_taxonomy( $taxonomy ) ) {
			return;
		}

		$this->purge( 'term', $term_id );
	}

	private function purge( string $object_type, int $object_id ): void {
		try {
			$this->repository->delete_for_object( $object_type, $object_id );
		} catch ( \Throwable $e ) {
			Logger::warning(
				'Cache purge failed',
				array(
					'object_type' => $object_type,
					'object_id'   => $object_id,
					'reason'      => $e->getMessage(),
				)
			);
		}
	}
}
