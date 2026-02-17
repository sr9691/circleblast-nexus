<?php
/**
 * Migration: 008 - Create matching rules table
 *
 * ITER-0010: Configurable rules with weights for the matching engine.
 * Seeded with 10 default rules on creation.
 */

defined('ABSPATH') || exit;

final class CBNexus_Migration_008_Create_Matching_Rules {

	public static function up(): bool {
		global $wpdb;

		$table   = $wpdb->prefix . 'cb_matching_rules';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			rule_type VARCHAR(50) NOT NULL,
			label VARCHAR(100) NOT NULL,
			description TEXT DEFAULT NULL,
			weight DECIMAL(5,2) NOT NULL DEFAULT 1.00,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			config_json TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY idx_rule_type (rule_type)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);

		$found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
		if ($found !== $table) {
			return false;
		}

		// Seed default rules if empty.
		$count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
		if ($count === 0) {
			self::seed_defaults($table);
		}

		return true;
	}

	private static function seed_defaults(string $table): void {
		global $wpdb;
		$now = gmdate('Y-m-d H:i:s');

		$rules = [
			['meeting_history',        'Meeting History',          "Haven't met > met long ago > met recently",                  3.00],
			['industry_diversity',     'Industry Diversity',       'Pairs members from different industries',                    2.00],
			['expertise_complement',   'Expertise Complementarity','Matches complementary skill sets',                           2.00],
			['needs_alignment',        'Needs Alignment',          "Matches looking_for with can_help_with",                     2.50],
			['new_member_priority',    'New Member Priority',      'Boosts newer members to accelerate connections',             1.50],
			['tenure_balance',         'Tenure Balance',           'Pairs experienced members with newer ones',                  1.00],
			['meeting_frequency',      'Meeting Frequency',        'Members with fewer meetings get priority',                   1.50],
			['response_rate',          'Response Rate',            'Members who accept/respond get priority',                    1.00],
			['admin_boost',            'Admin Boost',              'Admin can boost specific pairings via config',               0.00],
			['recency_penalty',        'Recency Penalty',          'Penalizes pairs who met within the last 2 months',          -1.50],
		];

		foreach ($rules as $r) {
			$wpdb->insert($table, [
				'rule_type'   => $r[0],
				'label'       => $r[1],
				'description' => $r[2],
				'weight'      => $r[3],
				'is_active'   => 1,
				'config_json' => '{}',
				'created_at'  => $now,
				'updated_at'  => $now,
			], ['%s', '%s', '%s', '%f', '%d', '%s', '%s', '%s']);
		}
	}
}
