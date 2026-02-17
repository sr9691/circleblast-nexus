<?php
/**
 * Suggestion Generator
 *
 * ITER-0011: Automated monthly matching cycle. Runs the matching engine,
 * creates suggested meeting records, and sends email notifications with
 * one-click accept/decline links using tokenized URLs.
 */

defined('ABSPATH') || exit;

final class CBNexus_Suggestion_Generator {

	private static $token_option = 'cbnexus_suggestion_tokens';

	/**
	 * Initialize hooks.
	 */
	public static function init(): void {
		add_action('admin_init', [__CLASS__, 'handle_admin_trigger']);
		add_action('init', [__CLASS__, 'handle_token_response']);
	}

	/**
	 * Run a suggestion cycle — called by WP-Cron or admin trigger.
	 *
	 * @return array{generated: int, emailed: int}
	 */
	public static function run_cycle(): array {
		$suggestions = CBNexus_Matching_Engine::generate_suggestions(0);
		$generated   = 0;
		$emailed     = 0;

		foreach ($suggestions as $s) {
			// Create meeting with status=suggested.
			$meeting_id = CBNexus_Meeting_Repository::create([
				'member_a_id' => $s['member_a_id'],
				'member_b_id' => $s['member_b_id'],
				'status'      => 'suggested',
				'source'      => 'auto',
				'score'       => $s['score'],
				'suggested_at' => gmdate('Y-m-d H:i:s'),
			]);

			if (!$meeting_id) { continue; }
			$generated++;

			// Send suggestion emails to both members.
			if (self::send_suggestion_emails($meeting_id, $s['member_a_id'], $s['member_b_id'])) {
				$emailed += 2;
			}
		}

		// Log the cycle.
		update_option('cbnexus_last_suggestion_cycle', [
			'timestamp'  => gmdate('Y-m-d H:i:s'),
			'generated'  => $generated,
			'emailed'    => $emailed,
			'total_pairs' => count($suggestions),
		]);

		if (class_exists('CBNexus_Logger')) {
			CBNexus_Logger::info('Suggestion cycle completed.', [
				'generated' => $generated, 'emailed' => $emailed,
			]);
		}

		return ['generated' => $generated, 'emailed' => $emailed];
	}

	/**
	 * WP-Cron callback.
	 */
	public static function cron_run(): void {
		self::run_cycle();
	}

	/**
	 * Send follow-up reminders for unresponded suggestions.
	 * Called by WP-Cron weekly.
	 */
	public static function send_follow_up_reminders(): void {
		global $wpdb;

		// Get suggestions older than 3 days with no response.
		$cutoff = gmdate('Y-m-d H:i:s', strtotime('-3 days'));
		$meetings = $wpdb->get_results($wpdb->prepare(
			"SELECT m.* FROM {$wpdb->prefix}cb_meetings m
			 WHERE m.status = 'suggested' AND m.suggested_at < %s
			 AND m.id NOT IN (
				SELECT DISTINCT meeting_id FROM {$wpdb->prefix}cb_meeting_responses
			 )",
			$cutoff
		));

		$portal_url = add_query_arg('section', 'meetings', CBNexus_Portal_Router::get_portal_url());

		foreach ($meetings ?: [] as $m) {
			foreach ([(int) $m->member_a_id, (int) $m->member_b_id] as $uid) {
				$profile = CBNexus_Member_Repository::get_profile($uid);
				$other   = CBNexus_Member_Repository::get_profile(
					CBNexus_Meeting_Repository::get_other_member($m, $uid)
				);
				if (!$profile || !$other) { continue; }

				CBNexus_Email_Service::send('suggestion_reminder', $profile['user_email'], [
					'first_name'  => $profile['first_name'],
					'other_name'  => $other['display_name'],
					'meetings_url' => $portal_url,
				], ['recipient_id' => $uid, 'related_id' => (int) $m->id, 'related_type' => 'suggestion_reminder']);
			}
		}
	}

	// ─── Admin Trigger ─────────────────────────────────────────────────

	public static function handle_admin_trigger(): void {
		if (!isset($_GET['cbnexus_run_cycle'])) { return; }
		if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(wp_unslash($_GET['_wpnonce']), 'cbnexus_run_suggestion_cycle')) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }

		self::run_cycle();

		wp_safe_redirect(admin_url('admin.php?page=cbnexus-matching&cbnexus_notice=cycle_complete'));
		exit;
	}

	// ─── Token-Based Accept/Decline ────────────────────────────────────

	/**
	 * Generate a secure token for email accept/decline links.
	 */
	public static function generate_token(int $meeting_id, int $user_id, string $action): string {
		$token = wp_generate_password(32, false);
		$tokens = get_option(self::$token_option, []);

		$tokens[$token] = [
			'meeting_id' => $meeting_id,
			'user_id'    => $user_id,
			'action'     => $action,
			'created'    => time(),
		];

		// Clean expired tokens (older than 14 days).
		$cutoff = time() - (14 * 86400);
		$tokens = array_filter($tokens, fn($t) => $t['created'] > $cutoff);

		update_option(self::$token_option, $tokens);

		return $token;
	}

	/**
	 * Handle token-based responses from email links.
	 */
	public static function handle_token_response(): void {
		if (!isset($_GET['cbnexus_response_token'])) { return; }

		$token  = sanitize_text_field(wp_unslash($_GET['cbnexus_response_token']));
		$tokens = get_option(self::$token_option, []);

		if (!isset($tokens[$token])) {
			wp_die(__('This link has expired or is invalid.', 'circleblast-nexus'), __('Invalid Link', 'circleblast-nexus'));
		}

		$data = $tokens[$token];

		// Remove the used token.
		unset($tokens[$token]);
		update_option(self::$token_option, $tokens);

		$meeting = CBNexus_Meeting_Repository::get($data['meeting_id']);
		if (!$meeting) {
			wp_die(__('Meeting not found.', 'circleblast-nexus'));
		}

		// For suggested meetings, first transition to pending, then accept/decline.
		if ($meeting->status === 'suggested') {
			CBNexus_Meeting_Repository::update($data['meeting_id'], ['status' => 'pending']);
		}

		if ($data['action'] === 'accept') {
			CBNexus_Meeting_Service::accept($data['meeting_id'], $data['user_id']);
		} elseif ($data['action'] === 'decline') {
			CBNexus_Meeting_Service::decline($data['meeting_id'], $data['user_id']);
		}

		// Redirect to portal meetings page.
		$portal_url = add_query_arg('section', 'meetings', CBNexus_Portal_Router::get_portal_url());
		wp_safe_redirect($portal_url);
		exit;
	}

	// ─── Emails ────────────────────────────────────────────────────────

	private static function send_suggestion_emails(int $meeting_id, int $member_a, int $member_b): bool {
		$success = true;

		foreach ([[$member_a, $member_b], [$member_b, $member_a]] as $pair) {
			$profile = CBNexus_Member_Repository::get_profile($pair[0]);
			$other   = CBNexus_Member_Repository::get_profile($pair[1]);
			if (!$profile || !$other) { $success = false; continue; }

			$accept_token  = self::generate_token($meeting_id, $pair[0], 'accept');
			$decline_token = self::generate_token($meeting_id, $pair[0], 'decline');

			$accept_url  = add_query_arg('cbnexus_response_token', $accept_token, home_url());
			$decline_url = add_query_arg('cbnexus_response_token', $decline_token, home_url());

			$sent = CBNexus_Email_Service::send('suggestion_match', $profile['user_email'], [
				'first_name'    => $profile['first_name'],
				'other_name'    => $other['display_name'],
				'other_title'   => ($other['cb_title'] ?? '') . ' at ' . ($other['cb_company'] ?? ''),
				'other_bio'     => mb_substr($other['cb_bio'] ?? '', 0, 150),
				'accept_url'    => $accept_url,
				'decline_url'   => $decline_url,
				'meetings_url'  => add_query_arg('section', 'meetings', CBNexus_Portal_Router::get_portal_url()),
			], ['recipient_id' => $pair[0], 'related_id' => $meeting_id, 'related_type' => 'suggestion']);

			if (!$sent) { $success = false; }
		}

		return $success;
	}

	/**
	 * Get the last suggestion cycle status for admin display.
	 */
	public static function get_last_cycle(): ?array {
		return get_option('cbnexus_last_suggestion_cycle', null) ?: null;
	}

	/**
	 * Get current cycle stats (suggested meetings acceptance rates).
	 */
	public static function get_cycle_stats(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'cb_meetings';

		$stats = $wpdb->get_row(
			"SELECT
				COUNT(*) as total,
				SUM(CASE WHEN status = 'suggested' THEN 1 ELSE 0 END) as pending,
				SUM(CASE WHEN status IN ('accepted', 'scheduled', 'completed', 'closed') THEN 1 ELSE 0 END) as accepted,
				SUM(CASE WHEN status = 'declined' THEN 1 ELSE 0 END) as declined
			 FROM {$table} WHERE source = 'auto'"
		);

		return [
			'total'    => (int) ($stats->total ?? 0),
			'pending'  => (int) ($stats->pending ?? 0),
			'accepted' => (int) ($stats->accepted ?? 0),
			'declined' => (int) ($stats->declined ?? 0),
		];
	}
}
