<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Imports image galleries from gallery/gallery-*.json into post_content.
 * Prepends a marked HTML block before existing content; re-runs replace the prior block.
 */
final class AsreKhodro_Gallery_Importer {

	private const MARKER_START = '<!-- ak-gallery:start -->';
	private const MARKER_END   = '<!-- ak-gallery:end -->';

	private string $import_dir;

	/** @var array<int, string> */
	private array $log = array();

	public function __construct( ?string $import_dir = null ) {
		$this->import_dir = $import_dir ?: ASREKHODRO_IMPORTER_DEFAULT_IMPORT_DIR;
	}

	/**
	 * @return array{updated:int,skipped_not_found:int,skipped_no_images:int,errors:int,log:array<int,string>}
	 */
	public function import_batch( array $rows, AsreKhodro_Importer $importer ): array {
		$stats = array(
			'updated'            => 0,
			'skipped_not_found'  => 0,
			'skipped_no_images'  => 0,
			'errors'             => 0,
		);

		$content_ids = array();
		foreach ( $rows as $row ) {
			$content_id = (int) ( $row['contentId'] ?? 0 );
			if ( $content_id > 0 ) {
				$content_ids[] = $content_id;
			}
		}

		$importer->prefetch_content_ids( $content_ids );

		foreach ( $rows as $row ) {
			$result = $this->import_one( $row, $importer );
			if ( isset( $stats[ $result ] ) ) {
				++$stats[ $result ];
			}
		}

		$stats['log'] = $this->log;

		return $stats;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return 'updated'|'skipped_not_found'|'skipped_no_images'|'errors'
	 */
	public function import_one( array $row, AsreKhodro_Importer $importer ): string {
		$content_id = (int) ( $row['contentId'] ?? 0 );
		if ( $content_id <= 0 ) {
			return 'skipped_no_images';
		}

		$images = $row['images'] ?? array();
		if ( ! is_array( $images ) || count( $images ) < 2 ) {
			return 'skipped_no_images';
		}

		$post_id = $importer->get_post_id_for_content_id( $content_id );
		if ( $post_id <= 0 ) {
			$this->log[] = sprintf( 'Skip contentId %d: post not found', $content_id );
			return 'skipped_not_found';
		}

		$gallery_html = self::build_gallery_html( $images );
		if ( $gallery_html === '' ) {
			return 'skipped_no_images';
		}

		$content = (string) get_post_field( 'post_content', $post_id );
		$content = self::strip_existing_gallery( $content );
		$new     = $gallery_html . "\n\n" . ltrim( $content );

		$updated = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $new,
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			$this->log[] = sprintf(
				'Error contentId %d (post %d): %s',
				$content_id,
				$post_id,
				$updated->get_error_message()
			);
			return 'errors';
		}

		$this->log[] = sprintf(
			'Updated contentId %d → post %d (%d images)',
			$content_id,
			$post_id,
			substr_count( $gallery_html, '<img' )
		);

		return 'updated';
	}

	/**
	 * @param array<int, mixed> $images
	 */
	public static function build_gallery_html( array $images ): string {
		$lines = array( self::MARKER_START );

		foreach ( $images as $url ) {
			$url = trim( (string) $url );
			if ( $url === '' ) {
				continue;
			}

			$lines[] = sprintf(
				'<p><img src="%s" alt="" /></p>',
				esc_url( $url )
			);
		}

		if ( count( $lines ) <= 1 ) {
			return '';
		}

		$lines[] = self::MARKER_END;
		$html    = implode( "\n", $lines );

		if ( class_exists( '\AsreKhodro\Theme\ImporterBridge' ) ) {
			$html = \AsreKhodro\Theme\ImporterBridge::rewrite_content_media_urls( $html );
		}

		return $html;
	}

	public static function strip_existing_gallery( string $content ): string {
		if ( $content === '' || ! str_contains( $content, 'ak-gallery:start' ) ) {
			return $content;
		}

		$result = preg_replace(
			'/<!--\s*ak-gallery:start\s*-->.*?<!--\s*ak-gallery:end\s*-->\s*/is',
			'',
			$content
		);

		return is_string( $result ) ? trim( $result ) : $content;
	}

	public static function extract_existing_gallery( string $content ): string {
		if ( $content === '' || ! str_contains( $content, 'ak-gallery:start' ) ) {
			return '';
		}

		if ( preg_match(
			'/<!--\s*ak-gallery:start\s*-->.*?<!--\s*ak-gallery:end\s*-->/is',
			$content,
			$matches
		) ) {
			return trim( (string) $matches[0] );
		}

		return '';
	}
}
