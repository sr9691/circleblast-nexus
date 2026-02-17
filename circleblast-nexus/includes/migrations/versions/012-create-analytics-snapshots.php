<?php
/**
 * Migration: 012 - Create analytics snapshots table
 *
 * ITER-0015: Historical metric tracking for dashboard trends.
 * Stores nightly snapshots of key metrics per member and club-wide.
 */

defined('ABSPATH') || exit;

final class CBNexus_Migration_012_Create_Analytics_Snapshots {

	public static function up(): bool {
		global $wpdb;

		$table   = $wpdb->prefix . 'cb_analytics_snapshots';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			snapshot_date DATE NOT NULL,
			scope VARCHAR(20) NOT NULL DEFAULT 'club',
			member_id BIGINT UNSIGNED DEFAULT NULL,
			metric_key VARCHAR(50) NOT NULL,
			metric_value DECIMAL(10,2) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_date_scope (snapshot_date, scope),
			KEY idx_member_metric (member_id, metric_key),
			UNIQUE KEY idx_unique_snap (snapshot_date, scope, member_id, metric_key)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);

		$found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
		return ($found === $table);
	}
}
