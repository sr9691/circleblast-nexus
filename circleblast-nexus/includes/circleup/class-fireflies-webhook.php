<?php
/**
 * Fireflies Webhook
 *
 * ITER-0012: REST API endpoint to receive Fireflies.ai webhook payloads.
 * Validates the payload, stores the transcript, and triggers the
 * AI extraction pipeline if configured.
 *
 * Endpoint: /wp-json/cbnexus/v1/fireflies-webhook
 * Secret:   Define CBNEXUS_FIREFLIES_SECRET in wp-config.php
 */

defined('ABSPATH') || exit;

final class CBNexus_Fireflies_Webhook {

	/**
	 * Register the REST API route.
	 */
	public static function init(): void {
		add_action('rest_api_init', [__CLASS__, 'register_routes']);
	}

	public static function register_routes(): void {
		register_rest_route('cbnexus/v1', '/fireflies-webhook', [
			'methods'             => 'POST',
			'callback'            => [__CLASS__, 'handle_webhook'],
			'permission_callback' => '__return_true', // Validated via secret.
		]);
	}

	/**
	 * Handle incoming Fireflies webhook.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public static function handle_webhook(\WP_REST_Request $request): \WP_REST_Response {
		// 1. Validate secret.
		if (!self::validate_secret($request)) {
			self::log('Webhook rejected: invalid secret.', 'warning');
			return new \WP_REST_Response(['error' => 'Unauthorized'], 401);
		}

		// 2. Parse payload.
		$payload = $request->get_json_params();
		if (empty($payload)) {
			self::log('Webhook rejected: empty payload.', 'warning');
			return new \WP_REST_Response(['error' => 'Empty payload'], 400);
		}

		// 3. Extract meeting data from Fireflies payload.
		$meeting_data = self::parse_payload($payload);
		if (!$meeting_data) {
			self::log('Webhook rejected: unrecognized payload format.', 'warning');
			return new \WP_REST_Response(['error' => 'Invalid payload format'], 400);
		}

		// 4. Check for duplicate (by fireflies_id).
		if (!empty($meeting_data['fireflies_id'])) {
			$existing = CBNexus_CircleUp_Repository::get_meeting_by_fireflies_id($meeting_data['fireflies_id']);
			if ($existing) {
				// Update transcript if it was empty.
				if (empty($existing->full_transcript) && !empty($meeting_data['full_transcript'])) {
					CBNexus_CircleUp_Repository::update_meeting((int) $existing->id, [
						'full_transcript'  => $meeting_data['full_transcript'],
						'duration_minutes' => $meeting_data['duration_minutes'],
						'recording_url'    => $meeting_data['recording_url'],
					]);
					self::log('Transcript updated for existing meeting.', 'info', (int) $existing->id);
				}
				return new \WP_REST_Response(['status' => 'updated', 'meeting_id' => $existing->id], 200);
			}
		}

		// 5. Create new CircleUp meeting.
		$meeting_id = CBNexus_CircleUp_Repository::create_meeting($meeting_data);
		if (!$meeting_id) {
			self::log('Failed to create CircleUp meeting from webhook.', 'error');
			return new \WP_REST_Response(['error' => 'Database error'], 500);
		}

		self::log('CircleUp meeting created from webhook.', 'info', $meeting_id);

		// 6. Try to match attendees from transcript participant names.
		if (!empty($payload['participants']) || !empty($payload['attendees'])) {
			self::match_attendees($meeting_id, $payload['participants'] ?? $payload['attendees'] ?? []);
		}

		return new \WP_REST_Response(['status' => 'created', 'meeting_id' => $meeting_id], 201);
	}

	/**
	 * Validate the webhook secret.
	 * Checks Authorization header or query param against CBNEXUS_FIREFLIES_SECRET.
	 */
	private static function validate_secret(\WP_REST_Request $request): bool {
		$expected = defined('CBNEXUS_FIREFLIES_SECRET') ? CBNEXUS_FIREFLIES_SECRET : '';
		if ($expected === '') {
			$db_keys = get_option('cbnexus_api_keys', []);
			$expected = $db_keys['fireflies_secret'] ?? '';
		}
		if ($expected === '') {
			// No secret configured — reject for security. Configure
			// CBNEXUS_FIREFLIES_SECRET in wp-config.php to enable.
			CBNexus_Logger::warning('Fireflies webhook rejected: no secret configured. Set CBNEXUS_FIREFLIES_SECRET in wp-config.php.');
			return false;
		}

		// Check Authorization: Bearer <secret>
		$auth = $request->get_header('Authorization');
		if ($auth && str_starts_with($auth, 'Bearer ')) {
			return hash_equals($expected, substr($auth, 7));
		}

		// Check query param fallback.
		$param = $request->get_param('secret');
		if ($param) {
			return hash_equals($expected, $param);
		}

		return false;
	}

	/**
	 * Parse Fireflies webhook payload into our meeting data format.
	 * Handles multiple Fireflies payload shapes.
	 */
	private static function parse_payload(array $payload): ?array {
		// Standard Fireflies webhook (transcriptCompleted event).
		$transcript = $payload['transcript'] ?? $payload['data']['transcript'] ?? $payload;

		$title = $transcript['title'] ?? $transcript['meeting_title'] ?? $payload['title'] ?? '';
		$text  = $transcript['sentences']
				?? $transcript['transcript']
				?? $transcript['text']
				?? $payload['transcript_text']
				?? null;

		// Convert sentences array to text block.
		if (is_array($text)) {
			$text = implode("\n", array_map(function ($s) {
				$speaker = $s['speaker_name'] ?? $s['speaker'] ?? 'Unknown';
				$content = $s['text'] ?? $s['sentence'] ?? '';
				return "{$speaker}: {$content}";
			}, $text));
		}

		if (empty($title) && empty($text)) {
			return null;
		}

		$date = $transcript['date'] ?? $transcript['meeting_date'] ?? $payload['date'] ?? gmdate('Y-m-d');
		if (strlen($date) > 10) {
			$date = substr($date, 0, 10); // Trim time portion.
		}

		return [
			'meeting_date'     => $date,
			'title'            => $title ?: 'CircleUp Meeting — ' . $date,
			'fireflies_id'     => $transcript['id'] ?? $transcript['fireflies_id'] ?? $payload['id'] ?? null,
			'full_transcript'  => is_string($text) ? $text : null,
			'duration_minutes' => $transcript['duration'] ?? $transcript['duration_minutes'] ?? null,
			'recording_url'    => $transcript['audio_url'] ?? $transcript['recording_url'] ?? null,
			'status'           => 'draft',
		];
	}

	/**
	 * Try to match participant names from Fireflies to CircleBlast members.
	 */
	private static function match_attendees(int $meeting_id, array $participants): void {
		foreach ($participants as $p) {
			$name = is_string($p) ? $p : ($p['name'] ?? $p['displayName'] ?? '');
			if (empty($name)) { continue; }

			// Search by display name.
			$members = CBNexus_Member_Repository::search($name, 'active');
			if (!empty($members)) {
				CBNexus_CircleUp_Repository::add_attendee($meeting_id, (int) $members[0]['user_id'], 'present');
			}
		}
	}

	private static function log(string $msg, string $level = 'info', int $meeting_id = 0): void {
		if (!class_exists('CBNexus_Logger')) { return; }
		$ctx = $meeting_id ? ['circleup_meeting_id' => $meeting_id] : [];
		match ($level) {
			'warning' => CBNexus_Logger::warning($msg, $ctx),
			'error'   => CBNexus_Logger::error($msg, $ctx),
			default   => CBNexus_Logger::info($msg, $ctx),
		};
	}
}