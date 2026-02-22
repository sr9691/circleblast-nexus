<?php
/**
 * Meeting Service
 *
 * ITER-0008 + Token Update: Business logic for the 1:1 meeting lifecycle.
 * All notification emails now include tokenized action links so members
 * can respond directly from their inbox — no login required.
 *
 * State machine:
 *   pending → accepted → scheduled → completed → closed
 *   pending → declined
 *   any active → cancelled
 *   suggested → pending (from auto-matching)
 */

defined('ABSPATH') || exit;

final class CBNexus_Meeting_Service {

	private static $transitions = [
		'suggested' => ['pending', 'declined', 'cancelled'],
		'pending'   => ['accepted', 'declined', 'cancelled'],
		'accepted'  => ['scheduled', 'completed', 'cancelled'],
		'scheduled' => ['completed', 'cancelled'],
		'completed' => ['closed'],
	];

	/**
	 * Request a 1:1 meeting (manual, member-initiated).
	 */
	public static function request_meeting(int $requester_id, int $target_id, string $message = ''): array {
		$errors = self::validate_request($requester_id, $target_id);
		if (!empty($errors)) {
			return ['success' => false, 'errors' => $errors];
		}

		$meeting_id = CBNexus_Meeting_Repository::create([
			'member_a_id' => $requester_id,
			'member_b_id' => $target_id,
			'status'      => 'pending',
			'source'      => 'manual',
		]);

		if (!$meeting_id) {
			return ['success' => false, 'errors' => ['Failed to create meeting record.']];
		}

		if ($message !== '') {
			CBNexus_Meeting_Repository::record_response($meeting_id, $requester_id, 'requested', $message);
		}

		self::send_request_emails($meeting_id, $requester_id, $target_id);
		self::log('Meeting requested.', $meeting_id, $requester_id);

		return ['success' => true, 'meeting_id' => $meeting_id];
	}

	/**
	 * Accept a pending meeting.
	 */
	public static function accept(int $meeting_id, int $user_id, string $message = ''): array {
		return self::respond($meeting_id, $user_id, 'accepted', $message);
	}

	/**
	 * Decline a pending meeting.
	 */
	public static function decline(int $meeting_id, int $user_id, string $message = ''): array {
		return self::respond($meeting_id, $user_id, 'declined', $message);
	}

	private static function respond(int $meeting_id, int $user_id, string $response, string $message): array {
		$meeting = CBNexus_Meeting_Repository::get($meeting_id);
		if (!$meeting) {
			return ['success' => false, 'errors' => ['Meeting not found.']];
		}

		if (!CBNexus_Meeting_Repository::is_participant($meeting, $user_id)) {
			return ['success' => false, 'errors' => ['You are not a participant in this meeting.']];
		}

		$new_status = $response;
		if (!self::can_transition($meeting->status, $new_status)) {
			return ['success' => false, 'errors' => [sprintf('Cannot %s a meeting with status "%s".', $response, $meeting->status)]];
		}

		CBNexus_Meeting_Repository::record_response($meeting_id, $user_id, $response, $message);
		CBNexus_Meeting_Repository::update($meeting_id, ['status' => $new_status]);

		$other_id = CBNexus_Meeting_Repository::get_other_member($meeting, $user_id);
		self::send_response_email($meeting_id, $user_id, $other_id, $response);

		self::log('Meeting ' . $response . '.', $meeting_id, $user_id);

		return ['success' => true];
	}

	/**
	 * Schedule an accepted meeting.
	 */
	public static function schedule(int $meeting_id, int $user_id, string $scheduled_at): array {
		$meeting = CBNexus_Meeting_Repository::get($meeting_id);
		if (!$meeting) {
			return ['success' => false, 'errors' => ['Meeting not found.']];
		}

		if (!CBNexus_Meeting_Repository::is_participant($meeting, $user_id)) {
			return ['success' => false, 'errors' => ['You are not a participant.']];
		}

		if (!self::can_transition($meeting->status, 'scheduled')) {
			return ['success' => false, 'errors' => ['Meeting must be accepted before scheduling.']];
		}

		if (!preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $scheduled_at)) {
			return ['success' => false, 'errors' => ['Invalid date format.']];
		}

		CBNexus_Meeting_Repository::update($meeting_id, [
			'status'       => 'scheduled',
			'scheduled_at' => $scheduled_at,
		]);

		self::log('Meeting scheduled.', $meeting_id, $user_id);

		return ['success' => true];
	}

	/**
	 * Mark a meeting as completed.
	 */
	public static function complete(int $meeting_id, int $user_id): array {
		$meeting = CBNexus_Meeting_Repository::get($meeting_id);
		if (!$meeting) {
			return ['success' => false, 'errors' => ['Meeting not found.']];
		}

		if (!CBNexus_Meeting_Repository::is_participant($meeting, $user_id)) {
			return ['success' => false, 'errors' => ['You are not a participant.']];
		}

		if (!self::can_transition($meeting->status, 'completed')) {
			return ['success' => false, 'errors' => ['Meeting must be accepted or scheduled before completing.']];
		}

		CBNexus_Meeting_Repository::update($meeting_id, [
			'status'       => 'completed',
			'completed_at' => gmdate('Y-m-d H:i:s'),
		]);

		// Send notes request emails with direct token links.
		self::send_notes_request_email($meeting_id, (int) $meeting->member_a_id, (int) $meeting->member_b_id);

		self::log('Meeting completed.', $meeting_id, $user_id);

		return ['success' => true];
	}

	/**
	 * Submit meeting notes.
	 */
	public static function submit_notes(int $meeting_id, int $user_id, array $notes_data): array {
		$meeting = CBNexus_Meeting_Repository::get($meeting_id);
		if (!$meeting) {
			return ['success' => false, 'errors' => ['Meeting not found.']];
		}

		if (!CBNexus_Meeting_Repository::is_participant($meeting, $user_id)) {
			return ['success' => false, 'errors' => ['You are not a participant.']];
		}

		if (!in_array($meeting->status, ['completed', 'closed'], true)) {
			return ['success' => false, 'errors' => ['Meeting must be completed before submitting notes.']];
		}

		$note_id = CBNexus_Meeting_Repository::save_notes($meeting_id, $user_id, $notes_data);
		if (!$note_id) {
			return ['success' => false, 'errors' => ['Failed to save notes.']];
		}

		$notes = CBNexus_Meeting_Repository::get_notes($meeting_id);
		$notes_status = (count($notes) >= 2) ? 'complete' : 'partial';
		CBNexus_Meeting_Repository::update($meeting_id, ['notes_status' => $notes_status]);

		if ($notes_status === 'complete') {
			CBNexus_Meeting_Repository::update($meeting_id, ['status' => 'closed']);
		}

		self::log('Notes submitted.', $meeting_id, $user_id);

		return ['success' => true];
	}

	/**
	 * Cancel a meeting.
	 */
	public static function cancel(int $meeting_id, int $user_id): array {
		$meeting = CBNexus_Meeting_Repository::get($meeting_id);
		if (!$meeting) {
			return ['success' => false, 'errors' => ['Meeting not found.']];
		}

		if (!CBNexus_Meeting_Repository::is_participant($meeting, $user_id)) {
			return ['success' => false, 'errors' => ['You are not a participant.']];
		}

		if (in_array($meeting->status, ['closed', 'declined', 'cancelled'], true)) {
			return ['success' => false, 'errors' => ['Meeting is already ' . $meeting->status . '.']];
		}

		CBNexus_Meeting_Repository::update($meeting_id, ['status' => 'cancelled']);
		CBNexus_Meeting_Repository::record_response($meeting_id, $user_id, 'cancelled', '');

		self::log('Meeting cancelled.', $meeting_id, $user_id);

		return ['success' => true];
	}

	/**
	 * Accept an auto-matched suggestion. Requires both members to accept.
	 *
	 * First accept:  suggested → pending (record who accepted).
	 * Second accept: pending → accepted (both agreed).
	 *
	 * @param int $meeting_id Meeting ID.
	 * @param int $user_id    User accepting.
	 * @return array Result with 'success', 'state' keys.
	 */
	public static function accept_suggestion(int $meeting_id, int $user_id): array {
		$meeting = CBNexus_Meeting_Repository::get($meeting_id);
		if (!$meeting) {
			return ['success' => false, 'errors' => ['Meeting not found.']];
		}
		if (!CBNexus_Meeting_Repository::is_participant($meeting, $user_id)) {
			return ['success' => false, 'errors' => ['You are not a participant.']];
		}

		// Already accepted by this user?
		if (self::has_responded($meeting_id, $user_id, 'accepted')) {
			return ['success' => false, 'errors' => ['You have already accepted this meeting.']];
		}

		// Record this member's acceptance.
		CBNexus_Meeting_Repository::record_response($meeting_id, $user_id, 'accepted', '');

		if ($meeting->status === 'suggested') {
			// First accept — move to pending.
			CBNexus_Meeting_Repository::update($meeting_id, ['status' => 'pending']);
			$other_id = CBNexus_Meeting_Repository::get_other_member($meeting, $user_id);
			self::send_suggestion_first_accept_email($meeting_id, $user_id, $other_id);
			self::log('Suggestion: first accept.', $meeting_id, $user_id);
			return ['success' => true, 'state' => 'waiting_for_other'];
		}

		if ($meeting->status === 'pending' && $meeting->source === 'auto') {
			// Second accept — both agreed, move to accepted.
			CBNexus_Meeting_Repository::update($meeting_id, ['status' => 'accepted']);
			$other_id = CBNexus_Meeting_Repository::get_other_member($meeting, $user_id);
			self::send_response_email($meeting_id, $user_id, $other_id, 'accepted');
			self::log('Suggestion: both accepted.', $meeting_id, $user_id);
			return ['success' => true, 'state' => 'accepted'];
		}

		return ['success' => false, 'errors' => ['Cannot accept this meeting in its current state.']];
	}

	/**
	 * Check if a member has already given a specific response.
	 *
	 * @param int    $meeting_id Meeting ID.
	 * @param int    $user_id    User ID.
	 * @param string $response   Response type.
	 * @return bool
	 */
	private static function has_responded(int $meeting_id, int $user_id, string $response): bool {
		global $wpdb;
		return (bool) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}cb_meeting_responses
			 WHERE meeting_id = %d AND responder_id = %d AND response = %s",
			$meeting_id, $user_id, $response
		));
	}

	/**
	 * Send email to the other member after the first person accepts a suggestion.
	 *
	 * @param int $meeting_id Meeting ID.
	 * @param int $accepter_id User who accepted.
	 * @param int $other_id    User still to respond.
	 */
	private static function send_suggestion_first_accept_email(int $meeting_id, int $accepter_id, int $other_id): void {
		$accepter = CBNexus_Member_Repository::get_profile($accepter_id);
		$other    = CBNexus_Member_Repository::get_profile($other_id);
		if (!$accepter || !$other) { return; }

		$accept_token  = CBNexus_Token_Service::generate($other_id, 'accept_meeting', ['meeting_id' => $meeting_id]);
		$decline_token = CBNexus_Token_Service::generate($other_id, 'decline_meeting', ['meeting_id' => $meeting_id]);

		CBNexus_Email_Service::send('suggestion_partner_accepted', $other['user_email'], [
			'first_name'  => $other['first_name'],
			'other_name'  => $accepter['display_name'],
			'accept_url'  => CBNexus_Token_Service::url($accept_token),
			'decline_url' => CBNexus_Token_Service::url($decline_token),
		], ['recipient_id' => $other_id, 'related_id' => $meeting_id, 'related_type' => 'suggestion_partner_accepted']);
	}

	// ─── Validation ────────────────────────────────────────────────────

	private static function validate_request(int $requester_id, int $target_id): array {
		$errors = [];

		if ($requester_id === $target_id) {
			$errors[] = 'You cannot request a meeting with yourself.';
		}
		if (!CBNexus_Member_Repository::is_member($requester_id)) {
			$errors[] = 'Requester is not a CircleBlast member.';
		}
		if (!CBNexus_Member_Repository::is_member($target_id)) {
			$errors[] = 'Target member not found.';
		}
		if (CBNexus_Meeting_Repository::has_active_meeting($requester_id, $target_id)) {
			$errors[] = 'You already have an active meeting with this member.';
		}

		return $errors;
	}

	private static function can_transition(string $from, string $to): bool {
		$allowed = self::$transitions[$from] ?? [];
		return in_array($to, $allowed, true);
	}

	// ─── Emails (Token-Powered) ────────────────────────────────────────

	private static function send_request_emails(int $meeting_id, int $requester_id, int $target_id): void {
		$requester = CBNexus_Member_Repository::get_profile($requester_id);
		$target    = CBNexus_Member_Repository::get_profile($target_id);
		if (!$requester || !$target) { return; }

		// Generate accept/decline tokens for the target.
		$accept_token  = CBNexus_Token_Service::generate($target_id, 'accept_meeting', ['meeting_id' => $meeting_id]);
		$decline_token = CBNexus_Token_Service::generate($target_id, 'decline_meeting', ['meeting_id' => $meeting_id]);

		CBNexus_Email_Service::send('meeting_request_received', $target['user_email'], [
			'first_name'      => $target['first_name'],
			'requester_name'  => $requester['display_name'],
			'requester_title' => ($requester['cb_title'] ?? '') . ' at ' . ($requester['cb_company'] ?? ''),
			'accept_url'      => CBNexus_Token_Service::url($accept_token),
			'decline_url'     => CBNexus_Token_Service::url($decline_token),
		], ['recipient_id' => $target_id, 'related_id' => $meeting_id, 'related_type' => 'meeting_request']);

		// Confirm to the requester.
		$portal_url = add_query_arg('section', 'meetings', CBNexus_Portal_Router::get_portal_url());
		CBNexus_Email_Service::send('meeting_request_sent', $requester['user_email'], [
			'first_name'   => $requester['first_name'],
			'target_name'  => $target['display_name'],
			'meetings_url' => $portal_url,
		], ['recipient_id' => $requester_id, 'related_id' => $meeting_id, 'related_type' => 'meeting_request']);
	}

	private static function send_response_email(int $meeting_id, int $responder_id, int $other_id, string $response): void {
		$responder = CBNexus_Member_Repository::get_profile($responder_id);
		$other     = CBNexus_Member_Repository::get_profile($other_id);
		if (!$responder || !$other) { return; }

		$template = ($response === 'accepted') ? 'meeting_accepted' : 'meeting_declined';

		$vars = [
			'first_name'     => $other['first_name'],
			'responder_name' => $responder['display_name'],
		];

		// For accepted meetings, include a "complete + notes" token for the recipient.
		if ($response === 'accepted') {
			$complete_token = CBNexus_Token_Service::generate($other_id, 'complete_meeting', ['meeting_id' => $meeting_id]);
			$vars['complete_url'] = CBNexus_Token_Service::url($complete_token);

			// Also send one to the responder.
			$responder_complete = CBNexus_Token_Service::generate($responder_id, 'complete_meeting', ['meeting_id' => $meeting_id]);
			CBNexus_Email_Service::send('meeting_accepted', $responder['user_email'], [
				'first_name'     => $responder['first_name'],
				'responder_name' => $other['display_name'],
				'complete_url'   => CBNexus_Token_Service::url($responder_complete),
			], ['recipient_id' => $responder_id, 'related_id' => $meeting_id, 'related_type' => 'meeting_' . $response]);
		}

		CBNexus_Email_Service::send($template, $other['user_email'], $vars,
			['recipient_id' => $other_id, 'related_id' => $meeting_id, 'related_type' => 'meeting_' . $response]);
	}

	private static function send_notes_request_email(int $meeting_id, int $member_a, int $member_b): void {
		$meeting = CBNexus_Meeting_Repository::get($meeting_id);

		foreach ([$member_a, $member_b] as $uid) {
			$profile  = CBNexus_Member_Repository::get_profile($uid);
			$other_id = ($uid === $member_a) ? $member_b : $member_a;
			$other    = CBNexus_Member_Repository::get_profile($other_id);
			if (!$profile || !$other) { continue; }

			// Generate a multi-use notes token (they might want to revise).
			$notes_token = CBNexus_Token_Service::generate($uid, 'submit_notes', ['meeting_id' => $meeting_id], 14, true);

			CBNexus_Email_Service::send('meeting_notes_request', $profile['user_email'], [
				'first_name' => $profile['first_name'],
				'other_name' => $other['display_name'],
				'notes_url'  => CBNexus_Token_Service::url($notes_token),
			], ['recipient_id' => $uid, 'related_id' => $meeting_id, 'related_type' => 'notes_request']);
		}
	}

	/**
	 * Send reminder emails for upcoming meetings — now with "We Met" token links.
	 */
	public static function send_reminders(): void {
		$from = gmdate('Y-m-d H:i:s');
		$to   = gmdate('Y-m-d H:i:s', strtotime('+24 hours'));

		$meetings = CBNexus_Meeting_Repository::get_upcoming_scheduled($from, $to);

		foreach ($meetings as $meeting) {
			foreach ([(int) $meeting->member_a_id, (int) $meeting->member_b_id] as $uid) {
				// Check reminder preference.
				$pref = get_user_meta($uid, 'cb_email_reminders', true);
				if ($pref === 'no') { continue; }

				$profile = CBNexus_Member_Repository::get_profile($uid);
				$other   = CBNexus_Member_Repository::get_profile(
					CBNexus_Meeting_Repository::get_other_member($meeting, $uid)
				);
				if (!$profile || !$other) { continue; }

				$complete_token = CBNexus_Token_Service::generate($uid, 'complete_meeting', ['meeting_id' => (int) $meeting->id]);

				CBNexus_Email_Service::send('meeting_reminder', $profile['user_email'], [
					'first_name'     => $profile['first_name'],
					'other_name'     => $other['display_name'],
					'scheduled_text' => $meeting->scheduled_at ? ' on ' . date_i18n('M j', strtotime($meeting->scheduled_at)) : '',
					'complete_url'   => CBNexus_Token_Service::url($complete_token),
				], ['recipient_id' => $uid, 'related_id' => (int) $meeting->id, 'related_type' => 'meeting_reminder']);
			}
		}
	}

	private static function log(string $msg, int $meeting_id, int $user_id): void {
		if (class_exists('CBNexus_Logger')) {
			CBNexus_Logger::info($msg, ['meeting_id' => $meeting_id, 'user_id' => $user_id]);
		}
	}
}
