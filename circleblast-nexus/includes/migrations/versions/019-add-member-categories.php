<?php
/**
 * Migration: 019 - Add member-category linkage
 *
 * Phase 1 of Recruitment Coverage Visibility:
 * - Adds target_count column to cb_recruitment_categories (default 1).
 * - Adds category_id FK column to cb_candidates so recruits link to a category.
 * - Registers cb_member_categories in the member meta schema (array of category IDs).
 */

defined('ABSPATH') || exit;

final class CBNexus_Migration_019_Add_Member_Categories {

	public static function up(): bool {
		$ok_target   = self::add_target_count_column();
		$ok_cat_fk   = self::add_candidate_category_id();
		$ok_meta     = self::register_member_categories_meta();

		return $ok_target && $ok_cat_fk && $ok_meta;
	}

	/**
	 * Add target_count column to cb_recruitment_categories.
	 */
	private static function add_target_count_column(): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'cb_recruitment_categories';

		$col = $wpdb->get_var("SHOW COLUMNS FROM `{$table}` LIKE 'target_count'");
		if ($col === null) {
			$wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `target_count` INT NOT NULL DEFAULT 1 AFTER `is_filled`");
		}

		return $wpdb->get_var("SHOW COLUMNS FROM `{$table}` LIKE 'target_count'") !== null;
	}

	/**
	 * Add category_id column to cb_candidates so recruits can be linked to a category.
	 */
	private static function add_candidate_category_id(): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'cb_candidates';

		$col = $wpdb->get_var("SHOW COLUMNS FROM `{$table}` LIKE 'category_id'");
		if ($col === null) {
			$wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `category_id` BIGINT UNSIGNED DEFAULT NULL AFTER `industry`");
			$wpdb->query("ALTER TABLE `{$table}` ADD KEY `idx_category` (`category_id`)");
		}

		return $wpdb->get_var("SHOW COLUMNS FROM `{$table}` LIKE 'category_id'") !== null;
	}

	/**
	 * Register cb_member_categories in the member meta schema option.
	 */
	private static function register_member_categories_meta(): bool {
		$schema = get_option('cbnexus_member_meta_schema', []);

		if (!isset($schema['cb_member_categories'])) {
			$schema['cb_member_categories'] = [
				'label'              => 'Recruitment Category',
				'type'               => 'category_select',
				'required'           => false,
				'editable_by_member' => false,
				'section'            => 'admin',
			];
			update_option('cbnexus_member_meta_schema', $schema, false);
		}

		$saved = get_option('cbnexus_member_meta_schema', []);
		return isset($saved['cb_member_categories']);
	}
}
