<?php
/**
 * Meeting Repository
 *
 * ITER-0008: Data access layer for 1:1 meetings, notes, and responses.
 * All database operations for the meetings system go through this class.
 */

defined('ABSPATH') || exit;

final class CBNexus_Meeting_Repository {

	// ─── Meeting CRUD ──────────────────────────────────────────────────

	/**
	 * Create a new meeting record.
	 *
	 * @param array $data Meeting data.
	 * @return int|false Meeting ID or false on failure.
	 */
	public static function create(array $data) {
		global $wpdb;
		$now = gmdate('Y-m-d H:i:s');

		$result = $wpdb->insert(
			$wpdb->prefix . 'cb_meetings',
			[
				'member_a_id'  => absint($data['member_a_id']),
				'member_b_id'  => absint($data['member_b_id']),
				'status'       => sanitize_key($data['status'] ?? 'pending'),
				'source'       => sanitize_key($data['source'] ?? 'manual'),
				'score'        => isset($data['score']) ? (float) $data['score'] : null,
				'suggested_at' => $data['suggested_at'] ?? null,
				'scheduled_at' => $data['scheduled_at'] ?? null,
				'completed_at' => null,
				'notes_status' => 'none',
				'created_at'   => $now,
				'updated_at'   => $now,
			],
			['%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s']
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get a meeting by ID.
	 *
	 * @param int $meeting_id Meeting ID.
	 * @return object|null Meeting row.
	 */
	public static function get(int $meeting_id): ?object {
		global $wpdb;
		$row = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}cb_meetings WHERE id = %d",
			$meeting_id
		));
		return $row ?: null;
	}

	/**
	 * Update meeting fields.
	 *
	 * @param int   $meeting_id Meeting ID.
	 * @param array $data       Fields to update.
	 * @return bool
	 */
	public static function update(int $meeting_id, array $data): bool {
		global $wpdb;
		$data['updated_at'] = gmdate('Y-m-d H:i:s');

		$result = $wpdb->update(
			$wpdb->prefix . 'cb_meetings',
			$data,
			['id' => $meeting_id],
			null,
			['%d']
		);

		return $result !== false;
	}

	/**
	 * Check for an active meeting between two members (any status except closed/declined).
	 *
	 * @param int $member_a User ID.
	 * @param int $member_b User ID.
	 * @return bool True if active meeting exists.
	 */
	public static function has_active_meeting(int $member_a, int $member_b): bool {
		global $wpdb;

		$count = $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}cb_meetings
			 WHERE ((member_a_id = %d AND member_b_id = %d) OR (member_a_id = %d AND member_b_id = %d))
			 AND status NOT IN ('closed', 'declined', 'cancelled')",
			$member_a, $member_b, $member_b, $member_a
		));

		return (int) $count > 0;
	}

	/**
	 * Get all meetings for a member.
	 *
	 * @param int    $user_id User ID.
	 * @param string $status  Optional status filter.
	 * @param int    $limit   Max results.
	 * @return array Meeting rows.
	 */
	public static function get_for_member(int $user_id, string $status = '', int $limit = 50): array {
		global $wpdb;

		$sql = "SELECT * FROM {$wpdb->prefix}cb_meetings
				WHERE (member_a_id = %d OR member_b_id = %d)";
		$params = [$user_id, $user_id];

		if ($status !== '') {
			$sql .= " AND status = %s";
			$params[] = $status;
		}

		$sql .= " ORDER BY updated_at DESC LIMIT %d";
		$params[] = $limit;

		return $wpdb->get_results($wpdb->prepare($sql, ...$params)) ?: [];
	}

	/**
	 * Get auto-suggested meetings awaiting this member's response.
	 * Includes both member_a and member_b since suggestions are mutual.
	 *
	 * @param int $user_id User ID.
	 * @return array Meeting rows.
	 */
	public static function get_suggested_for_member(int $user_id): array {
		global $wpdb;
		return $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}cb_meetings
			 WHERE (member_a_id = %d OR member_b_id = %d)
			 AND status = 'suggested' AND source = 'auto'
			 ORDER BY created_at DESC",
			$user_id, $user_id
		)) ?: [];
	}

	/**
	 * Get meetings requiring action from a specific member.
	 * "Pending" meetings where the member is the receiver (member_b).
	 *
	 * @param int $user_id User ID.
	 * @return array Meeting rows.
	 */
	public static function get_pending_for_member(int $user_id): array {
		global $wpdb;

		return $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}cb_meetings
			 WHERE member_b_id = %d AND status = 'pending'
			 ORDER BY created_at DESC",
			$user_id
		)) ?: [];
	}

	/**
	 * Get meetings needing notes from a member (completed but member hasn't submitted notes).
	 *
	 * @param int $user_id User ID.
	 * @return array Meeting rows.
	 */
	public static function get_needs_notes(int $user_id): array {
		global $wpdb;

		return $wpdb->get_results($wpdb->prepare(
			"SELECT m.* FROM {$wpdb->prefix}cb_meetings m
			 WHERE (m.member_a_id = %d OR m.member_b_id = %d)
			 AND m.status = 'completed'
			 AND m.id NOT IN (
				SELECT meeting_id FROM {$wpdb->prefix}cb_meeting_notes WHERE author_id = %d
			 )
			 ORDER BY m.completed_at DESC",
			$user_id, $user_id, $user_id
		)) ?: [];
	}

	/**
	 * Count meetings for a member by status.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, int>
	 */
	public static function count_for_member(int $user_id): array {
		global $wpdb;

		$results = $wpdb->get_results($wpdb->prepare(
			"SELECT status, COUNT(*) AS cnt FROM {$wpdb->prefix}cb_meetings
			 WHERE (member_a_id = %d OR member_b_id = %d)
			 GROUP BY status",
			$user_id, $user_id
		));

		$counts = [];
		foreach ($results as $row) {
			$counts[$row->status] = (int) $row->cnt;
		}
		return $counts;
	}

	/**
	 * Get upcoming scheduled meetings (for reminder cron).
	 *
	 * @param string $from Start datetime (GMT).
	 * @param string $to   End datetime (GMT).
	 * @return array Meeting rows.
	 */
	public static function get_upcoming_scheduled(string $from, string $to): array {
		global $wpdb;

		return $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}cb_meetings
			 WHERE status = 'scheduled' AND scheduled_at BETWEEN %s AND %s
			 ORDER BY scheduled_at ASC",
			$from, $to
		)) ?: [];
	}

	// ─── Notes ─────────────────────────────────────────────────────────

	/**
	 * Save meeting notes for a member.
	 *
	 * @param int   $meeting_id Meeting ID.
	 * @param int   $author_id  Member user ID.
	 * @param array $data       Notes data (wins, insights, action_items, rating).
	 * @return int|false Note ID or false.
	 */
	public static function save_notes(int $meeting_id, int $author_id, array $data) {
		global $wpdb;
		$table = $wpdb->prefix . 'cb_meeting_notes';

		// Check if notes already exist (upsert).
		$existing = $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM {$table} WHERE meeting_id = %d AND author_id = %d",
			$meeting_id, $author_id
		));

		$row = [
			'meeting_id'   => $meeting_id,
			'author_id'    => $author_id,
			'wins'         => sanitize_textarea_field($data['wins'] ?? ''),
			'insights'     => sanitize_textarea_field($data['insights'] ?? ''),
			'action_items' => sanitize_textarea_field($data['action_items'] ?? ''),
			'rating'       => isset($data['rating']) ? absint($data['rating']) : null,
			'created_at'   => gmdate('Y-m-d H:i:s'),
		];

		if ($existing) {
			$wpdb->update($table, $row, ['id' => $existing]);
			return (int) $existing;
		}

		$result = $wpdb->insert($table, $row, ['%d', '%d', '%s', '%s', '%s', '%d', '%s']);
		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get notes for a meeting.
	 *
	 * @param int $meeting_id Meeting ID.
	 * @return array Note rows.
	 */
	public static function get_notes(int $meeting_id): array {
		global $wpdb;

		return $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}cb_meeting_notes WHERE meeting_id = %d",
			$meeting_id
		)) ?: [];
	}

	/**
	 * Check if a member has submitted notes for a meeting.
	 */
	public static function has_notes(int $meeting_id, int $author_id): bool {
		global $wpdb;
		return (bool) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}cb_meeting_notes WHERE meeting_id = %d AND author_id = %d",
			$meeting_id, $author_id
		));
	}

	// ─── Responses ─────────────────────────────────────────────────────

	/**
	 * Record a meeting response.
	 *
	 * @param int    $meeting_id   Meeting ID.
	 * @param int    $responder_id Responder user ID.
	 * @param string $response     Response type (accepted, declined, reschedule).
	 * @param string $message      Optional message.
	 * @return int|false Response ID or false.
	 */
	public static function record_response(int $meeting_id, int $responder_id, string $response, string $message = '') {
		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'cb_meeting_responses',
			[
				'meeting_id'   => $meeting_id,
				'responder_id' => $responder_id,
				'response'     => sanitize_key($response),
				'message'      => sanitize_textarea_field($message),
				'responded_at' => gmdate('Y-m-d H:i:s'),
			],
			['%d', '%d', '%s', '%s', '%s']
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get the other member in a meeting.
	 *
	 * @param object $meeting Meeting row.
	 * @param int    $user_id Current user ID.
	 * @return int The other member's user ID.
	 */
	public static function get_other_member(object $meeting, int $user_id): int {
		return ((int) $meeting->member_a_id === $user_id)
			? (int) $meeting->member_b_id
			: (int) $meeting->member_a_id;
	}

	/**
	 * Check if a user is a participant in a meeting.
	 */
	public static function is_participant(object $meeting, int $user_id): bool {
		return ((int) $meeting->member_a_id === $user_id || (int) $meeting->member_b_id === $user_id);
	}
}
