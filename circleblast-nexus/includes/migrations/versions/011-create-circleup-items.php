<?php
/**
 * Migration: 011 - Create CircleUp items table
 *
 * ITER-0012: Structured items extracted from CircleUp meetings
 * (wins, insights, opportunities, action items).
 */

defined('ABSPATH') || exit;

final class CBNexus_Migration_011_Create_CircleUp_Items {

	public static function up(): bool {
		global $wpdb;

		$table   = $wpdb->prefix . 'cb_circleup_items';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			circleup_meeting_id BIGINT UNSIGNED NOT NULL,
			item_type VARCHAR(20) NOT NULL,
			content TEXT NOT NULL,
			speaker_id BIGINT UNSIGNED DEFAULT NULL,
			assigned_to BIGINT UNSIGNED DEFAULT NULL,
			due_date DATE DEFAULT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_meeting_id (circleup_meeting_id),
			KEY idx_item_type (item_type),
			KEY idx_speaker_id (speaker_id),
			KEY idx_assigned_to (assigned_to),
			KEY idx_status (status)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);

		$found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
		return ($found === $table);
	}
}
