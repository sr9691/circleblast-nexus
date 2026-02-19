<?php
/**
 * Migration 018: Add guest_cost to cb_events.
 *
 * The existing `cost` column is repurposed as `member_cost` at the application
 * layer. This migration adds a parallel `guest_cost` column.
 */

defined('ABSPATH') || exit;

final class CBNexus_Migration_018_Add_Guest_Cost {

	public static function up(): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'cb_events';

		// Add guest_cost column if it doesn't exist.
		$col = $wpdb->get_var("SHOW COLUMNS FROM `{$table}` LIKE 'guest_cost'");
		if ($col === null) {
			$wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `guest_cost` VARCHAR(100) DEFAULT NULL AFTER `cost`");
		}

		return $wpdb->get_var("SHOW COLUMNS FROM `{$table}` LIKE 'guest_cost'") !== null;
	}
}
