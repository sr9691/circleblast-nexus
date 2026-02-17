<?php
/**
 * Migration: 010 - Create CircleUp attendees table
 *
 * ITER-0012: Tracks member attendance at CircleUp meetings.
 */

defined('ABSPATH') || exit;

final class CBNexus_Migration_010_Create_CircleUp_Attendees {

	public static function up(): bool {
		global $wpdb;

		$table   = $wpdb->prefix . 'cb_circleup_attendees';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			circleup_meeting_id BIGINT UNSIGNED NOT NULL,
			member_id BIGINT UNSIGNED NOT NULL,
			attendance_status VARCHAR(20) NOT NULL DEFAULT 'present',
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY idx_meeting_member (circleup_meeting_id, member_id),
			KEY idx_member_id (member_id)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);

		$found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
		return ($found === $table);
	}
}
