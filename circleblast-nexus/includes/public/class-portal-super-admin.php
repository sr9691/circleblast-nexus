<?php
/**
 * Portal Super Admin
 *
 * In-portal super-admin dashboard visible only to cb_super_admin role.
 * Surfaces analytics with engagement scores, email template management,
 * plugin logs, and system settings — all styled within the branded portal.
 *
 * Sub-navigation uses ?section=superadmin&sa_tab=<tab> pattern.
 */

defined('ABSPATH') || exit;

final class CBNexus_Portal_Super_Admin {

	private static $tabs = [
		'analytics' => ['label' => 'Analytics', 'icon' => '📊', 'cap' => 'cbnexus_view_admin_analytics'],
		'emails'    => ['label' => 'Emails',    'icon' => '✉️',  'cap' => 'cbnexus_manage_plugin_settings'],
		'logs'      => ['label' => 'Logs',      'icon' => '📋', 'cap' => 'cbnexus_view_logs'],
		'settings'  => ['label' => 'Settings',  'icon' => '⚙️',  'cap' => 'cbnexus_manage_plugin_settings'],
	];

	public static function init(): void {
		add_action('init', [__CLASS__, 'handle_actions']);
	}

	public static function handle_actions(): void {
		if (!is_user_logged_in() || !self::is_super_admin()) { return; }

		// CSV export.
		if (isset($_GET['cbnexus_portal_export']) && $_GET['cbnexus_portal_export'] === 'members') {
			self::handle_export();
		}
		// Email template save.
		if (isset($_POST['cbnexus_portal_save_email_tpl'])) {
			self::handle_save_email_template();
		}
		// Email template reset.
		if (isset($_GET['cbnexus_portal_reset_tpl'])) {
			self::handle_reset_email_template();
		}
	}

	private static function is_super_admin(): bool {
		$user = wp_get_current_user();
		return in_array('cb_super_admin', $user->roles, true);
	}

	// ─── Render ─────────────────────────────────────────────────────────

	public static function render(array $profile): void {
		if (!self::is_super_admin()) {
			echo '<div class="cbnexus-card"><p>You do not have permission to access this page.</p></div>';
			return;
		}

		$tab = isset($_GET['sa_tab']) ? sanitize_key($_GET['sa_tab']) : 'analytics';
		if (!isset(self::$tabs[$tab]) || !current_user_can(self::$tabs[$tab]['cap'])) {
			$tab = 'analytics';
		}

		self::render_tab_nav($tab);

		echo '<div class="cbnexus-admin-content">';
		switch ($tab) {
			case 'analytics': self::render_analytics(); break;
			case 'emails':    self::render_emails(); break;
			case 'logs':      self::render_logs(); break;
			case 'settings':  self::render_settings(); break;
		}
		echo '</div>';
	}

	private static function render_tab_nav(string $current): void {
		$portal_url = CBNexus_Portal_Router::get_portal_url();
		$base_url   = add_query_arg('section', 'superadmin', $portal_url);
		?>
		<div class="cbnexus-admin-tabs">
			<?php foreach (self::$tabs as $slug => $tab) :
				if (!current_user_can($tab['cap'])) { continue; }
				$is_active = $slug === $current;
				$url = add_query_arg('sa_tab', $slug, $base_url);
			?>
				<a href="<?php echo esc_url($url); ?>" class="cbnexus-admin-tab <?php echo $is_active ? 'active' : ''; ?>">
					<span class="cbnexus-admin-tab-icon"><?php echo esc_html($tab['icon']); ?></span>
					<?php echo esc_html($tab['label']); ?>
				</a>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private static function sa_url(string $tab = 'analytics', array $extra = []): string {
		$portal_url = CBNexus_Portal_Router::get_portal_url();
		$args = array_merge(['section' => 'superadmin', 'sa_tab' => $tab], $extra);
		return add_query_arg($args, $portal_url);
	}

	// =====================================================================
	//  ANALYTICS TAB
	// =====================================================================

	private static function render_analytics(): void {
		$member_data = CBNexus_Admin_Analytics::compute_member_engagement();
		$overview    = self::compute_overview();
		$export_url  = wp_nonce_url(self::sa_url('analytics', ['cbnexus_portal_export' => 'members']), 'cbnexus_portal_export', '_panonce');
		?>
		<div class="cbnexus-card">
			<div class="cbnexus-admin-header-row">
				<h2>Club Analytics</h2>
				<a href="<?php echo esc_url($export_url); ?>" class="cbnexus-btn">Export CSV</a>
			</div>

			<!-- Overview stats -->
			<div class="cbnexus-admin-stats-row">
				<?php foreach ($overview as $label => $val) : ?>
					<div class="cbnexus-admin-stat">
						<div class="cbnexus-admin-stat-value"><?php echo esc_html($val); ?></div>
						<div class="cbnexus-admin-stat-label"><?php echo esc_html($label); ?></div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Engagement table -->
		<div class="cbnexus-card">
			<h3>Member Engagement</h3>
			<div class="cbnexus-admin-table-wrap">
				<table class="cbnexus-admin-table">
					<thead><tr>
						<th>Member</th>
						<th>Meetings</th>
						<th>Unique</th>
						<th>CircleUp</th>
						<th>Notes %</th>
						<th>Accept %</th>
						<th>Score</th>
						<th>Risk</th>
					</tr></thead>
					<tbody>
					<?php foreach ($member_data as $m) :
						$risk_classes = ['high' => 'red', 'medium' => 'gold', 'low' => 'green'];
						$rc = $risk_classes[$m['risk']] ?? 'muted';
					?>
						<tr>
							<td>
								<strong><?php echo esc_html($m['name']); ?></strong>
								<div class="cbnexus-admin-meta"><?php echo esc_html($m['company']); ?></div>
							</td>
							<td><?php echo esc_html($m['meetings']); ?></td>
							<td><?php echo esc_html($m['unique_met']); ?></td>
							<td><?php echo esc_html($m['circleup']); ?></td>
							<td><?php echo esc_html($m['notes_pct']); ?>%</td>
							<td><?php echo esc_html($m['accept_pct']); ?>%</td>
							<td><strong><?php echo esc_html($m['score']); ?></strong>/100</td>
							<td><span class="cbnexus-status-pill cbnexus-status-<?php echo esc_attr($rc); ?>"><?php echo esc_html(ucfirst($m['risk'])); ?></span></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	private static function compute_overview(): array {
		global $wpdb;
		$members = CBNexus_Member_Repository::get_all_members('active');
		$meetings = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cb_meetings WHERE status IN ('completed','closed')");
		$suggestions = CBNexus_Suggestion_Generator::get_cycle_stats();
		$accept_rate = $suggestions['total'] > 0 ? round($suggestions['accepted'] / $suggestions['total'] * 100) . '%' : '—';
		$member_data = CBNexus_Admin_Analytics::compute_member_engagement();
		$high_risk = count(array_filter($member_data, fn($m) => $m['risk'] === 'high'));

		return [
			'Active Members'     => count($members),
			'Completed Meetings' => $meetings,
			'Acceptance Rate'    => $accept_rate,
			'High Risk'          => $high_risk,
		];
	}

	private static function handle_export(): void {
		if (!wp_verify_nonce(wp_unslash($_GET['_panonce'] ?? ''), 'cbnexus_portal_export')) { return; }
		if (!current_user_can('cbnexus_export_data')) { return; }

		$data = CBNexus_Admin_Analytics::compute_member_engagement();

		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename=circleblast-analytics-' . gmdate('Y-m-d') . '.csv');

		$out = fopen('php://output', 'w');
		fputcsv($out, ['Name', 'Company', 'Meetings', 'Unique Met', 'CircleUp', 'Notes %', 'Accept %', 'Engagement', 'Risk']);
		foreach ($data as $row) {
			fputcsv($out, [$row['name'], $row['company'], $row['meetings'], $row['unique_met'], $row['circleup'], $row['notes_pct'], $row['accept_pct'], $row['score'], $row['risk']]);
		}
		fclose($out);
		exit;
	}

	// =====================================================================
	//  EMAIL TEMPLATES TAB
	// =====================================================================

	private static $email_templates = [
		'welcome_member'           => ['name' => 'Welcome New Member',        'group' => 'Members'],
		'meeting_request_received' => ['name' => 'Meeting Request Received',  'group' => '1:1 Meetings'],
		'meeting_request_sent'     => ['name' => 'Meeting Request Sent',      'group' => '1:1 Meetings'],
		'meeting_accepted'         => ['name' => 'Meeting Accepted',          'group' => '1:1 Meetings'],
		'meeting_declined'         => ['name' => 'Meeting Declined',          'group' => '1:1 Meetings'],
		'meeting_reminder'         => ['name' => 'Meeting Reminder',          'group' => '1:1 Meetings'],
		'meeting_notes_request'    => ['name' => 'Notes Request',             'group' => '1:1 Meetings'],
		'suggestion_match'         => ['name' => 'Monthly Match',             'group' => 'Matching'],
		'suggestion_reminder'      => ['name' => 'Match Reminder',            'group' => 'Matching'],
		'circleup_summary'         => ['name' => 'CircleUp Recap',            'group' => 'CircleUp'],
		'event_reminder'           => ['name' => 'Event Reminder',            'group' => 'Events'],
		'monthly_admin_report'     => ['name' => 'Monthly Admin Report',      'group' => 'Admin'],
	];

	private static function render_emails(): void {
		$notice = sanitize_key($_GET['pa_notice'] ?? '');
		self::render_notice($notice);

		// Editing a template?
		if (isset($_GET['tpl'])) {
			self::render_email_editor(sanitize_key($_GET['tpl']));
			return;
		}

		// List all templates.
		$grouped = [];
		foreach (self::$email_templates as $id => $meta) {
			$grouped[$meta['group']][$id] = $meta;
		}
		?>
		<div class="cbnexus-card">
			<h2>Email Templates</h2>
			<p class="cbnexus-text-muted">Customize the emails CircleBlast sends. Edits override the default templates.</p>

			<?php foreach ($grouped as $group => $templates) : ?>
				<h3 style="margin:20px 0 8px;"><?php echo esc_html($group); ?></h3>
				<div class="cbnexus-admin-table-wrap">
					<table class="cbnexus-admin-table">
						<thead><tr>
							<th>Template</th>
							<th style="width:100px;">Customized?</th>
							<th style="width:80px;">Actions</th>
						</tr></thead>
						<tbody>
						<?php foreach ($templates as $id => $meta) :
							$has_override = (bool) get_option('cbnexus_email_tpl_' . $id);
						?>
							<tr>
								<td><?php echo esc_html($meta['name']); ?></td>
								<td><?php echo $has_override ? '<span class="cbnexus-status-pill cbnexus-status-green">Yes</span>' : '<span class="cbnexus-admin-meta">Default</span>'; ?></td>
								<td><a href="<?php echo esc_url(self::sa_url('emails', ['tpl' => $id])); ?>" class="cbnexus-link">Edit</a></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private static function render_email_editor(string $tpl_id): void {
		if (!isset(self::$email_templates[$tpl_id])) {
			echo '<div class="cbnexus-card"><p>Template not found.</p></div>';
			return;
		}

		$meta     = self::$email_templates[$tpl_id];
		$override = get_option('cbnexus_email_tpl_' . $tpl_id);
		$file     = CBNEXUS_PLUGIN_DIR . 'templates/emails/' . $tpl_id . '.php';
		$default  = file_exists($file) ? include $file : ['subject' => '', 'body' => ''];

		$subject = $override['subject'] ?? $default['subject'] ?? '';
		$body    = $override['body'] ?? $default['body'] ?? '';

		// Enqueue the rich-text editor script.
		wp_enqueue_script(
			'cbnexus-email-editor',
			CBNEXUS_PLUGIN_URL . 'assets/js/email-editor.js',
			[],
			CBNEXUS_VERSION,
			true
		);
		?>
		<div class="cbnexus-card">
			<div class="cbnexus-admin-header-row">
				<h2>Edit: <?php echo esc_html($meta['name']); ?></h2>
				<a href="<?php echo esc_url(self::sa_url('emails')); ?>" class="cbnexus-btn">← Back</a>
			</div>

			<form method="post" action="">
				<?php wp_nonce_field('cbnexus_portal_save_email_tpl'); ?>
				<input type="hidden" name="tpl_id" value="<?php echo esc_attr($tpl_id); ?>" />

				<div class="cbnexus-admin-form-stack">
					<div>
						<label>Subject Line</label>
						<input type="text" name="subject" value="<?php echo esc_attr($subject); ?>" />
					</div>
					<div id="cbnexus-email-editor">
						<label style="margin-bottom:8px;display:block;">Body</label>
						<?php self::render_rte_editor($body); ?>
					</div>
				</div>

				<div class="cbnexus-admin-button-row">
					<button type="submit" name="cbnexus_portal_save_email_tpl" value="1" class="cbnexus-btn cbnexus-btn-accent">Save Template</button>
					<?php if ($override) : ?>
						<a href="<?php echo esc_url(wp_nonce_url(self::sa_url('emails', ['cbnexus_portal_reset_tpl' => $tpl_id]), 'cbnexus_portal_reset_' . $tpl_id, '_panonce')); ?>" class="cbnexus-btn" onclick="return confirm('Reset to default template?');">Reset to Default</a>
					<?php endif; ?>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the Visual / HTML toggle rich-text editor.
	 */
	private static function render_rte_editor(string $body): void {
		$placeholders = ['first_name', 'last_name', 'display_name', 'email', 'company', 'site_url', 'portal_url', 'login_url'];
		?>
		<!-- Editor mode tabs -->
		<div class="cbnexus-rte-tabs">
			<a href="#" data-rte-tab="visual" class="cbnexus-rte-tab active">Visual</a>
			<a href="#" data-rte-tab="html" class="cbnexus-rte-tab">HTML</a>
		</div>

		<!-- Formatting toolbar (visual mode only) -->
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

		<!-- Visual editor (contenteditable) -->
		<div class="cbnexus-rte-visual" contenteditable="true"><?php echo wp_kses_post($body); ?></div>

		<!-- HTML editor (textarea, hidden by default) -->
		<textarea class="cbnexus-rte-html" rows="14" style="display:none;"><?php echo esc_textarea($body); ?></textarea>

		<!-- Hidden textarea that the form actually submits -->
		<textarea name="body" style="display:none!important;"><?php echo esc_textarea($body); ?></textarea>

		<!-- Placeholder insertion -->
		<div class="cbnexus-rte-placeholders">
			<span class="cbnexus-admin-meta">Insert placeholder:</span>
			<?php foreach ($placeholders as $ph) : ?>
				<button type="button" class="cbnexus-rte-placeholder-btn" data-placeholder="<?php echo esc_attr($ph); ?>">{{<?php echo esc_html($ph); ?>}}</button>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private static function handle_save_email_template(): void {
		if (!wp_verify_nonce(wp_unslash($_POST['_wpnonce'] ?? ''), 'cbnexus_portal_save_email_tpl')) { return; }
		if (!current_user_can('cbnexus_manage_plugin_settings')) { return; }

		$tpl_id = sanitize_key($_POST['tpl_id'] ?? '');
		if (!isset(self::$email_templates[$tpl_id])) { return; }

		update_option('cbnexus_email_tpl_' . $tpl_id, [
			'subject' => sanitize_text_field(wp_unslash($_POST['subject'] ?? '')),
			'body'    => wp_unslash($_POST['body'] ?? ''),
		]);

		wp_safe_redirect(self::sa_url('emails', ['tpl' => $tpl_id, 'pa_notice' => 'template_saved']));
		exit;
	}

	private static function handle_reset_email_template(): void {
		$tpl_id = sanitize_key($_GET['cbnexus_portal_reset_tpl']);
		if (!wp_verify_nonce(wp_unslash($_GET['_panonce'] ?? ''), 'cbnexus_portal_reset_' . $tpl_id)) { return; }
		if (!current_user_can('cbnexus_manage_plugin_settings')) { return; }

		delete_option('cbnexus_email_tpl_' . $tpl_id);

		wp_safe_redirect(self::sa_url('emails', ['pa_notice' => 'template_reset']));
		exit;
	}

	// =====================================================================
	//  LOGS TAB
	// =====================================================================

	private static function render_logs(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'cbnexus_log';
		$found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
		if ($found !== $table) {
			echo '<div class="cbnexus-card"><p>Log table not found.</p></div>';
			return;
		}

		$level = sanitize_key($_GET['log_level'] ?? '');
		$where = $level ? $wpdb->prepare(" WHERE level = %s", $level) : '';
		$logs  = $wpdb->get_results("SELECT * FROM {$table}{$where} ORDER BY created_at_gmt DESC LIMIT 100");
		?>
		<div class="cbnexus-card">
			<h2>Plugin Logs</h2>

			<div class="cbnexus-admin-filters">
				<a href="<?php echo esc_url(self::sa_url('logs')); ?>" class="<?php echo $level === '' ? 'active' : ''; ?>">All</a>
				<?php foreach (['error', 'warning', 'info', 'debug'] as $lv) : ?>
					<a href="<?php echo esc_url(self::sa_url('logs', ['log_level' => $lv])); ?>" class="<?php echo $level === $lv ? 'active' : ''; ?>"><?php echo esc_html(ucfirst($lv)); ?></a>
				<?php endforeach; ?>
			</div>

			<div class="cbnexus-admin-table-wrap">
				<table class="cbnexus-admin-table cbnexus-admin-table-sm">
					<thead><tr>
						<th style="width:140px;">Time</th>
						<th style="width:70px;">Level</th>
						<th>Message</th>
					</tr></thead>
					<tbody>
					<?php if (empty($logs)) : ?>
						<tr><td colspan="3" class="cbnexus-admin-empty">No log entries found.</td></tr>
					<?php else : foreach ($logs as $log) :
						$level_colors = ['error' => 'red', 'warning' => 'gold', 'info' => 'blue', 'debug' => 'muted'];
						$lc = $level_colors[$log->level] ?? 'muted';
					?>
						<tr>
							<td class="cbnexus-admin-meta"><?php echo esc_html($log->created_at_gmt); ?></td>
							<td><span class="cbnexus-status-pill cbnexus-status-<?php echo esc_attr($lc); ?>"><?php echo esc_html(strtoupper($log->level)); ?></span></td>
							<td style="font-size:13px;"><?php echo esc_html($log->message); ?></td>
						</tr>
					<?php endforeach; endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	// =====================================================================
	//  SETTINGS TAB
	// =====================================================================

	private static function render_settings(): void {
		?>
		<div class="cbnexus-card">
			<h2>System Settings</h2>

			<div class="cbnexus-admin-form-stack">
				<div>
					<h3>Plugin Info</h3>
					<table class="cbnexus-admin-kv-table">
						<tr><td>Version</td><td><strong><?php echo esc_html(CBNEXUS_VERSION); ?></strong></td></tr>
						<tr><td>PHP</td><td><?php echo esc_html(PHP_VERSION); ?></td></tr>
						<tr><td>WordPress</td><td><?php echo esc_html(get_bloginfo('version')); ?></td></tr>
						<tr><td>Database Prefix</td><td><code><?php global $wpdb; echo esc_html($wpdb->prefix); ?></code></td></tr>
					</table>
				</div>

				<div>
					<h3>Cron Jobs</h3>
					<table class="cbnexus-admin-kv-table">
						<?php
						$crons = [
							'cbnexus_log_cleanup'          => 'Log Cleanup',
							'cbnexus_meeting_reminders'    => 'Meeting Reminders',
							'cbnexus_suggestion_cycle'     => 'Suggestion Cycle',
							'cbnexus_suggestion_reminders' => 'Suggestion Reminders',
							'cbnexus_ai_extraction'        => 'AI Extraction',
							'cbnexus_analytics_snapshot'   => 'Analytics Snapshot',
							'cbnexus_monthly_report'       => 'Monthly Report',
							'cbnexus_event_reminders'      => 'Event Reminders',
						'cbnexus_events_digest'        => 'Events Digest',
							'cbnexus_token_cleanup'        => 'Token Cleanup',
						];
						foreach ($crons as $hook => $label) :
							$next = wp_next_scheduled($hook);
							$next_str = $next ? date_i18n('M j, g:i a', $next) : 'Not scheduled';
						?>
							<tr>
								<td><?php echo esc_html($label); ?></td>
								<td class="cbnexus-admin-meta"><?php echo esc_html($next_str); ?></td>
							</tr>
						<?php endforeach; ?>
					</table>
				</div>

				<div>
					<h3>API Keys</h3>
					<table class="cbnexus-admin-kv-table">
						<tr>
							<td>Claude API Key</td>
							<td><?php echo defined('CBNEXUS_CLAUDE_API_KEY') ? '<span class="cbnexus-status-pill cbnexus-status-green">Configured</span>' : '<span class="cbnexus-status-pill cbnexus-status-gold">Not set</span>'; ?></td>
						</tr>
						<tr>
							<td>Fireflies Secret</td>
							<td><?php echo defined('CBNEXUS_FIREFLIES_SECRET') ? '<span class="cbnexus-status-pill cbnexus-status-green">Configured</span>' : '<span class="cbnexus-status-pill cbnexus-status-gold">Not set (dev mode)</span>'; ?></td>
						</tr>
					</table>
					<p class="cbnexus-admin-meta" style="margin-top:8px;">API keys are configured in wp-config.php per security policy. They cannot be changed from this interface.</p>
				</div>
			</div>
		</div>
		<?php
	}

	// ─── Shared UI Helpers ──────────────────────────────────────────────

	private static function render_notice(string $notice): void {
		if ($notice === '') { return; }
		$messages = [
			'template_saved' => 'Email template saved.',
			'template_reset' => 'Template reset to default.',
			'error'          => 'An error occurred.',
		];
		$msg = $messages[$notice] ?? '';
		if (!$msg) { return; }
		$type = ($notice === 'error') ? 'error' : 'success';
		echo '<div class="cbnexus-portal-notice cbnexus-notice-' . esc_attr($type) . '">' . esc_html($msg) . '</div>';
	}
}