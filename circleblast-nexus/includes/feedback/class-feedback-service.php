<?php
/**
 * Feedback Service
 *
 * Handles feedback/issue report CRUD and email notifications to super admins.
 * Feedback is stored in the cb_feedback table and surfaced in the Manage → Feedback tab.
 *
 * @since 1.3.0
 */

defined('ABSPATH') || exit;

final class CBNexus_Feedback_Service {

	/**
	 * Valid feedback types.
	 */
	private static $types = ['feedback', 'bug', 'idea', 'question'];

	/**
	 * Valid statuses.
	 */
	private static $statuses = ['new', 'reviewed', 'in_progress', 'resolved', 'dismissed'];

	/**
	 * Submit new feedback.
	 *
	 * @param int    $user_id      Submitter's WP user ID.
	 * @param string $type         One of: feedback, bug, idea, question.
	 * @param string $subject      Short subject line.
	 * @param string $message      Full message body.
	 * @param string $page_context The portal section the user was on when submitting.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function submit(int $user_id, string $type, string $subject, string $message, string $page_context = ''): int|false {
		global $wpdb;

		if (!in_array($type, self::$types, true)) {
			$type = 'feedback';
		}

		$data = [
			'user_id'      => $user_id,
			'type'         => $type,
			'subject'      => mb_substr($subject, 0, 255),
			'message'      => $message,
			'page_context' => mb_substr($page_context, 0, 100),
			'status'       => 'new',
			'created_at'   => gmdate('Y-m-d H:i:s'),
		];

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'cb_feedback',
			$data,
			['%d', '%s', '%s', '%s', '%s', '%s', '%s']
		);

		if (!$inserted) {
			return false;
		}

		$feedback_id = (int) $wpdb->insert_id;

		// Log.
		if (class_exists('CBNexus_Logger')) {
			CBNexus_Logger::info('Feedback submitted.', [
				'feedback_id' => $feedback_id,
				'user_id'     => $user_id,
				'type'        => $type,
			]);
		}

		// Notify super admins.
		self::notify_super_admins($feedback_id, $user_id, $type, $subject, $message);

		return $feedback_id;
	}

	/**
	 * Get feedback entries with optional filters.
	 *
	 * @param array $args {
	 *     @type string $status Filter by status.
	 *     @type string $type   Filter by type.
	 *     @type int    $limit  Number of results (default 50).
	 *     @type int    $offset Offset for pagination.
	 *     @type string $order  ASC or DESC (default DESC).
	 * }
	 * @return array
	 */
	public static function get_all(array $args = []): array {
		global $wpdb;
		$table = $wpdb->prefix . 'cb_feedback';

		$where  = [];
		$values = [];

		if (!empty($args['status'])) {
			$where[]  = 'f.status = %s';
			$values[] = $args['status'];
		}
		if (!empty($args['type'])) {
			$where[]  = 'f.type = %s';
			$values[] = $args['type'];
		}

		$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
		$order     = (isset($args['order']) && strtoupper($args['order']) === 'ASC') ? 'ASC' : 'DESC';
		$limit     = absint($args['limit'] ?? 50);
		$offset    = absint($args['offset'] ?? 0);

		$sql = "SELECT f.*, u.display_name AS author_name, u.user_email AS author_email
		        FROM {$table} f
		        LEFT JOIN {$wpdb->users} u ON u.ID = f.user_id
		        {$where_sql}
		        ORDER BY f.created_at {$order}
		        LIMIT %d OFFSET %d";

		$values[] = $limit;
		$values[] = $offset;

		if ($values) {
			$sql = $wpdb->prepare($sql, $values);
		}

		return $wpdb->get_results($sql) ?: [];
	}

	/**
	 * Get a single feedback entry.
	 */
	public static function get_by_id(int $id): ?object {
		global $wpdb;
		$table = $wpdb->prefix . 'cb_feedback';

		return $wpdb->get_row($wpdb->prepare(
			"SELECT f.*, u.display_name AS author_name, u.user_email AS author_email
			 FROM {$table} f
			 LEFT JOIN {$wpdb->users} u ON u.ID = f.user_id
			 WHERE f.id = %d",
			$id
		));
	}

	/**
	 * Count feedback entries by status.
	 *
	 * @return array<string, int> e.g. ['new' => 3, 'reviewed' => 1, …]
	 */
	public static function count_by_status(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'cb_feedback';

		$rows = $wpdb->get_results(
			"SELECT status, COUNT(*) AS cnt FROM {$table} GROUP BY status"
		);

		$counts = array_fill_keys(self::$statuses, 0);
		foreach ($rows as $row) {
			$counts[$row->status] = (int) $row->cnt;
		}
		return $counts;
	}

	/**
	 * Count new (unread) feedback entries.
	 */
	public static function count_new(): int {
		global $wpdb;
		$table = $wpdb->prefix . 'cb_feedback';

		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE status = 'new'"
		);
	}

	/**
	 * Update feedback status and optionally add admin notes.
	 */
	public static function update_status(int $id, string $status, string $admin_notes = '', ?int $admin_id = null): bool {
		global $wpdb;

		if (!in_array($status, self::$statuses, true)) {
			return false;
		}

		$data = [
			'status'      => $status,
			'admin_notes' => $admin_notes ?: null,
		];
		$formats = ['%s', '%s'];

		if ($status === 'resolved' || $status === 'dismissed') {
			$data['resolved_by'] = $admin_id ?? get_current_user_id();
			$data['resolved_at'] = gmdate('Y-m-d H:i:s');
			$formats[] = '%d';
			$formats[] = '%s';
		}

		return (bool) $wpdb->update(
			$wpdb->prefix . 'cb_feedback',
			$data,
			['id' => $id],
			$formats,
			['%d']
		);
	}

	/**
	 * Delete a feedback entry.
	 */
	public static function delete(int $id): bool {
		global $wpdb;
		return (bool) $wpdb->delete(
			$wpdb->prefix . 'cb_feedback',
			['id' => $id],
			['%d']
		);
	}

	/**
	 * Get valid types.
	 */
	public static function get_types(): array {
		return self::$types;
	}

	/**
	 * Get valid statuses.
	 */
	public static function get_statuses(): array {
		return self::$statuses;
	}

	/**
	 * Get a human-readable label for a type.
	 */
	public static function type_label(string $type): string {
		$labels = [
			'feedback' => 'General Feedback',
			'bug'      => 'Bug Report',
			'idea'     => 'Feature Idea',
			'question' => 'Question',
		];
		return $labels[$type] ?? ucfirst($type);
	}

	/**
	 * Get a human-readable label for a status.
	 */
	public static function status_label(string $status): string {
		$labels = [
			'new'         => 'New',
			'reviewed'    => 'Reviewed',
			'in_progress' => 'In Progress',
			'resolved'    => 'Resolved',
			'dismissed'   => 'Dismissed',
		];
		return $labels[$status] ?? ucfirst($status);
	}

	// ─── Email Notification ──────────────────────────────────────

	/**
	 * Notify all super admins about new feedback via email.
	 */
	private static function notify_super_admins(int $feedback_id, int $user_id, string $type, string $subject, string $message): void {
		$super_admins = get_users(['role' => 'cb_super_admin', 'fields' => ['ID', 'user_email', 'display_name']]);

		if (empty($super_admins)) {
			return;
		}

		$submitter  = get_userdata($user_id);
		$submitter_name = $submitter ? $submitter->display_name : 'Unknown Member';
		$type_label = self::type_label($type);

		// Build the portal link to the feedback tab.
		$portal_url = class_exists('CBNexus_Portal_Router') ? CBNexus_Portal_Router::get_portal_url() : home_url();
		$feedback_url = add_query_arg([
			'section'   => 'manage',
			'admin_tab' => 'feedback',
		], $portal_url);

		foreach ($super_admins as $admin) {
			if (class_exists('CBNexus_Email_Service')) {
				CBNexus_Email_Service::send('feedback_notification', $admin->user_email, [
					'admin_name'     => $admin->display_name,
					'submitter_name' => $submitter_name,
					'type_label'     => $type_label,
					'subject'        => $subject,
					'message'        => nl2br(esc_html($message)),
					'feedback_url'   => $feedback_url,
				], [
					'subject'      => '[CircleBlast] New ' . $type_label . ': ' . $subject,
					'recipient_id' => $admin->ID,
					'related_id'   => $feedback_id,
					'related_type' => 'feedback',
				]);
			}
		}
	}
}
