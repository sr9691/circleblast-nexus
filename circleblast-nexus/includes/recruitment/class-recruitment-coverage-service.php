<?php
/**
 * Recruitment Coverage Service
 *
 * Phase 1 of Recruitment Coverage Visibility.
 * Computes coverage status for each recruitment category based on
 * which active members are tagged with that category via cb_member_categories.
 * Also retrieves pipeline candidates linked to each category.
 */

defined('ABSPATH') || exit;

final class CBNexus_Recruitment_Coverage_Service {

	/**
	 * Coverage status constants.
	 */
	public const STATUS_COVERED  = 'covered';
	public const STATUS_PARTIAL  = 'partial';
	public const STATUS_GAP      = 'gap';

	/**
	 * Get full coverage data for all categories.
	 *
	 * Returns an array of category objects enriched with:
	 *   - member_count:  int
	 *   - members:       array of {user_id, display_name}
	 *   - coverage_status: 'covered' | 'partial' | 'gap'
	 *   - pipeline_candidates: array of candidate objects linked to this category
	 *
	 * @return array
	 */
	public static function get_full_coverage(): array {
		global $wpdb;

		$cat_table = $wpdb->prefix . 'cb_recruitment_categories';
		$categories = $wpdb->get_results("SELECT * FROM {$cat_table} ORDER BY sort_order ASC, priority DESC");

		if (empty($categories)) {
			return [];
		}

		// Build a map of category_id => [member data].
		$member_map = self::build_member_category_map();

		// Build a map of category_id => [pipeline candidates].
		$pipeline_map = self::build_pipeline_map();

		$results = [];
		foreach ($categories as $cat) {
			$members      = $member_map[$cat->id] ?? [];
			$member_count = count($members);
			$target       = isset($cat->target_count) ? max(1, (int) $cat->target_count) : 1;

			if ($member_count >= $target) {
				$status = self::STATUS_COVERED;
			} elseif ($member_count > 0) {
				$status = self::STATUS_PARTIAL;
			} else {
				$status = self::STATUS_GAP;
			}

			$cat->member_count       = $member_count;
			$cat->members            = $members;
			$cat->coverage_status    = $status;
			$cat->target_count       = $target;
			$cat->pipeline_candidates = $pipeline_map[$cat->id] ?? [];

			$results[] = $cat;
		}

		return $results;
	}

	/**
	 * Get summary stats across all categories.
	 *
	 * @return array{total: int, covered: int, partial: int, gaps: int, coverage_pct: float}
	 */
	public static function get_summary(): array {
		$categories = self::get_full_coverage();
		$total   = count($categories);
		$covered = 0;
		$partial = 0;
		$gaps    = 0;

		foreach ($categories as $cat) {
			switch ($cat->coverage_status) {
				case self::STATUS_COVERED:
					$covered++;
					break;
				case self::STATUS_PARTIAL:
					$partial++;
					break;
				case self::STATUS_GAP:
					$gaps++;
					break;
			}
		}

		return [
			'total'        => $total,
			'covered'      => $covered,
			'partial'      => $partial,
			'gaps'         => $gaps,
			'coverage_pct' => $total > 0 ? round(($covered / $total) * 100, 1) : 0,
		];
	}

	/**
	 * Get top N gap categories (open needs), ordered by priority.
	 *
	 * @param int $limit Max number of gaps to return.
	 * @return array
	 */
	public static function get_top_gaps(int $limit = 5): array {
		$categories = self::get_full_coverage();
		$priority_order = ['high' => 0, 'medium' => 1, 'low' => 2];

		$gaps = array_filter($categories, function ($cat) {
			return $cat->coverage_status === self::STATUS_GAP;
		});

		usort($gaps, function ($a, $b) use ($priority_order) {
			$pa = $priority_order[$a->priority] ?? 1;
			$pb = $priority_order[$b->priority] ?? 1;
			return $pa - $pb;
		});

		return array_slice($gaps, 0, $limit);
	}

	/**
	 * Get coverage data for a single category.
	 *
	 * @param int $category_id
	 * @return object|null
	 */
	public static function get_category_coverage(int $category_id): ?object {
		$all = self::get_full_coverage();
		foreach ($all as $cat) {
			if ((int) $cat->id === $category_id) {
				return $cat;
			}
		}
		return null;
	}

	/**
	 * Get the category ID assigned to a member (single category per the 1:1 constraint).
	 *
	 * @param int $user_id
	 * @return int|null Category ID or null.
	 */
	public static function get_member_category(int $user_id): ?int {
		$raw = get_user_meta($user_id, 'cb_member_categories', true);
		if (empty($raw)) {
			return null;
		}

		// Stored as JSON array but we only use the first value (one category per member).
		$decoded = is_string($raw) ? json_decode($raw, true) : $raw;
		if (is_array($decoded) && !empty($decoded)) {
			return (int) $decoded[0];
		}

		// Handle legacy scalar value.
		if (is_numeric($raw)) {
			return (int) $raw;
		}

		return null;
	}

	/**
	 * Set the category for a member.
	 *
	 * @param int      $user_id
	 * @param int|null $category_id  Null or 0 to clear.
	 * @return void
	 */
	public static function set_member_category(int $user_id, ?int $category_id): void {
		if (empty($category_id)) {
			delete_user_meta($user_id, 'cb_member_categories');
			return;
		}

		// Store as JSON array for consistency with other array meta fields.
		update_user_meta($user_id, 'cb_member_categories', wp_json_encode([$category_id]));
	}

	/**
	 * Build a map of category_id => list of active members tagged with that category.
	 *
	 * @return array<int, array>
	 */
	private static function build_member_category_map(): array {
		$members = CBNexus_Member_Repository::get_all_members('active');
		$map = [];

		foreach ($members as $m) {
			$cat_id = self::get_member_category($m['user_id']);
			if ($cat_id === null) {
				continue;
			}

			if (!isset($map[$cat_id])) {
				$map[$cat_id] = [];
			}

			$map[$cat_id][] = [
				'user_id'      => $m['user_id'],
				'display_name' => $m['display_name'],
				'cb_company'   => $m['cb_company'] ?? '',
			];
		}

		return $map;
	}

	/**
	 * Build a map of category_id => pipeline candidates (not yet accepted/declined).
	 *
	 * @return array<int, array>
	 */
	private static function build_pipeline_map(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'cb_candidates';
		$active_stages = ['referral', 'contacted', 'invited', 'visited', 'decision'];
		$placeholders  = implode(',', array_fill(0, count($active_stages), '%s'));

		$candidates = $wpdb->get_results($wpdb->prepare(
			"SELECT id, name, email, company, industry, category_id, stage, updated_at
			 FROM {$table}
			 WHERE category_id IS NOT NULL AND stage IN ({$placeholders})
			 ORDER BY updated_at DESC",
			...$active_stages
		));

		$map = [];
		foreach ($candidates as $c) {
			$cid = (int) $c->category_id;
			if (!isset($map[$cid])) {
				$map[$cid] = [];
			}
			$map[$cid][] = $c;
		}

		return $map;
	}
}
