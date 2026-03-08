<?php
/**
 * Migration 024: Add meeting_type column to cb_circleup_meetings.
 *
 * Supports two meeting types:
 *   - circleup  (default) — monthly all-member meeting
 *   - council             — select admins-only meeting
 */

defined('ABSPATH') || exit;

final class CBNexus_Migration_024_Add_Meeting_Type {

	public static function up(): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'cb_circleup_meetings';

		// Idempotent: skip if column already exists.
		$col = $wpdb->get_results( $wpdb->prepare(
			'SHOW COLUMNS FROM `' . $table . '` LIKE %s',
			'meeting_type'
		) );
		if ( ! empty( $col ) ) {
			return true;
		}

		// Add meeting_type after the title column.
		$result = $wpdb->query(
			"ALTER TABLE `{$table}` ADD COLUMN `meeting_type` VARCHAR(32) NOT NULL DEFAULT 'circleup' AFTER `title`"
		);

		if ( $result === false ) {
			return false;
		}

		// Index for filtering by type.
		$wpdb->query( "ALTER TABLE `{$table}` ADD INDEX `idx_cu_meeting_type` (`meeting_type`)" );

		return true;
	}
}
