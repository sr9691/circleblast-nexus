<?php
/**
 * Events ICS API
 *
 * REST endpoint to serve .ics calendar files for individual events.
 * Used by "Add to Calendar → iCal" links in event emails.
 */

defined('ABSPATH') || exit;

final class CBNexus_Events_ICS_API {

	public static function init(): void {
		add_action('rest_api_init', [__CLASS__, 'register_routes']);
	}

	public static function register_routes(): void {
		register_rest_route('cbnexus/v1', '/events/(?P<id>\d+)/ics', [
			'methods'             => 'GET',
			'callback'            => [__CLASS__, 'serve_ics'],
			'permission_callback' => '__return_true', // Public — link is shared via email.
			'args'                => [
				'id' => [
					'validate_callback' => fn($val) => is_numeric($val) && $val > 0,
					'sanitize_callback' => 'absint',
				],
			],
		]);
	}

	/**
	 * Serve an ICS file for a single event.
	 */
	public static function serve_ics(\WP_REST_Request $request): void {
		$event = CBNexus_Event_Repository::get($request->get_param('id'));

		if (!$event || $event->status !== 'approved') {
			status_header(404);
			echo 'Event not found.';
			exit;
		}

		$ics = self::build_ics($event);

		// Filename: circleblast-event-slug-2026-02-20.ics
		$slug = sanitize_title($event->title);
		$filename = 'circleblast-' . $slug . '-' . $event->event_date . '.ics';

		header('Content-Type: text/calendar; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Cache-Control: no-cache, no-store, must-revalidate');

		echo $ics;
		exit;
	}

	/**
	 * Build an iCalendar (.ics) string for one event.
	 *
	 * @see https://www.rfc-editor.org/rfc/rfc5545
	 */
	private static function build_ics(object $event): string {
		$uid      = 'event-' . $event->id . '@' . wp_parse_url(home_url(), PHP_URL_HOST);
		$now      = gmdate('Ymd\THis\Z');
		$summary  = self::ics_escape($event->title);
		$desc     = self::ics_escape(wp_strip_all_tags($event->description ?? ''));
		$location = self::ics_escape($event->location ?? '');

		// Date/time handling.
		$has_time  = !empty($event->event_time);
		$dtstart   = self::ics_datetime($event->event_date, $event->event_time ?? '');
		$dtend     = self::ics_datetime(
			$event->end_date ?: $event->event_date,
			$event->end_time ?: ($has_time ? date('H:i', strtotime($event->event_time) + 3600) : '')
		);

		// For all-day events, use VALUE=DATE format.
		if ($has_time) {
			$dtstart_line = 'DTSTART:' . $dtstart;
			$dtend_line   = 'DTEND:' . $dtend;
		} else {
			$dtstart_line = 'DTSTART;VALUE=DATE:' . $dtstart;
			// All-day end date is exclusive in ICS, so add 1 day.
			$end_date = date('Ymd', strtotime($event->end_date ?: $event->event_date) + 86400);
			$dtend_line   = 'DTEND;VALUE=DATE:' . $end_date;
		}

		$url_line = '';
		if (!empty($event->registration_url)) {
			$url_line = 'URL:' . $event->registration_url . "\r\n";
		}

		$lines = [
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//CircleBlast//Nexus//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'BEGIN:VEVENT',
			'UID:' . $uid,
			'DTSTAMP:' . $now,
			$dtstart_line,
			$dtend_line,
			'SUMMARY:' . $summary,
			'DESCRIPTION:' . $desc,
			'LOCATION:' . $location,
		];

		if ($url_line) {
			$lines[] = rtrim($url_line);
		}

		$lines[] = 'STATUS:CONFIRMED';
		$lines[] = 'END:VEVENT';
		$lines[] = 'END:VCALENDAR';

		return implode("\r\n", $lines) . "\r\n";
	}

	/**
	 * Format a date (+optional time) for ICS.
	 */
	private static function ics_datetime(string $date, string $time): string {
		if (empty($time)) {
			return gmdate('Ymd', strtotime($date));
		}
		return gmdate('Ymd\THis\Z', strtotime($date . ' ' . $time));
	}

	/**
	 * Escape text for ICS fields (RFC 5545 §3.3.11).
	 */
	private static function ics_escape(string $text): string {
		$text = str_replace(['\\', ';', ','], ['\\\\', '\\;', '\\,'], $text);
		$text = str_replace(["\r\n", "\r", "\n"], '\\n', $text);
		return $text;
	}
}
