<?php
/**
 * Migration: 015 - Create events table
 *
 * Events submitted by members or admins. Supports recurring events,
 * audience targeting, reminder notes, and registration links.
 */

defined('ABSPATH') || exit;

final class CBNexus_Migration_015_Create_Events {

	public static function up(): bool {
		global $wpdb;

		$table   = $wpdb->prefix . 'cb_events';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(200) NOT NULL,
			description TEXT DEFAULT NULL,
			event_date DATE NOT NULL,
			event_time TIME DEFAULT NULL,
			end_date DATE DEFAULT NULL,
			end_time TIME DEFAULT NULL,
			location VARCHAR(255) DEFAULT NULL,
			location_url VARCHAR(500) DEFAULT NULL,
			audience ENUM('all','members','public') NOT NULL DEFAULT 'all',
			category VARCHAR(100) DEFAULT NULL,
			registration_url VARCHAR(500) DEFAULT NULL,
			reminder_notes TEXT DEFAULT NULL,
			cost VARCHAR(100) DEFAULT NULL,
			max_attendees INT UNSIGNED DEFAULT NULL,
			organizer_id BIGINT UNSIGNED NOT NULL,
			status ENUM('draft','pending','approved','cancelled') NOT NULL DEFAULT 'pending',
			approved_by BIGINT UNSIGNED DEFAULT NULL,
			approved_at DATETIME DEFAULT NULL,
			reminder_sent TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_event_date (event_date),
			KEY idx_status (status),
			KEY idx_organizer (organizer_id),
			KEY idx_audience (audience)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);

		$found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
		return ($found === $table);
	}
}
