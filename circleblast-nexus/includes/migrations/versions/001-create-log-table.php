<?php
/**
 * Migration: 001 - Create log table
 *
 * Creates {$wpdb->prefix}cbnexus_log.
 */

defined('ABSPATH') || exit;

final class CBNexus_Migration_001_Create_Log_Table {

	public static function id(): string {
		return '001_create_log_table';
	}

	/**
	 * Apply migration.
	 *
	 * @return bool True on success (or if already applied).
	 */
	public static function up(): bool {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table = $wpdb->prefix . 'cbnexus_log';
		$charset_collate = $wpdb->get_charset_collate();

		// Keep schema minimal and safe.
		// No IP/email; context_json is optional.
		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at_gmt DATETIME NOT NULL,
			level VARCHAR(20) NOT NULL,
			message TEXT NOT NULL,
			context_json LONGTEXT NULL,
			source VARCHAR(191) NULL,
			request_id VARCHAR(64) NULL,
			user_id BIGINT(20) UNSIGNED NULL,
			PRIMARY KEY  (id),
			KEY created_at_gmt (created_at_gmt),
			KEY level (level),
			KEY request_id (request_id)
		) {$charset_collate};";

		dbDelta($sql);

		// Validate existence after dbDelta.
		$found = $wpdb->get_var($wpdb->prepare(
			"SHOW TABLES LIKE %s",
			$table
		));

		return ($found === $table);
	}
}
