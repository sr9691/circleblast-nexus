<?php
/**
 * Migration: 017 - Create recruitment categories table
 *
 * Stores the types/categories of members the group is looking to recruit.
 * Admins manage this list and can blast it to all members on a schedule.
 */

defined('ABSPATH') || exit;

final class CBNexus_Migration_017_Create_Recruitment_Categories {

	public static function up(): bool {
		global $wpdb;

		$table   = $wpdb->prefix . 'cb_recruitment_categories';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(200) NOT NULL,
			description TEXT DEFAULT NULL,
			industry VARCHAR(100) DEFAULT NULL,
			priority ENUM('high','medium','low') NOT NULL DEFAULT 'medium',
			is_filled TINYINT(1) NOT NULL DEFAULT 0,
			sort_order INT NOT NULL DEFAULT 0,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY idx_priority (priority),
			KEY idx_filled (is_filled)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);

		$found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
		return ($found === $table);
	}
}
