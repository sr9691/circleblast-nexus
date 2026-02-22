<?php
/**
 * Matching Engine
 *
 * ITER-0010: Core scoring algorithm for intelligent 1:1 member matching.
 * Loads active rules from the database, builds context data, scores all
 * possible pairs, and returns ranked suggestions.
 */

defined('ABSPATH') || exit;

final class CBNexus_Matching_Engine {

	/**
	 * Run the matching engine and return scored pairs.
	 *
	 * @param int $max_suggestions Max pairs to return. 0 = all.
	 * @return array Array of ['member_a_id', 'member_b_id', 'score', 'breakdown'].
	 */
	public static function generate_suggestions(int $max_suggestions = 0): array {
		// 1. Load active members.
		$members = CBNexus_Member_Repository::get_all_members('active');

		// Filter out members who paused matching or are on quarterly cadence.
		$members = array_filter($members, function ($m) {
			$freq = get_user_meta((int) $m['user_id'], 'cb_matching_frequency', true);
			if ($freq === 'paused') { return false; }
			if ($freq === 'quarterly') {
				return !self::was_recently_suggested((int) $m['user_id'], 80);
			}
			return true;
		});
		$members = array_values($members);

		if (count($members) < 2) {
			return [];
		}

		// 2. Load active rules.
		$rules = self::get_active_rules();
		if (empty($rules)) {
			return [];
		}

		// 3. Build context data (meeting history, counts, response rates).
		$context = self::build_context($members);

		// 4. Score all possible pairs.
		$pairs = self::score_all_pairs($members, $rules, $context);

		// 5. Sort by score descending.
		usort($pairs, fn($a, $b) => $b['score'] <=> $a['score']);

		// 6. Greedy selection: each member appears at most once.
		$selected = self::greedy_select($pairs, $max_suggestions);

		return $selected;
	}

	/**
	 * Dry-run: preview suggestions without creating meetings.
	 *
	 * @param int $max Max pairs.
	 * @return array Scored pairs with member names and breakdown.
	 */
	public static function dry_run(int $max = 20): array {
		$suggestions = self::generate_suggestions($max);

		// Enrich with names.
		foreach ($suggestions as &$s) {
			$pa = CBNexus_Member_Repository::get_profile($s['member_a_id']);
			$pb = CBNexus_Member_Repository::get_profile($s['member_b_id']);
			$s['member_a_name'] = $pa ? $pa['display_name'] : '(Unknown)';
			$s['member_b_name'] = $pb ? $pb['display_name'] : '(Unknown)';
		}

		return $suggestions;
	}

	/**
	 * Get active rules from the database.
	 *
	 * @return array Rule rows.
	 */
	public static function get_active_rules(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'cb_matching_rules';

		$found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
		if ($found !== $table) { return []; }

		return $wpdb->get_results(
			"SELECT * FROM {$table} WHERE is_active = 1 ORDER BY id ASC"
		) ?: [];
	}

	/**
	 * Get all rules (active and inactive) for admin UI.
	 */
	public static function get_all_rules(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'cb_matching_rules';

		$found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
		if ($found !== $table) { return []; }

		return $wpdb->get_results("SELECT * FROM {$table} ORDER BY id ASC") ?: [];
	}

	/**
	 * Update a rule's weight, active status, and config.
	 *
	 * @param int   $rule_id   Rule ID.
	 * @param array $data      Fields to update (weight, is_active, config_json).
	 * @return bool
	 */
	public static function update_rule(int $rule_id, array $data): bool {
		global $wpdb;
		$data['updated_at'] = gmdate('Y-m-d H:i:s');

		return $wpdb->update(
			$wpdb->prefix . 'cb_matching_rules',
			$data,
			['id' => $rule_id]
		) !== false;
	}

	// ─── Context Builder ───────────────────────────────────────────────

	/**
	 * Check if a member was suggested in the last N days.
	 *
	 * @param int $user_id User ID.
	 * @param int $days    Lookback period in days.
	 * @return bool
	 */
	private static function was_recently_suggested(int $user_id, int $days): bool {
		global $wpdb;
		$cutoff = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
		return (bool) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}cb_meetings
			 WHERE (member_a_id = %d OR member_b_id = %d)
			 AND source = 'auto'
			 AND suggested_at > %s",
			$user_id, $user_id, $cutoff
		));
	}

	/**
	 * Build pre-computed context data for all rules to use.
	 *
	 * @param array $members All active member profiles.
	 * @return array Context data.
	 */
	private static function build_context(array $members): array {
		global $wpdb;

		$user_ids = array_map(fn($m) => (int) $m['user_id'], $members);

		// Pair last meeting dates.
		$pair_last_meeting = self::build_pair_meeting_history($wpdb);

		// Meeting counts per member (completed + closed).
		$meeting_counts = self::build_meeting_counts($wpdb, $user_ids);

		// Response rates per member.
		$response_rates = self::build_response_rates($wpdb, $user_ids);

		return [
			'pair_last_meeting' => $pair_last_meeting,
			'meeting_counts'    => $meeting_counts,
			'response_rates'    => $response_rates,
		];
	}

	private static function build_pair_meeting_history($wpdb): array {
		$table = $wpdb->prefix . 'cb_meetings';

		$rows = $wpdb->get_results(
			"SELECT member_a_id, member_b_id, MAX(COALESCE(completed_at, created_at)) as last_date
			 FROM {$table}
			 WHERE status IN ('completed', 'closed')
			 GROUP BY LEAST(member_a_id, member_b_id), GREATEST(member_a_id, member_b_id)"
		);

		$map = [];
		foreach ($rows as $row) {
			$ids = [(int) $row->member_a_id, (int) $row->member_b_id];
			sort($ids);
			$map[$ids[0] . ':' . $ids[1]] = $row->last_date;
		}

		return $map;
	}

	private static function build_meeting_counts($wpdb, array $user_ids): array {
		if (empty($user_ids)) { return []; }

		$table = $wpdb->prefix . 'cb_meetings';
		$placeholders = implode(',', array_fill(0, count($user_ids), '%d'));

		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT user_id, SUM(cnt) as total FROM (
				SELECT member_a_id as user_id, COUNT(*) as cnt FROM {$table} WHERE status IN ('completed', 'closed') AND member_a_id IN ({$placeholders}) GROUP BY member_a_id
				UNION ALL
				SELECT member_b_id as user_id, COUNT(*) as cnt FROM {$table} WHERE status IN ('completed', 'closed') AND member_b_id IN ({$placeholders}) GROUP BY member_b_id
			) t GROUP BY user_id",
			...array_merge($user_ids, $user_ids)
		));

		$map = [];
		foreach ($rows as $row) {
			$map[(int) $row->user_id] = (int) $row->total;
		}
		return $map;
	}

	private static function build_response_rates($wpdb, array $user_ids): array {
		if (empty($user_ids)) { return []; }

		$table = $wpdb->prefix . 'cb_meeting_responses';
		$placeholders = implode(',', array_fill(0, count($user_ids), '%d'));

		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT responder_id,
				SUM(CASE WHEN response = 'accepted' THEN 1 ELSE 0 END) as accepted,
				COUNT(*) as total
			 FROM {$table}
			 WHERE responder_id IN ({$placeholders})
			 GROUP BY responder_id",
			...$user_ids
		));

		$map = [];
		foreach ($rows as $row) {
			$t = (int) $row->total;
			$map[(int) $row->responder_id] = ($t > 0) ? (int) $row->accepted / $t : 1.0;
		}
		return $map;
	}

	// ─── Scoring ───────────────────────────────────────────────────────

	private static function score_all_pairs(array $members, array $rules, array $context): array {
		$pairs = [];
		$count = count($members);

		// Batch-load all active meeting pairs in a single query instead of N² individual checks.
		$active_pairs = self::get_all_active_meeting_pairs();

		for ($i = 0; $i < $count; $i++) {
			for ($j = $i + 1; $j < $count; $j++) {
				$a = $members[$i];
				$b = $members[$j];

				// Skip if they already have an active meeting (O(1) hash lookup).
				$pair_key = min((int) $a['user_id'], (int) $b['user_id']) . ':' . max((int) $a['user_id'], (int) $b['user_id']);
				if (isset($active_pairs[$pair_key])) {
					continue;
				}

				$total_score = 0.0;
				$breakdown   = [];

				foreach ($rules as $rule) {
					$config = json_decode($rule->config_json ?: '{}', true) ?: [];
					$raw    = CBNexus_Matching_Rules::score($rule->rule_type, $a, $b, $context, $config);
					$weighted = $raw * (float) $rule->weight;

					$total_score += $weighted;
					$breakdown[$rule->rule_type] = [
						'raw'      => round($raw, 3),
						'weight'   => (float) $rule->weight,
						'weighted' => round($weighted, 3),
					];
				}

				$pairs[] = [
					'member_a_id' => (int) $a['user_id'],
					'member_b_id' => (int) $b['user_id'],
					'score'       => round($total_score, 3),
					'breakdown'   => $breakdown,
				];
			}
		}

		return $pairs;
	}

	/**
	 * Greedy selection: pick highest-scored pairs ensuring each member
	 * appears at most once.
	 */
	private static function greedy_select(array $sorted_pairs, int $max): array {
		$selected = [];
		$used     = [];

		foreach ($sorted_pairs as $pair) {
			if ($max > 0 && count($selected) >= $max) {
				break;
			}

			$a = $pair['member_a_id'];
			$b = $pair['member_b_id'];

			if (isset($used[$a]) || isset($used[$b])) {
				continue;
			}

			$selected[] = $pair;
			$used[$a] = true;
			$used[$b] = true;
		}

		return $selected;
	}

	/**
	 * Load all active meeting pairs in a single query.
	 * Returns a hash map with keys like "min_id:max_id" for O(1) lookup.
	 *
	 * @return array<string, true>
	 */
	private static function get_all_active_meeting_pairs(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'cb_meetings';

		$rows = $wpdb->get_results(
			"SELECT member_a_id, member_b_id FROM {$table}
			 WHERE status NOT IN ('closed', 'declined', 'cancelled')"
		);

		$pairs = [];
		foreach ($rows ?: [] as $row) {
			$key = min((int) $row->member_a_id, (int) $row->member_b_id) . ':' . max((int) $row->member_a_id, (int) $row->member_b_id);
			$pairs[$key] = true;
		}
		return $pairs;
	}
}
