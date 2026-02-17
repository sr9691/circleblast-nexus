<?php
/**
 * Migration: 009 - Create CircleUp meetings table
 *
 * ITER-0012: CircleUp monthly meeting records with transcript storage,
 * AI summaries, and publication workflow status.
 */

defined('ABSPATH') || exit;

final class CBNexus_Migration_009_Create_CircleUp_Meetings {

	public static function up(): bool {
		global $wpdb;

		$table   = $wpdb->prefix . 'cb_circleup_meetings';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			meeting_date DATE NOT NULL,
			title VARCHAR(255) NOT NULL,
			fireflies_id VARCHAR(100) DEFAULT NULL,
			full_transcript LONGTEXT DEFAULT NULL,
			ai_summary TEXT DEFAULT NULL,
			curated_summary TEXT DEFAULT NULL,
			duration_minutes INT UNSIGNED DEFAULT NULL,
			recording_url VARCHAR(500) DEFAULT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			published_by BIGINT UNSIGNED DEFAULT NULL,
			published_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY idx_fireflies_id (fireflies_id),
			KEY idx_meeting_date (meeting_date),
			KEY idx_status (status)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);

		$found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
		return ($found === $table);
	}
}
