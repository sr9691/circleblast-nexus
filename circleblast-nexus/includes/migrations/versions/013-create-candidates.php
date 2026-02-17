<?php
/**
 * Migration: 013 - Create candidates table
 *
 * ITER-0017: Recruitment pipeline for tracking potential new members.
 */

defined('ABSPATH') || exit;

final class CBNexus_Migration_013_Create_Candidates {

	public static function up(): bool {
		global $wpdb;

		$table   = $wpdb->prefix . 'cb_candidates';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(200) NOT NULL,
			email VARCHAR(200) DEFAULT NULL,
			company VARCHAR(200) DEFAULT NULL,
			industry VARCHAR(100) DEFAULT NULL,
			referrer_id BIGINT UNSIGNED DEFAULT NULL,
			stage VARCHAR(30) NOT NULL DEFAULT 'referral',
			notes TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_stage (stage),
			KEY idx_referrer (referrer_id)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);

		$found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
		return ($found === $table);
	}
}
