<?php
/**
 * Referral Email Parser
 *
 * Monitors a dedicated BCC inbox via IMAP. Members BCC the configured
 * address when sending referral emails, and the system auto-logs a
 * journal entry for the sender.
 *
 * Setup: configure IMAP credentials in the portal Settings tab under
 * "Referral Email Inbox". Add define('CBNEXUS_REFERRAL_IMAP_PASS', '...')
 * to wp-config.php (password never stored in DB).
 *
 * Parsing logic:
 *  1. From: header → matched against member email addresses → logger
 *  2. Body scanned for The Circle member names → recipient hint
 *  3. Subject line extracted as referral description
 *  4. Creates a referral_given journal entry for the sender
 *  5. Optionally creates a mirrored referral_received entry for the recipient
 */

defined('ABSPATH') || exit;

final class CBNexus_Referral_Email_Parser {

	/** Option key for non-sensitive IMAP settings. */
	const OPTION_KEY = 'cbnexus_referral_inbox';

	/** Cron hook. */
	const CRON_HOOK = 'cbnexus_referral_inbox_poll';

	// ─── Init ──────────────────────────────────────────────────────────

	public static function init(): void {
		add_action(self::CRON_HOOK, [__CLASS__, 'poll_inbox']);
	}

	/**
	 * Schedule or reschedule the polling cron based on current settings.
	 * Called on settings save.
	 */
	public static function reschedule(): void {
		$settings = self::get_settings();

		// Clear existing schedule.
		$ts = wp_next_scheduled(self::CRON_HOOK);
		if ($ts) {
			wp_unschedule_event($ts, self::CRON_HOOK);
		}

		if (empty($settings['enabled']) || empty($settings['imap_host']) || empty($settings['imap_user'])) {
			return;
		}

		$freq = $settings['poll_frequency'] ?? 'hourly';
		if (!wp_next_scheduled(self::CRON_HOOK)) {
			wp_schedule_event(time(), $freq, self::CRON_HOOK);
		}
	}

	// ─── Settings ──────────────────────────────────────────────────────

	/**
	 * Get IMAP settings (non-sensitive only — password comes from wp-config.php).
	 */
	public static function get_settings(): array {
		$defaults = [
			'enabled'        => false,
			'imap_host'      => '',
			'imap_port'      => 993,
			'imap_user'      => '',
			'imap_ssl'       => true,
			'imap_folder'    => 'INBOX',
			'poll_frequency' => 'hourly',
			'bcc_address'    => '',
			'auto_mirror'    => true, // Also log referral_received for the recipient member.
			'delete_after'   => false, // Delete/move messages after processing.
		];
		return wp_parse_args(get_option(self::OPTION_KEY, []), $defaults);
	}

	/**
	 * Save non-sensitive IMAP settings.
	 */
	public static function save_settings(array $data): void {
		$settings = [
			'enabled'        => !empty($data['enabled']),
			'imap_host'      => sanitize_text_field($data['imap_host'] ?? ''),
			'imap_port'      => absint($data['imap_port'] ?? 993),
			'imap_user'      => sanitize_text_field($data['imap_user'] ?? ''),
			'imap_ssl'       => !empty($data['imap_ssl']),
			'imap_folder'    => sanitize_text_field($data['imap_folder'] ?? 'INBOX'),
			'poll_frequency' => sanitize_key($data['poll_frequency'] ?? 'hourly'),
			'bcc_address'    => sanitize_email($data['bcc_address'] ?? ''),
			'auto_mirror'    => !empty($data['auto_mirror']),
			'delete_after'   => !empty($data['delete_after']),
		];
		update_option(self::OPTION_KEY, $settings);
		self::reschedule();
	}

	// ─── Polling ───────────────────────────────────────────────────────

	/**
	 * Main cron callback: connect to inbox, process new messages.
	 */
	public static function poll_inbox(): void {
		if (!extension_loaded('imap')) {
			CBNexus_Logger::warning('Referral inbox polling skipped: PHP IMAP extension not loaded.', [], 'referral_parser');
			return;
		}

		$settings = self::get_settings();
		if (empty($settings['enabled']) || empty($settings['imap_host']) || empty($settings['imap_user'])) {
			return;
		}

		// Password must be defined in wp-config.php — never stored in DB.
		if (!defined('CBNEXUS_REFERRAL_IMAP_PASS')) {
			CBNexus_Logger::warning('Referral inbox polling skipped: CBNEXUS_REFERRAL_IMAP_PASS not defined in wp-config.php.', [], 'referral_parser');
			return;
		}

		$ssl_flag = $settings['imap_ssl'] ? '/ssl' : '/notls';
		$mailbox  = sprintf(
			'{%s:%d/imap%s}%s',
			$settings['imap_host'],
			$settings['imap_port'],
			$ssl_flag,
			$settings['imap_folder']
		);

		$imap = @imap_open($mailbox, $settings['imap_user'], CBNEXUS_REFERRAL_IMAP_PASS);
		if (!$imap) {
			CBNexus_Logger::error('Referral inbox: IMAP connection failed. ' . imap_last_error(), [], 'referral_parser');
			return;
		}

		// Only fetch UNSEEN messages.
		$uids = imap_search($imap, 'UNSEEN');
		if (!$uids) {
			imap_close($imap);
			return;
		}

		$processed = 0;
		foreach ($uids as $uid) {
			try {
				$result = self::process_message($imap, $uid, $settings);
				if ($result) { $processed++; }
			} catch (Exception $e) {
				CBNexus_Logger::error('Referral parser error on message ' . $uid . ': ' . $e->getMessage(), [], 'referral_parser');
			}
		}

		imap_close($imap);

		if ($processed > 0) {
			CBNexus_Logger::info("Referral inbox: processed {$processed} message(s).", [], 'referral_parser');
		}
	}

	// ─── Message Processing ────────────────────────────────────────────

	/**
	 * Process a single IMAP message. Returns true if a journal entry was created.
	 */
	private static function process_message($imap, int $uid, array $settings): bool {
		$header  = imap_headerinfo($imap, $uid);
		$body    = self::get_body($imap, $uid);

		// Mark as seen.
		imap_setflag_full($imap, (string) $uid, '\\Seen');

		// ── Identify the sender ──────────────────────────────────────
		$from_email = strtolower(trim($header->from[0]->mailbox . '@' . $header->from[0]->host));
		$sender_uid = self::find_member_by_email($from_email);

		if (!$sender_uid) {
			// Not a member of The Circle — skip but mark seen.
			CBNexus_Logger::info("Referral inbox: message from {$from_email} — no matching member, skipping.", [], 'referral_parser');
			return false;
		}

		// ── Extract subject as referral description ──────────────────
		$subject = isset($header->subject) ? self::decode_header($header->subject) : '(no subject)';
		$subject = wp_strip_all_tags($subject);

		// ── Identify TO/CC recipients that are members ────────────────
		$recipient_uid = self::find_member_in_recipients($header);

		// ── Build context string ──────────────────────────────────────
		$context_parts = [];

		// Try to find a referred person's name in the body (first proper-noun-ish line).
		$prospect_name = self::extract_prospect_name($body);
		if ($prospect_name) {
			$context_parts[] = 'Person referred: ' . $prospect_name;
		}

		if ($recipient_uid) {
			$rp = CBNexus_Member_Repository::get_profile($recipient_uid);
			if ($rp) {
				$context_parts[] = 'Referred to: ' . $rp['display_name'];
			}
		}

		$context_parts[] = 'Via: BCC referral email';

		// ── Create journal entry for sender (referral_given) ──────────
		$entry_id = CBNexus_Journal_Repository::create($sender_uid, [
			'entry_type' => 'referral_given',
			'content'    => $subject,
			'context'    => implode(' · ', $context_parts),
			'entry_date' => gmdate('Y-m-d'),
			'visibility' => 'private',
		]);

		if (!$entry_id) {
			CBNexus_Logger::error("Referral inbox: failed to create journal entry for member {$sender_uid}.", [], 'referral_parser');
			return false;
		}

		// ── Mirror as referral_received for recipient member ──────────
		if ($settings['auto_mirror'] && $recipient_uid) {
			$sender_profile = CBNexus_Member_Repository::get_profile($sender_uid);
			$mirror_context = 'Referred by: ' . ($sender_profile['display_name'] ?? 'a member');
			if ($prospect_name) { $mirror_context .= ' · Prospect: ' . $prospect_name; }
			$mirror_context .= ' · Via: BCC referral email';

			CBNexus_Journal_Repository::create($recipient_uid, [
				'entry_type' => 'referral_received',
				'content'    => $subject,
				'context'    => $mirror_context,
				'entry_date' => gmdate('Y-m-d'),
				'visibility' => 'private',
			]);
		}

		// ── Optionally delete the message ─────────────────────────────
		if ($settings['delete_after']) {
			imap_delete($imap, (string) $uid);
		}

		return true;
	}

	// ─── Helpers ───────────────────────────────────────────────────────

	/**
	 * Find a member user ID by email address.
	 */
	private static function find_member_by_email(string $email): int {
		$user = get_user_by('email', $email);
		if (!$user) { return 0; }
		return CBNexus_Member_Repository::is_member($user->ID) ? $user->ID : 0;
	}

	/**
	 * Check To + CC recipients and return first one that is a CB member.
	 */
	private static function find_member_in_recipients(object $header): int {
		$pools = [];
		if (!empty($header->to))  { $pools = array_merge($pools, (array) $header->to); }
		if (!empty($header->cc))  { $pools = array_merge($pools, (array) $header->cc); }

		foreach ($pools as $addr) {
			$email = strtolower(trim(($addr->mailbox ?? '') . '@' . ($addr->host ?? '')));
			$uid   = self::find_member_by_email($email);
			if ($uid) { return $uid; }
		}
		return 0;
	}

	/**
	 * Decode a potentially MIME-encoded header value.
	 */
	private static function decode_header(string $value): string {
		$decoded = imap_mime_header_decode($value);
		$result  = '';
		foreach ($decoded as $part) {
			$result .= $part->text;
		}
		return $result;
	}

	/**
	 * Get plain text body of a message.
	 */
	private static function get_body($imap, int $uid): string {
		$structure = imap_fetchstructure($imap, $uid);

		// Multipart: look for text/plain part.
		if (!empty($structure->parts)) {
			foreach ($structure->parts as $i => $part) {
				if ($part->subtype === 'PLAIN') {
					$raw = imap_fetchbody($imap, $uid, (string)($i + 1));
					return self::decode_body($raw, $part->encoding);
				}
			}
		}

		// Simple single-part message.
		$raw = imap_body($imap, $uid);
		return self::decode_body($raw, $structure->encoding ?? 0);
	}

	/**
	 * Decode body based on IMAP encoding constant.
	 */
	private static function decode_body(string $raw, int $encoding): string {
		switch ($encoding) {
			case 3: return base64_decode($raw);           // BASE64
			case 4: return quoted_printable_decode($raw); // QUOTED_PRINTABLE
			default: return $raw;
		}
	}

	/**
	 * Heuristically extract a prospect name from the email body.
	 * Looks for lines like "Name: John Smith" or the first non-boilerplate line.
	 */
	private static function extract_prospect_name(string $body): string {
		$body = wp_strip_all_tags($body);
		$lines = array_filter(array_map('trim', explode("\n", $body)));

		foreach ($lines as $line) {
			// "Name: ..." pattern
			if (preg_match('/^(?:name|prospect|person|contact)\s*[:\-]\s*(.+)$/i', $line, $m)) {
				return sanitize_text_field($m[1]);
			}
		}

		// Fallback: first short line that looks like a name (2 words, no URL/email).
		foreach ($lines as $line) {
			if (strlen($line) < 60 && !str_contains($line, '@') && !str_contains($line, 'http')
				&& preg_match('/^[A-Z][a-z]+ [A-Z][a-z]+/', $line)) {
				return sanitize_text_field($line);
			}
		}

		return '';
	}
}
