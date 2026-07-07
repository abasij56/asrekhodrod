<?php

namespace ABI\Translator\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles DB schema creation/removal and rewrite flushing on activation.
 */
final class Installer {

	public const DB_VERSION_OPTION = 'abi_translator_db_version';

	/**
	 * Full translations table name including the site prefix.
	 */
	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'abi_translations';
	}

	public static function activate(): void {
		self::create_table();
		update_option( self::DB_VERSION_OPTION, ABI_TRANSLATOR_DB_VERSION, false );

		// Ensure /en/ routing works right after activation.
		flush_rewrite_rules( false );
	}

	public static function deactivate(): void {
		flush_rewrite_rules( false );
	}

	/**
	 * Create/upgrade the translations table. Never stores translations in post_meta.
	 */
	public static function create_table(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			object_type     VARCHAR(32)  NOT NULL,
			object_id       BIGINT UNSIGNED NOT NULL DEFAULT 0,
			field           VARCHAR(64)  NOT NULL,
			lang            VARCHAR(10)  NOT NULL,
			source_hash     CHAR(64)     NOT NULL,
			translated_text LONGTEXT     NOT NULL,
			provider        VARCHAR(32)  NOT NULL DEFAULT '',
			model           VARCHAR(64)  NOT NULL DEFAULT '',
			created_at      DATETIME     NOT NULL,
			updated_at      DATETIME     NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_object (object_type, object_id, field, lang),
			KEY idx_lang (lang),
			KEY idx_hash (source_hash)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Create the table on demand if a version bump happened without re-activation.
	 */
	public static function maybe_upgrade(): void {
		$installed = (string) get_option( self::DB_VERSION_OPTION, '' );

		if ( $installed === ABI_TRANSLATOR_DB_VERSION ) {
			return;
		}

		self::create_table();
		update_option( self::DB_VERSION_OPTION, ABI_TRANSLATOR_DB_VERSION, false );
	}
}
