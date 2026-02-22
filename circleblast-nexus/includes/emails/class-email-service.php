<?php
/**
 * Email Service
 *
 * ITER-0005: Centralized email sender with template support and
 * automatic logging to cb_email_log. All plugin emails go through here.
 */

defined('ABSPATH') || exit;

final class CBNexus_Email_Service {

	/**
	 * Send an email using a template.
	 *
	 * @param string $template_id  Template identifier (e.g. 'welcome_member').
	 * @param string $to_email     Recipient email address.
	 * @param array  $vars         Template variables for placeholder replacement.
	 * @param array  $options      Optional: 'subject', 'recipient_id', 'related_id', 'related_type'.
	 * @return bool True if wp_mail reported success.
	 */
	public static function send(string $template_id, string $to_email, array $vars = [], array $options = []): bool {
		$template = self::load_template($template_id);

		if ($template === null) {
			self::log_email($to_email, $template_id, 'Template not found', 'failed', $options, 'Template "' . $template_id . '" not found.');
			return false;
		}

		// Inject color scheme variables so templates can use {{color_primary}}, {{color_secondary}}, etc.
		$colors = CBNexus_Color_Scheme::get_email_colors();
		$vars = array_merge([
			'color_primary'   => $colors['btn_primary'],
			'color_secondary' => $colors['secondary'],
			'color_accent'    => $colors['accent_text'],
			'color_green'     => $colors['green'],
			'color_blue'      => $colors['blue'],
			'logo_url'        => CBNexus_Color_Scheme::get_logo_url('email'),
		], $vars);

		$subject   = self::replace_placeholders($options['subject'] ?? $template['subject'], $vars);

		// Pre-process special HTML block variables.
		self::process_html_blocks($vars);

		$body      = self::replace_placeholders($template['body'], $vars);

		// Generate preferences URL for the email footer.
		$unsub_url = '';
		if (!empty($options['recipient_id']) && class_exists('CBNexus_Token_Service')) {
			$token = CBNexus_Token_Service::generate(
				(int) $options['recipient_id'],
				'manage_preferences', [], 90, true
			);
			$unsub_url = CBNexus_Token_Service::url($token);
		}

		$html_body = self::wrap_html($body, $subject, $template_id, $unsub_url);

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			self::get_from_header(),
		];

		$sent   = wp_mail($to_email, $subject, $html_body, $headers);
		$status = $sent ? 'sent' : 'failed';
		$error  = $sent ? null : 'wp_mail returned false.';

		self::log_email($to_email, $template_id, $subject, $status, $options, $error);

		if (class_exists('CBNexus_Logger')) {
			CBNexus_Logger::info('Email ' . $status . '.', [
				'template' => $template_id, 'recipient' => $to_email,
			]);
		}

		return $sent;
	}

	/**
	 * Build the From: header using saved settings (or defaults).
	 */
	public static function get_from_header(): string {
		$settings = self::get_sender_settings();
		return 'From: ' . $settings['from_name'] . ' <' . $settings['from_email'] . '>';
	}

	/**
	 * Get the saved sender settings.
	 */
	public static function get_sender_settings(): array {
		$saved = get_option('cbnexus_email_sender', []);
		return [
			'from_name'  => $saved['from_name']  ?? 'CircleBlast',
			'from_email' => $saved['from_email'] ?? 'noreply@circleblast.org',
		];
	}

	/**
	 * Save sender settings.
	 */
	public static function save_sender_settings(array $data): void {
		update_option('cbnexus_email_sender', $data, false);
	}

	/**
	 * Send a welcome email to a newly created member.
	 */
	public static function send_welcome(int $user_id, array $profile): bool {
		$to = $profile['user_email'] ?? '';
		if (empty($to)) {
			return false;
		}

		$reset_key = get_password_reset_key(get_userdata($user_id));
		$login_url = '';
		if (!is_wp_error($reset_key)) {
			$login_url = network_site_url("wp-login.php?action=rp&key={$reset_key}&login=" . rawurlencode($profile['user_email']), 'login');
		}

		$vars = [
			'first_name'   => $profile['first_name'] ?? '',
			'last_name'    => $profile['last_name'] ?? '',
			'display_name' => $profile['display_name'] ?? '',
			'email'        => $to,
			'company'      => $profile['cb_company'] ?? '',
			'login_url'    => $login_url,
			'site_url'     => home_url(),
			'site_name'    => get_bloginfo('name'),
		];

		return self::send('welcome_member', $to, $vars, [
			'recipient_id' => $user_id,
			'related_type' => 'member_creation',
		]);
	}

	/**
	 * Send a reactivation email to a member whose status changed to active.
	 */
	public static function send_reactivation(int $user_id, array $profile): bool {
		$to = $profile['user_email'] ?? '';
		if (empty($to)) {
			return false;
		}

		$reset_key = get_password_reset_key(get_userdata($user_id));
		$login_url = '';
		if (!is_wp_error($reset_key)) {
			$login_url = network_site_url("wp-login.php?action=rp&key={$reset_key}&login=" . rawurlencode($profile['user_email']), 'login');
		}

		$vars = [
			'first_name'   => $profile['first_name'] ?? '',
			'last_name'    => $profile['last_name'] ?? '',
			'display_name' => $profile['display_name'] ?? '',
			'email'        => $to,
			'company'      => $profile['cb_company'] ?? '',
			'login_url'    => $login_url,
			'site_url'     => home_url(),
			'site_name'    => get_bloginfo('name'),
		];

		return self::send('reactivation_member', $to, $vars, [
			'recipient_id' => $user_id,
			'related_type' => 'member_reactivation',
		]);
	}

	private static function load_template(string $template_id): ?array {
		// Check DB override from Admin Email Templates.
		if (class_exists('CBNexus_Admin_Email_Templates')) {
			$override = get_option('cbnexus_email_tpl_' . $template_id);
			if ($override && !empty($override['subject']) && !empty($override['body'])) {
				return $override;
			}
		}

		$file = CBNEXUS_PLUGIN_DIR . 'templates/emails/' . $template_id . '.php';
		if (!file_exists($file)) {
			return null;
		}
		$template = include $file;
		if (!is_array($template) || empty($template['subject']) || empty($template['body'])) {
			return null;
		}
		return $template;
	}

	private static function replace_placeholders(string $text, array $vars): string {
		foreach ($vars as $key => $value) {
			$text = str_replace('{{' . $key . '}}', (string) $value, $text);
		}
		return $text;
	}

	/**
	 * Pre-process variables that contain pre-rendered HTML blocks.
	 */
	private static function process_html_blocks(array &$vars): void {
		$colors = CBNexus_Color_Scheme::get_email_colors();
		$primary = esc_attr($colors['btn_primary']);
		$secondary = esc_attr($colors['secondary']);

		// Reminder notes block for event emails.
		if (!empty($vars['reminder_notes'])) {
			$vars['reminder_notes_block'] = '<div style="background:#fff7ed;border-left:3px solid ' . $secondary . ';padding:12px 16px;margin:16px 0;font-size:14px;">'
				. '<strong>📝 Notes:</strong> ' . esc_html($vars['reminder_notes']) . '</div>';
		} else {
			$vars['reminder_notes_block'] = '';
		}

		// Registration block.
		if (!empty($vars['registration_url'])) {
			$vars['registration_block'] = '<table role="presentation" cellspacing="0" cellpadding="0" style="margin:16px 0;">'
				. '<tr><td style="background-color:' . $primary . ';border-radius:6px;">'
				. '<a href="' . esc_url($vars['registration_url']) . '" style="display:inline-block;padding:12px 24px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:600;">Register →</a>'
				. '</td></tr></table>';
		} else {
			$vars['registration_block'] = '';
		}

		// Forward note block.
		if (!empty($vars['forward_note'])) {
			$vars['forward_note_block'] = '<div style="background:#f8fafc;border-left:3px solid ' . $primary . ';padding:12px 16px;margin:16px 0;font-style:italic;font-size:14px;color:#4a5568;">'
				. esc_html($vars['forward_note']) . '</div>';
		} else {
			$vars['forward_note_block'] = $vars['forward_note_block'] ?? '';
		}
	}

	/**
	 * Public wrapper for test emails from Admin Email Templates.
	 */
	public static function test_wrap(string $body, string $subject): string {
		return self::wrap_html($body, $subject, '', '');
	}

	private static function wrap_html(string $body, string $subject, string $template_id = '', string $unsub_url = ''): string {
		$year = gmdate('Y');
		$colors = CBNexus_Color_Scheme::get_email_colors();
		$logo_url = CBNexus_Color_Scheme::get_logo_url('email');
		$header_bg = esc_attr($colors['header_bg']);

		// Determine referral prompt type for this template.
		$referral_html = '';
		$prompt_type = 'none';
		if ($template_id !== '' && class_exists('CBNexus_Recruitment_Coverage_Service')) {
			$prompt_type = self::get_referral_prompt_type($template_id);
			if ($prompt_type === 'subtle') {
				$referral_html = CBNexus_Recruitment_Coverage_Service::get_footer_prompt_html();
			} elseif ($prompt_type === 'prominent') {
				$referral_html = CBNexus_Recruitment_Coverage_Service::get_prominent_prompt_html();
			}
		}

		// For prominent type, append to the body content.
		// For subtle type, insert as a separate row before the footer.
		$body_with_prominent = ($referral_html && $prompt_type === 'prominent')
			? $body . $referral_html
			: $body;

		$subtle_row = ($referral_html && $prompt_type === 'subtle')
			? $referral_html
			: '';

		return '<!DOCTYPE html>
<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . esc_html($subject) . '</title></head>
<body style="margin:0;padding:0;background-color:#f5f5f5;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f5f5f5;">
<tr><td align="center" style="padding:30px 15px;">
<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;">
<tr><td style="background-color:' . $header_bg . ';padding:24px 30px;text-align:center;">
<img src="' . esc_url($logo_url) . '" alt="CircleBlast" width="48" height="48" style="display:inline-block;vertical-align:middle;margin-right:10px;" />
<span style="display:inline-block;vertical-align:middle;color:#ffffff;font-size:22px;font-weight:600;">CircleBlast</span>
</td></tr>
<tr><td style="padding:30px;">' . $body_with_prominent . '</td></tr>
' . $subtle_row . '
<tr><td style="padding:20px 30px;background-color:#f8f9fa;text-align:center;font-size:13px;color:#6c757d;">
<p style="margin:0;">CircleBlast Professional Networking Group</p>
<p style="margin:5px 0 0;">&copy; ' . $year . ' CircleBlast. All rights reserved.</p>
' . ($unsub_url ? '<p style="margin:5px 0 0;"><a href="' . esc_url($unsub_url) . '" style="color:#6c757d;text-decoration:underline;font-size:13px;">Manage email preferences</a></p>' : '') . '
</td></tr></table></td></tr></table></body></html>';
	}

	/**
	 * Defaults for which templates get which referral prompt type.
	 */
	private static $default_referral_prompts = [
		'meeting_reminder'               => 'subtle',
		'meeting_notes_request'          => 'subtle',
		'event_reminder'                 => 'subtle',
		'event_submitted_confirmation'   => 'subtle',
		'events_digest'                  => 'prominent',
		'circleup_summary'               => 'prominent',
		'monthly_admin_report'           => 'prominent',
	];

	/**
	 * Get the referral prompt type for a given template.
	 *
	 * Checks admin overrides (stored in cbnexus_email_referral_prompts option),
	 * then falls back to built-in defaults.
	 *
	 * @return string 'subtle', 'prominent', or 'none'
	 */
	public static function get_referral_prompt_type(string $template_id): string {
		$overrides = get_option('cbnexus_email_referral_prompts', []);

		if (isset($overrides[$template_id])) {
			return $overrides[$template_id];
		}

		return self::$default_referral_prompts[$template_id] ?? 'none';
	}

	/**
	 * Get all template IDs with their referral prompt setting (for admin UI).
	 */
	public static function get_all_referral_prompt_settings(): array {
		$overrides = get_option('cbnexus_email_referral_prompts', []);
		$all = [];

		// Gather all known templates from the defaults + any overrides.
		$known = array_keys(self::$default_referral_prompts);
		foreach ($overrides as $tid => $type) {
			if (!in_array($tid, $known, true)) {
				$known[] = $tid;
			}
		}

		foreach ($known as $tid) {
			$all[$tid] = $overrides[$tid] ?? (self::$default_referral_prompts[$tid] ?? 'none');
		}

		return $all;
	}

	/**
	 * Save referral prompt settings from admin UI.
	 */
	public static function save_referral_prompt_settings(array $settings): void {
		$clean = [];
		foreach ($settings as $tid => $type) {
			$tid  = sanitize_key($tid);
			$type = in_array($type, ['subtle', 'prominent', 'none'], true) ? $type : 'none';

			// Only store if different from default (keeps option small).
			$default = self::$default_referral_prompts[$tid] ?? 'none';
			if ($type !== $default) {
				$clean[$tid] = $type;
			}
		}
		update_option('cbnexus_email_referral_prompts', $clean, false);
	}

	private static function log_email(string $to_email, string $template_id, string $subject, string $status, array $options, ?string $error = null): void {
		global $wpdb;
		$table = $wpdb->prefix . 'cb_email_log';
		$found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
		if ($found !== $table) {
			return;
		}

		$recipient_id = isset($options['recipient_id']) ? (int) $options['recipient_id'] : null;
		$related_id   = isset($options['related_id']) ? (int) $options['related_id'] : null;

		$data = [
			'recipient_id'    => $recipient_id,
			'recipient_email' => substr($to_email, 0, 200),
			'template_id'     => substr($template_id, 0, 50),
			'subject'         => substr($subject, 0, 255),
			'status'          => substr($status, 0, 20),
			'related_id'      => $related_id,
			'related_type'    => isset($options['related_type']) ? substr($options['related_type'], 0, 50) : null,
			'error_message'   => $error,
			'sent_at_gmt'     => gmdate('Y-m-d H:i:s'),
		];

		// Build format array dynamically — %d for non-null ints, omit for nulls
		// so wpdb produces SQL NULL instead of casting null to 0.
		$format = [
			$recipient_id !== null ? '%d' : '%s', // recipient_id — null passed as %s produces NULL
			'%s', '%s', '%s', '%s',
			$related_id !== null ? '%d' : '%s', // related_id
			'%s', '%s', '%s',
		];

		$wpdb->insert($table, $data, $format);
	}
}