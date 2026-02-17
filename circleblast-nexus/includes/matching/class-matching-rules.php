<?php
/**
 * Matching Rules
 *
 * ITER-0010: Individual rule implementations for the matching engine.
 * Each rule scores a member pair from 0.0 (worst) to 1.0 (best).
 * The engine multiplies each score by the rule's configured weight.
 */

defined('ABSPATH') || exit;

final class CBNexus_Matching_Rules {

	/**
	 * Score a pair based on rule type.
	 *
	 * @param string $rule_type  Rule identifier.
	 * @param array  $member_a   Profile array of member A.
	 * @param array  $member_b   Profile array of member B.
	 * @param array  $context    Pre-computed context (meeting history, etc.).
	 * @param array  $config     Rule-specific config from config_json.
	 * @return float Score between 0.0 and 1.0.
	 */
	public static function score(string $rule_type, array $member_a, array $member_b, array $context, array $config): float {
		return match ($rule_type) {
			'meeting_history'       => self::meeting_history($member_a, $member_b, $context),
			'industry_diversity'    => self::industry_diversity($member_a, $member_b),
			'expertise_complement'  => self::expertise_complement($member_a, $member_b),
			'needs_alignment'       => self::needs_alignment($member_a, $member_b),
			'new_member_priority'   => self::new_member_priority($member_a, $member_b),
			'tenure_balance'        => self::tenure_balance($member_a, $member_b),
			'meeting_frequency'     => self::meeting_frequency($member_a, $member_b, $context),
			'response_rate'         => self::response_rate($member_a, $member_b, $context),
			'admin_boost'           => self::admin_boost($member_a, $member_b, $config),
			'recency_penalty'       => self::recency_penalty($member_a, $member_b, $context),
			default                 => 0.5,
		};
	}

	/**
	 * Haven't met = 1.0, met long ago = partial, met recently = 0.0.
	 */
	private static function meeting_history(array $a, array $b, array $ctx): float {
		$pair_key = self::pair_key($a, $b);
		$last_meeting = $ctx['pair_last_meeting'][$pair_key] ?? null;

		if ($last_meeting === null) {
			return 1.0; // Never met.
		}

		$days_ago = (time() - strtotime($last_meeting)) / 86400;

		if ($days_ago > 365) { return 0.9; }
		if ($days_ago > 180) { return 0.7; }
		if ($days_ago > 90)  { return 0.4; }
		if ($days_ago > 30)  { return 0.15; }
		return 0.0; // Met within last month.
	}

	/**
	 * Different industries = 1.0, same = 0.3.
	 */
	private static function industry_diversity(array $a, array $b): float {
		$ind_a = $a['cb_industry'] ?? '';
		$ind_b = $b['cb_industry'] ?? '';

		if ($ind_a === '' || $ind_b === '') { return 0.5; }
		return ($ind_a !== $ind_b) ? 1.0 : 0.3;
	}

	/**
	 * Complementary expertise — more overlap = lower, more unique = higher.
	 */
	private static function expertise_complement(array $a, array $b): float {
		$exp_a = self::to_tags($a['cb_expertise'] ?? []);
		$exp_b = self::to_tags($b['cb_expertise'] ?? []);

		if (empty($exp_a) || empty($exp_b)) { return 0.5; }

		$overlap  = count(array_intersect($exp_a, $exp_b));
		$total    = count(array_unique(array_merge($exp_a, $exp_b)));

		if ($total === 0) { return 0.5; }

		// Jaccard distance: less overlap = more complementary.
		$jaccard = $overlap / $total;
		return 1.0 - $jaccard;
	}

	/**
	 * Match looking_for with can_help_with (bidirectional).
	 */
	private static function needs_alignment(array $a, array $b): float {
		$a_looking = self::to_tags($a['cb_looking_for'] ?? []);
		$a_help    = self::to_tags($a['cb_can_help_with'] ?? []);
		$b_looking = self::to_tags($b['cb_looking_for'] ?? []);
		$b_help    = self::to_tags($b['cb_can_help_with'] ?? []);

		$matches = 0;
		$total   = 0;

		// A's needs vs B's offerings.
		if (!empty($a_looking) && !empty($b_help)) {
			$matches += count(array_intersect($a_looking, $b_help));
			$total   += count($a_looking);
		}

		// B's needs vs A's offerings.
		if (!empty($b_looking) && !empty($a_help)) {
			$matches += count(array_intersect($b_looking, $a_help));
			$total   += count($b_looking);
		}

		if ($total === 0) { return 0.3; }
		return min(1.0, $matches / max(1, $total) * 1.5); // Boost for strong alignment.
	}

	/**
	 * Members joined within last 90 days get a boost.
	 */
	private static function new_member_priority(array $a, array $b): float {
		$new_a = self::days_since_join($a) < 90;
		$new_b = self::days_since_join($b) < 90;

		if ($new_a && $new_b) { return 0.8; } // Both new — connect them.
		if ($new_a || $new_b) { return 1.0; } // One new — high priority.
		return 0.3; // Both established.
	}

	/**
	 * Pair experienced with newer members for mentorship.
	 */
	private static function tenure_balance(array $a, array $b): float {
		$days_a = self::days_since_join($a);
		$days_b = self::days_since_join($b);

		$diff = abs($days_a - $days_b);
		if ($diff > 365) { return 0.8; } // Large tenure gap = mentorship opportunity.
		if ($diff > 180) { return 0.6; }
		if ($diff > 90)  { return 0.4; }
		return 0.3; // Similar tenure.
	}

	/**
	 * Members with fewer total meetings get priority.
	 */
	private static function meeting_frequency(array $a, array $b, array $ctx): float {
		$count_a = $ctx['meeting_counts'][$a['user_id']] ?? 0;
		$count_b = $ctx['meeting_counts'][$b['user_id']] ?? 0;

		$min_meetings = min($count_a, $count_b);

		if ($min_meetings === 0) { return 1.0; } // Someone has zero meetings.
		if ($min_meetings < 3)   { return 0.8; }
		if ($min_meetings < 6)   { return 0.5; }
		return 0.3; // Both well-connected.
	}

	/**
	 * Members who accept/respond to requests get priority.
	 */
	private static function response_rate(array $a, array $b, array $ctx): float {
		$rate_a = $ctx['response_rates'][$a['user_id']] ?? 1.0;
		$rate_b = $ctx['response_rates'][$b['user_id']] ?? 1.0;

		return ($rate_a + $rate_b) / 2.0;
	}

	/**
	 * Admin-configured boost for specific pairings.
	 * Config format: {"boost_pairs": [[user_id_a, user_id_b], ...]}
	 */
	private static function admin_boost(array $a, array $b, array $config): float {
		$pairs = $config['boost_pairs'] ?? [];
		foreach ($pairs as $pair) {
			if (!is_array($pair) || count($pair) < 2) { continue; }
			if (($pair[0] == $a['user_id'] && $pair[1] == $b['user_id'])
				|| ($pair[0] == $b['user_id'] && $pair[1] == $a['user_id'])) {
				return 1.0;
			}
		}
		return 0.0; // No boost.
	}

	/**
	 * Penalize pairs who met within the last 2 months.
	 * Returns 1.0 for penalty (multiplied by negative weight).
	 */
	private static function recency_penalty(array $a, array $b, array $ctx): float {
		$pair_key = self::pair_key($a, $b);
		$last_meeting = $ctx['pair_last_meeting'][$pair_key] ?? null;

		if ($last_meeting === null) { return 0.0; } // No penalty.

		$days_ago = (time() - strtotime($last_meeting)) / 86400;
		if ($days_ago < 60) { return 1.0; } // Full penalty.
		if ($days_ago < 90) { return 0.5; } // Partial penalty.
		return 0.0; // No penalty.
	}

	// ─── Helpers ────────────────────────────────────────────────────────

	private static function pair_key(array $a, array $b): string {
		$ids = [(int) $a['user_id'], (int) $b['user_id']];
		sort($ids);
		return $ids[0] . ':' . $ids[1];
	}

	private static function to_tags($value): array {
		if (is_array($value)) {
			return array_map('strtolower', array_map('trim', $value));
		}
		if (is_string($value) && $value !== '') {
			return array_map('strtolower', array_map('trim', explode(',', $value)));
		}
		return [];
	}

	private static function days_since_join(array $member): int {
		$join = $member['cb_join_date'] ?? '';
		if ($join === '') { return 0; }
		$diff = time() - strtotime($join);
		return max(0, (int) ($diff / 86400));
	}
}
