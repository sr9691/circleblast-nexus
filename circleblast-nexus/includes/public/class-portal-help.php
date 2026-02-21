<?php
/**
 * Portal Help System
 *
 * Context-sensitive help drawer for the member portal. Content is keyed
 * by the current ?section= and ?admin_tab= URL parameters. Default
 * content lives in get_defaults(); admin overrides are stored in
 * wp_options as JSON (same pattern as email template overrides).
 *
 * Files involved:
 *   - This file: content registry, drawer renderer, admin editor UI
 *   - assets/js/help.js: drawer toggle + RTE for admin editor
 *   - assets/css/portal/_help.css: drawer + tooltip styles
 *   - class-portal-router.php: header button + render_drawer() call
 *   - class-portal-admin-settings.php: renders the editor card
 *   - class-portal-admin.php: action dispatch for save/reset
 *
 * @since 1.2.0
 */

defined('ABSPATH') || exit;

final class CBNexus_Portal_Help {

	/** wp_option key for admin overrides. */
	const OPTION_KEY = 'cbnexus_help_content';

	/** wp_option key for stat tooltip overrides. */
	const TOOLTIPS_OPTION_KEY = 'cbnexus_stat_tooltips';

	// =====================================================================
	//  STAT TOOLTIPS
	// =====================================================================

	/**
	 * Get all stat tooltips (defaults merged with overrides).
	 *
	 * @return array<string, string> key => tooltip text
	 */
	public static function get_all_tooltips(): array {
		$defaults  = self::get_default_tooltips();
		$overrides = get_option(self::TOOLTIPS_OPTION_KEY, []);

		return array_merge($defaults, array_filter($overrides));
	}

	/**
	 * Get tooltips for a specific group (dashboard, club, analytics_overview, analytics_columns).
	 *
	 * @param string $group Group name.
	 * @return array<string, string> key => tooltip text
	 */
	public static function get_tooltips_for(string $group): array {
		$all    = self::get_all_tooltips();
		$groups = self::get_tooltip_groups();
		$keys   = $groups[$group]['keys'] ?? [];
		$result = [];

		foreach ($keys as $key => $label) {
			$result[$key] = $all[$group . '.' . $key] ?? '';
		}

		return $result;
	}

	/**
	 * Tooltip group definitions: which keys belong to which group,
	 * with human-readable labels for the admin editor.
	 *
	 * @return array<string, array{label: string, keys: array<string, string>}>
	 */
	public static function get_tooltip_groups(): array {
		return [
			'dashboard' => [
				'label' => '🏠 Home Dashboard Stats',
				'keys'  => [
					'meetings'      => 'Meetings',
					'met'           => 'Met',
					'circleups'     => 'CircleUps',
					'notes'         => 'Notes',
					'contributions' => 'Contributions',
				],
			],
			'club' => [
				'label' => '📊 Club Stats',
				'keys'  => [
					'members'   => 'Members',
					'meetings'  => '1:1 Meetings',
					'connected' => 'Connected',
					'new'       => 'New (90d)',
					'circleups' => 'CircleUps',
					'wins'      => 'Wins',
				],
			],
			'analytics_overview' => [
				'label' => '📊 Analytics Overview Cards',
				'keys'  => [
					'Active Members'     => 'Active Members',
					'Completed Meetings' => 'Completed Meetings',
					'Acceptance Rate'    => 'Acceptance Rate',
					'High Risk'          => 'High Risk',
				],
			],
			'analytics_columns' => [
				'label' => '📊 Analytics Table Columns',
				'keys'  => [
					'Meetings'  => 'Meetings',
					'Unique'    => 'Unique',
					'CircleUp'  => 'CircleUp',
					'Notes %'   => 'Notes %',
					'Accept %'  => 'Accept %',
					'Score'     => 'Score',
					'Risk'      => 'Risk',
				],
			],
			'matching' => [
				'label' => '🔗 Matching Tab',
				'keys'  => [
					'last_run'          => 'Last Run',
					'total_suggestions' => 'Total Suggestions',
					'pending'           => 'Pending',
					'accepted'          => 'Accepted',
					'accept_rate'       => 'Accept Rate',
				],
			],
			'coverage' => [
				'label' => '🎯 Recruitment Coverage',
				'keys'  => [
					'coverage'    => 'Coverage %',
					'filled'      => 'Filled',
					'partial'     => 'Partial',
					'open'        => 'Open',
					'total'       => 'Total',
					'in_pipeline' => 'In Pipeline',
				],
			],
		];
	}

	/**
	 * Default tooltip text for all stat cards across the portal.
	 *
	 * @return array<string, string> "group.key" => text
	 */
	private static function get_default_tooltips(): array {
		return [
			// Dashboard
			'dashboard.meetings'      => 'Total 1:1 meetings you\'ve completed (status: completed or closed).',
			'dashboard.met'           => 'Unique members you\'ve had at least one completed meeting with, out of total active members (excluding yourself).',
			'dashboard.circleups'     => 'Number of monthly CircleUp group meetings you\'ve attended.',
			'dashboard.notes'         => 'Percentage of your completed meetings where you submitted notes.',
			'dashboard.contributions' => 'Wins and insights you\'ve shared during CircleUp meetings (approved items only).',

			// Club
			'club.members'   => 'Total active members in the group.',
			'club.meetings'  => 'Total 1:1 meetings completed across all members.',
			'club.connected' => 'Percentage of all possible member pairs who have actually met. Higher = stronger network.',
			'club.new'       => 'Members who joined in the last 90 days.',
			'club.circleups' => 'Published CircleUp group meeting summaries.',
			'club.wins'      => 'Total wins shared across all CircleUp meetings.',

			// Analytics overview
			'analytics_overview.Active Members'     => 'Total members with active status in the system.',
			'analytics_overview.Completed Meetings' => 'Total 1:1 meetings that reached completed or closed status.',
			'analytics_overview.Acceptance Rate'    => 'Percentage of auto-generated suggestions that were accepted by members.',
			'analytics_overview.High Risk'          => 'Members with engagement score below 20 or inactive for 90+ days.',

			// Analytics columns
			'analytics_columns.Meetings'  => '1:1 meetings completed or closed.',
			'analytics_columns.Unique'    => 'Distinct members connected with.',
			'analytics_columns.CircleUp'  => 'CircleUp group meetings attended.',
			'analytics_columns.Notes %'   => 'Percentage of meetings with notes submitted.',
			'analytics_columns.Accept %'  => 'Percentage of meeting suggestions accepted.',
			'analytics_columns.Score'     => 'Engagement score (0–100): meetings 30pts + unique 24pts + CircleUp 24pts + notes 12pts + accept 10pts.',
			'analytics_columns.Risk'      => 'High: >90d inactive or score<20. Medium: >45d or score<40. Low: all else.',

			// Matching
			'matching.last_run'          => 'Date and time the matching engine last generated suggestions.',
			'matching.total_suggestions' => 'Total 1:1 suggestions created in the most recent cycle.',
			'matching.pending'           => 'Suggestions still awaiting a response from one or both members.',
			'matching.accepted'          => 'Suggestions that were accepted and led to scheduled meetings.',
			'matching.accept_rate'       => 'Percentage of suggestions accepted out of total sent.',

			// Coverage
			'coverage.coverage'    => 'Percentage of recruitment categories that have at least one active member.',
			'coverage.filled'      => 'Categories fully covered (member count meets or exceeds target).',
			'coverage.partial'     => 'Categories with some members but below the target count.',
			'coverage.open'        => 'Categories with zero members assigned — active gaps to fill.',
			'coverage.total'       => 'Total number of recruitment categories defined.',
			'coverage.in_pipeline' => 'Candidates currently in the recruitment pipeline across all stages.',
		];
	}

	// =====================================================================
	//  CONTENT RESOLUTION
	// =====================================================================

	/**
	 * Build the context key from the current URL state.
	 *
	 * @return string e.g. "dashboard", "manage.recruitment"
	 */
	public static function get_context_key(): string {
		$section   = isset($_GET['section']) ? sanitize_key($_GET['section']) : 'dashboard';
		$admin_tab = isset($_GET['admin_tab']) ? sanitize_key($_GET['admin_tab']) : '';

		return $admin_tab && $section === 'manage'
			? 'manage.' . $admin_tab
			: $section;
	}

	/**
	 * Get help content for the current portal context.
	 * Database overrides take precedence over PHP defaults.
	 *
	 * @return array{title: string, body: string}|null
	 */
	public static function get_current_help(): ?array {
		$key = self::get_context_key();

		// Check for admin override first.
		$overrides = get_option(self::OPTION_KEY, []);
		if (!empty($overrides[$key]['title']) || !empty($overrides[$key]['body'])) {
			return [
				'title' => $overrides[$key]['title'] ?? '',
				'body'  => $overrides[$key]['body'] ?? '',
			];
		}

		// Fall back to PHP defaults.
		$defaults = self::get_defaults();
		return $defaults[$key] ?? $defaults['_default'] ?? null;
	}

	/**
	 * Get help content for a specific key (used by admin editor).
	 *
	 * @param string $key Context key.
	 * @return array{title: string, body: string, source: string}
	 */
	public static function get_for_key(string $key): array {
		$overrides = get_option(self::OPTION_KEY, []);
		$defaults  = self::get_defaults();

		if (!empty($overrides[$key])) {
			return [
				'title'  => $overrides[$key]['title'] ?? '',
				'body'   => $overrides[$key]['body'] ?? '',
				'source' => 'custom',
			];
		}

		if (!empty($defaults[$key])) {
			return [
				'title'  => $defaults[$key]['title'],
				'body'   => $defaults[$key]['body'],
				'source' => 'default',
			];
		}

		return ['title' => '', 'body' => '', 'source' => 'none'];
	}

	/**
	 * Return all context keys with labels for the admin editor dropdown.
	 *
	 * @return array<string, string> key => human label
	 */
	public static function get_context_labels(): array {
		return [
			'_default'            => '🏠 Default (fallback)',
			'dashboard'           => '🏠 Home / Dashboard',
			'directory'           => '👥 Directory',
			'meetings'            => '🤝 Meetings',
			'circleup'            => '📢 CircleUp Archive',
			'events'              => '📅 Events',
			'club'                => '📊 Club Stats',
			'profile'             => '👤 Profile',
			'manage.members'      => '🛡️ Manage › Members',
			'manage.recruitment'  => '🛡️ Manage › Recruitment',
			'manage.matching'     => '🛡️ Manage › Matching',
			'manage.archivist'    => '🛡️ Manage › Meeting Notes',
			'manage.events'       => '🛡️ Manage › Events',
			'manage.analytics'    => '🛡️ Manage › Analytics',
			'manage.emails'       => '🛡️ Manage › Emails',
			'manage.help'         => '🛡️ Manage › Help',
			'manage.logs'         => '🛡️ Manage › Logs',
			'manage.settings'     => '🛡️ Manage › Settings',
		];
	}

	// =====================================================================
	//  DRAWER RENDERER
	// =====================================================================

	/**
	 * Render the help drawer HTML (called once in the portal shell).
	 */
	public static function render_drawer(): void {
		$help = self::get_current_help();
		if (!$help) { return; }
		?>
		<div id="cbnexus-help-drawer" class="cbnexus-help-drawer" aria-hidden="true" role="dialog" aria-label="<?php esc_attr_e('Page help', 'circleblast-nexus'); ?>">
			<div class="cbnexus-help-drawer-header">
				<h3><?php echo esc_html($help['title']); ?></h3>
				<button type="button" class="cbnexus-help-close" aria-label="<?php esc_attr_e('Close help', 'circleblast-nexus'); ?>">&times;</button>
			</div>
			<div class="cbnexus-help-drawer-body">
				<?php echo wp_kses_post($help['body']); ?>
			</div>
		</div>
		<div id="cbnexus-help-overlay" class="cbnexus-help-overlay"></div>
		<?php
	}

	// =====================================================================
	//  ADMIN EDITOR (rendered inside Settings tab)
	// =====================================================================

	/**
	 * Render the help content editor card for the Settings admin tab.
	 */
	public static function render_editor(): void {
		$contexts  = self::get_context_labels();
		$overrides = get_option(self::OPTION_KEY, []);
		$editing   = isset($_GET['help_ctx']) ? sanitize_key($_GET['help_ctx']) : '';

		if ($editing && isset($contexts[$editing])) {
			self::render_editor_form($editing, $contexts[$editing]);
			return;
		}

		// List view: show all contexts with override status.
		?>
		<div class="cbnexus-card">
			<h2>📄 Page Help Content</h2>
			<p class="cbnexus-admin-meta" style="margin:0 0 16px;">
				Customize the help text shown when members click the <strong>?</strong> button on each portal page. Edits override the built-in defaults.
			</p>

			<div class="cbnexus-admin-table-wrap">
				<table class="cbnexus-admin-table">
					<thead>
						<tr>
							<th>Page</th>
							<th style="width:120px;">Status</th>
							<th style="width:80px;">Actions</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ($contexts as $key => $label) :
						$has_override = !empty($overrides[$key]);
					?>
						<tr>
							<td><?php echo esc_html($label); ?></td>
							<td>
								<?php if ($has_override) : ?>
									<span class="cbnexus-status-pill cbnexus-status-green">Customized</span>
								<?php else : ?>
									<span class="cbnexus-admin-meta">Default</span>
								<?php endif; ?>
							</td>
							<td>
								<a href="<?php echo esc_url(CBNexus_Portal_Admin::admin_url('help', ['help_ctx' => $key])); ?>" class="cbnexus-link">Edit</a>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>

		<?php self::render_tooltip_editor(); ?>
		<?php
	}

	/**
	 * Render the editor form for a specific context.
	 */
	private static function render_editor_form(string $key, string $label): void {
		$data = self::get_for_key($key);

		// Enqueue the email editor script (reuse for RTE).
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
				<h2>Edit Help: <?php echo esc_html($label); ?></h2>
				<a href="<?php echo esc_url(CBNexus_Portal_Admin::admin_url('help')); ?>" class="cbnexus-btn">← Back</a>
			</div>

			<?php if ($data['source'] === 'custom') : ?>
				<p class="cbnexus-admin-meta" style="margin:0 0 12px;">
					This page has custom help content. The default text will be used if you reset it.
				</p>
			<?php else : ?>
				<p class="cbnexus-admin-meta" style="margin:0 0 12px;">
					Currently showing the built-in default. Save to create a custom override.
				</p>
			<?php endif; ?>

			<form method="post" action="">
				<?php wp_nonce_field('cbnexus_portal_save_help_content'); ?>
				<input type="hidden" name="help_context_key" value="<?php echo esc_attr($key); ?>" />

				<div class="cbnexus-admin-form-stack">
					<div>
						<label><strong>Title</strong></label>
						<input type="text" name="help_title" value="<?php echo esc_attr($data['title']); ?>" class="cbnexus-input" style="width:100%;max-width:500px;" placeholder="Help panel title…" />
					</div>

					<div id="cbnexus-email-editor">
						<label style="margin-bottom:8px;display:block;"><strong>Body</strong></label>

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
							<button type="button" data-cmd="formatBlock" data-val="<h3>" title="Heading">H</button>
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
						<div class="cbnexus-rte-visual" contenteditable="true"><?php echo wp_kses_post($data['body']); ?></div>

						<!-- HTML editor (hidden by default) -->
						<textarea class="cbnexus-rte-html" rows="12" style="display:none;"><?php echo esc_textarea($data['body']); ?></textarea>

						<!-- Hidden textarea for form submission -->
						<textarea name="help_body" style="display:none!important;"><?php echo esc_textarea($data['body']); ?></textarea>
					</div>
				</div>

				<div class="cbnexus-admin-button-row">
					<button type="submit" name="cbnexus_portal_save_help_content" value="1" class="cbnexus-btn cbnexus-btn-primary">💾 Save Help Content</button>
					<?php if ($data['source'] === 'custom') : ?>
						<a href="<?php echo esc_url(wp_nonce_url(
							CBNexus_Portal_Admin::admin_url('help', ['cbnexus_portal_reset_help' => $key]),
							'cbnexus_portal_reset_help_' . $key,
							'_panonce'
						)); ?>" class="cbnexus-btn" onclick="return confirm('Reset to default help text?');">Reset to Default</a>
					<?php endif; ?>
				</div>
			</form>
		</div>
		<?php
	}

	// =====================================================================
	//  STAT TOOLTIP EDITOR
	// =====================================================================

	/**
	 * Render the stat tooltip editor card.
	 */
	private static function render_tooltip_editor(): void {
		$groups    = self::get_tooltip_groups();
		$defaults  = self::get_default_tooltips();
		$overrides = get_option(self::TOOLTIPS_OPTION_KEY, []);

		$notice = sanitize_key($_GET['pa_notice'] ?? '');
		?>
		<div class="cbnexus-card">
			<h2>ⓘ Stat Card Tooltips</h2>
			<p class="cbnexus-admin-meta" style="margin:0 0 16px;">
				Edit the tooltip text shown when members hover the <strong>ⓘ</strong> icon on stat cards and table headers. Leave a field blank to use the default text.
			</p>

			<form method="post" action="">
				<?php wp_nonce_field('cbnexus_portal_save_stat_tooltips'); ?>

				<?php foreach ($groups as $group_key => $group) : ?>
					<h3 style="margin:20px 0 8px;"><?php echo esc_html($group['label']); ?></h3>
					<div class="cbnexus-admin-table-wrap">
						<table class="cbnexus-admin-table cbnexus-admin-table-sm">
							<thead>
								<tr>
									<th style="width:140px;">Stat</th>
									<th>Tooltip Text</th>
									<th style="width:90px;">Status</th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ($group['keys'] as $stat_key => $stat_label) :
								$full_key     = $group_key . '.' . $stat_key;
								$default_val  = $defaults[$full_key] ?? '';
								$override_val = $overrides[$full_key] ?? '';
								$display_val  = $override_val !== '' ? $override_val : $default_val;
								$has_override = $override_val !== '';
							?>
								<tr>
									<td><strong><?php echo esc_html($stat_label); ?></strong></td>
									<td>
										<input type="text"
											name="tooltip[<?php echo esc_attr($full_key); ?>]"
											value="<?php echo esc_attr($display_val); ?>"
											class="cbnexus-input"
											style="width:100%;font-size:13px;"
											placeholder="<?php echo esc_attr($default_val); ?>"
										/>
									</td>
									<td>
										<?php if ($has_override) : ?>
											<span class="cbnexus-status-pill cbnexus-status-green">Custom</span>
										<?php else : ?>
											<span class="cbnexus-admin-meta">Default</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endforeach; ?>

				<div class="cbnexus-admin-button-row" style="margin-top:16px;">
					<button type="submit" name="cbnexus_portal_save_stat_tooltips" value="1" class="cbnexus-btn cbnexus-btn-primary">💾 Save Tooltips</button>
					<?php if (!empty($overrides)) : ?>
						<a href="<?php echo esc_url(wp_nonce_url(
							CBNexus_Portal_Admin::admin_url('help', ['cbnexus_portal_reset_tooltips' => '1']),
							'cbnexus_portal_reset_tooltips',
							'_panonce'
						)); ?>" class="cbnexus-btn" onclick="return confirm('Reset all tooltips to defaults?');">Reset All to Defaults</a>
					<?php endif; ?>
				</div>
			</form>
		</div>
		<?php
	}

	// =====================================================================
	//  ACTION HANDLERS
	// =====================================================================

	/**
	 * Save help content override for a specific context.
	 */
	public static function handle_save_help_content(): void {
		if (!wp_verify_nonce(wp_unslash($_POST['_wpnonce'] ?? ''), 'cbnexus_portal_save_help_content')) { return; }
		if (!current_user_can('cbnexus_manage_plugin_settings')) { return; }

		$key   = sanitize_key($_POST['help_context_key'] ?? '');
		$title = sanitize_text_field(wp_unslash($_POST['help_title'] ?? ''));
		$body  = wp_unslash($_POST['help_body'] ?? '');

		$contexts = self::get_context_labels();
		if (!isset($contexts[$key])) { return; }

		$overrides = get_option(self::OPTION_KEY, []);
		$overrides[$key] = [
			'title' => $title,
			'body'  => $body,
		];
		update_option(self::OPTION_KEY, $overrides, false);

		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('help', [
			'help_ctx'  => $key,
			'pa_notice' => 'help_saved',
		]));
		exit;
	}

	/**
	 * Reset help content for a specific context back to default.
	 */
	public static function handle_reset_help_content(): void {
		$key = sanitize_key($_GET['cbnexus_portal_reset_help']);
		if (!wp_verify_nonce(wp_unslash($_GET['_panonce'] ?? ''), 'cbnexus_portal_reset_help_' . $key)) { return; }
		if (!current_user_can('cbnexus_manage_plugin_settings')) { return; }

		$overrides = get_option(self::OPTION_KEY, []);
		unset($overrides[$key]);
		update_option(self::OPTION_KEY, $overrides, false);

		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('help', ['pa_notice' => 'help_reset']));
		exit;
	}

	/**
	 * Save stat tooltip overrides.
	 */
	public static function handle_save_tooltips(): void {
		if (!wp_verify_nonce(wp_unslash($_POST['_wpnonce'] ?? ''), 'cbnexus_portal_save_stat_tooltips')) { return; }
		if (!current_user_can('cbnexus_manage_plugin_settings')) { return; }

		$input    = $_POST['tooltip'] ?? [];
		$defaults = self::get_default_tooltips();
		$saves    = [];

		foreach ($input as $full_key => $val) {
			$full_key = sanitize_text_field($full_key);
			$val      = sanitize_text_field(wp_unslash($val));

			// Only accept keys that exist in defaults (whitelist).
			if (!isset($defaults[$full_key])) { continue; }

			// Only save if different from default (empty = reset to default).
			if ($val !== '' && $val !== $defaults[$full_key]) {
				$saves[$full_key] = $val;
			}
		}

		update_option(self::TOOLTIPS_OPTION_KEY, $saves, false);

		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('help', ['pa_notice' => 'tooltips_saved']));
		exit;
	}

	/**
	 * Reset all stat tooltips to defaults.
	 */
	public static function handle_reset_tooltips(): void {
		if (!wp_verify_nonce(wp_unslash($_GET['_panonce'] ?? ''), 'cbnexus_portal_reset_tooltips')) { return; }
		if (!current_user_can('cbnexus_manage_plugin_settings')) { return; }

		delete_option(self::TOOLTIPS_OPTION_KEY);

		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('help', ['pa_notice' => 'tooltips_reset']));
		exit;
	}

	// =====================================================================
	//  DEFAULT CONTENT
	// =====================================================================

	/**
	 * Built-in help content for every portal context.
	 * These serve as defaults; admin overrides (wp_option) take precedence.
	 *
	 * @return array<string, array{title: string, body: string}>
	 */
	private static function get_defaults(): array {
		// Role-aware branching for contexts that need it.
		$is_admin = current_user_can('cbnexus_manage_members');

		return [
			'_default' => [
				'title' => __('Welcome to CircleBlast', 'circleblast-nexus'),
				'body'  => '<p>Use the navigation tabs to explore your member portal. Each page has its own help content — click the <strong>?</strong> icon anytime for guidance specific to where you are.</p>'
				         . '<p>If you need further assistance, reach out to your group admin.</p>',
			],

			// ── Member-Facing Sections ──────────────────────────────────

			'dashboard' => [
				'title' => __('Your Home Dashboard', 'circleblast-nexus'),
				'body'  => '<p>This is your personal snapshot of everything happening in your circle.</p>'
				         . '<h3>Quick Stats</h3>'
				         . '<p>The five cards at the top show your engagement at a glance. Tap the <strong>ⓘ</strong> icon on any stat to see exactly what it measures.</p>'
				         . '<h3>Needs Your Attention</h3>'
				         . '<p>Gold-highlighted items need your action — pending meeting requests to accept or decline, and completed meetings that still need notes. Acting on these promptly keeps the group connected.</p>'
				         . '<h3>Who We\'re Looking For</h3>'
				         . '<p>Open roles the group wants to fill. Know someone who\'d be a good fit? Click <strong>"Know someone?"</strong> to open the referral form — just a name is all you need.</p>'
				         . '<h3>Coming Up & Action Items</h3>'
				         . '<p>Your upcoming 1:1 meetings and any tasks assigned to you from CircleUp group sessions.</p>',
			],

			'directory' => [
				'title' => __('Member Directory', 'circleblast-nexus'),
				'body'  => '<p>Browse all active members in your group.</p>'
				         . '<h3>Search & Filter</h3>'
				         . '<p>Use the search bar to find members by name, company, or keywords. Filter by industry, expertise tags, or recruitment category to narrow results.</p>'
				         . '<h3>Request a 1:1</h3>'
				         . '<p>Click <strong>"Request 1:1"</strong> on any member\'s card to send them a meeting request. They\'ll receive an email notification and can accept or decline.</p>'
				         . '<h3>Ghost Cards</h3>'
				         . '<p>Cards with dashed borders represent roles the group still needs to fill. If you know someone who\'d be a great fit, use the <strong>"Refer Someone"</strong> button to submit a quick referral.</p>',
			],

			'meetings' => [
				'title' => __('1:1 Meetings', 'circleblast-nexus'),
				'body'  => '<p>Track your entire 1:1 meeting lifecycle from request through notes.</p>'
				         . '<h3>How It Works</h3>'
				         . '<ol>'
				         . '<li><strong>Request</strong> — Find someone in the Directory and click "Request 1:1".</li>'
				         . '<li><strong>Accept</strong> — When someone requests to meet you, accept or decline here.</li>'
				         . '<li><strong>Schedule</strong> — Coordinate a date and time, then mark it scheduled.</li>'
				         . '<li><strong>Complete</strong> — After your meeting, mark it as complete.</li>'
				         . '<li><strong>Notes</strong> — Capture wins, insights, and action items. This is how the group learns from every connection.</li>'
				         . '</ol>'
				         . '<h3>Automated Suggestions</h3>'
				         . '<p>Each month, the matching system suggests new connections based on who you haven\'t met yet, complementary expertise, and other factors. These appear as suggested meetings with one-click accept.</p>',
			],

			'circleup' => [
				'title' => __('CircleUp Archive', 'circleblast-nexus'),
				'body'  => '<p>The archive of all published CircleUp group meeting summaries — your group\'s collective memory.</p>'
				         . '<h3>Browse Meetings</h3>'
				         . '<p>Each meeting shows a summary of wins, insights, opportunities, and action items. Click into any meeting for the full details.</p>'
				         . '<h3>Quick Share</h3>'
				         . '<p>Have a win, insight, or opportunity to share? Use the <strong>Quick Share</strong> form at the top to submit it anytime — no need to wait for the next meeting. Your submission will be reviewed by the Archivist.</p>'
				         . '<h3>Action Items</h3>'
				         . '<p>Click <strong>"My Action Items"</strong> to see tasks assigned to you across all meetings, with status tracking and due dates.</p>',
			],

			'events' => [
				'title' => __('Events', 'circleblast-nexus'),
				'body'  => '<p>See all upcoming group events and manage your RSVPs.</p>'
				         . '<h3>Calendar View</h3>'
				         . '<p>Events are shown in a calendar format. Click any event for details including time, location, and description.</p>'
				         . '<h3>RSVP</h3>'
				         . '<p>Mark yourself as attending or not attending. Your RSVP helps organizers plan appropriately.</p>'
				         . '<h3>Suggest an Event</h3>'
				         . '<p>Have an idea for a group activity? Use the submission form to propose an event. Admins will review and approve it.</p>',
			],

			'club' => [
				'title' => __('Club Stats', 'circleblast-nexus'),
				'body'  => '<p>Group-wide metrics showing how your circle is growing and connecting.</p>'
				         . '<h3>Stats Cards</h3>'
				         . '<p>The top row shows key group health indicators. Tap the <strong>ⓘ</strong> icon on any card to see what it measures.</p>'
				         . '<h3>Top Connectors</h3>'
				         . '<p>Members ranked by completed 1:1 meetings. See who\'s leading the way in building connections.</p>'
				         . '<h3>Recruitment Coverage</h3>'
				         . '<p>A scorecard showing which professional categories are filled and where there are gaps. Green = covered, gold = partially covered, red = open.</p>'
				         . '<h3>Presentation Mode</h3>'
				         . '<p>Click <strong>"Present"</strong> to launch a full-screen view designed for sharing at CircleUp meetings. Large type, auto-advance sections, and a QR code for members to follow along.</p>',
			],

			'profile' => [
				'title' => __('Your Profile', 'circleblast-nexus'),
				'body'  => '<p>Manage the information other members see about you in the Directory.</p>'
				         . '<h3>What to Update</h3>'
				         . '<ul>'
				         . '<li><strong>Bio</strong> — A brief intro that helps others understand your background and interests.</li>'
				         . '<li><strong>Expertise</strong> — What you\'re great at. This helps the matching engine connect you with complementary members.</li>'
				         . '<li><strong>Looking For</strong> — What kind of help or connections you\'re seeking.</li>'
				         . '<li><strong>Can Help With</strong> — What you can offer to other members.</li>'
				         . '<li><strong>Contact Info</strong> — Phone, LinkedIn, and website so members can reach you outside the portal.</li>'
				         . '</ul>'
				         . '<p>Keep your profile current — the matching engine uses this data to create better 1:1 suggestions.</p>',
			],

			// ── Admin Tab Sections ──────────────────────────────────────

			'manage.members' => [
				'title' => __('Member Management', 'circleblast-nexus'),
				'body'  => '<p>Create, edit, and manage all group members.</p>'
				         . '<h3>Adding a Member</h3>'
				         . '<p>Click <strong>"+ Add Member"</strong> to create a new member. Fill in their profile details and they\'ll receive a welcome email with login credentials.</p>'
				         . '<h3>Member Status</h3>'
				         . '<ul>'
				         . '<li><strong>Active</strong> — Full portal access, included in matching and emails.</li>'
				         . '<li><strong>Inactive</strong> — Portal access removed, excluded from matching. Use for members on leave.</li>'
				         . '<li><strong>Alumni</strong> — Permanently departed. Preserved in history but fully excluded from active features.</li>'
				         . '</ul>'
				         . '<h3>Category Tags</h3>'
				         . '<p>Assign recruitment categories to members to track which professional roles are filled in the group.</p>',
			],

			'manage.recruitment' => [
				'title' => __('Recruitment Management', 'circleblast-nexus'),
				'body'  => '<h3>Recruitment Categories</h3>'
				         . '<p>Define the professional roles your group needs. Set priority levels (high/medium/low), target member counts, and descriptions. These appear throughout the portal to encourage referrals.</p>'
				         . '<h3>Monthly Focus Rotation</h3>'
				         . '<p>The system automatically highlights different categories each month based on coverage gaps and priority. Configure how many categories to feature and how the rotation works.</p>'
				         . '<h3>Candidate Pipeline</h3>'
				         . '<p>Track referrals through the stages: <strong>Referral → Contacted → Invited → Visited → Decision → Accepted/Declined</strong>. Each stage change triggers an email to keep the referrer informed.</p>',
			],

			'manage.matching' => [
				'title' => __('Matching Engine', 'circleblast-nexus'),
				'body'  => '<p>Configure how automatic 1:1 suggestions are generated.</p>'
				         . '<h3>Rules & Weights</h3>'
				         . '<p>Each matching rule can be enabled/disabled and weighted. Higher weight = more influence on who gets matched. Key rules include:</p>'
				         . '<ul>'
				         . '<li><strong>Meeting history</strong> — Prioritize pairs who haven\'t met yet.</li>'
				         . '<li><strong>Expertise complementarity</strong> — Match members with different skills.</li>'
				         . '<li><strong>Looking-for alignment</strong> — Connect people who can help each other.</li>'
				         . '<li><strong>New member priority</strong> — Give newer members extra matching weight.</li>'
				         . '</ul>'
				         . '<h3>Running a Cycle</h3>'
				         . '<p>Click <strong>"Run Suggestion Cycle"</strong> to manually generate matches. Normally this runs automatically via cron (configured in Settings). Each cycle sends suggestion emails to all matched pairs.</p>',
			],

			'manage.archivist' => [
				'title' => __('Meeting Notes (Archivist)', 'circleblast-nexus'),
				'body'  => '<p>Review and publish CircleUp group meeting summaries.</p>'
				         . '<h3>Workflow</h3>'
				         . '<ol>'
				         . '<li><strong>Create</strong> — Add a new CircleUp meeting record. Paste your meeting notes and action items will be extracted automatically.</li>'
				         . '<li><strong>Extract</strong> — Parse structured summaries for items, or run AI extraction (if configured) for deeper analysis. You can also add items manually.</li>'
				         . '<li><strong>Review</strong> — Edit the extracted items, assign speakers and action owners, approve or reject individual items.</li>'
				         . '<li><strong>Publish</strong> — Publish the meeting summary. All active members receive an email recap.</li>'
				         . '</ol>',
			],

			'manage.events' => [
				'title' => __('Events Management', 'circleblast-nexus'),
				'body'  => '<p>Create, approve, and manage group events.</p>'
				         . '<h3>Creating Events</h3>'
				         . '<p>Add events with title, date/time, location, description, and type. Published events appear immediately on the members\' Events page.</p>'
				         . '<h3>Member Submissions</h3>'
				         . '<p>Members can suggest events, which arrive as pending for your approval.</p>'
				         . '<h3>Events Digest</h3>'
				         . '<p>Configure automatic digest emails that summarize upcoming events. Set the frequency and how far ahead to look in Settings › Cron Jobs.</p>',
			],

			'manage.analytics' => [
				'title' => __('Analytics Dashboard', 'circleblast-nexus'),
				'body'  => '<p>Monitor member engagement and group health.</p>'
				         . '<h3>Stat Cards</h3>'
				         . '<p>Click any stat card to filter the member table below. Tap the <strong>ⓘ</strong> icon for an explanation of each metric.</p>'
				         . '<h3>Engagement Table</h3>'
				         . '<p>Each member\'s activity: meetings completed, notes rate, CircleUp attendance, and overall engagement score. Click column headers to sort. Use <strong>"Export CSV"</strong> to download the data.</p>',
			],

			'manage.emails' => [
				'title' => __('Email Templates', 'circleblast-nexus'),
				'body'  => '<p>Customize the emails CircleBlast sends automatically.</p>'
				         . '<h3>Editing Templates</h3>'
				         . '<p>Click <strong>"Edit"</strong> on any template to customize its subject line and body. Use the Visual editor for formatting or switch to HTML for full control.</p>'
				         . '<h3>Placeholders</h3>'
				         . '<p>Insert dynamic placeholders like <code>{{first_name}}</code> or <code>{{portal_url}}</code> that get replaced with real values when the email is sent.</p>'
				         . '<h3>Referral Prompts</h3>'
				         . '<p>Each template can include a recruitment referral section (subtle footer or prominent card). Configure this per template to passively remind members about open roles.</p>'
				         . '<h3>Reset</h3>'
				         . '<p>Click <strong>"Reset to Default"</strong> to discard your customizations and restore the built-in template.</p>',
			],

			'manage.logs' => [
				'title' => __('Plugin Logs', 'circleblast-nexus'),
				'body'  => '<p>System log entries from CircleBlast Nexus for troubleshooting.</p>'
				         . '<p>Logs are automatically cleaned up after 30 days. Use this page to diagnose issues with email delivery, webhook processing, AI extraction, and cron job execution.</p>',
			],

			'manage.help' => [
				'title' => __('Help Content Editor', 'circleblast-nexus'),
				'body'  => '<p>Customize the help text shown when members click the <strong>?</strong> button on each portal page.</p>'
				         . '<h3>How It Works</h3>'
				         . '<p>Each portal page has built-in default help text. You can override any page\'s help by clicking <strong>"Edit"</strong>, making your changes in the visual or HTML editor, and saving.</p>'
				         . '<h3>Reset to Default</h3>'
				         . '<p>If you\'ve customized a page\'s help text, click <strong>"Reset to Default"</strong> to discard your changes and restore the built-in text.</p>',
			],

			'manage.settings' => [
				'title' => __('System Settings', 'circleblast-nexus'),
				'body'  => '<p>Core configuration for the plugin.</p>'
				         . '<h3>Color Scheme</h3>'
				         . '<p>Choose a preset or create a custom color scheme. Changes apply immediately to the portal and all future emails.</p>'
				         . '<h3>Cron Jobs</h3>'
				         . '<p>Control the frequency of automated tasks: meeting reminders, suggestion cycles, AI extraction, analytics snapshots, and more. Set any task to "Disabled" to stop it entirely.</p>'
				         . '<h3>Email Sender</h3>'
				         . '<p>Configure the "From" name and address used on all outgoing emails.</p>'
				         . '<h3>API Keys</h3>'
				         . '<p>Manage keys for external integrations (Claude AI, Fireflies.ai). Keys set in <code>wp-config.php</code> take precedence and can\'t be edited here.</p>',
			],
		];
	}
}