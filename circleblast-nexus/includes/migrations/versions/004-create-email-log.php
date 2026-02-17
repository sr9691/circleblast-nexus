<?php
/**
 * Migration: 004 - Create email log table
 *
 * ITER-0005: Tracks all automated emails sent by the plugin.
 */

defined('ABSPATH') || exit;

final class CBNexus_Migration_004_Create_Email_Log {

	public static function up(): bool {
		global $wpdb;

		$table   = $wpdb->prefix . 'cb_email_log';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			recipient_id BIGINT UNSIGNED DEFAULT NULL,
			recipient_email VARCHAR(200) NOT NULL,
			template_id VARCHAR(50) NOT NULL,
			subject VARCHAR(255) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'sent',
			related_id BIGINT UNSIGNED DEFAULT NULL,
			related_type VARCHAR(50) DEFAULT NULL,
			error_message TEXT DEFAULT NULL,
			sent_at_gmt DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_recipient_id (recipient_id),
			KEY idx_template_id (template_id),
			KEY idx_sent_at_gmt (sent_at_gmt),
			KEY idx_status (status)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);

		$found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
		return ($found === $table);
	}
}
