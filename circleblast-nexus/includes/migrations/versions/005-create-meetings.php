<?php
/**
 * Migration: 005 - Create meetings table
 *
 * ITER-0008: Core table for 1:1 meeting records.
 * Tracks the full meeting lifecycle from suggestion through notes capture.
 */

defined('ABSPATH') || exit;

final class CBNexus_Migration_005_Create_Meetings {

	public static function up(): bool {
		global $wpdb;

		$table   = $wpdb->prefix . 'cb_meetings';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			member_a_id BIGINT UNSIGNED NOT NULL,
			member_b_id BIGINT UNSIGNED NOT NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'pending',
			source VARCHAR(20) NOT NULL DEFAULT 'manual',
			score DECIMAL(5,2) DEFAULT NULL,
			suggested_at DATETIME DEFAULT NULL,
			scheduled_at DATETIME DEFAULT NULL,
			completed_at DATETIME DEFAULT NULL,
			notes_status VARCHAR(20) NOT NULL DEFAULT 'none',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_member_a (member_a_id),
			KEY idx_member_b (member_b_id),
			KEY idx_status (status),
			KEY idx_scheduled_at (scheduled_at),
			KEY idx_created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);

		$found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
		return ($found === $table);
	}
}
