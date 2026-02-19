<?php
/**
 * Portal Admin (Manage)
 *
 * Unified in-portal admin dashboard visible to cb_admin and cb_super_admin.
 * Admin tabs: Members, Recruitment, Matching, Archivist, Events.
 * Super-admin tabs (additional): Analytics, Emails, Logs, Settings.
 *
 * Sub-navigation uses ?section=manage&admin_tab=<tab> pattern.
 */

defined('ABSPATH') || exit;

final class CBNexus_Portal_Admin {

	private static $tabs = [
		// Admin tabs (cb_admin + cb_super_admin).
		'members'     => ['label' => 'Members',     'icon' => '👥', 'cap' => 'cbnexus_manage_members'],
		'recruitment' => ['label' => 'Recruitment',  'icon' => '🎯', 'cap' => 'cbnexus_manage_members'],
		'matching'    => ['label' => 'Matching',     'icon' => '🔗', 'cap' => 'cbnexus_manage_matching_rules'],
		'archivist'   => ['label' => 'Archivist',    'icon' => '📝', 'cap' => 'cbnexus_manage_circleup'],
		'events'      => ['label' => 'Events',       'icon' => '📅', 'cap' => 'cbnexus_manage_members'],
		// Super-admin tabs (cb_super_admin only).
		'analytics'   => ['label' => 'Analytics',    'icon' => '📊', 'cap' => 'cbnexus_export_data'],
		'emails'      => ['label' => 'Emails',       'icon' => '✉️',  'cap' => 'cbnexus_manage_plugin_settings'],
		'logs'        => ['label' => 'Logs',         'icon' => '📋', 'cap' => 'cbnexus_view_logs'],
		'settings'    => ['label' => 'Settings',     'icon' => '⚙️',  'cap' => 'cbnexus_manage_plugin_settings'],
	];

	private static $recruit_stages = [
		'referral'  => 'Referral',
		'contacted' => 'Contacted',
		'invited'   => 'Invited',
		'visited'   => 'Visited',
		'decision'  => 'Decision',
		'accepted'  => 'Accepted',
		'declined'  => 'Declined',
	];

	public static function init(): void {
		add_action('init', [__CLASS__, 'handle_actions']);
	}

	// ─── Actions ────────────────────────────────────────────────────────

	public static function handle_actions(): void {
		if (!is_user_logged_in() || !current_user_can('cbnexus_manage_members')) { return; }

		// Member status change.
		if (isset($_GET['cbnexus_portal_member_action'], $_GET['uid'], $_GET['_panonce'])) {
			self::handle_member_status();
		}
		// Recruitment: add candidate.
		if (isset($_POST['cbnexus_portal_add_candidate'])) {
			self::handle_add_candidate();
		}
		// Recruitment: update candidate stage.
		if (isset($_POST['cbnexus_portal_update_candidate'])) {
			self::handle_update_candidate();
		}
		// Matching: save rules.
		if (isset($_POST['cbnexus_portal_save_rules'])) {
			self::handle_save_rules();
		}
		// Archivist: create meeting.
		if (isset($_POST['cbnexus_portal_create_circleup'])) {
			self::handle_create_circleup();
		}
		// Archivist: save meeting edits.
		if (isset($_POST['cbnexus_portal_save_circleup'])) {
			self::handle_save_circleup();
		}
		// Archivist: run AI extraction.
		if (isset($_GET['cbnexus_portal_extract'])) {
			self::handle_extract();
		}
		// Archivist: publish.
		if (isset($_GET['cbnexus_portal_publish'])) {
			self::handle_publish();
		}
		// Events: approve / cancel / delete.
		if (isset($_GET['cbnexus_portal_event_action'])) {
			self::handle_event_action();
		}
		// Events: save.
		if (isset($_POST['cbnexus_portal_save_event'])) {
			self::handle_save_event();
		}
		// Super-admin: CSV export.
		if (isset($_GET['cbnexus_portal_export']) && $_GET['cbnexus_portal_export'] === 'members') {
			self::handle_export();
		}
		// Super-admin: email template save.
		if (isset($_POST['cbnexus_portal_save_email_tpl'])) {
			self::handle_save_email_template();
		}
		// Super-admin: email template reset.
		if (isset($_GET['cbnexus_portal_reset_tpl'])) {
			self::handle_reset_email_template();
		}
	}

	// ─── Render Entry Point ─────────────────────────────────────────────

	public static function render(array $profile): void {
		if (!current_user_can('cbnexus_manage_members')) {
			echo '<div class="cbnexus-card"><p>You do not have permission to access this page.</p></div>';
			return;
		}

		$tab = isset($_GET['admin_tab']) ? sanitize_key($_GET['admin_tab']) : 'members';
		if (!isset(self::$tabs[$tab]) || !current_user_can(self::$tabs[$tab]['cap'])) {
			$tab = 'members';
		}

		self::render_tab_nav($tab);

		echo '<div class="cbnexus-admin-content">';
		switch ($tab) {
			case 'members':     self::render_members(); break;
			case 'recruitment': self::render_recruitment(); break;
			case 'matching':    self::render_matching(); break;
			case 'archivist':   self::render_archivist(); break;
			case 'events':      self::render_events(); break;
			case 'analytics':   self::render_analytics(); break;
			case 'emails':      self::render_emails(); break;
			case 'logs':        self::render_logs(); break;
			case 'settings':    self::render_settings(); break;
		}
		echo '</div>';
	}

	private static function render_tab_nav(string $current): void {
		$portal_url = CBNexus_Portal_Router::get_portal_url();
		$base_url   = add_query_arg('section', 'manage', $portal_url);
		?>
		<div class="cbnexus-admin-tabs">
			<?php foreach (self::$tabs as $slug => $tab) :
				if (!current_user_can($tab['cap'])) { continue; }
				$is_active = $slug === $current;
				$url = add_query_arg('admin_tab', $slug, $base_url);
			?>
				<a href="<?php echo esc_url($url); ?>" class="cbnexus-admin-tab <?php echo $is_active ? 'active' : ''; ?>">
					<span class="cbnexus-admin-tab-icon"><?php echo esc_html($tab['icon']); ?></span>
					<?php echo esc_html($tab['label']); ?>
				</a>
			<?php endforeach; ?>
		</div>
		<?php
	}

	// ─── Helper: portal admin URL builder ───────────────────────────────

	private static function admin_url(string $tab = 'members', array $extra = []): string {
		$portal_url = CBNexus_Portal_Router::get_portal_url();
		$args = array_merge(['section' => 'manage', 'admin_tab' => $tab], $extra);
		return add_query_arg($args, $portal_url);
	}

	// =====================================================================
	//  MEMBERS TAB
	// =====================================================================

	private static function render_members(): void {
		$notice = sanitize_key($_GET['pa_notice'] ?? '');
		$filter_status = sanitize_key($_GET['status'] ?? '');
		$search = sanitize_text_field($_GET['s'] ?? '');

		$members = ($search !== '')
			? CBNexus_Member_Repository::search($search, $filter_status)
			: CBNexus_Member_Repository::get_all_members($filter_status);

		$counts = CBNexus_Member_Repository::count_by_status();
		$base   = self::admin_url('members');
		?>
		<?php self::render_notice($notice); ?>

		<div class="cbnexus-card">
			<div class="cbnexus-admin-header-row">
				<h2>Members (<?php echo esc_html($counts['total']); ?>)</h2>
				<?php if (current_user_can('cbnexus_create_members')) : ?>
					<a href="<?php echo esc_url(admin_url('admin.php?page=cbnexus-member-new')); ?>" class="cbnexus-btn cbnexus-btn-accent" target="_blank">+ Add New Member</a>
				<?php endif; ?>
			</div>

			<!-- Status filters -->
			<div class="cbnexus-admin-filters">
				<a href="<?php echo esc_url($base); ?>" class="<?php echo $filter_status === '' ? 'active' : ''; ?>">All (<?php echo esc_html($counts['total']); ?>)</a>
				<a href="<?php echo esc_url(add_query_arg('status', 'active', $base)); ?>" class="<?php echo $filter_status === 'active' ? 'active' : ''; ?>">Active (<?php echo esc_html($counts['active']); ?>)</a>
				<a href="<?php echo esc_url(add_query_arg('status', 'inactive', $base)); ?>" class="<?php echo $filter_status === 'inactive' ? 'active' : ''; ?>">Inactive (<?php echo esc_html($counts['inactive']); ?>)</a>
				<a href="<?php echo esc_url(add_query_arg('status', 'alumni', $base)); ?>" class="<?php echo $filter_status === 'alumni' ? 'active' : ''; ?>">Alumni (<?php echo esc_html($counts['alumni']); ?>)</a>
			</div>

			<!-- Search -->
			<form method="get" action="<?php echo esc_url($base); ?>" class="cbnexus-admin-search">
				<input type="hidden" name="section" value="admin" />
				<input type="hidden" name="admin_tab" value="members" />
				<?php if ($filter_status) : ?><input type="hidden" name="status" value="<?php echo esc_attr($filter_status); ?>" /><?php endif; ?>
				<input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search by name or email…" />
				<button type="submit" class="cbnexus-btn">Search</button>
			</form>

			<!-- Table -->
			<div class="cbnexus-admin-table-wrap">
				<table class="cbnexus-admin-table">
					<thead><tr>
						<th>Name</th>
						<th>Company</th>
						<th>Industry</th>
						<th>Status</th>
						<th>Joined</th>
						<th>Actions</th>
					</tr></thead>
					<tbody>
					<?php if (empty($members)) : ?>
						<tr><td colspan="6" class="cbnexus-admin-empty">No members found.</td></tr>
					<?php else : foreach ($members as $m) :
						$uid    = $m['user_id'];
						$status = $m['cb_member_status'] ?? 'active';
						$edit   = admin_url('admin.php?page=cbnexus-member-new&edit=' . $uid);
					?>
						<tr>
							<td>
								<strong><?php echo esc_html($m['display_name']); ?></strong>
								<div class="cbnexus-admin-meta"><?php echo esc_html($m['user_email']); ?></div>
							</td>
							<td><?php echo esc_html($m['cb_company'] ?? '—'); ?></td>
							<td><?php echo esc_html($m['cb_industry'] ?? '—'); ?></td>
							<td><?php self::status_pill($status); ?></td>
							<td class="cbnexus-admin-meta"><?php echo esc_html($m['cb_join_date'] ?? '—'); ?></td>
							<td class="cbnexus-admin-actions-cell">
								<a href="<?php echo esc_url($edit); ?>" target="_blank" class="cbnexus-link">Edit</a>
								<?php if ($status !== 'active') : ?>
									<a href="<?php echo esc_url(self::member_action_url('activate', $uid)); ?>" class="cbnexus-link cbnexus-link-green">Activate</a>
								<?php endif; ?>
								<?php if ($status !== 'inactive') : ?>
									<a href="<?php echo esc_url(self::member_action_url('deactivate', $uid)); ?>" class="cbnexus-link cbnexus-link-red">Deactivate</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	private static function member_action_url(string $action, int $uid): string {
		$base = self::admin_url('members');
		$url  = add_query_arg([
			'cbnexus_portal_member_action' => $action,
			'uid' => $uid,
		], $base);
		return wp_nonce_url($url, 'cbnexus_pa_member_' . $uid, '_panonce');
	}

	private static function handle_member_status(): void {
		$action = sanitize_key($_GET['cbnexus_portal_member_action']);
		$uid    = absint($_GET['uid']);

		if (!wp_verify_nonce(wp_unslash($_GET['_panonce']), 'cbnexus_pa_member_' . $uid)) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }

		$map = ['activate' => 'active', 'deactivate' => 'inactive', 'alumni' => 'alumni'];
		if (!isset($map[$action])) { return; }

		$result = CBNexus_Member_Service::transition_status($uid, $map[$action]);
		$notice = $result['success'] ? 'status_updated' : 'error';
		wp_safe_redirect(self::admin_url('members', ['pa_notice' => $notice]));
		exit;
	}

	// =====================================================================
	//  RECRUITMENT TAB
	// =====================================================================

	private static function render_recruitment(): void {
		global $wpdb;
		$table  = $wpdb->prefix . 'cb_candidates';
		$notice = sanitize_key($_GET['pa_notice'] ?? '');
		$filter = sanitize_key($_GET['stage'] ?? '');
		$members = CBNexus_Member_Repository::get_all_members('active');

		// Stage counts.
		$stage_counts = [];
		foreach (self::$recruit_stages as $key => $label) {
			$stage_counts[$key] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE stage = %s", $key));
		}

		// Candidates.
		$sql = "SELECT c.*, u.display_name as referrer_name FROM {$table} c LEFT JOIN {$wpdb->users} u ON c.referrer_id = u.ID";
		if ($filter !== '' && isset(self::$recruit_stages[$filter])) {
			$sql .= $wpdb->prepare(" WHERE c.stage = %s", $filter);
		}
		$sql .= " ORDER BY c.updated_at DESC";
		$candidates = $wpdb->get_results($sql);

		$base = self::admin_url('recruitment');
		?>
		<?php self::render_notice($notice); ?>

		<div class="cbnexus-card">
			<h2>Recruitment Pipeline</h2>

			<!-- Funnel -->
			<div class="cbnexus-admin-filters">
				<a href="<?php echo esc_url($base); ?>" class="<?php echo $filter === '' ? 'active' : ''; ?>">All (<?php echo array_sum($stage_counts); ?>)</a>
				<?php foreach (self::$recruit_stages as $key => $label) : ?>
					<a href="<?php echo esc_url(add_query_arg('stage', $key, $base)); ?>" class="<?php echo $filter === $key ? 'active' : ''; ?>"><?php echo esc_html($label); ?> (<?php echo esc_html($stage_counts[$key]); ?>)</a>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Add Candidate -->
		<div class="cbnexus-card">
			<h3>Add Candidate</h3>
			<form method="post" action="" class="cbnexus-admin-inline-form">
				<?php wp_nonce_field('cbnexus_portal_add_candidate'); ?>
				<div class="cbnexus-admin-form-grid">
					<div>
						<label>Name *</label>
						<input type="text" name="name" required />
					</div>
					<div>
						<label>Email</label>
						<input type="email" name="email" />
					</div>
					<div>
						<label>Company</label>
						<input type="text" name="company" />
					</div>
					<div>
						<label>Industry</label>
						<input type="text" name="industry" />
					</div>
					<div>
						<label>Referred By</label>
						<select name="referrer_id">
							<option value="0">—</option>
							<?php foreach ($members as $m) : ?><option value="<?php echo esc_attr($m['user_id']); ?>"><?php echo esc_html($m['display_name']); ?></option><?php endforeach; ?>
						</select>
					</div>
					<div>
						<label>Notes</label>
						<input type="text" name="notes" />
					</div>
				</div>
				<button type="submit" name="cbnexus_portal_add_candidate" value="1" class="cbnexus-btn cbnexus-btn-accent">Add Candidate</button>
			</form>
		</div>

		<!-- Candidates Table -->
		<div class="cbnexus-card">
			<div class="cbnexus-admin-table-wrap">
				<table class="cbnexus-admin-table">
					<thead><tr>
						<th>Candidate</th>
						<th>Company</th>
						<th>Referred By</th>
						<th>Stage</th>
						<th>Notes</th>
						<th>Updated</th>
					</tr></thead>
					<tbody>
					<?php if (empty($candidates)) : ?>
						<tr><td colspan="6" class="cbnexus-admin-empty">No candidates yet.</td></tr>
					<?php else : foreach ($candidates as $c) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html($c->name); ?></strong>
								<?php if ($c->email) : ?><div class="cbnexus-admin-meta"><?php echo esc_html($c->email); ?></div><?php endif; ?>
							</td>
							<td><?php echo esc_html($c->company ?: '—'); ?></td>
							<td><?php echo esc_html($c->referrer_name ?: '—'); ?></td>
							<td>
								<form method="post" action="" class="cbnexus-admin-stage-form">
									<?php wp_nonce_field('cbnexus_portal_update_candidate'); ?>
									<input type="hidden" name="candidate_id" value="<?php echo esc_attr($c->id); ?>" />
									<input type="hidden" name="notes" value="<?php echo esc_attr($c->notes); ?>" />
									<input type="hidden" name="cbnexus_portal_update_candidate" value="1" />
									<select name="stage" onchange="this.form.submit();">
										<?php foreach (self::$recruit_stages as $key => $label) : ?>
											<option value="<?php echo esc_attr($key); ?>" <?php selected($c->stage, $key); ?>><?php echo esc_html($label); ?></option>
										<?php endforeach; ?>
									</select>
								</form>
							</td>
							<td class="cbnexus-admin-meta"><?php echo esc_html($c->notes ?: '—'); ?></td>
							<td class="cbnexus-admin-meta"><?php echo esc_html(date_i18n('M j', strtotime($c->updated_at))); ?></td>
						</tr>
					<?php endforeach; endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	private static function handle_add_candidate(): void {
		if (!wp_verify_nonce(wp_unslash($_POST['_wpnonce'] ?? ''), 'cbnexus_portal_add_candidate')) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }

		global $wpdb;
		$now = gmdate('Y-m-d H:i:s');

		$wpdb->insert($wpdb->prefix . 'cb_candidates', [
			'name'        => sanitize_text_field($_POST['name'] ?? ''),
			'email'       => sanitize_email($_POST['email'] ?? ''),
			'company'     => sanitize_text_field($_POST['company'] ?? ''),
			'industry'    => sanitize_text_field($_POST['industry'] ?? ''),
			'referrer_id' => absint($_POST['referrer_id'] ?? 0) ?: null,
			'stage'       => 'referral',
			'notes'       => sanitize_textarea_field(wp_unslash($_POST['notes'] ?? '')),
			'created_at'  => $now,
			'updated_at'  => $now,
		], ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']);

		wp_safe_redirect(self::admin_url('recruitment', ['pa_notice' => 'candidate_added']));
		exit;
	}

	private static function handle_update_candidate(): void {
		if (!wp_verify_nonce(wp_unslash($_POST['_wpnonce'] ?? ''), 'cbnexus_portal_update_candidate')) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }

		global $wpdb;
		$wpdb->update($wpdb->prefix . 'cb_candidates', [
			'stage'      => sanitize_key($_POST['stage'] ?? 'referral'),
			'notes'      => sanitize_textarea_field(wp_unslash($_POST['notes'] ?? '')),
			'updated_at' => gmdate('Y-m-d H:i:s'),
		], ['id' => absint($_POST['candidate_id'] ?? 0)], ['%s', '%s', '%s'], ['%d']);

		wp_safe_redirect(self::admin_url('recruitment', ['pa_notice' => 'candidate_updated']));
		exit;
	}

	// =====================================================================
	//  MATCHING TAB
	// =====================================================================

	private static function render_matching(): void {
		if (!current_user_can('cbnexus_manage_matching_rules')) {
			echo '<div class="cbnexus-card"><p>Permission denied.</p></div>';
			return;
		}

		$rules   = CBNexus_Matching_Engine::get_all_rules();
		$dry_run = isset($_GET['dry_run']);
		$suggestions = $dry_run ? CBNexus_Matching_Engine::dry_run(20) : [];
		$notice  = sanitize_key($_GET['pa_notice'] ?? '');

		$last_cycle  = CBNexus_Suggestion_Generator::get_last_cycle();
		$cycle_stats = CBNexus_Suggestion_Generator::get_cycle_stats();
		?>
		<?php self::render_notice($notice); ?>

		<!-- Cycle Status -->
		<div class="cbnexus-card">
			<h2>Suggestion Cycle</h2>
			<div class="cbnexus-admin-stats-row">
				<?php self::stat_card('Last Run', $last_cycle ? esc_html($last_cycle['timestamp']) : 'Never'); ?>
				<?php self::stat_card('Total Suggestions', $cycle_stats['total']); ?>
				<?php self::stat_card('Pending', $cycle_stats['pending']); ?>
				<?php self::stat_card('Accepted', $cycle_stats['accepted']); ?>
				<?php if ($cycle_stats['total'] > 0) : self::stat_card('Accept Rate', round($cycle_stats['accepted'] / $cycle_stats['total'] * 100) . '%'); endif; ?>
			</div>
			<div class="cbnexus-admin-button-row">
				<a href="<?php echo esc_url(self::admin_url('matching', ['dry_run' => 1])); ?>" class="cbnexus-btn">Preview Suggestions</a>
				<?php if (current_user_can('cbnexus_run_matching_cycle')) : ?>
					<a href="<?php echo esc_url(wp_nonce_url(
						self::admin_url('matching', ['cbnexus_portal_run_cycle' => 1]),
						'cbnexus_portal_run_cycle', '_panonce'
					)); ?>" class="cbnexus-btn cbnexus-btn-accent" onclick="return confirm('Run the suggestion cycle? This will send match emails to all paired members.');">Run Cycle</a>
				<?php endif; ?>
			</div>
		</div>

		<!-- Rules Config -->
		<div class="cbnexus-card">
			<h2>Matching Rules</h2>
			<form method="post" action="">
				<?php wp_nonce_field('cbnexus_portal_save_matching_rules'); ?>
				<div class="cbnexus-admin-table-wrap">
					<table class="cbnexus-admin-table">
						<thead><tr>
							<th style="width:50px;">Active</th>
							<th>Rule</th>
							<th>Description</th>
							<th style="width:90px;">Weight</th>
						</tr></thead>
						<tbody>
						<?php foreach ($rules as $rule) : ?>
							<tr>
								<td><input type="checkbox" name="active_<?php echo esc_attr($rule->id); ?>" value="1" <?php checked($rule->is_active, 1); ?> /></td>
								<td><strong><?php echo esc_html($rule->label); ?></strong></td>
								<td class="cbnexus-admin-meta"><?php echo esc_html($rule->description); ?></td>
								<td><input type="number" name="weight_<?php echo esc_attr($rule->id); ?>" value="<?php echo esc_attr($rule->weight); ?>" step="0.25" min="-5" max="10" class="cbnexus-input-sm" /></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<button type="submit" name="cbnexus_portal_save_rules" value="1" class="cbnexus-btn cbnexus-btn-accent">Save Rules</button>
			</form>
		</div>

		<?php if ($dry_run) : ?>
		<div class="cbnexus-card">
			<h2>Dry Run Preview</h2>
			<?php if (empty($suggestions)) : ?>
				<p class="cbnexus-text-muted">No suggestions generated. Ensure at least 2 active members and active rules.</p>
			<?php else : ?>
				<div class="cbnexus-admin-table-wrap">
					<table class="cbnexus-admin-table">
						<thead><tr>
							<th style="width:40px;">#</th>
							<th>Member A</th>
							<th>Member B</th>
							<th style="width:70px;">Score</th>
						</tr></thead>
						<tbody>
						<?php $rank = 0; foreach ($suggestions as $s) : $rank++; ?>
							<tr>
								<td><?php echo esc_html($rank); ?></td>
								<td><?php echo esc_html($s['member_a_name']); ?></td>
								<td><?php echo esc_html($s['member_b_name']); ?></td>
								<td><strong><?php echo esc_html($s['score']); ?></strong></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
		<?php endif; ?>
		<?php
	}

	private static function handle_save_rules(): void {
		if (!wp_verify_nonce(wp_unslash($_POST['_wpnonce'] ?? ''), 'cbnexus_portal_save_matching_rules')) { return; }
		if (!current_user_can('cbnexus_manage_matching_rules')) { return; }

		$rules = CBNexus_Matching_Engine::get_all_rules();
		foreach ($rules as $rule) {
			$id = (int) $rule->id;
			CBNexus_Matching_Engine::update_rule($id, [
				'weight'    => isset($_POST['weight_' . $id]) ? (float) $_POST['weight_' . $id] : (float) $rule->weight,
				'is_active' => isset($_POST['active_' . $id]) ? 1 : 0,
			]);
		}

		wp_safe_redirect(self::admin_url('matching', ['pa_notice' => 'rules_saved']));
		exit;
	}

	// =====================================================================
	//  ARCHIVIST TAB
	// =====================================================================

	private static function render_archivist(): void {
		if (!current_user_can('cbnexus_manage_circleup')) {
			echo '<div class="cbnexus-card"><p>Permission denied.</p></div>';
			return;
		}

		// Sub-views.
		if (isset($_GET['circleup_id'])) {
			self::render_archivist_edit(absint($_GET['circleup_id']));
			return;
		}
		if (isset($_GET['admin_action']) && $_GET['admin_action'] === 'new_circleup') {
			self::render_archivist_add();
			return;
		}

		$notice = sanitize_key($_GET['pa_notice'] ?? '');
		self::render_notice($notice);

		// List meetings.
		$meetings = CBNexus_CircleUp_Repository::get_meetings();
		?>
		<div class="cbnexus-card">
			<div class="cbnexus-admin-header-row">
				<h2>CircleUp Meetings</h2>
				<a href="<?php echo esc_url(self::admin_url('archivist', ['admin_action' => 'new_circleup'])); ?>" class="cbnexus-btn cbnexus-btn-accent">+ Add Meeting</a>
			</div>

			<div class="cbnexus-admin-table-wrap">
				<table class="cbnexus-admin-table">
					<thead><tr>
						<th>Date</th>
						<th>Title</th>
						<th>Status</th>
						<th>Items</th>
						<th>Actions</th>
					</tr></thead>
					<tbody>
					<?php if (empty($meetings)) : ?>
						<tr><td colspan="5" class="cbnexus-admin-empty">No CircleUp meetings yet.</td></tr>
					<?php else : foreach ($meetings as $m) :
						$items = CBNexus_CircleUp_Repository::get_items($m->id);
						$item_count = count($items);
					?>
						<tr>
							<td><?php echo esc_html(date_i18n('M j, Y', strtotime($m->meeting_date))); ?></td>
							<td><strong><?php echo esc_html($m->title); ?></strong></td>
							<td><?php self::status_pill($m->status); ?></td>
							<td><?php echo esc_html($item_count); ?></td>
							<td>
								<a href="<?php echo esc_url(self::admin_url('archivist', ['circleup_id' => $m->id])); ?>" class="cbnexus-link">Review</a>
							</td>
						</tr>
					<?php endforeach; endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	private static function render_archivist_add(): void {
		?>
		<div class="cbnexus-card">
			<h2>Add CircleUp Meeting</h2>
			<form method="post" action="">
				<?php wp_nonce_field('cbnexus_portal_create_circleup'); ?>
				<div class="cbnexus-admin-form-stack">
					<div>
						<label>Title *</label>
						<input type="text" name="title" required />
					</div>
					<div>
						<label>Meeting Date *</label>
						<input type="date" name="meeting_date" required value="<?php echo esc_attr(gmdate('Y-m-d')); ?>" />
					</div>
					<div>
						<label>Duration (minutes)</label>
						<input type="number" name="duration_minutes" value="60" />
					</div>
					<div>
						<label>Transcript</label>
						<textarea name="full_transcript" rows="8" placeholder="Paste meeting transcript here…"></textarea>
					</div>
				</div>
				<button type="submit" name="cbnexus_portal_create_circleup" value="1" class="cbnexus-btn cbnexus-btn-accent">Create Meeting</button>
				<a href="<?php echo esc_url(self::admin_url('archivist')); ?>" class="cbnexus-btn">Cancel</a>
			</form>
		</div>
		<?php
	}

	private static function render_archivist_edit(int $id): void {
		$meeting = CBNexus_CircleUp_Repository::get_meeting($id);
		if (!$meeting) {
			echo '<div class="cbnexus-card"><p>Meeting not found.</p></div>';
			return;
		}

		$items    = CBNexus_CircleUp_Repository::get_items($id);
		$members  = CBNexus_Member_Repository::get_all_members('active');
		$attendees = CBNexus_CircleUp_Repository::get_attendees($id);
		$attendee_ids = array_column($attendees, 'member_id');
		$notice = sanitize_key($_GET['pa_notice'] ?? '');
		$base = self::admin_url('archivist', ['circleup_id' => $id]);
		?>
		<?php self::render_notice($notice); ?>

		<div class="cbnexus-card">
			<div class="cbnexus-admin-header-row">
				<h2><?php echo esc_html($meeting->title); ?></h2>
				<a href="<?php echo esc_url(self::admin_url('archivist')); ?>" class="cbnexus-btn">← Back</a>
			</div>
			<div class="cbnexus-admin-meta"><?php echo esc_html(date_i18n('F j, Y', strtotime($meeting->meeting_date))); ?> · Status: <?php echo esc_html(ucfirst($meeting->status)); ?></div>
		</div>

		<!-- Summary & Attendees -->
		<div class="cbnexus-card">
			<form method="post" action="">
				<?php wp_nonce_field('cbnexus_portal_save_circleup'); ?>
				<input type="hidden" name="circleup_id" value="<?php echo esc_attr($id); ?>" />
				<div class="cbnexus-admin-form-stack">
					<div>
						<label>Curated Summary</label>
						<textarea name="curated_summary" rows="5"><?php echo esc_textarea($meeting->curated_summary ?? ''); ?></textarea>
					</div>
					<div>
						<label>Attendees</label>
						<div class="cbnexus-admin-checkbox-grid">
							<?php foreach ($members as $m) : ?>
								<label><input type="checkbox" name="attendees[]" value="<?php echo esc_attr($m['user_id']); ?>" <?php echo in_array((int) $m['user_id'], array_map('intval', $attendee_ids), true) ? 'checked' : ''; ?> /> <?php echo esc_html($m['display_name']); ?></label>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
				<button type="submit" name="cbnexus_portal_save_circleup" value="1" class="cbnexus-btn cbnexus-btn-accent">Save</button>
			</form>
		</div>

		<!-- Extracted Items -->
		<?php if (!empty($items)) : ?>
		<div class="cbnexus-card">
			<h3>Extracted Items (<?php echo count($items); ?>)</h3>
			<?php
			$grouped = [];
			foreach ($items as $item) { $grouped[$item->item_type][] = $item; }
			foreach (['win', 'insight', 'opportunity', 'action'] as $type) :
				if (empty($grouped[$type])) { continue; }
			?>
				<h4 style="text-transform:capitalize;margin:16px 0 8px;"><?php echo esc_html($type); ?>s (<?php echo count($grouped[$type]); ?>)</h4>
				<?php foreach ($grouped[$type] as $item) : ?>
					<div class="cbnexus-admin-item-row">
						<span><?php echo esc_html($item->content); ?></span>
						<span class="cbnexus-admin-meta"><?php echo esc_html(ucfirst($item->status)); ?></span>
					</div>
				<?php endforeach; ?>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<!-- Actions -->
		<div class="cbnexus-card">
			<h3>Actions</h3>
			<div class="cbnexus-admin-button-row">
				<?php if ($meeting->full_transcript) : ?>
					<a href="<?php echo esc_url(wp_nonce_url(add_query_arg('cbnexus_portal_extract', $id, $base), 'cbnexus_portal_extract_' . $id, '_panonce')); ?>" class="cbnexus-btn" onclick="return confirm('Run AI extraction? This will replace existing items.');">Run AI Extraction</a>
				<?php endif; ?>
				<?php if ($meeting->status !== 'published' && current_user_can('cbnexus_publish_circleup')) : ?>
					<a href="<?php echo esc_url(wp_nonce_url(add_query_arg('cbnexus_portal_publish', $id, $base), 'cbnexus_portal_publish_' . $id, '_panonce')); ?>" class="cbnexus-btn cbnexus-btn-accent" onclick="return confirm('Publish and email summary to all members?');">Publish &amp; Email</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private static function handle_create_circleup(): void {
		if (!wp_verify_nonce(wp_unslash($_POST['_wpnonce'] ?? ''), 'cbnexus_portal_create_circleup')) { return; }
		if (!current_user_can('cbnexus_manage_circleup')) { return; }

		$id = CBNexus_CircleUp_Repository::create_meeting([
			'title'            => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
			'meeting_date'     => sanitize_text_field(wp_unslash($_POST['meeting_date'] ?? '')),
			'duration_minutes' => absint($_POST['duration_minutes'] ?? 60),
			'full_transcript'  => wp_unslash($_POST['full_transcript'] ?? ''),
			'status'           => 'draft',
		]);

		wp_safe_redirect(self::admin_url('archivist', ['circleup_id' => $id, 'pa_notice' => 'circleup_created']));
		exit;
	}

	private static function handle_save_circleup(): void {
		if (!wp_verify_nonce(wp_unslash($_POST['_wpnonce'] ?? ''), 'cbnexus_portal_save_circleup')) { return; }
		if (!current_user_can('cbnexus_manage_circleup')) { return; }

		$id = absint($_POST['circleup_id'] ?? 0);
		CBNexus_CircleUp_Repository::update_meeting($id, [
			'curated_summary' => wp_unslash($_POST['curated_summary'] ?? ''),
		]);

		// Sync attendees.
		$attendee_ids = array_map('absint', (array) ($_POST['attendees'] ?? []));
		// Sync attendees: clear existing, re-add checked ones.
		global $wpdb;
		$wpdb->delete($wpdb->prefix . 'cb_circleup_attendees', ['circleup_meeting_id' => $id], ['%d']);
		foreach ($attendee_ids as $aid) {
			if ($aid > 0) {
				CBNexus_CircleUp_Repository::add_attendee($id, $aid, 'present');
			}
		}

		wp_safe_redirect(self::admin_url('archivist', ['circleup_id' => $id, 'pa_notice' => 'circleup_saved']));
		exit;
	}

	private static function handle_extract(): void {
		$id = absint($_GET['cbnexus_portal_extract']);
		if (!wp_verify_nonce(wp_unslash($_GET['_panonce'] ?? ''), 'cbnexus_portal_extract_' . $id)) { return; }
		if (!current_user_can('cbnexus_manage_circleup')) { return; }

		CBNexus_AI_Extractor::process_meeting($id);

		wp_safe_redirect(self::admin_url('archivist', ['circleup_id' => $id, 'pa_notice' => 'extraction_done']));
		exit;
	}

	private static function handle_publish(): void {
		$id = absint($_GET['cbnexus_portal_publish']);
		if (!wp_verify_nonce(wp_unslash($_GET['_panonce'] ?? ''), 'cbnexus_portal_publish_' . $id)) { return; }
		if (!current_user_can('cbnexus_publish_circleup')) { return; }

		CBNexus_CircleUp_Repository::update_meeting($id, [
			'status'       => 'published',
			'published_by' => get_current_user_id(),
			'published_at' => gmdate('Y-m-d H:i:s'),
		]);

		// Send summary email.
		$meeting = CBNexus_CircleUp_Repository::get_meeting($id);
		$items   = CBNexus_CircleUp_Repository::get_items($id);
		$wins    = count(array_filter($items, fn($i) => $i->item_type === 'win' && $i->status === 'approved'));
		$insights = count(array_filter($items, fn($i) => $i->item_type === 'insight' && $i->status === 'approved'));
		$actions = count(array_filter($items, fn($i) => $i->item_type === 'action' && $i->status === 'approved'));

		$all_members = CBNexus_Member_Repository::get_all_members('active');
		foreach ($all_members as $m) {
			CBNexus_Email_Service::send('circleup_summary', $m['user_email'], [
				'first_name'      => $m['first_name'],
				'meeting_title'   => $meeting->title,
				'meeting_date'    => date_i18n('F j, Y', strtotime($meeting->meeting_date)),
				'curated_summary' => $meeting->curated_summary ?? '',
				'wins_count'      => $wins,
				'insights_count'  => $insights,
				'actions_count'   => $actions,
				'portal_url'      => CBNexus_Portal_Router::get_portal_url(),
			], ['recipient_id' => (int) $m['user_id'], 'related_type' => 'circleup_publish']);
		}

		wp_safe_redirect(self::admin_url('archivist', ['circleup_id' => $id, 'pa_notice' => 'published']));
		exit;
	}

	// =====================================================================
	//  EVENTS TAB
	// =====================================================================

	private static function render_events(): void {
		$notice = sanitize_key($_GET['pa_notice'] ?? '');
		self::render_notice($notice);

		// If editing or creating, show the form instead of the list.
		$edit_id = absint($_GET['edit_event'] ?? 0);
		if ($edit_id || isset($_GET['new_event'])) {
			self::render_event_form($edit_id);
			return;
		}

		$events = CBNexus_Event_Repository::query();
		?>
		<div class="cbnexus-card">
			<div class="cbnexus-admin-header-row">
				<h2>Events</h2>
				<a href="<?php echo esc_url(self::admin_url('events', ['new_event' => '1'])); ?>" class="cbnexus-btn cbnexus-btn-primary cbnexus-btn-sm">+ Add Event</a>
			</div>

			<div class="cbnexus-admin-table-wrap">
				<table class="cbnexus-admin-table">
					<thead><tr>
						<th>Date</th>
						<th>Event</th>
						<th>Location</th>
						<th>Status</th>
						<th>RSVPs</th>
						<th>Actions</th>
					</tr></thead>
					<tbody>
					<?php if (empty($events)) : ?>
						<tr><td colspan="6" class="cbnexus-admin-empty">No events yet.</td></tr>
					<?php else : foreach ($events as $e) :
						$rsvps = CBNexus_Event_Repository::get_rsvp_counts($e->id);
						$rsvp_total = ($rsvps['going'] ?? 0) + ($rsvps['maybe'] ?? 0);
					?>
						<tr>
							<td><?php echo esc_html(date_i18n('M j, Y', strtotime($e->event_date))); ?></td>
							<td><strong><?php echo esc_html($e->title); ?></strong></td>
							<td class="cbnexus-admin-meta"><?php echo esc_html($e->location ?: '—'); ?></td>
							<td><?php self::status_pill($e->status); ?></td>
							<td><?php echo esc_html($rsvp_total); ?></td>
							<td class="cbnexus-admin-actions-cell">
								<a href="<?php echo esc_url(self::admin_url('events', ['edit_event' => $e->id])); ?>" class="cbnexus-link">Edit</a>
								<?php if ($e->status === 'pending') : ?>
									<a href="<?php echo esc_url(wp_nonce_url(self::admin_url('events', ['cbnexus_portal_event_action' => 'approve', 'event_id' => $e->id]), 'cbnexus_portal_event_' . $e->id, '_panonce')); ?>" class="cbnexus-link cbnexus-link-green">Approve</a>
								<?php endif; ?>
								<?php if ($e->status !== 'cancelled') : ?>
									<a href="<?php echo esc_url(wp_nonce_url(self::admin_url('events', ['cbnexus_portal_event_action' => 'cancel', 'event_id' => $e->id]), 'cbnexus_portal_event_' . $e->id, '_panonce')); ?>" class="cbnexus-link cbnexus-link-red">Cancel</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	private static function render_event_form(int $id): void {
		$event = $id ? CBNexus_Event_Repository::get($id) : null;
		$categories = defined('CBNexus_Event_Service::CATEGORIES') || method_exists('CBNexus_Event_Service', 'get_categories')
			? CBNexus_Event_Service::CATEGORIES
			: [];
		?>
		<div class="cbnexus-card">
			<div class="cbnexus-admin-header-row">
				<h2><?php echo $event ? 'Edit Event' : 'Add New Event'; ?></h2>
				<a href="<?php echo esc_url(self::admin_url('events')); ?>" class="cbnexus-btn cbnexus-btn-outline cbnexus-btn-sm">← Back</a>
			</div>

			<form method="post" style="max-width:600px;">
				<?php wp_nonce_field('cbnexus_portal_save_event', '_panonce'); ?>
				<?php if ($id) : ?><input type="hidden" name="event_id" value="<?php echo esc_attr($id); ?>" /><?php endif; ?>

				<div style="display:flex;flex-direction:column;gap:12px;margin-top:12px;">
					<div>
						<label style="display:block;font-weight:600;margin-bottom:4px;">Title *</label>
						<input type="text" name="title" value="<?php echo esc_attr($event->title ?? ''); ?>" class="cbnexus-input" style="width:100%;" required />
					</div>
					<div style="display:flex;gap:12px;">
						<div style="flex:1;">
							<label style="display:block;font-weight:600;margin-bottom:4px;">Date *</label>
							<input type="date" name="event_date" value="<?php echo esc_attr($event->event_date ?? ''); ?>" class="cbnexus-input" required />
						</div>
						<div>
							<label style="display:block;font-weight:600;margin-bottom:4px;">Start Time</label>
							<input type="time" name="event_time" value="<?php echo esc_attr($event->event_time ?? ''); ?>" class="cbnexus-input" />
						</div>
						<div>
							<label style="display:block;font-weight:600;margin-bottom:4px;">End Time</label>
							<input type="time" name="end_time" value="<?php echo esc_attr($event->end_time ?? ''); ?>" class="cbnexus-input" />
						</div>
					</div>
					<div>
						<label style="display:block;font-weight:600;margin-bottom:4px;">Description</label>
						<textarea name="description" rows="3" class="cbnexus-input" style="width:100%;"><?php echo esc_textarea($event->description ?? ''); ?></textarea>
					</div>
					<div>
						<label style="display:block;font-weight:600;margin-bottom:4px;">Location</label>
						<input type="text" name="location" value="<?php echo esc_attr($event->location ?? ''); ?>" class="cbnexus-input" style="width:100%;" />
					</div>
					<div style="display:flex;gap:12px;">
						<div style="flex:1;">
							<label style="display:block;font-weight:600;margin-bottom:4px;">Category</label>
							<select name="category" class="cbnexus-input">
								<option value="">—</option>
								<?php foreach ($categories as $k => $v) : ?>
									<option value="<?php echo esc_attr($k); ?>" <?php selected($event->category ?? '', $k); ?>><?php echo esc_html($v); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div style="flex:1;">
							<label style="display:block;font-weight:600;margin-bottom:4px;">Audience</label>
							<select name="audience" class="cbnexus-input">
								<option value="all" <?php selected($event->audience ?? '', 'all'); ?>>Everyone</option>
								<option value="members" <?php selected($event->audience ?? '', 'members'); ?>>Members Only</option>
								<option value="public" <?php selected($event->audience ?? '', 'public'); ?>>Public</option>
							</select>
						</div>
					</div>
					<div style="display:flex;gap:12px;">
						<div style="flex:1;">
							<label style="display:block;font-weight:600;margin-bottom:4px;">Registration URL</label>
							<input type="url" name="registration_url" value="<?php echo esc_attr($event->registration_url ?? ''); ?>" class="cbnexus-input" style="width:100%;" />
						</div>
						<div style="width:150px;">
							<label style="display:block;font-weight:600;margin-bottom:4px;">Cost</label>
							<input type="text" name="cost" value="<?php echo esc_attr($event->cost ?? ''); ?>" class="cbnexus-input" placeholder="Free, $25" />
						</div>
					</div>
					<div>
						<label style="display:block;font-weight:600;margin-bottom:4px;">Reminder Notes</label>
						<textarea name="reminder_notes" rows="2" class="cbnexus-input" style="width:100%;" placeholder="Notes to include in the reminder email"><?php echo esc_textarea($event->reminder_notes ?? ''); ?></textarea>
					</div>
				</div>

				<div style="margin-top:16px;display:flex;gap:8px;">
					<button type="submit" name="cbnexus_portal_save_event" value="1" class="cbnexus-btn cbnexus-btn-primary"><?php echo $event ? 'Update Event' : 'Create Event'; ?></button>
					<a href="<?php echo esc_url(self::admin_url('events')); ?>" class="cbnexus-btn cbnexus-btn-outline">Cancel</a>
				</div>
			</form>
		</div>
		<?php
	}

	private static function handle_event_action(): void {
		$action = sanitize_key($_GET['cbnexus_portal_event_action']);
		$id     = absint($_GET['event_id'] ?? 0);
		if (!wp_verify_nonce(wp_unslash($_GET['_panonce'] ?? ''), 'cbnexus_portal_event_' . $id)) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }

		if ($action === 'approve') {
			CBNexus_Event_Repository::update($id, ['status' => 'approved']);
		} elseif ($action === 'cancel') {
			CBNexus_Event_Repository::update($id, ['status' => 'cancelled']);
		}

		wp_safe_redirect(self::admin_url('events', ['pa_notice' => 'event_updated']));
		exit;
	}

	private static function handle_save_event(): void {
		if (!wp_verify_nonce(wp_unslash($_POST['_panonce'] ?? ''), 'cbnexus_portal_save_event')) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }

		$id = absint($_POST['event_id'] ?? 0);
		$data = [
			'title'            => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
			'description'      => sanitize_textarea_field(wp_unslash($_POST['description'] ?? '')),
			'event_date'       => sanitize_text_field($_POST['event_date'] ?? ''),
			'event_time'       => sanitize_text_field($_POST['event_time'] ?? ''),
			'end_time'         => sanitize_text_field($_POST['end_time'] ?? ''),
			'location'         => sanitize_text_field(wp_unslash($_POST['location'] ?? '')),
			'audience'         => sanitize_key($_POST['audience'] ?? 'all'),
			'category'         => sanitize_key($_POST['category'] ?? ''),
			'registration_url' => esc_url_raw($_POST['registration_url'] ?? ''),
			'reminder_notes'   => sanitize_textarea_field(wp_unslash($_POST['reminder_notes'] ?? '')),
			'cost'             => sanitize_text_field(wp_unslash($_POST['cost'] ?? '')),
		];

		if ($id) {
			CBNexus_Event_Repository::update($id, $data);
		} else {
			$data['organizer_id'] = get_current_user_id();
			$data['status'] = 'approved';
			CBNexus_Event_Repository::create($data);
		}

		wp_safe_redirect(self::admin_url('events', ['pa_notice' => 'event_updated']));
		exit;
	}

	// ─── Shared UI Helpers ──────────────────────────────────────────────

	private static function status_pill(string $status): void {
		$colors = [
			'active' => 'green', 'approved' => 'green', 'published' => 'green', 'accepted' => 'green',
			'inactive' => 'red', 'cancelled' => 'red', 'declined' => 'red',
			'alumni' => 'muted', 'closed' => 'muted',
			'pending' => 'gold', 'draft' => 'gold', 'suggested' => 'gold',
			'referral' => 'blue', 'contacted' => 'blue', 'invited' => 'blue', 'visited' => 'blue', 'decision' => 'gold',
		];
		$c = $colors[$status] ?? 'muted';
		echo '<span class="cbnexus-status-pill cbnexus-status-' . esc_attr($c) . '">' . esc_html(ucfirst($status)) . '</span>';
	}

	private static function stat_card(string $label, $value): void {
		?>
		<div class="cbnexus-admin-stat">
			<div class="cbnexus-admin-stat-value"><?php echo esc_html($value); ?></div>
			<div class="cbnexus-admin-stat-label"><?php echo esc_html($label); ?></div>
		</div>
		<?php
	}

	private static function render_notice(string $notice): void {
		if ($notice === '') { return; }
		$messages = [
			'status_updated'     => 'Member status updated.',
			'candidate_added'    => 'Candidate added to pipeline.',
			'candidate_updated'  => 'Candidate stage updated.',
			'rules_saved'        => 'Matching rules saved.',
			'cycle_complete'     => 'Suggestion cycle completed. Emails sent.',
			'circleup_created'   => 'CircleUp meeting created.',
			'circleup_saved'     => 'Meeting details saved.',
			'extraction_done'    => 'AI extraction complete.',
			'published'          => 'Meeting published and summary emailed to all members.',
			'event_updated'      => 'Event updated.',
			'template_saved'     => 'Email template saved.',
			'template_reset'     => 'Template reset to default.',
			'error'              => 'An error occurred.',
		];
		$msg = $messages[$notice] ?? '';
		if (!$msg) { return; }
		$type = ($notice === 'error') ? 'error' : 'success';
		echo '<div class="cbnexus-portal-notice cbnexus-notice-' . esc_attr($type) . '">' . esc_html($msg) . '</div>';
	}

	// =====================================================================
	//  ANALYTICS TAB (super-admin)
	// =====================================================================

	private static function render_analytics(): void {
		if (!current_user_can('cbnexus_export_data')) {
			echo '<div class="cbnexus-card"><p>Permission denied.</p></div>';
			return;
		}

		$member_data = CBNexus_Admin_Analytics::compute_member_engagement();
		$overview    = self::compute_overview();
		$export_url  = wp_nonce_url(self::admin_url('analytics', ['cbnexus_portal_export' => 'members']), 'cbnexus_portal_export', '_panonce');
		?>
		<div class="cbnexus-card">
			<div class="cbnexus-admin-header-row">
				<h2>Club Analytics</h2>
				<a href="<?php echo esc_url($export_url); ?>" class="cbnexus-btn">Export CSV</a>
			</div>

			<div class="cbnexus-admin-stats-row">
				<?php foreach ($overview as $label => $val) : ?>
					<div class="cbnexus-admin-stat">
						<div class="cbnexus-admin-stat-value"><?php echo esc_html($val); ?></div>
						<div class="cbnexus-admin-stat-label"><?php echo esc_html($label); ?></div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

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
	//  EMAILS TAB (super-admin)
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
		if (!current_user_can('cbnexus_manage_plugin_settings')) {
			echo '<div class="cbnexus-card"><p>Permission denied.</p></div>';
			return;
		}

		$notice = sanitize_key($_GET['pa_notice'] ?? '');
		self::render_notice($notice);

		if (isset($_GET['tpl'])) {
			self::render_email_editor(sanitize_key($_GET['tpl']));
			return;
		}

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
								<td><a href="<?php echo esc_url(self::admin_url('emails', ['tpl' => $id])); ?>" class="cbnexus-link">Edit</a></td>
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
		?>
		<div class="cbnexus-card">
			<div class="cbnexus-admin-header-row">
				<h2>Edit: <?php echo esc_html($meta['name']); ?></h2>
				<a href="<?php echo esc_url(self::admin_url('emails')); ?>" class="cbnexus-btn">← Back</a>
			</div>

			<form method="post" action="">
				<?php wp_nonce_field('cbnexus_portal_save_email_tpl'); ?>
				<input type="hidden" name="tpl_id" value="<?php echo esc_attr($tpl_id); ?>" />

				<div class="cbnexus-admin-form-stack">
					<div>
						<label>Subject Line</label>
						<input type="text" name="subject" value="<?php echo esc_attr($subject); ?>" />
					</div>
					<div>
						<label>Body (HTML)</label>
						<textarea name="body" rows="12" style="font-family:monospace;font-size:13px;"><?php echo esc_textarea($body); ?></textarea>
					</div>
					<p class="cbnexus-admin-meta">Use <code>{{variable}}</code> placeholders. Available: first_name, last_name, display_name, email, site_url, portal_url, login_url.</p>
				</div>

				<div class="cbnexus-admin-button-row">
					<button type="submit" name="cbnexus_portal_save_email_tpl" value="1" class="cbnexus-btn cbnexus-btn-accent">Save Template</button>
					<?php if ($override) : ?>
						<a href="<?php echo esc_url(wp_nonce_url(self::admin_url('emails', ['cbnexus_portal_reset_tpl' => $tpl_id]), 'cbnexus_portal_reset_' . $tpl_id, '_panonce')); ?>" class="cbnexus-btn" onclick="return confirm('Reset to default template?');">Reset to Default</a>
					<?php endif; ?>
				</div>
			</form>
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

		wp_safe_redirect(self::admin_url('emails', ['tpl' => $tpl_id, 'pa_notice' => 'template_saved']));
		exit;
	}

	private static function handle_reset_email_template(): void {
		$tpl_id = sanitize_key($_GET['cbnexus_portal_reset_tpl']);
		if (!wp_verify_nonce(wp_unslash($_GET['_panonce'] ?? ''), 'cbnexus_portal_reset_' . $tpl_id)) { return; }
		if (!current_user_can('cbnexus_manage_plugin_settings')) { return; }

		delete_option('cbnexus_email_tpl_' . $tpl_id);

		wp_safe_redirect(self::admin_url('emails', ['pa_notice' => 'template_reset']));
		exit;
	}

	// =====================================================================
	//  LOGS TAB (super-admin)
	// =====================================================================

	private static function render_logs(): void {
		if (!current_user_can('cbnexus_view_logs')) {
			echo '<div class="cbnexus-card"><p>Permission denied.</p></div>';
			return;
		}

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
				<a href="<?php echo esc_url(self::admin_url('logs')); ?>" class="<?php echo $level === '' ? 'active' : ''; ?>">All</a>
				<?php foreach (['error', 'warning', 'info', 'debug'] as $lv) : ?>
					<a href="<?php echo esc_url(self::admin_url('logs', ['log_level' => $lv])); ?>" class="<?php echo $level === $lv ? 'active' : ''; ?>"><?php echo esc_html(ucfirst($lv)); ?></a>
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
	//  SETTINGS TAB (super-admin)
	// =====================================================================

	private static function render_settings(): void {
		if (!current_user_can('cbnexus_manage_plugin_settings')) {
			echo '<div class="cbnexus-card"><p>Permission denied.</p></div>';
			return;
		}
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
}