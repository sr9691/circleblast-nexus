<?php
/**
 * Migration: 014 - Create tokens table
 *
 * Universal token system for email-based actions.
 * Tokens are hashed (SHA-256), support single-use and multi-use,
 * and expire after a configurable period.
 */

defined('ABSPATH') || exit;

final class CBNexus_Migration_014_Create_Tokens {

	public static function up(): bool {
		global $wpdb;

		$table   = $wpdb->prefix . 'cb_tokens';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			token VARCHAR(64) NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			action VARCHAR(50) NOT NULL,
			payload TEXT DEFAULT NULL,
			multi_use TINYINT(1) NOT NULL DEFAULT 0,
			use_count INT UNSIGNED NOT NULL DEFAULT 0,
			expires_at DATETIME NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY idx_token (token),
			KEY idx_user_action (user_id, action),
			KEY idx_expires (expires_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);

		$found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
		return ($found === $table);
	}
}
