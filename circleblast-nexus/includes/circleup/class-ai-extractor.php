<?php
/**
 * AI Extractor
 *
 * ITER-0013: Sends CircleUp transcripts to the Claude API for structured
 * extraction of wins, insights, opportunities, and action items.
 * API key must be defined as CBNEXUS_CLAUDE_API_KEY in wp-config.php.
 */

defined('ABSPATH') || exit;

final class CBNexus_AI_Extractor {

	private static $api_url = 'https://api.anthropic.com/v1/messages';
	private static $model   = 'claude-sonnet-4-20250514';

	/**
	 * Extract structured items from a CircleUp meeting transcript.
	 *
	 * @param int $meeting_id CircleUp meeting ID.
	 * @return array{success: bool, items_count?: int, summary?: string, errors?: string[]}
	 */
	public static function extract(int $meeting_id): array {
		$meeting = CBNexus_CircleUp_Repository::get_meeting($meeting_id);
		if (!$meeting) {
			return ['success' => false, 'errors' => ['Meeting not found.']];
		}

		if (empty($meeting->full_transcript)) {
			return ['success' => false, 'errors' => ['No transcript available for extraction.']];
		}

		$api_key = defined('CBNEXUS_CLAUDE_API_KEY') ? CBNEXUS_CLAUDE_API_KEY : '';
		if ($api_key === '') {
			$db_keys = get_option('cbnexus_api_keys', []);
			$api_key = $db_keys['claude_api_key'] ?? '';
		}
		if ($api_key === '') {
			return ['success' => false, 'errors' => ['Claude API key not configured. Set it in Settings → API Keys or define CBNEXUS_CLAUDE_API_KEY in wp-config.php.']];
		}

		// Build the member name map for speaker attribution.
		$members = CBNexus_Member_Repository::get_all_members('active');
		$name_map = [];
		foreach ($members as $m) {
			$name_map[strtolower($m['display_name'])] = (int) $m['user_id'];
			$name_map[strtolower($m['first_name'] . ' ' . $m['last_name'])] = (int) $m['user_id'];
			if (!empty($m['first_name'])) {
				$name_map[strtolower($m['first_name'])] = (int) $m['user_id'];
			}
		}

		// Call Claude API.
		$prompt = self::build_prompt($meeting->full_transcript, $name_map);
		$result = self::call_claude($api_key, $prompt);

		if (!$result['success']) {
			self::log('AI extraction failed.', 'error', $meeting_id, $result);
			return $result;
		}

		// Parse the structured response.
		$parsed = self::parse_response($result['content'], $name_map);
		if (empty($parsed['items']) && empty($parsed['summary'])) {
			return ['success' => false, 'errors' => ['AI returned no extractable content.']];
		}

		// Clear old extracted items and insert new ones.
		CBNexus_CircleUp_Repository::delete_items_for_meeting($meeting_id);
		$count = CBNexus_CircleUp_Repository::insert_items($meeting_id, $parsed['items']);

		// Save AI summary.
		CBNexus_CircleUp_Repository::update_meeting($meeting_id, [
			'ai_summary' => $parsed['summary'],
		]);

		self::log('AI extraction completed.', 'info', $meeting_id, ['items' => $count]);

		return ['success' => true, 'items_count' => $count, 'summary' => $parsed['summary']];
	}

	/**
	 * Process all pending meetings (WP-Cron callback).
	 */
	public static function process_pending(): void {
		$meetings = CBNexus_CircleUp_Repository::get_pending_extraction();
		foreach ($meetings as $meeting) {
			self::extract((int) $meeting->id);
		}
	}

	// ─── Claude API ────────────────────────────────────────────────────

	private static function build_prompt(string $transcript, array $name_map): string {
		$member_names = array_keys($name_map);
		$names_list   = implode(', ', array_map('ucwords', array_unique($member_names)));

		// Truncate very long transcripts to ~100k chars.
		if (mb_strlen($transcript) > 100000) {
			$transcript = mb_substr($transcript, 0, 100000) . "\n\n[Transcript truncated]";
		}

		return <<<PROMPT
You are analyzing a transcript from a CircleBlast professional networking group meeting (called "CircleUp").

Known group members: {$names_list}

Extract the following from this transcript and return ONLY valid JSON (no markdown, no code fences):

{
  "summary": "A 2-3 paragraph summary of the meeting covering main topics discussed",
  "items": [
    {
      "item_type": "win|insight|opportunity|action",
      "content": "Description of the item",
      "speaker": "Name of the person who shared this (or null)",
      "assigned_to": "Name of person responsible (for action items, or null)",
      "due_date": "YYYY-MM-DD or null"
    }
  ]
}

Item types:
- "win": A success, accomplishment, or positive outcome shared by a member
- "insight": A lesson learned, useful knowledge, or key takeaway
- "opportunity": A referral, collaboration idea, or business opportunity mentioned
- "action": A specific next step someone committed to

Guidelines:
- Extract ALL items mentioned, typically 10-30 per meeting
- Attribute speakers when clearly identifiable from the transcript
- For action items, identify who is responsible if stated
- Keep content concise but specific (1-2 sentences)
- Match speaker/assigned names to the known members list when possible

TRANSCRIPT:
{$transcript}
PROMPT;
	}

	private static function call_claude(string $api_key, string $prompt): array {
		$body = wp_json_encode([
			'model'      => self::$model,
			'max_tokens' => 4096,
			'messages'   => [
				['role' => 'user', 'content' => $prompt],
			],
		]);

		$response = wp_remote_post(self::$api_url, [
			'timeout' => 120,
			'headers' => [
				'Content-Type'      => 'application/json',
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
			],
			'body' => $body,
		]);

		if (is_wp_error($response)) {
			return ['success' => false, 'errors' => [$response->get_error_message()]];
		}

		$code = wp_remote_retrieve_response_code($response);
		$data = json_decode(wp_remote_retrieve_body($response), true);

		if ($code !== 200) {
			$err = $data['error']['message'] ?? "API returned HTTP {$code}";
			return ['success' => false, 'errors' => [$err]];
		}

		$content = $data['content'][0]['text'] ?? '';
		if (empty($content)) {
			return ['success' => false, 'errors' => ['Empty response from Claude API.']];
		}

		return ['success' => true, 'content' => $content];
	}

	// ─── Response Parsing ──────────────────────────────────────────────

	private static function parse_response(string $content, array $name_map): array {
		// Strip markdown code fences if present.
		$content = preg_replace('/^```(?:json)?\s*\n?/m', '', $content);
		$content = preg_replace('/\n?```\s*$/m', '', $content);
		$content = trim($content);

		$data = json_decode($content, true);
		if (!is_array($data)) {
			return ['summary' => '', 'items' => []];
		}

		$summary = $data['summary'] ?? '';
		$items   = [];

		foreach ($data['items'] ?? [] as $raw) {
			$type = $raw['item_type'] ?? 'insight';
			if (!in_array($type, ['win', 'insight', 'opportunity', 'action'], true)) {
				$type = 'insight';
			}

			$speaker_name = strtolower(trim($raw['speaker'] ?? ''));
			$assigned_name = strtolower(trim($raw['assigned_to'] ?? ''));

			$items[] = [
				'item_type'   => $type,
				'content'     => $raw['content'] ?? '',
				'speaker_id'  => self::resolve_member($speaker_name, $name_map),
				'assigned_to' => self::resolve_member($assigned_name, $name_map),
				'due_date'    => self::parse_date($raw['due_date'] ?? null),
				'status'      => 'draft',
			];
		}

		return ['summary' => $summary, 'items' => $items];
	}

	private static function resolve_member(string $name, array $name_map): ?int {
		if ($name === '' || $name === 'null') { return null; }

		// Exact match.
		if (isset($name_map[$name])) { return $name_map[$name]; }

		// Partial match (first name).
		foreach ($name_map as $key => $id) {
			if (str_contains($key, $name) || str_contains($name, $key)) {
				return $id;
			}
		}

		return null;
	}

	private static function parse_date(?string $date): ?string {
		if (!$date || $date === 'null') { return null; }
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { return $date; }
		return null;
	}

	private static function log(string $msg, string $level, int $meeting_id, array $ctx = []): void {
		if (!class_exists('CBNexus_Logger')) { return; }
		$ctx['circleup_meeting_id'] = $meeting_id;
		match ($level) {
			'error'   => CBNexus_Logger::error($msg, $ctx),
			'warning' => CBNexus_Logger::warning($msg, $ctx),
			default   => CBNexus_Logger::info($msg, $ctx),
		};
	}
}