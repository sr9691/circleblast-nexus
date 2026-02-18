<?php
/**
 * Events Service
 *
 * Business logic for event submission, approval, and reminders.
 */

defined('ABSPATH') || exit;

final class CBNexus_Event_Service {

	const CATEGORIES = [
		'networking'  => 'Networking',
		'workshop'    => 'Workshop',
		'social'      => 'Social',
		'speaker'     => 'Speaker / Panel',
		'community'   => 'Community',
		'other'       => 'Other',
	];

	/**
	 * Submit a new event (member-facing).
	 */
	public static function submit(int $organizer_id, array $data): array {
		$errors = self::validate($data);
		if (!empty($errors)) {
			return ['success' => false, 'errors' => $errors];
		}

		$data['organizer_id'] = $organizer_id;
		// Admins auto-approve, members need approval.
		$data['status'] = current_user_can('cbnexus_manage_members') ? 'approved' : 'pending';

		$id = CBNexus_Event_Repository::create($data);
		if (!$id) {
			return ['success' => false, 'errors' => ['Failed to create event.']];
		}

		if ($data['status'] === 'pending') {
			self::notify_admins_pending($id, $data);
		}

		return ['success' => true, 'event_id' => $id];
	}

	/**
	 * Approve a pending event.
	 */
	public static function approve(int $event_id, int $admin_id): bool {
		return CBNexus_Event_Repository::update($event_id, [
			'status'      => 'approved',
			'approved_by' => $admin_id,
			'approved_at' => gmdate('Y-m-d H:i:s'),
		]);
	}

	/**
	 * Send event reminders for tomorrow's events.
	 * Called by WP-Cron daily.
	 */
	public static function send_reminders(): void {
		$events = CBNexus_Event_Repository::get_needing_reminders();
		$members = CBNexus_Member_Repository::get_all_members('active');

		foreach ($events as $event) {
			foreach ($members as $m) {
				if ($event->audience === 'members' || $event->audience === 'all') {
					CBNexus_Email_Service::send('event_reminder', $m['user_email'], [
						'first_name'       => $m['first_name'],
						'event_title'      => $event->title,
						'event_date'       => date_i18n('l, F j', strtotime($event->event_date)),
						'event_time'       => $event->event_time ? date_i18n('g:i A', strtotime($event->event_time)) : '',
						'event_location'   => $event->location ?: 'TBD',
						'reminder_notes'   => $event->reminder_notes ?: '',
						'registration_url' => $event->registration_url ?: '',
						'description'      => wp_trim_words($event->description, 40),
					], ['recipient_id' => (int) $m['user_id'], 'related_id' => (int) $event->id, 'related_type' => 'event_reminder']);
				}
			}

			CBNexus_Event_Repository::update((int) $event->id, ['reminder_sent' => 1]);
		}
	}

	/**
	 * Notify admins of a pending event submission.
	 */
	private static function notify_admins_pending(int $event_id, array $data): void {
		$admins = get_users(['role__in' => ['cb_admin', 'cb_super_admin', 'administrator']]);
		$review_url = admin_url('admin.php?page=cbnexus-events&action=edit&id=' . $event_id);

		foreach ($admins as $admin) {
			CBNexus_Email_Service::send('event_pending', $admin->user_email, [
				'admin_name'   => $admin->first_name ?: $admin->display_name,
				'event_title'  => $data['title'],
				'event_date'   => $data['event_date'],
				'review_url'   => $review_url,
			], ['recipient_id' => $admin->ID, 'related_id' => $event_id, 'related_type' => 'event_pending']);
		}
	}

	private static function validate(array $data): array {
		$errors = [];
		if (empty($data['title'])) { $errors[] = 'Title is required.'; }
		if (empty($data['event_date'])) { $errors[] = 'Event date is required.'; }
		if (!empty($data['event_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['event_date'])) {
			$errors[] = 'Invalid date format.';
		}
		return $errors;
	}
}
