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

		// Pre-process special HTML blocks before placeholder replacement.
		$vars = self::process_html_blocks($vars);

		$subject   = self::replace_placeholders($options['subject'] ?? $template['subject'], $vars);
		$body      = self::replace_placeholders($template['body'], $vars);
		$html_body = self::wrap_html($body, $subject);

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: CircleBlast <noreply@circleblast.org>',
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

	private static function load_template(string $template_id): ?array {
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
	 * Pre-process special HTML block variables.
	 * These contain pre-rendered HTML and should not be escaped.
	 */
	private static function process_html_blocks(array $vars): array {
		// Handle forward_note_block: render personal note if present.
		if (isset($vars['forward_note']) && !empty($vars['forward_note'])) {
			$vars['forward_note_block'] = '<div style="background:#f8fafc;border-left:3px solid #5b2d6e;padding:12px 16px;margin:16px 0;font-style:italic;font-size:14px;color:#4a5568;">' . esc_html($vars['forward_note']) . '</div>';
		} else {
			$vars['forward_note_block'] = '';
		}

		return $vars;
	}

	private static function wrap_html(string $body, string $subject): string {
		$year = gmdate('Y');
		return '<!DOCTYPE html>
<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . esc_html($subject) . '</title></head>
<body style="margin:0;padding:0;background-color:#f5f5f5;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f5f5f5;">
<tr><td align="center" style="padding:30px 15px;">
<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;">
<tr><td style="background-color:#1a365d;padding:24px 30px;text-align:center;">
<h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:600;">CircleBlast</h1>
</td></tr>
<tr><td style="padding:30px;">' . $body . '</td></tr>
<tr><td style="padding:20px 30px;background-color:#f8f9fa;text-align:center;font-size:13px;color:#6c757d;">
<p style="margin:0;">CircleBlast Professional Networking Group</p>
<p style="margin:5px 0 0;">&copy; ' . $year . ' CircleBlast. All rights reserved.</p>
</td></tr></table></td></tr></table></body></html>';
	}

	private static function log_email(string $to_email, string $template_id, string $subject, string $status, array $options, ?string $error = null): void {
		global $wpdb;
		$table = $wpdb->prefix . 'cb_email_log';
		$found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
		if ($found !== $table) {
			return;
		}
		$wpdb->insert($table, [
			'recipient_id'    => $options['recipient_id'] ?? null,
			'recipient_email' => substr($to_email, 0, 200),
			'template_id'     => substr($template_id, 0, 50),
			'subject'         => substr($subject, 0, 255),
			'status'          => substr($status, 0, 20),
			'related_id'      => $options['related_id'] ?? null,
			'related_type'    => isset($options['related_type']) ? substr($options['related_type'], 0, 50) : null,
			'error_message'   => $error,
			'sent_at_gmt'     => gmdate('Y-m-d H:i:s'),
		], ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']);
	}
}
