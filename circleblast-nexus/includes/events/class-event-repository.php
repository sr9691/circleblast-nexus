<?php
/**
 * Events Repository
 *
 * Data access layer for cb_events and cb_event_rsvps tables.
 */

defined('ABSPATH') || exit;

final class CBNexus_Event_Repository {

	// ─── Events CRUD ───────────────────────────────────────────────────

	public static function create(array $data): int {
		global $wpdb;
		$now = gmdate('Y-m-d H:i:s');

		$wpdb->insert($wpdb->prefix . 'cb_events', [
			'title'            => sanitize_text_field($data['title']),
			'description'      => sanitize_textarea_field($data['description'] ?? ''),
			'event_date'       => sanitize_text_field($data['event_date']),
			'event_time'       => !empty($data['event_time']) ? sanitize_text_field($data['event_time']) : null,
			'end_date'         => !empty($data['end_date']) ? sanitize_text_field($data['end_date']) : null,
			'end_time'         => !empty($data['end_time']) ? sanitize_text_field($data['end_time']) : null,
			'location'         => sanitize_text_field($data['location'] ?? ''),
			'location_url'     => esc_url_raw($data['location_url'] ?? ''),
			'audience'         => in_array($data['audience'] ?? '', ['all', 'members', 'public'], true) ? $data['audience'] : 'all',
			'category'         => sanitize_text_field($data['category'] ?? ''),
			'registration_url' => esc_url_raw($data['registration_url'] ?? ''),
			'reminder_notes'   => sanitize_textarea_field($data['reminder_notes'] ?? ''),
			'cost'             => sanitize_text_field($data['cost'] ?? ''),
			'max_attendees'    => !empty($data['max_attendees']) ? absint($data['max_attendees']) : null,
			'organizer_id'     => absint($data['organizer_id']),
			'status'           => $data['status'] ?? 'pending',
			'created_at'       => $now,
			'updated_at'       => $now,
		]);

		return (int) $wpdb->insert_id;
	}

	public static function update(int $id, array $data): bool {
		global $wpdb;
		$data['updated_at'] = gmdate('Y-m-d H:i:s');
		return (bool) $wpdb->update($wpdb->prefix . 'cb_events', $data, ['id' => $id]);
	}

	public static function get(int $id): ?object {
		global $wpdb;
		return $wpdb->get_row($wpdb->prepare(
			"SELECT e.*, u.display_name as organizer_name
			 FROM {$wpdb->prefix}cb_events e
			 LEFT JOIN {$wpdb->users} u ON e.organizer_id = u.ID
			 WHERE e.id = %d", $id
		));
	}

	public static function delete(int $id): bool {
		global $wpdb;
		$wpdb->delete($wpdb->prefix . 'cb_event_rsvps', ['event_id' => $id], ['%d']);
		return (bool) $wpdb->delete($wpdb->prefix . 'cb_events', ['id' => $id], ['%d']);
	}

	/**
	 * Get events with flexible filtering.
	 */
	public static function query(array $args = []): array {
		global $wpdb;
		$table = $wpdb->prefix . 'cb_events';

		$where  = ['1=1'];
		$params = [];

		if (!empty($args['status'])) {
			$where[]  = 'e.status = %s';
			$params[] = $args['status'];
		}

		if (!empty($args['from_date'])) {
			$where[]  = 'e.event_date >= %s';
			$params[] = $args['from_date'];
		}

		if (!empty($args['to_date'])) {
			$where[]  = 'e.event_date <= %s';
			$params[] = $args['to_date'];
		}

		if (!empty($args['audience'])) {
			$where[]  = 'e.audience IN (%s, "all")';
			$params[] = $args['audience'];
		}

		if (!empty($args['category'])) {
			$where[]  = 'e.category = %s';
			$params[] = $args['category'];
		}

		$order = ($args['order'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
		$limit = absint($args['limit'] ?? 100);

		$sql = "SELECT e.*, u.display_name as organizer_name
		        FROM {$table} e
		        LEFT JOIN {$wpdb->users} u ON e.organizer_id = u.ID
		        WHERE " . implode(' AND ', $where) . "
		        ORDER BY e.event_date {$order}, e.event_time {$order}
		        LIMIT {$limit}";

		if (!empty($params)) {
			$sql = $wpdb->prepare($sql, ...$params);
		}

		return $wpdb->get_results($sql) ?: [];
	}

	/**
	 * Get upcoming approved events.
	 */
	public static function get_upcoming(int $limit = 20): array {
		return self::query([
			'status'    => 'approved',
			'from_date' => gmdate('Y-m-d'),
			'order'     => 'ASC',
			'limit'     => $limit,
		]);
	}

	/**
	 * Get events needing reminders (tomorrow, not yet sent).
	 */
	public static function get_needing_reminders(): array {
		global $wpdb;
		$tomorrow = gmdate('Y-m-d', strtotime('+1 day'));
		return $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}cb_events
			 WHERE status = 'approved' AND event_date = %s AND reminder_sent = 0",
			$tomorrow
		)) ?: [];
	}

	/**
	 * Get all distinct event categories.
	 */
	public static function get_categories(): array {
		global $wpdb;
		return $wpdb->get_col(
			"SELECT DISTINCT category FROM {$wpdb->prefix}cb_events
			 WHERE category != '' AND category IS NOT NULL ORDER BY category"
		) ?: [];
	}

	// ─── RSVPs ─────────────────────────────────────────────────────────

	public static function rsvp(int $event_id, int $member_id, string $status = 'going'): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'cb_event_rsvps';

		$existing = $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM {$table} WHERE event_id = %d AND member_id = %d",
			$event_id, $member_id
		));

		if ($existing) {
			return (bool) $wpdb->update($table, ['status' => $status], ['id' => $existing]);
		}

		return (bool) $wpdb->insert($table, [
			'event_id'   => $event_id,
			'member_id'  => $member_id,
			'status'     => $status,
			'created_at' => gmdate('Y-m-d H:i:s'),
		]);
	}

	public static function get_rsvps(int $event_id): array {
		global $wpdb;
		return $wpdb->get_results($wpdb->prepare(
			"SELECT r.*, u.display_name
			 FROM {$wpdb->prefix}cb_event_rsvps r
			 JOIN {$wpdb->users} u ON r.member_id = u.ID
			 WHERE r.event_id = %d ORDER BY r.created_at",
			$event_id
		)) ?: [];
	}

	public static function get_rsvp_counts(int $event_id): array {
		global $wpdb;
		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT status, COUNT(*) as cnt
			 FROM {$wpdb->prefix}cb_event_rsvps
			 WHERE event_id = %d GROUP BY status",
			$event_id
		));
		$counts = ['going' => 0, 'maybe' => 0, 'not_going' => 0];
		foreach ($rows ?: [] as $r) { $counts[$r->status] = (int) $r->cnt; }
		return $counts;
	}

	public static function get_member_rsvp(int $event_id, int $member_id): ?string {
		global $wpdb;
		return $wpdb->get_var($wpdb->prepare(
			"SELECT status FROM {$wpdb->prefix}cb_event_rsvps WHERE event_id = %d AND member_id = %d",
			$event_id, $member_id
		));
	}
}
