<?php
/**
 * Migration: 007 - Create meeting responses table
 *
 * ITER-0008: Tracks member responses to meeting requests/suggestions.
 */

defined('ABSPATH') || exit;

final class CBNexus_Migration_007_Create_Meeting_Responses {

	public static function up(): bool {
		global $wpdb;

		$table   = $wpdb->prefix . 'cb_meeting_responses';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			meeting_id BIGINT UNSIGNED NOT NULL,
			responder_id BIGINT UNSIGNED NOT NULL,
			response VARCHAR(20) NOT NULL,
			message TEXT DEFAULT NULL,
			responded_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_meeting_id (meeting_id),
			KEY idx_responder_id (responder_id)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);

		$found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
		return ($found === $table);
	}
}
