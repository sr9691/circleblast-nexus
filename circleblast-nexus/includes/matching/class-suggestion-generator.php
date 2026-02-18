<?php
/**
 * Suggestion Generator
 *
 * ITER-0011 + Token Update: Automated monthly matching cycle.
 * Now uses the universal CBNexus_Token_Service for accept/decline links.
 * Old token handler kept for backward compat with previously sent emails.
 */

defined('ABSPATH') || exit;

final class CBNexus_Suggestion_Generator {

	private static $token_option = 'cbnexus_suggestion_tokens';

	public static function init(): void {
		add_action('admin_init', [__CLASS__, 'handle_admin_trigger']);
		// Legacy handler for old-style tokens already in sent emails.
		add_action('init', [__CLASS__, 'handle_legacy_token_response']);
	}

	/**
	 * Run a suggestion cycle.
	 */
	public static function run_cycle(): array {
		$suggestions = CBNexus_Matching_Engine::generate_suggestions(0);
		$generated   = 0;
		$emailed     = 0;

		foreach ($suggestions as $s) {
			$meeting_id = CBNexus_Meeting_Repository::create([
				'member_a_id'  => $s['member_a_id'],
				'member_b_id'  => $s['member_b_id'],
				'status'       => 'suggested',
				'source'       => 'auto',
				'score'        => $s['score'],
				'suggested_at' => gmdate('Y-m-d H:i:s'),
			]);

			if (!$meeting_id) { continue; }
			$generated++;

			if (self::send_suggestion_emails($meeting_id, $s['member_a_id'], $s['member_b_id'])) {
				$emailed += 2;
			}
		}

		update_option('cbnexus_last_suggestion_cycle', [
			'timestamp'   => gmdate('Y-m-d H:i:s'),
			'generated'   => $generated,
			'emailed'     => $emailed,
			'total_pairs' => count($suggestions),
		]);

		if (class_exists('CBNexus_Logger')) {
			CBNexus_Logger::info('Suggestion cycle completed.', [
				'generated' => $generated, 'emailed' => $emailed,
			]);
		}

		return ['generated' => $generated, 'emailed' => $emailed];
	}

	public static function cron_run(): void {
		self::run_cycle();
	}

	/**
	 * Send follow-up reminders — now with token links.
	 */
	public static function send_follow_up_reminders(): void {
		global $wpdb;

		$cutoff = gmdate('Y-m-d H:i:s', strtotime('-3 days'));
		$meetings = $wpdb->get_results($wpdb->prepare(
			"SELECT m.* FROM {$wpdb->prefix}cb_meetings m
			 WHERE m.status = 'suggested' AND m.suggested_at < %s
			 AND m.id NOT IN (
				SELECT DISTINCT meeting_id FROM {$wpdb->prefix}cb_meeting_responses
			 )",
			$cutoff
		));

		foreach ($meetings ?: [] as $m) {
			foreach ([(int) $m->member_a_id, (int) $m->member_b_id] as $uid) {
				$profile = CBNexus_Member_Repository::get_profile($uid);
				$other   = CBNexus_Member_Repository::get_profile(
					CBNexus_Meeting_Repository::get_other_member($m, $uid)
				);
				if (!$profile || !$other) { continue; }

				$accept_token  = CBNexus_Token_Service::generate($uid, 'accept_meeting', ['meeting_id' => (int) $m->id]);
				$decline_token = CBNexus_Token_Service::generate($uid, 'decline_meeting', ['meeting_id' => (int) $m->id]);

				CBNexus_Email_Service::send('suggestion_reminder', $profile['user_email'], [
					'first_name'   => $profile['first_name'],
					'other_name'   => $other['display_name'],
					'accept_url'   => CBNexus_Token_Service::url($accept_token),
					'decline_url'  => CBNexus_Token_Service::url($decline_token),
					'meetings_url' => add_query_arg('section', 'meetings', CBNexus_Portal_Router::get_portal_url()),
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

	// ─── Legacy Token Handler (backward compat) ────────────────────────

	public static function handle_legacy_token_response(): void {
		if (!isset($_GET['cbnexus_response_token'])) { return; }

		$token  = sanitize_text_field(wp_unslash($_GET['cbnexus_response_token']));
		$tokens = get_option(self::$token_option, []);

		if (!isset($tokens[$token])) {
			// Not a legacy token — the new Token Router will handle it.
			return;
		}

		$data = $tokens[$token];

		unset($tokens[$token]);
		update_option(self::$token_option, $tokens);

		$meeting = CBNexus_Meeting_Repository::get($data['meeting_id']);
		if (!$meeting) {
			wp_die(__('Meeting not found.', 'circleblast-nexus'));
		}

		if ($meeting->status === 'suggested') {
			CBNexus_Meeting_Repository::update($data['meeting_id'], ['status' => 'pending']);
		}

		if ($data['action'] === 'accept') {
			CBNexus_Meeting_Service::accept($data['meeting_id'], $data['user_id']);
		} elseif ($data['action'] === 'decline') {
			CBNexus_Meeting_Service::decline($data['meeting_id'], $data['user_id']);
		}

		$portal_url = add_query_arg('section', 'meetings', CBNexus_Portal_Router::get_portal_url());
		wp_safe_redirect($portal_url);
		exit;
	}

	// ─── Emails (now using universal tokens) ───────────────────────────

	private static function send_suggestion_emails(int $meeting_id, int $member_a, int $member_b): bool {
		$success = true;

		foreach ([[$member_a, $member_b], [$member_b, $member_a]] as $pair) {
			$profile = CBNexus_Member_Repository::get_profile($pair[0]);
			$other   = CBNexus_Member_Repository::get_profile($pair[1]);
			if (!$profile || !$other) { $success = false; continue; }

			$accept_token  = CBNexus_Token_Service::generate($pair[0], 'accept_meeting', ['meeting_id' => $meeting_id]);
			$decline_token = CBNexus_Token_Service::generate($pair[0], 'decline_meeting', ['meeting_id' => $meeting_id]);

			$sent = CBNexus_Email_Service::send('suggestion_match', $profile['user_email'], [
				'first_name'   => $profile['first_name'],
				'other_name'   => $other['display_name'],
				'other_title'  => ($other['cb_title'] ?? '') . ' at ' . ($other['cb_company'] ?? ''),
				'other_bio'    => mb_substr($other['cb_bio'] ?? '', 0, 150),
				'accept_url'   => CBNexus_Token_Service::url($accept_token),
				'decline_url'  => CBNexus_Token_Service::url($decline_token),
				'meetings_url' => add_query_arg('section', 'meetings', CBNexus_Portal_Router::get_portal_url()),
			], ['recipient_id' => $pair[0], 'related_id' => $meeting_id, 'related_type' => 'suggestion']);

			if (!$sent) { $success = false; }
		}

		return $success;
	}

	public static function get_last_cycle(): ?array {
		return get_option('cbnexus_last_suggestion_cycle', null) ?: null;
	}

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
