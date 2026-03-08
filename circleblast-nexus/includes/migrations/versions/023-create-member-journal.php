<?php
/**
 * Migration: 023 - Create member journal table
 *
 * Members can log wins, insights, referrals given/received, and action items
 * independently — without needing a 1:1 meeting record.
 */

defined('ABSPATH') || exit;

final class CBNexus_Migration_023_Create_Member_Journal {

	public static function up(): bool {
		global $wpdb;

		$table   = $wpdb->prefix . 'cb_member_journal';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			member_id   BIGINT UNSIGNED NOT NULL,
			entry_type  VARCHAR(32)     NOT NULL DEFAULT 'win',
			content     TEXT            NOT NULL,
			context     TEXT            DEFAULT NULL,
			entry_date  DATE            NOT NULL,
			visibility  VARCHAR(16)     NOT NULL DEFAULT 'private',
			created_at  DATETIME        NOT NULL,
			PRIMARY KEY (id),
			KEY idx_journal_member   (member_id),
			KEY idx_journal_type     (entry_type),
			KEY idx_journal_date     (entry_date),
			KEY idx_journal_member_type (member_id, entry_type)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);

		$found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
		return ($found === $table);
	}
}
