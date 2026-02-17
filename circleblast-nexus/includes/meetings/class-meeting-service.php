<?php
/**
 * Meeting Service
 *
 * ITER-0008: Business logic for the 1:1 meeting lifecycle.
 * Manages state transitions, validation, and coordination
 * between repository, email, and response tracking.
 *
 * State machine:
 *   pending → accepted → scheduled → completed → closed
 *   pending → declined
 *   any active → cancelled
 *   suggested → pending (from auto-matching)
 */

defined('ABSPATH') || exit;

final class CBNexus_Meeting_Service {

	/**
	 * Valid state transitions.
	 */
	private static $transitions = [
		'suggested' => ['pending', 'declined', 'cancelled'],
		'pending'   => ['accepted', 'declined', 'cancelled'],
		'accepted'  => ['scheduled', 'cancelled'],
		'scheduled' => ['completed', 'cancelled'],
		'completed' => ['closed'],
	];

	/**
	 * Request a 1:1 meeting (manual, member-initiated).
	 *
	 * @param int    $requester_id User ID of requester.
	 * @param int    $target_id    User ID of target member.
	 * @param string $message      Optional message with the request.
	 * @return array{success: bool, meeting_id?: int, errors?: string[]}
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

		// Record the request as a response entry.
		if ($message !== '') {
			CBNexus_Meeting_Repository::record_response($meeting_id, $requester_id, 'requested', $message);
		}

		// Send notification emails.
		self::send_request_emails($meeting_id, $requester_id, $target_id);

		self::log('Meeting requested.', $meeting_id, $requester_id);

		return ['success' => true, 'meeting_id' => $meeting_id];
	}

	/**
	 * Accept a pending meeting.
	 *
	 * @param int    $meeting_id Meeting ID.
	 * @param int    $user_id    Responder user ID.
	 * @param string $message    Optional message.
	 * @return array{success: bool, errors?: string[]}
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

	/**
	 * Process a response (accept/decline).
	 */
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

		// Record response.
		CBNexus_Meeting_Repository::record_response($meeting_id, $user_id, $response, $message);

		// Update meeting status.
		CBNexus_Meeting_Repository::update($meeting_id, ['status' => $new_status]);

		// Send notification to the other member.
		$other_id = CBNexus_Meeting_Repository::get_other_member($meeting, $user_id);
		self::send_response_email($meeting_id, $user_id, $other_id, $response);

		self::log('Meeting ' . $response . '.', $meeting_id, $user_id);

		return ['success' => true];
	}

	/**
	 * Schedule an accepted meeting.
	 *
	 * @param int    $meeting_id   Meeting ID.
	 * @param int    $user_id      User setting the schedule.
	 * @param string $scheduled_at DateTime string (Y-m-d H:i:s).
	 * @return array{success: bool, errors?: string[]}
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
			return ['success' => false, 'errors' => ['Meeting must be scheduled before completing.']];
		}

		CBNexus_Meeting_Repository::update($meeting_id, [
			'status'       => 'completed',
			'completed_at' => gmdate('Y-m-d H:i:s'),
		]);

		// Send follow-up email requesting notes.
		$other_id = CBNexus_Meeting_Repository::get_other_member($meeting, $user_id);
		self::send_notes_request_email($meeting_id, (int) $meeting->member_a_id, (int) $meeting->member_b_id);

		self::log('Meeting completed.', $meeting_id, $user_id);

		return ['success' => true];
	}

	/**
	 * Submit meeting notes and update notes_status.
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

		// Update notes_status: check if both participants have submitted.
		$notes = CBNexus_Meeting_Repository::get_notes($meeting_id);
		$notes_status = (count($notes) >= 2) ? 'complete' : 'partial';
		CBNexus_Meeting_Repository::update($meeting_id, ['notes_status' => $notes_status]);

		// If both notes in, transition to closed.
		if ($notes_status === 'complete') {
			CBNexus_Meeting_Repository::update($meeting_id, ['status' => 'closed']);
		}

		self::log('Notes submitted.', $meeting_id, $user_id);

		return ['success' => true];
	}

	/**
	 * Cancel a meeting (any active status).
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

	// ─── Validation ────────────────────────────────────────────────────

	/**
	 * Validate a meeting request.
	 */
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

	/**
	 * Check if a state transition is valid.
	 */
	private static function can_transition(string $from, string $to): bool {
		$allowed = self::$transitions[$from] ?? [];
		return in_array($to, $allowed, true);
	}

	// ─── Emails ────────────────────────────────────────────────────────

	private static function send_request_emails(int $meeting_id, int $requester_id, int $target_id): void {
		$requester = CBNexus_Member_Repository::get_profile($requester_id);
		$target    = CBNexus_Member_Repository::get_profile($target_id);
		if (!$requester || !$target) { return; }

		$portal_url = CBNexus_Portal_Router::get_portal_url();
		$meetings_url = add_query_arg('section', 'meetings', $portal_url);

		// Notify the target.
		CBNexus_Email_Service::send('meeting_request_received', $target['user_email'], [
			'first_name'      => $target['first_name'],
			'requester_name'  => $requester['display_name'],
			'requester_title' => ($requester['cb_title'] ?? '') . ' at ' . ($requester['cb_company'] ?? ''),
			'meetings_url'    => $meetings_url,
		], ['recipient_id' => $target_id, 'related_id' => $meeting_id, 'related_type' => 'meeting_request']);

		// Confirm to the requester.
		CBNexus_Email_Service::send('meeting_request_sent', $requester['user_email'], [
			'first_name'  => $requester['first_name'],
			'target_name' => $target['display_name'],
			'meetings_url' => $meetings_url,
		], ['recipient_id' => $requester_id, 'related_id' => $meeting_id, 'related_type' => 'meeting_request']);
	}

	private static function send_response_email(int $meeting_id, int $responder_id, int $other_id, string $response): void {
		$responder = CBNexus_Member_Repository::get_profile($responder_id);
		$other     = CBNexus_Member_Repository::get_profile($other_id);
		if (!$responder || !$other) { return; }

		$portal_url = add_query_arg('section', 'meetings', CBNexus_Portal_Router::get_portal_url());
		$template   = ($response === 'accepted') ? 'meeting_accepted' : 'meeting_declined';

		CBNexus_Email_Service::send($template, $other['user_email'], [
			'first_name'     => $other['first_name'],
			'responder_name' => $responder['display_name'],
			'meetings_url'   => $portal_url,
		], ['recipient_id' => $other_id, 'related_id' => $meeting_id, 'related_type' => 'meeting_' . $response]);
	}

	private static function send_notes_request_email(int $meeting_id, int $member_a, int $member_b): void {
		$portal_url = add_query_arg('section', 'meetings', CBNexus_Portal_Router::get_portal_url());

		foreach ([$member_a, $member_b] as $uid) {
			$profile = CBNexus_Member_Repository::get_profile($uid);
			if (!$profile) { continue; }

			CBNexus_Email_Service::send('meeting_notes_request', $profile['user_email'], [
				'first_name'  => $profile['first_name'],
				'meetings_url' => $portal_url,
			], ['recipient_id' => $uid, 'related_id' => $meeting_id, 'related_type' => 'notes_request']);
		}
	}

	/**
	 * Send reminder emails for meetings scheduled within a time window.
	 * Called by WP-Cron.
	 */
	public static function send_reminders(): void {
		$from = gmdate('Y-m-d H:i:s');
		$to   = gmdate('Y-m-d H:i:s', strtotime('+24 hours'));

		$meetings = CBNexus_Meeting_Repository::get_upcoming_scheduled($from, $to);
		$portal_url = add_query_arg('section', 'meetings', CBNexus_Portal_Router::get_portal_url());

		foreach ($meetings as $meeting) {
			foreach ([(int) $meeting->member_a_id, (int) $meeting->member_b_id] as $uid) {
				$profile = CBNexus_Member_Repository::get_profile($uid);
				$other   = CBNexus_Member_Repository::get_profile(
					CBNexus_Meeting_Repository::get_other_member($meeting, $uid)
				);
				if (!$profile || !$other) { continue; }

				CBNexus_Email_Service::send('meeting_reminder', $profile['user_email'], [
					'first_name'   => $profile['first_name'],
					'other_name'   => $other['display_name'],
					'scheduled_at' => $meeting->scheduled_at,
					'meetings_url' => $portal_url,
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
