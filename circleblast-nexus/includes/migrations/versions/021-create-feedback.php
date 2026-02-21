<?php
/**
 * Migration 021: Create cb_feedback table.
 *
 * Stores member feedback and issue reports submitted through the portal.
 */

defined('ABSPATH') || exit;

final class CBNexus_Migration_021_Create_Feedback {

	public static function up(): bool {
		global $wpdb;
		$table   = $wpdb->prefix . 'cb_feedback';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id     BIGINT UNSIGNED NOT NULL,
			type        VARCHAR(20)     NOT NULL DEFAULT 'feedback',
			subject     VARCHAR(255)    NOT NULL DEFAULT '',
			message     TEXT            NOT NULL,
			page_context VARCHAR(100)   NOT NULL DEFAULT '',
			status      VARCHAR(20)     NOT NULL DEFAULT 'new',
			admin_notes TEXT            NULL,
			resolved_by BIGINT UNSIGNED NULL,
			resolved_at DATETIME        NULL,
			created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_user_id    (user_id),
			KEY idx_status     (status),
			KEY idx_created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);

		// Verify the table was created.
		$exists = $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
			DB_NAME,
			$table
		));

		return (bool) $exists;
	}
}
