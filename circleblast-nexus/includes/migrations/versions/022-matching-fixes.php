<?php
/**
 * Migration 022: Matching System Fixes
 *
 * Adds reminder_count column to cb_meetings for capping follow-up reminders.
 * Registers notification preference meta keys in the member meta schema.
 */

defined('ABSPATH') || exit;

final class CBNexus_Migration_022_Matching_Fixes {

	/**
	 * Run the migration.
	 *
	 * @return bool True on success.
	 */
	public static function up(): bool {
		global $wpdb;

		// 1. Add reminder_count to cb_meetings.
		$table = $wpdb->prefix . 'cb_meetings';
		$col   = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'reminder_count'");
		if (empty($col)) {
			$wpdb->query("ALTER TABLE {$table} ADD COLUMN reminder_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER notes_status");
		}

		// 2. Register notification preference meta keys.
		$schema = get_option('cbnexus_member_meta_schema', []);

		$schema['cb_matching_frequency'] = [
			'label'              => 'Matching Frequency',
			'type'               => 'select',
			'required'           => false,
			'editable_by_member' => true,
			'section'            => 'preferences',
			'options'            => ['monthly', 'quarterly', 'paused'],
			'default'            => 'monthly',
		];
		$schema['cb_email_digest'] = [
			'label'              => 'Events Digest Emails',
			'type'               => 'select',
			'required'           => false,
			'editable_by_member' => true,
			'section'            => 'preferences',
			'options'            => ['yes', 'no'],
			'default'            => 'yes',
		];
		$schema['cb_email_reminders'] = [
			'label'              => 'Reminder Emails',
			'type'               => 'select',
			'required'           => false,
			'editable_by_member' => true,
			'section'            => 'preferences',
			'options'            => ['yes', 'no'],
			'default'            => 'yes',
		];

		update_option('cbnexus_member_meta_schema', $schema, false);

		// Validate.
		$check = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'reminder_count'");
		return !empty($check);
	}
}
