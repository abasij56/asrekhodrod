<?php

namespace ABI\Translator\Core\Translation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABI\Translator\Core\Installer;

/**
 * Data-access layer for the {prefix}abi_translations table.
 * Translations are ALWAYS stored here, never in post_meta.
 */
final class TranslationRepository {

	private string $table;

	public function __construct() {
		$this->table = Installer::table_name();
	}

	/**
	 * Fetch a stored translation only if the source hash still matches.
	 * Returns null on miss or when the source text changed (stale).
	 */
	public function get( string $object_type, int $object_id, string $field, string $lang, string $source_hash ): ?string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
		$sql = $wpdb->prepare(
			"SELECT translated_text, source_hash FROM {$this->table}
			 WHERE object_type = %s AND object_id = %d AND field = %s AND lang = %s
			 LIMIT 1",
			$object_type,
			$object_id,
			$field,
			$lang
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $sql, ARRAY_A );

		if ( ! is_array( $row ) ) {
			return null;
		}

		if ( ( $row['source_hash'] ?? '' ) !== $source_hash ) {
			return null; // Stale: original text changed.
		}

		return (string) $row['translated_text'];
	}

	/**
	 * Batch-fetch stored translations for many objects of the same type/field/lang.
	 * Only rows whose source_hash still matches the provided hash are returned.
	 *
	 * @param array<int, string> $id_to_hash Map of object_id => current source hash.
	 * @return array<int, string> Map of object_id => translated_text (matches only).
	 */
	public function getBatch( string $object_type, array $id_to_hash, string $field, string $lang ): array {
		global $wpdb;

		$ids = array_map( 'intval', array_keys( $id_to_hash ) );
		$ids = array_values( array_filter( $ids, static fn( int $id ): bool => $id > 0 ) );

		if ( $ids === array() ) {
			return array();
		}

		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		$params = array_merge( array( $object_type, $field, $lang ), $ids );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name + generated placeholders.
		$sql = $wpdb->prepare(
			"SELECT object_id, translated_text, source_hash FROM {$this->table}
			 WHERE object_type = %s AND field = %s AND lang = %s AND object_id IN ({$placeholders})",
			$params
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();
		foreach ( $rows as $row ) {
			$id = (int) ( $row['object_id'] ?? 0 );
			if ( ! isset( $id_to_hash[ $id ] ) ) {
				continue;
			}
			if ( (string) ( $row['source_hash'] ?? '' ) !== (string) $id_to_hash[ $id ] ) {
				continue; // Stale.
			}
			$out[ $id ] = (string) $row['translated_text'];
		}

		return $out;
	}

	/**
	 * Insert or update a translation row (unique on object_type+object_id+field+lang).
	 */
	public function save(
		string $object_type,
		int $object_id,
		string $field,
		string $lang,
		string $source_hash,
		string $translated_text,
		string $provider = '',
		string $model = ''
	): void {
		global $wpdb;

		$now = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
		$sql = $wpdb->prepare(
			"INSERT INTO {$this->table}
				(object_type, object_id, field, lang, source_hash, translated_text, provider, model, created_at, updated_at)
			 VALUES (%s, %d, %s, %s, %s, %s, %s, %s, %s, %s)
			 ON DUPLICATE KEY UPDATE
				source_hash = VALUES(source_hash),
				translated_text = VALUES(translated_text),
				provider = VALUES(provider),
				model = VALUES(model),
				updated_at = VALUES(updated_at)",
			$object_type,
			$object_id,
			$field,
			$lang,
			$source_hash,
			$translated_text,
			$provider,
			$model,
			$now,
			$now
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $sql );
	}

	/**
	 * Delete all cached translations for an object (used when the source is updated/purged).
	 */
	public function delete_for_object( string $object_type, int $object_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete(
			$this->table,
			array(
				'object_type' => $object_type,
				'object_id'   => $object_id,
			),
			array( '%s', '%d' )
		);
	}
}
