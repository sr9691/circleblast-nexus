<?php
/**
 * CircleUp Repository
 *
 * ITER-0012: Data access layer for CircleUp meetings, attendees,
 * and extracted items. All CircleUp database operations go through this class.
 */

defined('ABSPATH') || exit;

final class CBNexus_CircleUp_Repository {

	// ─── Meetings ──────────────────────────────────────────────────────

	/**
	 * Create a CircleUp meeting record.
	 *
	 * @param array $data Meeting data.
	 * @return int|false Meeting ID or false.
	 */
	public static function create_meeting(array $data) {
		global $wpdb;
		$now = gmdate('Y-m-d H:i:s');

		$result = $wpdb->insert(
			$wpdb->prefix . 'cb_circleup_meetings',
			[
				'meeting_date'     => $data['meeting_date'],
				'title'            => sanitize_text_field($data['title']),
				'fireflies_id'     => $data['fireflies_id'] ?? null,
				'full_transcript'  => $data['full_transcript'] ?? null,
				'ai_summary'       => $data['ai_summary'] ?? null,
				'curated_summary'  => $data['curated_summary'] ?? null,
				'duration_minutes' => isset($data['duration_minutes']) ? absint($data['duration_minutes']) : null,
				'recording_url'    => $data['recording_url'] ?? null,
				'status'           => $data['status'] ?? 'draft',
				'created_at'       => $now,
				'updated_at'       => $now,
			],
			['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
		);

		return $result ? $wpdb->insert_id : false;
	}

	public static function get_meeting(int $id): ?object {
		global $wpdb;
		$row = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}cb_circleup_meetings WHERE id = %d", $id
		));
		return $row ?: null;
	}

	public static function get_meeting_by_fireflies_id(string $ff_id): ?object {
		global $wpdb;
		$row = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}cb_circleup_meetings WHERE fireflies_id = %s", $ff_id
		));
		return $row ?: null;
	}

	public static function update_meeting(int $id, array $data): bool {
		global $wpdb;
		$data['updated_at'] = gmdate('Y-m-d H:i:s');
		return $wpdb->update($wpdb->prefix . 'cb_circleup_meetings', $data, ['id' => $id]) !== false;
	}

	/**
	 * Get meetings by status, ordered by date descending.
	 */
	public static function get_meetings(string $status = '', int $limit = 50): array {
		global $wpdb;
		$sql = "SELECT * FROM {$wpdb->prefix}cb_circleup_meetings";
		$params = [];

		if ($status !== '') {
			$sql .= " WHERE status = %s";
			$params[] = $status;
		}

		$sql .= " ORDER BY meeting_date DESC LIMIT %d";
		$params[] = $limit;

		return $wpdb->get_results($wpdb->prepare($sql, ...$params)) ?: [];
	}

	/**
	 * Get meetings needing AI extraction (have transcript, no ai_summary yet).
	 */
	public static function get_pending_extraction(): array {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}cb_circleup_meetings
			 WHERE full_transcript IS NOT NULL
			 AND full_transcript != ''
			 AND ai_summary IS NULL
			 ORDER BY meeting_date DESC"
		) ?: [];
	}

	// ─── Attendees ─────────────────────────────────────────────────────

	public static function add_attendee(int $meeting_id, int $member_id, string $status = 'present'): bool {
		global $wpdb;
		$result = $wpdb->replace(
			$wpdb->prefix . 'cb_circleup_attendees',
			[
				'circleup_meeting_id' => $meeting_id,
				'member_id'           => $member_id,
				'attendance_status'   => $status,
				'created_at'          => gmdate('Y-m-d H:i:s'),
			],
			['%d', '%d', '%s', '%s']
		);
		return $result !== false;
	}

	public static function get_attendees(int $meeting_id): array {
		global $wpdb;
		return $wpdb->get_results($wpdb->prepare(
			"SELECT a.*, u.display_name FROM {$wpdb->prefix}cb_circleup_attendees a
			 LEFT JOIN {$wpdb->users} u ON a.member_id = u.ID
			 WHERE a.circleup_meeting_id = %d
			 ORDER BY u.display_name ASC",
			$meeting_id
		)) ?: [];
	}

	public static function get_attendance_count(int $member_id): int {
		global $wpdb;
		return (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}cb_circleup_attendees WHERE member_id = %d",
			$member_id
		));
	}

	// ─── Items ─────────────────────────────────────────────────────────

	/**
	 * Bulk insert extracted items for a meeting.
	 *
	 * @param int   $meeting_id CircleUp meeting ID.
	 * @param array $items      Array of item arrays with keys: item_type, content, speaker_id, assigned_to, due_date.
	 * @return int Number of items inserted.
	 */
	public static function insert_items(int $meeting_id, array $items): int {
		global $wpdb;
		$table = $wpdb->prefix . 'cb_circleup_items';
		$now   = gmdate('Y-m-d H:i:s');
		$count = 0;

		foreach ($items as $item) {
			$speaker_id  = !empty($item['speaker_id']) ? absint($item['speaker_id']) : null;
			$assigned_to = !empty($item['assigned_to']) ? absint($item['assigned_to']) : null;
			$due_date    = !empty($item['due_date']) ? sanitize_text_field($item['due_date']) : null;

			// Build columns and values dynamically to handle NULLs properly.
			$cols = [
				'circleup_meeting_id' => [$meeting_id, '%d'],
				'item_type'           => [sanitize_key($item['item_type'] ?? 'insight'), '%s'],
				'content'             => [sanitize_textarea_field($item['content'] ?? ''), '%s'],
				'status'              => [$item['status'] ?? 'draft', '%s'],
				'created_at'          => [$now, '%s'],
				'updated_at'          => [$now, '%s'],
			];
			if ($speaker_id !== null)  { $cols['speaker_id']  = [$speaker_id, '%d']; }
			if ($assigned_to !== null) { $cols['assigned_to'] = [$assigned_to, '%d']; }
			if ($due_date !== null)    { $cols['due_date']    = [$due_date, '%s']; }

			$data    = array_combine(array_keys($cols), array_column(array_values($cols), 0));
			$formats = array_column(array_values($cols), 1);

			$result = $wpdb->insert($table, $data, $formats);
			if ($result) { $count++; }
		}

		return $count;
	}

	public static function get_items(int $meeting_id, string $type = ''): array {
		global $wpdb;
		$sql = "SELECT i.*, u.display_name as speaker_name
				FROM {$wpdb->prefix}cb_circleup_items i
				LEFT JOIN {$wpdb->users} u ON i.speaker_id = u.ID
				WHERE i.circleup_meeting_id = %d";
		$params = [$meeting_id];

		if ($type !== '') {
			$sql .= " AND i.item_type = %s";
			$params[] = $type;
		}

		$sql .= " ORDER BY i.id ASC";
		return $wpdb->get_results($wpdb->prepare($sql, ...$params)) ?: [];
	}

	public static function update_item(int $item_id, array $data): bool {
		global $wpdb;
		$data['updated_at'] = gmdate('Y-m-d H:i:s');

		// For nullable int columns that are explicitly null, use a raw UPDATE
		// because wpdb->update with %d converts null to 0.
		$int_cols = ['speaker_id', 'assigned_to'];
		$has_null_int = false;
		foreach ($int_cols as $col) {
			if (array_key_exists($col, $data) && $data[$col] === null) {
				$has_null_int = true;
				break;
			}
		}

		if ($has_null_int) {
			$sets = [];
			$values = [];
			foreach ($data as $key => $value) {
				if (in_array($key, $int_cols, true) && $value === null) {
					$sets[] = "`$key` = NULL";
				} elseif (in_array($key, $int_cols, true)) {
					$sets[] = "`$key` = %d";
					$values[] = $value;
				} else {
					$sets[] = "`$key` = %s";
					$values[] = $value;
				}
			}
			$values[] = $item_id;
			$sql = "UPDATE {$wpdb->prefix}cb_circleup_items SET " . implode(', ', $sets) . " WHERE id = %d";
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Built dynamically with column whitelist.
			return $wpdb->query($wpdb->prepare($sql, ...$values)) !== false;
		}

		// Standard path: no null ints.
		$formats = [];
		foreach ($data as $key => $value) {
			$formats[] = in_array($key, $int_cols, true) ? '%d' : '%s';
		}
		return $wpdb->update($wpdb->prefix . 'cb_circleup_items', $data, ['id' => $item_id], $formats, ['%d']) !== false;
	}

	public static function delete_items_for_meeting(int $meeting_id): bool {
		global $wpdb;
		return $wpdb->delete($wpdb->prefix . 'cb_circleup_items', ['circleup_meeting_id' => $meeting_id]) !== false;
	}

	/**
	 * Get action items assigned to a member.
	 * Returns all action items except rejected ones.
	 */
	public static function get_member_actions(int $member_id): array {
		global $wpdb;
		return $wpdb->get_results($wpdb->prepare(
			"SELECT i.*, m.title as meeting_title, m.meeting_date
			 FROM {$wpdb->prefix}cb_circleup_items i
			 JOIN {$wpdb->prefix}cb_circleup_meetings m ON i.circleup_meeting_id = m.id
			 WHERE i.assigned_to = %d AND i.item_type = 'action' AND i.status != 'rejected'
			 ORDER BY FIELD(i.status, 'approved', 'in_progress', 'pending', 'draft', 'done') ASC, i.due_date ASC, i.created_at DESC",
			$member_id
		)) ?: [];
	}

	/**
	 * Count open (non-done, non-rejected) action items assigned to a member.
	 */
	public static function count_open_actions(int $member_id): int {
		global $wpdb;
		return (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}cb_circleup_items
			 WHERE assigned_to = %d AND item_type = 'action' AND status NOT IN ('done', 'rejected')",
			$member_id
		));
	}
}