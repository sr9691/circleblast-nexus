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
			$event = CBNexus_Event_Repository::get($id);
			self::notify_admins_pending($id, $data);
			if ($event) {
				self::notify_submitter_pending($id, $event);
			}
		}

		return ['success' => true, 'event_id' => $id];
	}

	/**
	 * Approve a pending event and notify the submitting member.
	 */
	public static function approve(int $event_id, int $admin_id): bool {
		$event = CBNexus_Event_Repository::get($event_id);
		if (!$event || !in_array($event->status, ['pending', 'denied'], true)) { return false; }

		$updated = CBNexus_Event_Repository::update($event_id, [
			'status'      => 'approved',
			'approved_by' => $admin_id,
			'approved_at' => gmdate('Y-m-d H:i:s'),
		]);

		if ($updated && $event->organizer_id) {
			self::notify_submitter_approved($event);
		}

		return $updated;
	}

	/**
	 * Deny a pending event and notify the submitting member.
	 */
	public static function deny(int $event_id, int $admin_id): bool {
		$event = CBNexus_Event_Repository::get($event_id);
		if (!$event || $event->status !== 'pending') { return false; }

		$updated = CBNexus_Event_Repository::update($event_id, [
			'status' => 'denied',
		]);

		if ($updated && $event->organizer_id) {
			self::notify_submitter_denied($event);
		}

		return $updated;
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
						'cost_block'       => self::build_cost_block($event),
						'reminder_notes'   => $event->reminder_notes ?: '',
						'registration_url' => $event->registration_url ?: '',
						'description'      => wp_trim_words($event->description, 40),
						'calendar_block'   => self::build_calendar_block($event),
					], ['recipient_id' => (int) $m['user_id'], 'related_id' => (int) $event->id, 'related_type' => 'event_reminder']);
				}
			}

			CBNexus_Event_Repository::update((int) $event->id, ['reminder_sent' => 1]);
		}
	}

	/**
	 * Notify Super Admin users of a pending event with tokenized approve/deny links.
	 */
	private static function notify_admins_pending(int $event_id, array $data): void {
		$super_admins = get_users(['role__in' => ['cb_super_admin', 'administrator']]);
		if (empty($super_admins)) { return; }

		$event = CBNexus_Event_Repository::get($event_id);
		if (!$event) { return; }

		$submitter    = get_userdata($event->organizer_id);
		$submitter_name = $submitter ? $submitter->display_name : 'A member';

		$portal_url   = class_exists('CBNexus_Portal_Router')
			? CBNexus_Portal_Router::get_portal_url() : home_url();
		$review_url   = add_query_arg(['section' => 'admin', 'admin_tab' => 'events', 'edit_event' => $event_id], $portal_url);

		// Build conditional detail rows for the email.
		$time_row       = '';
		$location_row   = '';
		$audience_row   = '';
		$category_row   = '';
		$cost_row       = '';
		$registration_row = '';
		$description_block = '';

		if (!empty($event->event_time)) {
			$time_str = date_i18n('g:i A', strtotime($event->event_time));
			if (!empty($event->end_time)) {
				$time_str .= ' – ' . date_i18n('g:i A', strtotime($event->end_time));
			}
			$time_row = '<tr><td style="padding:4px 0;font-size:14px;color:#4a5568;width:30px;">🕐</td>'
				. '<td style="padding:4px 0;font-size:14px;color:#333;">' . esc_html($time_str) . '</td></tr>';
		}
		if (!empty($event->location)) {
			$location_row = '<tr><td style="padding:4px 0;font-size:14px;color:#4a5568;width:30px;">📍</td>'
				. '<td style="padding:4px 0;font-size:14px;color:#333;">' . esc_html($event->location) . '</td></tr>';
		}
		$audience_labels = ['all' => 'Everyone', 'members' => 'Members Only', 'public' => 'Open to Public'];
		$aud_label = $audience_labels[$event->audience] ?? 'Everyone';
		$audience_row = '<tr><td style="padding:4px 0;font-size:14px;color:#4a5568;width:30px;">👥</td>'
			. '<td style="padding:4px 0;font-size:14px;color:#333;">' . esc_html($aud_label) . '</td></tr>';
		if (!empty($event->category) && isset(self::CATEGORIES[$event->category])) {
			$category_row = '<tr><td style="padding:4px 0;font-size:14px;color:#4a5568;width:30px;">🏷️</td>'
				. '<td style="padding:4px 0;font-size:14px;color:#333;">' . esc_html(self::CATEGORIES[$event->category]) . '</td></tr>';
		}
		$cost_parts = [];
		if (!empty($event->cost))       { $cost_parts[] = 'Members: ' . esc_html($event->cost); }
		if (!empty($event->guest_cost)) { $cost_parts[] = 'Guests: ' . esc_html($event->guest_cost); }
		if (!empty($cost_parts)) {
			$cost_row = '<tr><td style="padding:4px 0;font-size:14px;color:#4a5568;width:30px;">💰</td>'
				. '<td style="padding:4px 0;font-size:14px;color:#333;">' . implode(' · ', $cost_parts) . '</td></tr>';
		}
		if (!empty($event->registration_url)) {
			$registration_row = '<tr><td style="padding:4px 0;font-size:14px;color:#4a5568;width:30px;">🔗</td>'
				. '<td style="padding:4px 0;font-size:14px;color:#333;"><a href="' . esc_url($event->registration_url) . '" style="color:#2563eb;">Registration link</a></td></tr>';
		}
		if (!empty($event->description)) {
			$description_block = '<div style="margin-top:12px;padding-top:12px;border-top:1px solid #e2e8f0;">'
				. '<p style="margin:0;font-size:14px;color:#4a5568;line-height:1.6;">' . esc_html(wp_trim_words($event->description, 60)) . '</p></div>';
		}

		foreach ($super_admins as $admin) {
			// Generate unique single-use tokens for each admin.
			$approve_token = CBNexus_Token_Service::generate($admin->ID, 'approve_event', ['event_id' => $event_id], 14);
			$deny_token    = CBNexus_Token_Service::generate($admin->ID, 'deny_event', ['event_id' => $event_id], 14);

			CBNexus_Email_Service::send('event_pending', $admin->user_email, [
				'admin_name'          => $admin->first_name ?: $admin->display_name,
				'submitter_name'      => $submitter_name,
				'event_title'         => $event->title,
				'event_date_formatted' => date_i18n('l, F j, Y', strtotime($event->event_date)),
				'time_row'            => $time_row,
				'location_row'        => $location_row,
				'audience_row'        => $audience_row,
				'category_row'        => $category_row,
				'cost_row'            => $cost_row,
				'registration_row'    => $registration_row,
				'description_block'   => $description_block,
				'approve_url'         => CBNexus_Token_Service::url($approve_token),
				'deny_url'            => CBNexus_Token_Service::url($deny_token),
				'portal_review_url'   => $review_url,
			], ['recipient_id' => $admin->ID, 'related_id' => $event_id, 'related_type' => 'event_pending']);
		}
	}

	/**
	 * Send a confirmation email to the member who submitted the event.
	 */
	private static function notify_submitter_pending(int $event_id, object $event): void {
		$submitter = get_userdata($event->organizer_id);
		if (!$submitter) { return; }

		$time_line = '';
		if (!empty($event->event_time)) {
			$t = date_i18n('g:i A', strtotime($event->event_time));
			if (!empty($event->end_time)) { $t .= ' – ' . date_i18n('g:i A', strtotime($event->end_time)); }
			$time_line = '<p style="margin:4px 0 0;font-size:14px;color:#4a5568;">🕐 ' . esc_html($t) . '</p>';
		}
		$location_line = '';
		if (!empty($event->location)) {
			$location_line = '<p style="margin:4px 0 0;font-size:14px;color:#4a5568;">📍 ' . esc_html($event->location) . '</p>';
		}

		CBNexus_Email_Service::send('event_submitted_confirmation', $submitter->user_email, [
			'first_name'          => $submitter->first_name ?: $submitter->display_name,
			'event_title'         => $event->title,
			'event_date_formatted' => date_i18n('l, F j, Y', strtotime($event->event_date)),
			'time_line'           => $time_line,
			'location_line'       => $location_line,
		], ['recipient_id' => $submitter->ID, 'related_id' => $event_id, 'related_type' => 'event_submitted_confirmation']);
	}

	/**
	 * Notify the submitter that their event has been approved.
	 */
	private static function notify_submitter_approved(object $event): void {
		$submitter = get_userdata($event->organizer_id);
		if (!$submitter) { return; }

		$portal_url = class_exists('CBNexus_Portal_Router')
			? CBNexus_Portal_Router::get_portal_url() : home_url();
		$event_url = add_query_arg(['section' => 'events', 'event_id' => $event->id], $portal_url);

		$time_line = '';
		if (!empty($event->event_time)) {
			$t = date_i18n('g:i A', strtotime($event->event_time));
			if (!empty($event->end_time)) { $t .= ' – ' . date_i18n('g:i A', strtotime($event->end_time)); }
			$time_line = '<p style="margin:4px 0 0;font-size:14px;color:#166534;">🕐 ' . esc_html($t) . '</p>';
		}
		$location_line = '';
		if (!empty($event->location)) {
			$location_line = '<p style="margin:4px 0 0;font-size:14px;color:#166534;">📍 ' . esc_html($event->location) . '</p>';
		}

		CBNexus_Email_Service::send('event_approved', $submitter->user_email, [
			'first_name'          => $submitter->first_name ?: $submitter->display_name,
			'event_title'         => $event->title,
			'event_date_formatted' => date_i18n('l, F j, Y', strtotime($event->event_date)),
			'time_line'           => $time_line,
			'location_line'       => $location_line,
			'event_url'           => $event_url,
		], ['recipient_id' => $submitter->ID, 'related_id' => (int) $event->id, 'related_type' => 'event_approved']);
	}

	/**
	 * Notify the submitter that their event has been denied.
	 */
	private static function notify_submitter_denied(object $event): void {
		$submitter = get_userdata($event->organizer_id);
		if (!$submitter) { return; }

		CBNexus_Email_Service::send('event_denied', $submitter->user_email, [
			'first_name'  => $submitter->first_name ?: $submitter->display_name,
			'event_title' => $event->title,
		], ['recipient_id' => $submitter->ID, 'related_id' => (int) $event->id, 'related_type' => 'event_denied']);
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

	// ─── Settings Defaults ──────────────────────────────────────────────

	/**
	 * Get events digest settings with defaults.
	 *
	 * @return array{frequency: string, lookahead_days: int, enabled: bool}
	 */
	public static function get_digest_settings(): array {
		$defaults = [
			'enabled'        => true,
			'frequency'      => 'weekly',   // weekly, biweekly, monthly
			'lookahead_days' => 14,          // how far ahead to include events
			'day_of_week'    => 'monday',    // monday-sunday
			'time_of_day'    => '09:00',     // HH:MM in 24h format (site timezone)
		];
		$saved = get_option('cbnexus_events_digest_settings', []);
		return wp_parse_args($saved, $defaults);
	}

	public static function save_digest_settings(array $settings): void {
		$valid_days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
		$day = strtolower($settings['day_of_week'] ?? 'monday');
		if (!in_array($day, $valid_days, true)) { $day = 'monday'; }

		$time = sanitize_text_field($settings['time_of_day'] ?? '09:00');
		if (!preg_match('/^\d{2}:\d{2}$/', $time)) { $time = '09:00'; }

		$freq_days = ['weekly' => 7, 'biweekly' => 14, 'monthly' => 30];
		$frequency = in_array($settings['frequency'] ?? '', ['weekly', 'biweekly', 'monthly'], true)
			? $settings['frequency'] : 'weekly';
		$base = $freq_days[$frequency];
		$lookahead = absint($settings['lookahead_days'] ?? $base);
		$lookahead = max($base, min($base * 3, $lookahead));

		update_option('cbnexus_events_digest_settings', [
			'enabled'        => !empty($settings['enabled']),
			'frequency'      => $frequency,
			'lookahead_days' => $lookahead,
			'day_of_week'    => $day,
			'time_of_day'    => $time,
		]);
	}

	// ─── Automated Digest (WP-Cron) ─────────────────────────────────────

	/**
	 * Send the scheduled events digest to all active members.
	 * Called by WP-Cron at the configured frequency.
	 */
	public static function send_digest(): void {
		$settings = self::get_digest_settings();
		if (!$settings['enabled']) { return; }

		// Check if today is the right day of week (using site timezone).
		$tz = wp_timezone();
		$now = new \DateTimeImmutable('now', $tz);
		$today_day = strtolower($now->format('l')); // e.g. 'monday'

		if ($today_day !== $settings['day_of_week']) { return; }

		// For biweekly/monthly, check if enough time has passed since last send.
		$last_sent = get_option('cbnexus_events_digest_last_sent', '');
		if ($last_sent !== '') {
			$last = new \DateTimeImmutable($last_sent, $tz);
			$days_since = (int) $now->diff($last)->days;

			if ($settings['frequency'] === 'biweekly' && $days_since < 13) { return; }
			if ($settings['frequency'] === 'monthly' && $days_since < 27) { return; }
		}

		$from = $now->format('Y-m-d');
		$to   = $now->modify('+' . $settings['lookahead_days'] . ' days')->format('Y-m-d');

		$events = CBNexus_Event_Repository::query([
			'status'    => 'approved',
			'from_date' => $from,
			'to_date'   => $to,
			'order'     => 'ASC',
		]);

		if (empty($events)) { return; }

		$events_html = self::build_events_html($events);
		$intro = sprintf(
			'Here are the upcoming ClubWorks events for the next %d days:',
			$settings['lookahead_days']
		);

		self::send_digest_to_members($events_html, $intro);

		// Record last sent time for biweekly/monthly frequency checks.
		update_option('cbnexus_events_digest_last_sent', $now->format('Y-m-d H:i:s'));

		if (class_exists('CBNexus_Logger')) {
			CBNexus_Logger::info('Events digest sent.', [
				'event_count' => count($events),
				'lookahead'   => $settings['lookahead_days'],
			]);
		}
	}

	// ─── On-Demand Send (Admin) ─────────────────────────────────────────

	/**
	 * Send an on-demand notification for specific events.
	 *
	 * @param int[] $event_ids IDs of events to include.
	 * @return int Number of members emailed.
	 */
	public static function send_on_demand(array $event_ids): int {
		$events = [];
		foreach ($event_ids as $eid) {
			$e = CBNexus_Event_Repository::get(absint($eid));
			if ($e && $e->status === 'approved') {
				$events[] = $e;
			}
		}

		if (empty($events)) { return 0; }

		// Sort by date.
		usort($events, fn($a, $b) => strcmp($a->event_date, $b->event_date));

		$events_html = self::build_events_html($events);
		$count_label = count($events) === 1
			? 'an upcoming event we wanted to make sure you know about:'
			: count($events) . ' upcoming events we wanted to make sure you know about:';
		$intro = "Here's " . $count_label;

		$sent = self::send_digest_to_members($events_html, $intro);

		if (class_exists('CBNexus_Logger')) {
			CBNexus_Logger::info('On-demand event notification sent.', [
				'event_ids'     => $event_ids,
				'members_sent'  => $sent,
			]);
		}

		return $sent;
	}

	// ─── Calendar Link Helpers ──────────────────────────────────────────

	/**
	 * Build a Google Calendar "Add Event" URL.
	 */
	public static function google_calendar_url(object $event): string {
		$start = self::calendar_datetime($event->event_date, $event->event_time ?? '');
		$end   = self::calendar_datetime(
			$event->end_date ?: $event->event_date,
			$event->end_time ?: ($event->event_time ? date('H:i', strtotime($event->event_time) + 3600) : '')
		);

		$params = [
			'action'   => 'TEMPLATE',
			'text'     => $event->title,
			'dates'    => $start . '/' . $end,
			'details'  => wp_strip_all_tags($event->description ?? ''),
			'location' => $event->location ?? '',
		];

		return 'https://calendar.google.com/calendar/render?' . http_build_query($params);
	}

	/**
	 * Build an Outlook.com calendar URL.
	 */
	public static function outlook_calendar_url(object $event): string {
		$start = self::iso_datetime($event->event_date, $event->event_time ?? '');
		$end   = self::iso_datetime(
			$event->end_date ?: $event->event_date,
			$event->end_time ?: ($event->event_time ? date('H:i', strtotime($event->event_time) + 3600) : '')
		);

		$params = [
			'rru'      => 'addevent',
			'startdt'  => $start,
			'enddt'    => $end,
			'subject'  => $event->title,
			'body'     => wp_strip_all_tags($event->description ?? ''),
			'location' => $event->location ?? '',
			'path'     => '/calendar/action/compose',
		];

		return 'https://outlook.live.com/calendar/0/action/compose?' . http_build_query($params);
	}

	/**
	 * Build a downloadable .ics data URI for Apple Calendar / other ICS clients.
	 *
	 * Uses a webcal-compatible URL served via a REST endpoint.
	 */
	public static function ics_download_url(int $event_id): string {
		return rest_url('cbnexus/v1/events/' . $event_id . '/ics');
	}

	/**
	 * Build the "Add to Calendar" HTML block for a single event.
	 */
	public static function build_calendar_block(object $event): string {
		$google  = esc_url(self::google_calendar_url($event));
		$outlook = esc_url(self::outlook_calendar_url($event));
		$ics     = esc_url(self::ics_download_url((int) $event->id));

		$link_style = 'display:inline-block;padding:6px 14px;font-size:13px;font-weight:600;'
			. 'text-decoration:none;border-radius:5px;border:1px solid #d1d5db;color:#374151;background:#ffffff;';

		return '<div style="margin:12px 0 4px;">'
			. '<span style="font-size:13px;color:#6b7280;margin-right:8px;">📆 Add to calendar:</span>'
			. '<a href="' . $google . '" target="_blank" style="' . $link_style . 'margin-right:6px;">Google</a>'
			. '<a href="' . $outlook . '" target="_blank" style="' . $link_style . 'margin-right:6px;">Outlook</a>'
			. '<a href="' . $ics . '" style="' . $link_style . '">iCal (.ics)</a>'
			. '</div>';
	}

	/**
	 * Format date+time for Google Calendar (YYYYMMDDTHHmmSSZ or YYYYMMDD for all-day).
	 */
	private static function calendar_datetime(string $date, string $time): string {
		if (empty($time)) {
			return gmdate('Ymd', strtotime($date));
		}
		$ts = strtotime($date . ' ' . $time);
		return gmdate('Ymd\THis\Z', $ts);
	}

	/**
	 * Format date+time as ISO 8601 for Outlook (or all-day fallback).
	 */
	private static function iso_datetime(string $date, string $time): string {
		if (empty($time)) {
			return gmdate('Y-m-d', strtotime($date));
		}
		$ts = strtotime($date . ' ' . $time);
		return gmdate('Y-m-d\TH:i:s\Z', $ts);
	}

	// ─── Shared Helpers ─────────────────────────────────────────────────

	/**
	 * Build HTML for a list of events (used by both digest and on-demand).
	 */
	private static function build_cost_block(object $event): string {
		$parts = [];
		if (!empty($event->cost))       { $parts[] = 'Members: ' . esc_html($event->cost); }
		if (!empty($event->guest_cost)) { $parts[] = 'Guests: ' . esc_html($event->guest_cost); }
		if (empty($parts)) { return ''; }
		return '<p style="margin:0 0 4px;font-size:14px;color:#4a5568;">💰 ' . implode(' &middot; ', $parts) . '</p>';
	}

	private static function build_events_html(array $events): string {
		// Audience display: label, badge background, badge text, card border-left color.
		$audience_styles = [
			'members' => ['label' => '🔒 Members Only',     'bg' => '#5b2d6e', 'color' => '#ffffff', 'border' => '#5b2d6e'],
			'all'     => ['label' => '👥 Members &amp; Guests', 'bg' => '#c49a3c', 'color' => '#ffffff', 'border' => '#c49a3c'],
			'public'  => ['label' => '🌐 Open to Everyone', 'bg' => '#2563eb', 'color' => '#ffffff', 'border' => '#2563eb'],
		];

		$html = '';
		foreach ($events as $e) {
			$date = date_i18n('l, F j', strtotime($e->event_date));
			$time = !empty($e->event_time) ? date_i18n('g:i A', strtotime($e->event_time)) : '';
			$location = $e->location ?: 'TBD';
			$aud = $audience_styles[$e->audience ?? 'all'] ?? $audience_styles['all'];

			$html .= '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-left:4px solid ' . $aud['border'] . ';border-radius:8px;padding:16px;margin:12px 0;">';
			$html .= '<table role="presentation" cellspacing="0" cellpadding="0" width="100%"><tr>';
			$html .= '<td><p style="margin:0 0 4px;font-size:16px;font-weight:600;color:#333;">' . esc_html($e->title) . '</p></td>';
			$html .= '<td style="text-align:right;vertical-align:top;"><span style="display:inline-block;background:' . $aud['bg'] . ';color:' . $aud['color'] . ';font-size:11px;font-weight:600;padding:3px 10px;border-radius:12px;white-space:nowrap;">' . $aud['label'] . '</span></td>';
			$html .= '</tr></table>';
			$html .= '<p style="margin:0 0 4px;font-size:14px;color:#4a5568;">📅 ' . esc_html($date);
			if ($time) { $html .= ' · 🕐 ' . esc_html($time); }
			$html .= '</p>';
			$html .= '<p style="margin:0 0 4px;font-size:14px;color:#4a5568;">📍 ' . esc_html($location) . '</p>';
			// Cost line — show member and/or guest cost if set.
			$cost_parts = [];
			if (!empty($e->cost))       { $cost_parts[] = 'Members: ' . esc_html($e->cost); }
			if (!empty($e->guest_cost)) { $cost_parts[] = 'Guests: ' . esc_html($e->guest_cost); }
			if (!empty($cost_parts)) {
				$html .= '<p style="margin:0 0 4px;font-size:14px;color:#4a5568;">💰 ' . implode(' · ', $cost_parts) . '</p>';
			}
			if (!empty($e->description)) {
				$html .= '<p style="margin:8px 0 0;font-size:14px;color:#666;line-height:1.5;">' . esc_html(wp_trim_words($e->description, 30)) . '</p>';
			}
			if (!empty($e->registration_url)) {
				$html .= '<p style="margin:8px 0 0;"><a href="' . esc_url($e->registration_url) . '" style="color:#5b2d6e;font-weight:600;font-size:14px;">Register →</a></p>';
			}
			$html .= self::build_calendar_block($e);
			$html .= '</div>';
		}
		return $html;
	}

	/**
	 * Send the digest email to all active members.
	 *
	 * @return int Number of members emailed.
	 */
	private static function send_digest_to_members(string $events_html, string $intro): int {
		$members = CBNexus_Member_Repository::get_all_members('active');
		$portal_url = class_exists('CBNexus_Portal_Router')
			? CBNexus_Portal_Router::get_portal_url() . '?section=events'
			: home_url();
		$count = 0;

		foreach ($members as $m) {
			$sent = CBNexus_Email_Service::send('events_digest', $m['user_email'], [
				'first_name'  => $m['first_name'],
				'intro_text'  => $intro,
				'events_list' => $events_html,
				'portal_url'  => $portal_url,
			], [
				'recipient_id' => (int) $m['user_id'],
				'related_type' => 'events_digest',
			]);
			if ($sent) { $count++; }
		}

		return $count;
	}
}