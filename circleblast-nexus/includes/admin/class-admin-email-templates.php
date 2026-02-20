<?php
/**
 * Admin Email Templates
 *
 * Manage all plugin email templates from wp-admin.
 * Edits are stored in wp_options and override the file-based defaults.
 * Includes preview and send-test functionality.
 */

defined('ABSPATH') || exit;

final class CBNexus_Admin_Email_Templates {

	const OPTION_PREFIX = 'cbnexus_email_tpl_';

	private static $templates = [
		'welcome_member'         => ['name' => 'Welcome New Member',         'group' => 'Members'],
		'meeting_request_received' => ['name' => 'Meeting Request Received', 'group' => '1:1 Meetings'],
		'meeting_request_sent'   => ['name' => 'Meeting Request Sent',       'group' => '1:1 Meetings'],
		'meeting_accepted'       => ['name' => 'Meeting Accepted',           'group' => '1:1 Meetings'],
		'meeting_declined'       => ['name' => 'Meeting Declined',           'group' => '1:1 Meetings'],
		'meeting_reminder'       => ['name' => 'Meeting Reminder',           'group' => '1:1 Meetings'],
		'meeting_notes_request'  => ['name' => 'Notes Request',              'group' => '1:1 Meetings'],
		'suggestion_match'       => ['name' => 'Monthly Match',              'group' => 'Matching'],
		'suggestion_reminder'    => ['name' => 'Match Reminder',             'group' => 'Matching'],
		'circleup_summary'       => ['name' => 'CircleUp Recap',             'group' => 'CircleUp'],
		'circleup_forward'       => ['name' => 'CircleUp Forward',           'group' => 'CircleUp'],
		'event_reminder'         => ['name' => 'Event Reminder',             'group' => 'Events'],
		'events_digest'          => ['name' => 'Events Digest',              'group' => 'Events'],
		'event_pending'          => ['name' => 'Event Pending Approval',     'group' => 'Events'],
		'monthly_admin_report'   => ['name' => 'Monthly Admin Report',       'group' => 'Admin'],
		'recruitment_categories' => ['name' => 'Recruitment Categories',     'group' => 'Recruitment'],
	];

	public static function init(): void {
		add_action('admin_menu', [__CLASS__, 'register_menu']);
		add_action('admin_init', [__CLASS__, 'handle_actions']);
	}

	public static function register_menu(): void {
		add_submenu_page(
			'cbnexus-members',
			__('Email Templates', 'circleblast-nexus'),
			__('Email Templates', 'circleblast-nexus'),
			'cbnexus_manage_members',
			'cbnexus-email-templates',
			[__CLASS__, 'render_page']
		);
	}

	public static function handle_actions(): void {
		if (isset($_POST['cbnexus_save_template'])) { self::handle_save(); }
		if (isset($_POST['cbnexus_test_template'])) { self::handle_test(); }
		if (isset($_GET['cbnexus_reset_template'])) { self::handle_reset(); }
	}

	// ─── Save / Reset / Test ───────────────────────────────────────────

	private static function handle_save(): void {
		check_admin_referer('cbnexus_save_email_template');
		if (!current_user_can('cbnexus_manage_members')) { wp_die('Permission denied.'); }

		$tpl_id = sanitize_key($_POST['template_id'] ?? '');
		if (!isset(self::$templates[$tpl_id])) { return; }

		$data = [
			'subject' => sanitize_text_field(wp_unslash($_POST['subject'] ?? '')),
			'body'    => wp_kses_post(wp_unslash($_POST['body'] ?? '')),
		];

		update_option(self::OPTION_PREFIX . $tpl_id, $data);

		wp_safe_redirect(admin_url('admin.php?page=cbnexus-email-templates&edit=' . $tpl_id . '&cbnexus_notice=saved'));
		exit;
	}

	private static function handle_reset(): void {
		$tpl_id = sanitize_key($_GET['cbnexus_reset_template'] ?? '');
		if (!wp_verify_nonce(wp_unslash($_GET['_wpnonce'] ?? ''), 'cbnexus_reset_' . $tpl_id)) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }

		delete_option(self::OPTION_PREFIX . $tpl_id);

		wp_safe_redirect(admin_url('admin.php?page=cbnexus-email-templates&edit=' . $tpl_id . '&cbnexus_notice=reset'));
		exit;
	}

	private static function handle_test(): void {
		check_admin_referer('cbnexus_save_email_template');
		if (!current_user_can('cbnexus_manage_members')) { wp_die('Permission denied.'); }

		$tpl_id = sanitize_key($_POST['template_id'] ?? '');
		$email  = sanitize_email($_POST['test_email'] ?? '');

		if (!$email || !isset(self::$templates[$tpl_id])) { return; }

		$tpl = self::get_template_content($tpl_id);
		$vars = self::get_sample_vars();
		$subject = self::replace_vars($tpl['subject'], $vars);
		$body    = self::replace_vars($tpl['body'], $vars);

		$html = CBNexus_Email_Service::test_wrap($body, $subject);
		$headers = ['Content-Type: text/html; charset=UTF-8', CBNexus_Email_Service::get_from_header()];
		wp_mail($email, '[TEST] ' . $subject, $html, $headers);

		wp_safe_redirect(admin_url('admin.php?page=cbnexus-email-templates&edit=' . $tpl_id . '&cbnexus_notice=test_sent'));
		exit;
	}

	// ─── Template Loading ──────────────────────────────────────────────

	/**
	 * Get template content — DB override takes precedence over file.
	 * Public so the Email Service can call this.
	 */
	public static function get_template_content(string $tpl_id): array {
		// Check DB override first.
		$override = get_option(self::OPTION_PREFIX . $tpl_id);
		if ($override && !empty($override['subject']) && !empty($override['body'])) {
			return $override;
		}

		// Fall back to file.
		$file = CBNEXUS_PLUGIN_DIR . 'templates/emails/' . $tpl_id . '.php';
		if (file_exists($file)) {
			$tpl = include $file;
			if (is_array($tpl) && !empty($tpl['subject']) && !empty($tpl['body'])) {
				return $tpl;
			}
		}

		return ['subject' => '(Template not found)', 'body' => ''];
	}

	/**
	 * Check if a template has a DB override.
	 */
	public static function has_override(string $tpl_id): bool {
		return (bool) get_option(self::OPTION_PREFIX . $tpl_id);
	}

	// ─── Render ────────────────────────────────────────────────────────

	public static function render_page(): void {
		$editing = sanitize_key($_GET['edit'] ?? '');
		$notice  = sanitize_key($_GET['cbnexus_notice'] ?? '');

		if ($editing && isset(self::$templates[$editing])) {
			self::render_editor($editing, $notice);
			return;
		}

		self::render_list($notice);
	}

	private static function render_list(string $notice): void {
		$notices = [
			'saved'     => __('Template saved.', 'circleblast-nexus'),
			'reset'     => __('Template reset to default.', 'circleblast-nexus'),
			'test_sent' => __('Test email sent.', 'circleblast-nexus'),
		];
		?>
		<div class="wrap">
			<h1><?php esc_html_e('Email Templates', 'circleblast-nexus'); ?></h1>

			<?php if (isset($notices[$notice])) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html($notices[$notice]); ?></p></div>
			<?php endif; ?>

			<p><?php esc_html_e('Manage all automated email templates. Edits override the built-in defaults. Use {{placeholder}} syntax for dynamic content.', 'circleblast-nexus'); ?></p>

			<table class="wp-list-table widefat fixed striped">
				<thead><tr><th><?php esc_html_e('Template', 'circleblast-nexus'); ?></th><th><?php esc_html_e('Group', 'circleblast-nexus'); ?></th><th><?php esc_html_e('Subject', 'circleblast-nexus'); ?></th><th><?php esc_html_e('Status', 'circleblast-nexus'); ?></th><th><?php esc_html_e('Actions', 'circleblast-nexus'); ?></th></tr></thead>
				<tbody>
				<?php foreach (self::$templates as $id => $meta) :
					$tpl = self::get_template_content($id);
					$customized = self::has_override($id);
				?>
					<tr>
						<td><strong><?php echo esc_html($meta['name']); ?></strong><br/><code style="font-size:11px;"><?php echo esc_html($id); ?></code></td>
						<td><?php echo esc_html($meta['group']); ?></td>
						<td><?php echo esc_html($tpl['subject']); ?></td>
						<td><?php echo $customized ? '<span style="color:#059669;font-weight:600;">Customized</span>' : '<span style="color:#666;">Default</span>'; ?></td>
						<td><a href="<?php echo esc_url(admin_url('admin.php?page=cbnexus-email-templates&edit=' . $id)); ?>" class="button button-small"><?php esc_html_e('Edit', 'circleblast-nexus'); ?></a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private static function render_editor(string $tpl_id, string $notice): void {
		$meta = self::$templates[$tpl_id];
		$tpl  = self::get_template_content($tpl_id);
		$customized = self::has_override($tpl_id);
		$notices = [
			'saved'     => __('Template saved.', 'circleblast-nexus'),
			'reset'     => __('Template reset to default.', 'circleblast-nexus'),
			'test_sent' => __('Test email sent.', 'circleblast-nexus'),
		];
		$placeholders = ['first_name', 'last_name', 'display_name', 'email', 'company', 'site_url', 'site_name', 'login_url'];

		wp_enqueue_script(
			'cbnexus-email-editor',
			CBNEXUS_PLUGIN_URL . 'assets/js/email-editor.js',
			[],
			CBNEXUS_VERSION,
			true
		);
		?>
		<div class="wrap">
			<h1><a href="<?php echo esc_url(admin_url('admin.php?page=cbnexus-email-templates')); ?>">← <?php esc_html_e('Templates', 'circleblast-nexus'); ?></a> / <?php echo esc_html($meta['name']); ?></h1>

			<?php if (isset($notices[$notice])) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html($notices[$notice]); ?></p></div>
			<?php endif; ?>

			<form method="post" style="max-width:800px;">
				<?php wp_nonce_field('cbnexus_save_email_template'); ?>
				<input type="hidden" name="template_id" value="<?php echo esc_attr($tpl_id); ?>" />

				<table class="form-table">
					<tr><th><label><?php esc_html_e('Subject', 'circleblast-nexus'); ?></label></th>
						<td><input type="text" name="subject" value="<?php echo esc_attr($tpl['subject']); ?>" class="large-text" /></td></tr>
					<tr><th><label><?php esc_html_e('Body', 'circleblast-nexus'); ?></label></th>
						<td>
							<div id="cbnexus-email-editor">
								<!-- Editor mode tabs -->
								<div class="cbnexus-rte-tabs">
									<a href="#" data-rte-tab="visual" class="cbnexus-rte-tab active">Visual</a>
									<a href="#" data-rte-tab="html" class="cbnexus-rte-tab">HTML</a>
								</div>

								<!-- Formatting toolbar -->
								<div class="cbnexus-rte-toolbar">
									<button type="button" data-cmd="bold" title="Bold"><strong>B</strong></button>
									<button type="button" data-cmd="italic" title="Italic"><em>I</em></button>
									<button type="button" data-cmd="underline" title="Underline"><u>U</u></button>
									<span class="cbnexus-rte-sep"></span>
									<button type="button" data-cmd="formatBlock" data-val="<h2>" title="Heading">H</button>
									<button type="button" data-cmd="formatBlock" data-val="<h3>" title="Subheading">H<small>2</small></button>
									<button type="button" data-cmd="formatBlock" data-val="<p>" title="Paragraph">¶</button>
									<span class="cbnexus-rte-sep"></span>
									<button type="button" data-cmd="insertUnorderedList" title="Bullet List">• List</button>
									<button type="button" data-cmd="insertOrderedList" title="Numbered List">1. List</button>
									<span class="cbnexus-rte-sep"></span>
									<button type="button" data-cmd="createLink" title="Insert Link">🔗 Link</button>
									<button type="button" data-cmd="unlink" title="Remove Link">Unlink</button>
									<span class="cbnexus-rte-sep"></span>
									<button type="button" data-cmd="removeFormat" title="Clear Formatting">✕ Clear</button>
								</div>

								<!-- Visual editor -->
								<div class="cbnexus-rte-visual" contenteditable="true"><?php echo wp_kses_post($tpl['body']); ?></div>

								<!-- HTML editor (hidden by default) -->
								<textarea class="cbnexus-rte-html" rows="18" style="display:none;"><?php echo esc_textarea($tpl['body']); ?></textarea>

								<!-- Hidden textarea for form submission -->
								<textarea name="body" style="display:none!important;"><?php echo esc_textarea($tpl['body']); ?></textarea>

								<!-- Placeholder insertion -->
								<div class="cbnexus-rte-placeholders">
									<span class="description">Insert placeholder:</span>
									<?php foreach ($placeholders as $ph) : ?>
										<button type="button" class="cbnexus-rte-placeholder-btn" data-placeholder="<?php echo esc_attr($ph); ?>">{{<?php echo esc_html($ph); ?>}}</button>
									<?php endforeach; ?>
								</div>

								<style>
								.cbnexus-rte-tabs { display:flex; gap:0; margin-bottom:0; }
								.cbnexus-rte-tab { padding:6px 16px; font-size:13px; font-weight:600; text-decoration:none; border:1px solid #c3c4c7; border-bottom:none; border-radius:4px 4px 0 0; background:#f0f0f1; color:#50575e; cursor:pointer; }
								.cbnexus-rte-tab.active { background:#fff; color:#1d2327; border-bottom-color:#fff; position:relative; z-index:1; }
								.cbnexus-rte-toolbar { display:flex; flex-wrap:wrap; gap:2px; padding:6px 8px; background:#f0f0f1; border:1px solid #c3c4c7; border-top:none; }
								.cbnexus-rte-toolbar button { padding:4px 8px; font-size:13px; background:#fff; border:1px solid #c3c4c7; border-radius:3px; cursor:pointer; color:#50575e; line-height:1.4; }
								.cbnexus-rte-toolbar button:hover { background:#f0f0f1; border-color:#8c8f94; color:#1d2327; }
								.cbnexus-rte-sep { width:1px; background:#dcdcde; margin:2px 4px; }
								.cbnexus-rte-visual { min-height:280px; max-height:500px; overflow-y:auto; padding:14px 16px; border:1px solid #c3c4c7; border-top:none; background:#fff; font-size:14px; line-height:1.7; color:#1d2327; outline:none; border-radius:0 0 4px 4px; }
								.cbnexus-rte-visual:focus { border-color:#2271b1; box-shadow:0 0 0 1px #2271b1; }
								.cbnexus-rte-visual h2, .cbnexus-rte-visual h3 { margin:12px 0 4px; }
								.cbnexus-rte-visual p { margin:0 0 8px; }
								.cbnexus-rte-visual a { color:#2271b1; }
								.cbnexus-rte-visual ul, .cbnexus-rte-visual ol { margin:0 0 8px 20px; }
								.cbnexus-rte-html { width:100%; font-family:Consolas, Monaco, monospace; font-size:13px; line-height:1.6; padding:14px 16px; border:1px solid #c3c4c7; border-top:none; border-radius:0 0 4px 4px; resize:vertical; }
								.cbnexus-rte-html:focus { border-color:#2271b1; outline:none; box-shadow:0 0 0 1px #2271b1; }
								.cbnexus-rte-placeholders { display:flex; flex-wrap:wrap; gap:4px; align-items:center; margin-top:8px; }
								.cbnexus-rte-placeholder-btn { padding:2px 8px; font-size:11px; font-family:Consolas, Monaco, monospace; background:#f0f0f1; border:1px solid #c3c4c7; border-radius:3px; cursor:pointer; color:#50575e; }
								.cbnexus-rte-placeholder-btn:hover { background:#dcdcde; border-color:#8c8f94; color:#1d2327; }
								</style>
							</div>
						</td></tr>
					<tr><th><label><?php esc_html_e('Send Test To', 'circleblast-nexus'); ?></label></th>
						<td><div style="display:flex;gap:8px;">
							<input type="email" name="test_email" value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>" class="regular-text" />
							<button type="submit" name="cbnexus_test_template" value="1" class="button"><?php esc_html_e('Send Test', 'circleblast-nexus'); ?></button>
						</div></td></tr>
				</table>

				<p>
					<button type="submit" name="cbnexus_save_template" value="1" class="button button-primary"><?php esc_html_e('Save Template', 'circleblast-nexus'); ?></button>
					<?php if ($customized) : ?>
						<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cbnexus-email-templates&cbnexus_reset_template=' . $tpl_id), 'cbnexus_reset_' . $tpl_id)); ?>" class="button" onclick="return confirm('Reset to default? Your customizations will be lost.');"><?php esc_html_e('Reset to Default', 'circleblast-nexus'); ?></a>
					<?php endif; ?>
				</p>
			</form>

			<div style="margin-top:24px;padding:16px;background:#f0f0f1;border-radius:4px;">
				<h3 style="margin-top:0;"><?php esc_html_e('Available Placeholders', 'circleblast-nexus'); ?></h3>
				<p><code>{{first_name}}</code> <code>{{last_name}}</code> <code>{{display_name}}</code> <code>{{email}}</code> <code>{{company}}</code> <code>{{site_url}}</code> <code>{{site_name}}</code> <code>{{login_url}}</code></p>
				<p><?php esc_html_e('Template-specific placeholders depend on the email type. Check the default template for available variables.', 'circleblast-nexus'); ?></p>
			</div>
		</div>
		<?php
	}

	// ─── Helpers ────────────────────────────────────────────────────────

	private static function replace_vars(string $text, array $vars): string {
		foreach ($vars as $k => $v) {
			$text = str_replace('{{' . $k . '}}', (string) $v, $text);
		}
		// Remove any remaining unreplaced placeholders.
		return preg_replace('/\{\{[a-z_]+\}\}/', '', $text);
	}

	private static function get_sample_vars(): array {
		return [
			'first_name'       => 'Alex',
			'last_name'        => 'Johnson',
			'display_name'     => 'Alex Johnson',
			'email'            => 'alex@example.com',
			'company'          => 'Acme Corp',
			'site_url'         => home_url(),
			'site_name'        => get_bloginfo('name'),
			'login_url'        => wp_login_url(),
			'requester_name'   => 'Jamie Smith',
			'requester_title'  => 'VP Marketing at TechCo',
			'responder_name'   => 'Jordan Lee',
			'target_name'      => 'Morgan Davis',
			'other_name'       => 'Casey Williams',
			'meetings_url'     => home_url('/portal/?section=meetings'),
			'accept_url'       => '#accept',
			'decline_url'      => '#decline',
			'complete_url'     => '#complete',
			'notes_url'        => '#notes',
			'view_url'         => '#view',
			'forward_url'      => '#forward',
			'quick_share_url'  => '#share',
			'event_title'      => 'Monthly Networking Breakfast',
			'event_date'       => 'Thursday, March 15',
			'event_time'       => '8:00 AM',
			'event_location'   => 'Downtown Conference Center',
			'title'            => 'February CircleUp',
			'meeting_date'     => 'February 14, 2026',
			'scheduled_text'   => ' on Mar 20',
			'wins_count'       => '5',
			'insights_count'   => '3',
			'actions_count'    => '2',
			'summary_text'     => '<p>Great discussion about Q1 goals and member growth.</p>',
			'action_items_block' => '',
			'forward_note_block' => '',
			'reminder_notes'   => 'Bring your laptop!',
			'registration_url' => 'https://example.com/register',
			'description'      => 'Join us for an evening of networking and growth.',
			'admin_name'       => 'Admin',
			'review_url'       => '#review',
		];
	}
}