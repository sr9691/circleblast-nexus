<?php
/**
 * Migration: 016 - Create event RSVPs table
 */

defined('ABSPATH') || exit;

final class CBNexus_Migration_016_Create_Event_RSVPs {

	public static function up(): bool {
		global $wpdb;

		$table   = $wpdb->prefix . 'cb_event_rsvps';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id BIGINT UNSIGNED NOT NULL,
			member_id BIGINT UNSIGNED NOT NULL,
			status ENUM('going','maybe','not_going') NOT NULL DEFAULT 'going',
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY idx_event_member (event_id, member_id),
			KEY idx_member (member_id)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);

		$found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
		return ($found === $table);
	}
}
