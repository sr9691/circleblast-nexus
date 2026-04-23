<?php
/**
 * Migration 026: Add is_private column to cb_meeting_notes.
 *
 * Allows members to choose whether their meeting notes are shared
 * with the group (visible in dashboards/presentations) or kept private.
 * Default is 0 (shared) to preserve existing behavior.
 */

defined('ABSPATH') || exit;

final class CBNexus_Migration_026_Add_Meeting_Notes_Visibility {

	public static function up(): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'cb_meeting_notes';

		// Idempotent: skip if column already exists.
		$col = $wpdb->get_results($wpdb->prepare(
			'SHOW COLUMNS FROM `' . $table . '` LIKE %s',
			'is_private'
		));
		if (!empty($col)) {
			return true;
		}

		$result = $wpdb->query(
			"ALTER TABLE `{$table}` ADD COLUMN `is_private` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `rating`"
		);

		return ($result !== false);
	}
}
