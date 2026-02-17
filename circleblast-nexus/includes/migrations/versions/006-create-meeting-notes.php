<?php
/**
 * Migration: 006 - Create meeting notes table
 *
 * ITER-0008: Structured post-meeting notes capture.
 * Each participant submits their own notes for a meeting.
 */

defined('ABSPATH') || exit;

final class CBNexus_Migration_006_Create_Meeting_Notes {

	public static function up(): bool {
		global $wpdb;

		$table   = $wpdb->prefix . 'cb_meeting_notes';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			meeting_id BIGINT UNSIGNED NOT NULL,
			author_id BIGINT UNSIGNED NOT NULL,
			wins TEXT DEFAULT NULL,
			insights TEXT DEFAULT NULL,
			action_items TEXT DEFAULT NULL,
			rating TINYINT UNSIGNED DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_meeting_id (meeting_id),
			KEY idx_author_id (author_id),
			UNIQUE KEY idx_meeting_author (meeting_id, author_id)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);

		$found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
		return ($found === $table);
	}
}
