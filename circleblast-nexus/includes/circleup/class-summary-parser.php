<?php
/**
 * Summary Parser
 *
 * Parses structured meeting summaries (e.g. from Fireflies.ai note output)
 * into CircleUp items without requiring the Claude API. Looks for a clear
 * "Action items" section with assignee names and indented bullet descriptions,
 * and optionally parses the top-level bullet summary into insights.
 *
 * This provides a zero-cost, zero-API-key path to populate meeting items.
 */

defined('ABSPATH') || exit;

final class CBNexus_Summary_Parser {

	/**
	 * Parse a meeting summary/transcript and return structured items.
	 *
	 * @param string $text      The pasted summary text.
	 * @param int    $meeting_id Meeting ID (for context, not stored here).
	 * @return array{items: array, summary: string}
	 */
	public static function parse(string $text, int $meeting_id = 0): array {
		if (trim($text) === '') {
			return ['items' => [], 'summary' => ''];
		}

		// Build member name map for assignee resolution.
		$name_map = self::build_name_map();

		$items = [];

		// 1. Extract action items from the "Action items" section.
		$actions = self::extract_action_items($text, $name_map);
		$items = array_merge($items, $actions);

		// 2. Extract top-level bullet summary lines as insights.
		$insights = self::extract_summary_bullets($text, $name_map);
		$items = array_merge($items, $insights);

		// 3. Build a summary from the top bullets if present.
		$summary = self::extract_summary_text($text);

		return ['items' => $items, 'summary' => $summary];
	}

	/**
	 * Check if text appears to contain a parseable structured summary.
	 *
	 * @param string $text The text to check.
	 * @return bool
	 */
	public static function looks_parseable(string $text): bool {
		// Look for "Action items" section header or structured bullet patterns.
		return (bool) preg_match('/^#{0,3}\s*Action\s+items?\s*$/mi', $text)
			|| (bool) preg_match('/\n[A-Z][a-z]+ [A-Z][a-z]+\n/m', $text); // Name on its own line (assignee pattern).
	}

	// ─── Action Items Extraction ──────────────────────────────────────

	/**
	 * Parse the "Action items" section.
	 *
	 * Expected format:
	 *   Action items
	 *   Person Name
	 *   Description of action item (timestamp)
	 *   Another action for same person (timestamp)
	 *   Another Person
	 *   Their action item (timestamp)
	 *   Unassigned
	 *   Some unassigned action (timestamp)
	 */
	private static function extract_action_items(string $text, array $name_map): array {
		// Find the "Action items" section.
		$pattern = '/^#{0,3}\s*Action\s+items?\s*$/mi';
		if (!preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE)) {
			return [];
		}

		// Get everything after "Action items" header.
		$section = substr($text, $match[0][1] + strlen($match[0][0]));

		// Stop at the next major section header (if any).
		// Major sections typically start with a line that doesn't look like an action item.
		$lines = preg_split('/\r?\n/', $section);
		$items = [];
		$current_assignee = null;
		$current_assignee_id = null;

		foreach ($lines as $line) {
			$trimmed = trim($line);
			if ($trimmed === '') { continue; }

			// Check if this line is an assignee name (a line with just a name, no description text).
			// Assignee lines: short (1-4 words), no leading bullet, often a known member or "Unassigned".
			if (self::is_assignee_line($trimmed, $name_map)) {
				if (strtolower($trimmed) === 'unassigned') {
					$current_assignee = null;
					$current_assignee_id = null;
				} else {
					$current_assignee = $trimmed;
					$current_assignee_id = self::resolve_member($trimmed, $name_map);
				}
				continue;
			}

			// This is an action item line — clean it up.
			$content = self::clean_action_line($trimmed);
			if ($content === '') { continue; }

			// Extract timestamp reference if present (e.g. "(07:50)").
			$content = preg_replace('/\s*\(\d{1,2}:\d{2}\)\s*$/', '', $content);

			$items[] = [
				'item_type'   => 'action',
				'content'     => $content,
				'speaker_id'  => $current_assignee_id,
				'assigned_to' => $current_assignee_id,
				'due_date'    => null,
				'status'      => 'draft',
			];
		}

		return $items;
	}

	/**
	 * Check if a line looks like an assignee name rather than an action description.
	 */
	private static function is_assignee_line(string $line, array $name_map): bool {
		// "Unassigned" is a special assignee marker.
		if (strtolower($line) === 'unassigned') {
			return true;
		}

		// Remove any trailing colon.
		$clean = rtrim($line, ':');

		// Assignee lines are typically 1-4 words, no punctuation beyond the name.
		$word_count = str_word_count($clean);
		if ($word_count < 1 || $word_count > 4) {
			return false;
		}

		// Should not start with a bullet, dash, number, or typical action verbs.
		if (preg_match('/^[-•*▪▸►→\d]/', $clean)) {
			return false;
		}

		// Check if it matches a known member name.
		$resolved = self::resolve_member($clean, $name_map);
		if ($resolved !== null) {
			return true;
		}

		// Heuristic: looks like "Firstname Lastname" (each word capitalized, 2-3 words).
		if ($word_count >= 2 && $word_count <= 3 && preg_match('/^[A-Z][a-z]+ [A-Z][a-z]+/', $clean)) {
			return true;
		}

		return false;
	}

	/**
	 * Clean an action item line by removing leading bullets, dashes, etc.
	 */
	private static function clean_action_line(string $line): string {
		// Remove leading bullets, dashes, asterisks, numbers.
		$line = preg_replace('/^[-•*▪▸►→]\s*/', '', $line);
		$line = preg_replace('/^\d+[\.\)]\s*/', '', $line);
		return trim($line);
	}

	// ─── Summary Bullets Extraction ───────────────────────────────────

	/**
	 * Extract the top-level bullet summary (lines before the Notes/details section).
	 * These become insight items.
	 */
	private static function extract_summary_bullets(string $text, array $name_map): array {
		// The top summary is typically before "Notes" or the first major subsection.
		$parts = preg_split('/^#{0,3}\s*(?:Notes|Action\s+items?|Detailed\s+Notes)\s*$/mi', $text);
		$top = $parts[0] ?? '';

		$lines = preg_split('/\r?\n/', $top);
		$items = [];

		foreach ($lines as $line) {
			$trimmed = trim($line);
			if ($trimmed === '') { continue; }

			// Look for lines that are concise summary statements (typically one sentence with a colon pattern).
			// e.g. "Standardized Onboarding Process Needed: Current onboarding lacks structure..."
			if (mb_strlen($trimmed) > 20 && mb_strlen($trimmed) < 500 && str_contains($trimmed, ':')) {
				$items[] = [
					'item_type'   => 'insight',
					'content'     => $trimmed,
					'speaker_id'  => null,
					'assigned_to' => null,
					'due_date'    => null,
					'status'      => 'draft',
				];
			}
		}

		return $items;
	}

	/**
	 * Build a summary string from the top-level bullets.
	 */
	private static function extract_summary_text(string $text): string {
		$parts = preg_split('/^#{0,3}\s*(?:Notes|Action\s+items?|Detailed\s+Notes)\s*$/mi', $text);
		$top = trim($parts[0] ?? '');

		if ($top === '') { return ''; }

		// If the top section is short enough, use it as the summary directly.
		if (mb_strlen($top) < 2000) {
			return $top;
		}

		// Otherwise truncate.
		return mb_substr($top, 0, 2000) . '…';
	}

	// ─── Member Resolution ────────────────────────────────────────────

	/**
	 * Build a name map from active members for assignee resolution.
	 *
	 * @return array<string, int> Lowercase name => user ID.
	 */
	private static function build_name_map(): array {
		if (!class_exists('CBNexus_Member_Repository')) {
			return [];
		}

		$members = CBNexus_Member_Repository::get_all_members('active');
		$map = [];

		foreach ($members as $m) {
			$uid = (int) $m['user_id'];
			$map[strtolower($m['display_name'])] = $uid;
			$full = strtolower(trim($m['first_name'] . ' ' . $m['last_name']));
			if ($full !== '') {
				$map[$full] = $uid;
			}
			if (!empty($m['first_name'])) {
				$map[strtolower($m['first_name'])] = $uid;
			}
		}

		return $map;
	}

	/**
	 * Resolve a name string to a member ID.
	 */
	private static function resolve_member(string $name, array $name_map): ?int {
		$lower = strtolower(trim($name));
		if ($lower === '' || $lower === 'null') { return null; }

		// Exact match.
		if (isset($name_map[$lower])) { return $name_map[$lower]; }

		// Partial match (e.g. "Coach Woody" matching "Lucas Woody").
		foreach ($name_map as $key => $id) {
			// Check if the last word (surname) matches.
			$name_parts = explode(' ', $lower);
			$key_parts = explode(' ', $key);
			$name_last = end($name_parts);
			$key_last = end($key_parts);

			if ($name_last === $key_last && mb_strlen($name_last) > 2) {
				return $id;
			}

			// General substring match.
			if (str_contains($key, $lower) || str_contains($lower, $key)) {
				return $id;
			}
		}

		return null;
	}
}