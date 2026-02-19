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
		// Recruitment: save candidate edit (full form).
		if (isset($_POST['cbnexus_portal_save_candidate'])) {
			self::handle_save_candidate();
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
		// Members: save (add or edit).
		if (isset($_POST['cbnexus_portal_save_member'])) {
			self::handle_save_member();
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

		// If editing or creating, show the member form instead of the list.
		$edit_uid = absint($_GET['edit_member'] ?? 0);
		if ($edit_uid || isset($_GET['new_member'])) {
			self::render_member_form($edit_uid);
			return;
		}

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
					<a href="<?php echo esc_url(self::admin_url('members', ['new_member' => '1'])); ?>" class="cbnexus-btn cbnexus-btn-primary cbnexus-btn-sm">+ Add Member</a>
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
			<form method="get" action="" class="cbnexus-admin-search">
				<input type="hidden" name="section" value="manage" />
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
								<a href="<?php echo esc_url(self::admin_url('members', ['edit_member' => $uid])); ?>" class="cbnexus-link">Edit</a>
								<?php if ($status !== 'active') : ?>
									<a href="<?php echo esc_url(self::member_action_url('activate', $uid)); ?>" class="cbnexus-link cbnexus-link-green">Activate</a>
								<?php endif; ?>
								<?php if ($status !== 'inactive') : ?>
									<a href="<?php echo esc_url(self::member_action_url('deactivate', $uid)); ?>" class="cbnexus-link cbnexus-link-red">Deactivate</a>
								<?php endif; ?>
								<?php if ($status !== 'alumni') : ?>
									<a href="<?php echo esc_url(self::member_action_url('alumni', $uid)); ?>" class="cbnexus-link" style="color:#6b7280;">Alumni</a>
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

	/**
	 * Inline member add/edit form within the portal.
	 */
	private static function render_member_form(int $edit_uid): void {
		$editing  = $edit_uid > 0;
		$profile  = $editing ? CBNexus_Member_Repository::get_profile($edit_uid) : null;

		if ($editing && !$profile) {
			echo '<div class="cbnexus-card"><p>Member not found.</p></div>';
			return;
		}
		if (!$editing && !current_user_can('cbnexus_create_members')) {
			echo '<div class="cbnexus-card"><p>You do not have permission to create members.</p></div>';
			return;
		}

		// Retrieve flash data from a failed submission.
		$flash_errors = get_transient('cbnexus_pa_member_errors') ?: null;
		$flash_data   = get_transient('cbnexus_pa_member_flash') ?: null;
		delete_transient('cbnexus_pa_member_errors');
		delete_transient('cbnexus_pa_member_flash');

		$industries = CBNexus_Member_Service::get_industries();

		// Value helper — flash data takes priority, then profile, then empty.
		$v = function (string $k) use ($flash_data, $profile): string {
			if ($flash_data && isset($flash_data[$k])) { return sanitize_text_field($flash_data[$k]); }
			return (string) ($profile[$k] ?? '');
		};
		// Tags helper (expertise, looking_for, can_help_with).
		$vt = function (string $k) use ($flash_data, $profile): string {
			if ($flash_data && isset($flash_data[$k])) { return sanitize_text_field($flash_data[$k]); }
			$t = $profile[$k] ?? [];
			return is_array($t) ? implode(', ', $t) : (string) $t;
		};
		// Current role helper.
		$cur_role = 'cb_member';
		if ($profile) {
			foreach (['cb_super_admin', 'cb_admin', 'cb_member'] as $r) {
				if (in_array($r, $profile['roles'] ?? [], true)) { $cur_role = $r; break; }
			}
		}
		?>
		<div class="cbnexus-card">
			<div class="cbnexus-admin-header-row">
				<h2><?php echo $editing ? 'Edit Member' : 'Add New Member'; ?></h2>
				<a href="<?php echo esc_url(self::admin_url('members')); ?>" class="cbnexus-btn cbnexus-btn-outline cbnexus-btn-sm">← Back</a>
			</div>

			<?php if ($flash_errors) : ?>
				<div class="cbnexus-notice cbnexus-notice-error" style="margin-bottom:12px;">
					<?php foreach ($flash_errors as $err) : ?><p><?php echo esc_html($err); ?></p><?php endforeach; ?>
				</div>
			<?php endif; ?>

			<form method="post" style="max-width:650px;">
				<?php wp_nonce_field('cbnexus_portal_save_member', '_panonce'); ?>
				<?php if ($editing) : ?><input type="hidden" name="edit_user_id" value="<?php echo esc_attr($edit_uid); ?>" /><?php endif; ?>

				<!-- Account Information -->
				<h3 style="margin:16px 0 8px;color:var(--cbnexus-plum,#4a154b);">Account Information</h3>
				<div style="display:flex;flex-direction:column;gap:10px;">
					<div style="display:flex;gap:12px;">
						<div style="flex:1;">
							<label style="display:block;font-weight:600;margin-bottom:4px;">First Name *</label>
							<input type="text" name="first_name" value="<?php echo esc_attr($v('first_name')); ?>" class="cbnexus-input" style="width:100%;" required />
						</div>
						<div style="flex:1;">
							<label style="display:block;font-weight:600;margin-bottom:4px;">Last Name *</label>
							<input type="text" name="last_name" value="<?php echo esc_attr($v('last_name')); ?>" class="cbnexus-input" style="width:100%;" required />
						</div>
					</div>
					<div style="display:flex;gap:12px;">
						<div style="flex:1;">
							<label style="display:block;font-weight:600;margin-bottom:4px;">Email *</label>
							<input type="email" name="user_email" value="<?php echo esc_attr($v('user_email')); ?>" class="cbnexus-input" style="width:100%;" required <?php echo $editing ? '' : ''; ?> />
						</div>
						<div style="width:180px;">
							<label style="display:block;font-weight:600;margin-bottom:4px;">Role</label>
							<select name="cb_role" class="cbnexus-input" style="width:100%;">
								<option value="cb_member" <?php selected($cur_role, 'cb_member'); ?>>Member</option>
								<option value="cb_admin" <?php selected($cur_role, 'cb_admin'); ?>>Admin</option>
								<option value="cb_super_admin" <?php selected($cur_role, 'cb_super_admin'); ?>>Super Admin</option>
							</select>
						</div>
					</div>
				</div>

				<!-- Professional Information -->
				<h3 style="margin:20px 0 8px;color:var(--cbnexus-plum,#4a154b);">Professional Information</h3>
				<div style="display:flex;flex-direction:column;gap:10px;">
					<div style="display:flex;gap:12px;">
						<div style="flex:1;">
							<label style="display:block;font-weight:600;margin-bottom:4px;">Company *</label>
							<input type="text" name="cb_company" value="<?php echo esc_attr($v('cb_company')); ?>" class="cbnexus-input" style="width:100%;" required />
						</div>
						<div style="flex:1;">
							<label style="display:block;font-weight:600;margin-bottom:4px;">Job Title *</label>
							<input type="text" name="cb_title" value="<?php echo esc_attr($v('cb_title')); ?>" class="cbnexus-input" style="width:100%;" required />
						</div>
					</div>
					<div>
						<label style="display:block;font-weight:600;margin-bottom:4px;">Industry *</label>
						<select name="cb_industry" class="cbnexus-input" style="width:100%;" required>
							<option value="">— Select —</option>
							<?php foreach ($industries as $ind) : ?>
								<option value="<?php echo esc_attr($ind); ?>" <?php selected($v('cb_industry'), $ind); ?>><?php echo esc_html($ind); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<!-- Networking -->
				<h3 style="margin:20px 0 8px;color:var(--cbnexus-plum,#4a154b);">Networking</h3>
				<div style="display:flex;flex-direction:column;gap:10px;">
					<div>
						<label style="display:block;font-weight:600;margin-bottom:4px;">Expertise / Skills</label>
						<input type="text" name="cb_expertise" value="<?php echo esc_attr($vt('cb_expertise')); ?>" class="cbnexus-input" style="width:100%;" placeholder="Comma-separated" />
					</div>
					<div>
						<label style="display:block;font-weight:600;margin-bottom:4px;">Looking For</label>
						<input type="text" name="cb_looking_for" value="<?php echo esc_attr($vt('cb_looking_for')); ?>" class="cbnexus-input" style="width:100%;" placeholder="Comma-separated" />
					</div>
					<div>
						<label style="display:block;font-weight:600;margin-bottom:4px;">Can Help With</label>
						<input type="text" name="cb_can_help_with" value="<?php echo esc_attr($vt('cb_can_help_with')); ?>" class="cbnexus-input" style="width:100%;" placeholder="Comma-separated" />
					</div>
				</div>

				<!-- Contact Information -->
				<h3 style="margin:20px 0 8px;color:var(--cbnexus-plum,#4a154b);">Contact Information</h3>
				<div style="display:flex;flex-direction:column;gap:10px;">
					<div style="display:flex;gap:12px;">
						<div style="flex:1;">
							<label style="display:block;font-weight:600;margin-bottom:4px;">Phone</label>
							<input type="tel" name="cb_phone" value="<?php echo esc_attr($v('cb_phone')); ?>" class="cbnexus-input" style="width:100%;" />
						</div>
						<div style="flex:1;">
							<label style="display:block;font-weight:600;margin-bottom:4px;">LinkedIn URL</label>
							<input type="url" name="cb_linkedin" value="<?php echo esc_attr($v('cb_linkedin')); ?>" class="cbnexus-input" style="width:100%;" />
						</div>
					</div>
					<div>
						<label style="display:block;font-weight:600;margin-bottom:4px;">Website</label>
						<input type="url" name="cb_website" value="<?php echo esc_attr($v('cb_website')); ?>" class="cbnexus-input" style="width:100%;" />
					</div>
				</div>

				<!-- Personal -->
				<h3 style="margin:20px 0 8px;color:var(--cbnexus-plum,#4a154b);">Personal</h3>
				<div style="display:flex;flex-direction:column;gap:10px;">
					<div>
						<label style="display:block;font-weight:600;margin-bottom:4px;">Bio / About</label>
						<textarea name="cb_bio" rows="3" class="cbnexus-input" style="width:100%;"><?php echo esc_textarea($v('cb_bio')); ?></textarea>
					</div>
					<div>
						<label style="display:block;font-weight:600;margin-bottom:4px;">Profile Photo URL</label>
						<input type="url" name="cb_photo_url" value="<?php echo esc_attr($v('cb_photo_url')); ?>" class="cbnexus-input" style="width:100%;" />
					</div>
				</div>

				<!-- Admin Information -->
				<h3 style="margin:20px 0 8px;color:var(--cbnexus-plum,#4a154b);">Admin Information</h3>
				<div style="display:flex;flex-direction:column;gap:10px;">
					<div style="display:flex;gap:12px;">
						<div style="flex:1;">
							<label style="display:block;font-weight:600;margin-bottom:4px;">Referred By</label>
							<input type="text" name="cb_referred_by" value="<?php echo esc_attr($v('cb_referred_by')); ?>" class="cbnexus-input" style="width:100%;" />
						</div>
						<div style="width:160px;">
							<label style="display:block;font-weight:600;margin-bottom:4px;">Join Date</label>
							<input type="date" name="cb_join_date" value="<?php echo esc_attr($v('cb_join_date') ?: gmdate('Y-m-d')); ?>" class="cbnexus-input" />
						</div>
					</div>
					<div style="display:flex;gap:12px;">
						<div style="flex:1;">
							<label style="display:block;font-weight:600;margin-bottom:4px;">Status</label>
							<select name="cb_member_status" class="cbnexus-input" style="width:100%;">
								<option value="active" <?php selected($v('cb_member_status') ?: 'active', 'active'); ?>>Active</option>
								<option value="inactive" <?php selected($v('cb_member_status'), 'inactive'); ?>>Inactive</option>
								<option value="alumni" <?php selected($v('cb_member_status'), 'alumni'); ?>>Alumni</option>
							</select>
						</div>
						<div style="flex:1;">
							<label style="display:block;font-weight:600;margin-bottom:4px;">Onboarding Stage</label>
							<select name="cb_onboarding_stage" class="cbnexus-input" style="width:100%;">
								<option value="access_setup" <?php selected($v('cb_onboarding_stage') ?: 'access_setup', 'access_setup'); ?>>Access Setup</option>
								<option value="walkthrough" <?php selected($v('cb_onboarding_stage'), 'walkthrough'); ?>>Walkthrough</option>
								<option value="ignite" <?php selected($v('cb_onboarding_stage'), 'ignite'); ?>>Ignite</option>
								<option value="ambassador" <?php selected($v('cb_onboarding_stage'), 'ambassador'); ?>>Ambassador</option>
								<option value="complete" <?php selected($v('cb_onboarding_stage'), 'complete'); ?>>Complete</option>
							</select>
						</div>
					</div>
					<div>
						<label style="display:block;font-weight:600;margin-bottom:4px;">Ambassador (User ID)</label>
						<input type="text" name="cb_ambassador_id" value="<?php echo esc_attr($v('cb_ambassador_id')); ?>" class="cbnexus-input" style="width:200px;" />
					</div>
					<div>
						<label style="display:block;font-weight:600;margin-bottom:4px;">Admin Notes</label>
						<textarea name="cb_notes_admin" rows="2" class="cbnexus-input" style="width:100%;"><?php echo esc_textarea($v('cb_notes_admin')); ?></textarea>
					</div>
				</div>

				<div style="margin-top:20px;display:flex;gap:8px;">
					<button type="submit" name="cbnexus_portal_save_member" value="1" class="cbnexus-btn cbnexus-btn-primary"><?php echo $editing ? 'Update Member' : 'Create Member'; ?></button>
					<a href="<?php echo esc_url(self::admin_url('members')); ?>" class="cbnexus-btn cbnexus-btn-outline">Cancel</a>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle member add/edit form submission from the portal.
	 */
	private static function handle_save_member(): void {
		if (!wp_verify_nonce(wp_unslash($_POST['_panonce'] ?? ''), 'cbnexus_portal_save_member')) { return; }

		$editing = isset($_POST['edit_user_id']) && absint($_POST['edit_user_id']) > 0;
		$edit_uid = $editing ? absint($_POST['edit_user_id']) : 0;

		if ($editing && !current_user_can('cbnexus_manage_members')) { return; }
		if (!$editing && !current_user_can('cbnexus_create_members')) { return; }

		$user_data = [
			'user_email'   => sanitize_email(wp_unslash($_POST['user_email'] ?? '')),
			'first_name'   => sanitize_text_field(wp_unslash($_POST['first_name'] ?? '')),
			'last_name'    => sanitize_text_field(wp_unslash($_POST['last_name'] ?? '')),
			'display_name' => trim(sanitize_text_field(wp_unslash($_POST['first_name'] ?? '')) . ' ' . sanitize_text_field(wp_unslash($_POST['last_name'] ?? ''))),
		];

		$meta_keys    = CBNexus_Member_Repository::get_meta_keys();
		$profile_data = [];
		foreach ($meta_keys as $k) {
			if (isset($_POST[$k])) { $profile_data[$k] = wp_unslash($_POST[$k]); }
		}

		$role = sanitize_text_field(wp_unslash($_POST['cb_role'] ?? 'cb_member'));
		if (!in_array($role, ['cb_member', 'cb_admin', 'cb_super_admin'], true)) { $role = 'cb_member'; }

		if ($editing) {
			// Update WP user fields.
			$wp_update = [
				'ID'           => $edit_uid,
				'first_name'   => $user_data['first_name'],
				'last_name'    => $user_data['last_name'],
				'display_name' => $user_data['display_name'],
			];
			// Update email if changed and available.
			$current = get_userdata($edit_uid);
			if ($current && $current->user_email !== $user_data['user_email'] && !empty($user_data['user_email']) && !email_exists($user_data['user_email'])) {
				$wp_update['user_email'] = $user_data['user_email'];
			}
			wp_update_user($wp_update);

			// Update role.
			$user = get_userdata($edit_uid);
			if ($user && !in_array($role, $user->roles, true)) {
				foreach (['cb_member', 'cb_admin', 'cb_super_admin'] as $r) { $user->remove_role($r); }
				$user->add_role($role);
			}

			$result = CBNexus_Member_Service::update_member($edit_uid, $profile_data, true);

			if (!$result['success']) {
				set_transient('cbnexus_pa_member_errors', $result['errors'], 60);
				wp_safe_redirect(self::admin_url('members', ['edit_member' => $edit_uid]));
				exit;
			}

			wp_safe_redirect(self::admin_url('members', ['pa_notice' => 'member_updated']));
			exit;

		} else {
			// Create new member.
			$result = CBNexus_Member_Service::create_member($user_data, $profile_data, $role);

			if (!$result['success']) {
				set_transient('cbnexus_pa_member_errors', $result['errors'], 60);
				set_transient('cbnexus_pa_member_flash', $_POST, 60);
				wp_safe_redirect(self::admin_url('members', ['new_member' => '1']));
				exit;
			}

			// Send welcome email.
			$new_profile = CBNexus_Member_Repository::get_profile($result['user_id']);
			if ($new_profile) {
				CBNexus_Email_Service::send_welcome($result['user_id'], $new_profile);
			}

			wp_safe_redirect(self::admin_url('members', ['pa_notice' => 'member_created']));
			exit;
		}
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

		// If editing a candidate, show the edit form.
		$edit_id = absint($_GET['edit_candidate'] ?? 0);
		if ($edit_id) {
			self::render_candidate_form($edit_id, $members);
			return;
		}

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
						<th>Actions</th>
					</tr></thead>
					<tbody>
					<?php if (empty($candidates)) : ?>
						<tr><td colspan="7" class="cbnexus-admin-empty">No candidates yet.</td></tr>
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
							<td class="cbnexus-admin-meta">
								<?php echo esc_html($c->notes ?: '—'); ?>
								<?php
								$fb = get_option('cbnexus_visit_feedback_' . $c->id);
								if ($fb && is_array($fb) && !empty($fb['label'])) :
								?>
									<div style="margin-top:4px;"><span style="display:inline-block;padding:2px 8px;background:#f3eef6;border-radius:10px;font-size:11px;color:#5b2d6e;font-weight:600;">📊 <?php echo esc_html($fb['label']); ?></span></div>
								<?php endif; ?>
							</td>
							<td class="cbnexus-admin-meta"><?php echo esc_html(date_i18n('M j', strtotime($c->updated_at))); ?></td>
							<td class="cbnexus-admin-actions-cell">
								<a href="<?php echo esc_url(self::admin_url('recruitment', ['edit_candidate' => $c->id])); ?>" class="cbnexus-link">Edit</a>
							</td>
						</tr>
					<?php endforeach; endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Inline candidate edit form within the portal.
	 */
	private static function render_candidate_form(int $id, array $members): void {
		global $wpdb;
		$table = $wpdb->prefix . 'cb_candidates';
		$c = $wpdb->get_row($wpdb->prepare(
			"SELECT c.*, u.display_name as referrer_name FROM {$table} c LEFT JOIN {$wpdb->users} u ON c.referrer_id = u.ID WHERE c.id = %d",
			$id
		));

		if (!$c) {
			echo '<div class="cbnexus-card"><p>Candidate not found.</p></div>';
			return;
		}
		?>
		<div class="cbnexus-card">
			<div class="cbnexus-admin-header-row">
				<h2>Edit Candidate</h2>
				<a href="<?php echo esc_url(self::admin_url('recruitment')); ?>" class="cbnexus-btn cbnexus-btn-outline cbnexus-btn-sm">← Back</a>
			</div>

			<form method="post" style="max-width:600px;margin-top:12px;">
				<?php wp_nonce_field('cbnexus_portal_save_candidate'); ?>
				<input type="hidden" name="candidate_id" value="<?php echo esc_attr($c->id); ?>" />

				<div style="display:flex;flex-direction:column;gap:12px;">
					<div style="display:flex;gap:12px;">
						<div style="flex:1;">
							<label style="display:block;font-weight:600;margin-bottom:4px;">Name *</label>
							<input type="text" name="name" value="<?php echo esc_attr($c->name); ?>" class="cbnexus-input" style="width:100%;" required />
						</div>
						<div style="flex:1;">
							<label style="display:block;font-weight:600;margin-bottom:4px;">Email</label>
							<input type="email" name="email" value="<?php echo esc_attr($c->email); ?>" class="cbnexus-input" style="width:100%;" />
						</div>
					</div>
					<div style="display:flex;gap:12px;">
						<div style="flex:1;">
							<label style="display:block;font-weight:600;margin-bottom:4px;">Company</label>
							<input type="text" name="company" value="<?php echo esc_attr($c->company); ?>" class="cbnexus-input" style="width:100%;" />
						</div>
						<div style="flex:1;">
							<label style="display:block;font-weight:600;margin-bottom:4px;">Industry</label>
							<input type="text" name="industry" value="<?php echo esc_attr($c->industry); ?>" class="cbnexus-input" style="width:100%;" />
						</div>
					</div>
					<div style="display:flex;gap:12px;">
						<div style="flex:1;">
							<label style="display:block;font-weight:600;margin-bottom:4px;">Stage</label>
							<select name="stage" class="cbnexus-input" style="width:100%;">
								<?php foreach (self::$recruit_stages as $key => $label) : ?>
									<option value="<?php echo esc_attr($key); ?>" <?php selected($c->stage, $key); ?>><?php echo esc_html($label); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div style="flex:1;">
							<label style="display:block;font-weight:600;margin-bottom:4px;">Referred By</label>
							<select name="referrer_id" class="cbnexus-input" style="width:100%;">
								<option value="0">—</option>
								<?php foreach ($members as $m) : ?>
									<option value="<?php echo esc_attr($m['user_id']); ?>" <?php selected((int) $c->referrer_id, $m['user_id']); ?>><?php echo esc_html($m['display_name']); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
					<div>
						<label style="display:block;font-weight:600;margin-bottom:4px;">Notes</label>
						<textarea name="notes" rows="3" class="cbnexus-input" style="width:100%;"><?php echo esc_textarea($c->notes); ?></textarea>
					</div>
				</div>

				<div style="margin-top:16px;display:flex;gap:8px;">
					<button type="submit" name="cbnexus_portal_save_candidate" value="1" class="cbnexus-btn cbnexus-btn-primary">Update Candidate</button>
					<a href="<?php echo esc_url(self::admin_url('recruitment')); ?>" class="cbnexus-btn cbnexus-btn-outline">Cancel</a>
				</div>
			</form>

			<div style="margin-top:16px;padding-top:12px;border-top:1px solid #e5e7eb;font-size:13px;color:#6b7280;">
				Added <?php echo esc_html(date_i18n('M j, Y', strtotime($c->created_at))); ?>
				· Last updated <?php echo esc_html(date_i18n('M j, Y g:i A', strtotime($c->updated_at))); ?>
				<?php
				$fb = get_option('cbnexus_visit_feedback_' . $c->id);
				if ($fb && is_array($fb) && !empty($fb['label'])) :
				?>
					<div style="margin-top:8px;padding:10px 14px;background:#f8f5fa;border-radius:8px;color:#4a154b;font-size:13px;">
						<strong>📊 Visit Feedback:</strong> <?php echo esc_html($fb['label']); ?>
						<span style="color:#a094a8;margin-left:6px;">(<?php echo esc_html(date_i18n('M j, Y', strtotime($fb['answered_at']))); ?>)</span>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle full candidate edit form save.
	 */
	private static function handle_save_candidate(): void {
		if (!wp_verify_nonce(wp_unslash($_POST['_wpnonce'] ?? ''), 'cbnexus_portal_save_candidate')) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }

		global $wpdb;
		$table = $wpdb->prefix . 'cb_candidates';
		$id    = absint($_POST['candidate_id'] ?? 0);
		$new_stage = sanitize_key($_POST['stage'] ?? 'referral');

		// Get current state for automation comparison.
		$candidate = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
		if (!$candidate) { return; }

		$old_stage = $candidate->stage;

		$wpdb->update($table, [
			'name'        => sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
			'email'       => sanitize_email($_POST['email'] ?? ''),
			'company'     => sanitize_text_field(wp_unslash($_POST['company'] ?? '')),
			'industry'    => sanitize_text_field(wp_unslash($_POST['industry'] ?? '')),
			'referrer_id' => absint($_POST['referrer_id'] ?? 0) ?: null,
			'stage'       => $new_stage,
			'notes'       => sanitize_textarea_field(wp_unslash($_POST['notes'] ?? '')),
			'updated_at'  => gmdate('Y-m-d H:i:s'),
		], ['id' => $id], ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s'], ['%d']);

		// Trigger automations if stage changed.
		// Re-fetch the updated candidate so automations use the new referrer_id/email.
		if ($old_stage !== $new_stage) {
			$updated = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
			if ($updated) {
				self::run_recruitment_automations($updated, $old_stage, $new_stage);
			}
		}

		wp_safe_redirect(self::admin_url('recruitment', ['pa_notice' => 'candidate_saved']));
		exit;
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
		$table = $wpdb->prefix . 'cb_candidates';
		$id    = absint($_POST['candidate_id'] ?? 0);
		$new_stage = sanitize_key($_POST['stage'] ?? 'referral');
		$notes = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));

		// Get the candidate's current state before updating.
		$candidate = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
		if (!$candidate) { return; }

		$old_stage = $candidate->stage;

		// Update the stage.
		$wpdb->update($table, [
			'stage'      => $new_stage,
			'notes'      => $notes,
			'updated_at' => gmdate('Y-m-d H:i:s'),
		], ['id' => $id], ['%s', '%s', '%s'], ['%d']);

		// If stage actually changed, trigger automations.
		if ($old_stage !== $new_stage) {
			self::run_recruitment_automations($candidate, $old_stage, $new_stage);
		}

		wp_safe_redirect(self::admin_url('recruitment', ['pa_notice' => 'candidate_updated']));
		exit;
	}

	/**
	 * Recruitment pipeline automations triggered on stage transitions.
	 * Public wrapper so WP-admin recruitment handler can also trigger these.
	 */
	public static function trigger_recruitment_automation(object $candidate, string $old_stage, string $new_stage): void {
		self::run_recruitment_automations($candidate, $old_stage, $new_stage);
	}

	/**
	 * Recruitment pipeline automations triggered on stage transitions.
	 *
	 * - Any stage change → notify referrer
	 * - Moved to "invited" → email the candidate with invitation
	 * - Moved to "visited" → thank-you email to candidate with feedback request (once only)
	 * - Moved to "accepted" → auto-create member account, send welcome email, notify referrer with congrats
	 */
	private static function run_recruitment_automations(object $candidate, string $old_stage, string $new_stage): void {
		$referrer = $candidate->referrer_id ? get_userdata($candidate->referrer_id) : null;
		$stage_labels = self::$recruit_stages;
		$candidate_first = explode(' ', trim($candidate->name))[0] ?? $candidate->name;
		$company_line = $candidate->company ? ' (' . $candidate->company . ')' : '';

		// ── Stage-specific detail block for referrer emails ──
		$stage_details = [
			'contacted' => 'We\'ve reached out to them to start the conversation.',
			'invited'   => 'An invitation to visit one of our meetings has been sent.',
			'visited'   => 'They attended a meeting — we\'re now evaluating fit.',
			'decision'  => 'The group is making a decision on their membership.',
			'accepted'  => 'They\'ve been accepted! Their member account is being created.',
			'declined'  => 'After careful consideration, we\'ve decided not to proceed at this time.',
		];
		$detail_text = $stage_details[$new_stage] ?? '';
		$detail_block = $detail_text
			? '<div style="background:#f0f9ff;border-left:3px solid #2563eb;padding:12px 16px;margin:16px 0;font-size:14px;color:#1e40af;">' . esc_html($detail_text) . '</div>'
			: '';

		// ── 1. "Accepted" → auto-create member account ──
		if ($new_stage === 'accepted') {
			$created_user_id = self::convert_candidate_to_member($candidate);

			// Notify referrer with the special accepted template.
			if ($referrer && $created_user_id) {
				CBNexus_Email_Service::send('recruit_accepted', $referrer->user_email, [
					'referrer_name'   => $referrer->display_name,
					'candidate_name'  => $candidate->name,
					'portal_url'      => CBNexus_Portal_Router::get_portal_url(),
				], [
					'recipient_id' => $referrer->ID,
					'related_type' => 'recruitment_accepted',
				]);
			}

			// Log it.
			if (class_exists('CBNexus_Logger')) {
				CBNexus_Logger::info('Candidate accepted and converted to member.', [
					'candidate_id' => $candidate->id,
					'candidate'    => $candidate->name,
					'new_user_id'  => $created_user_id,
				]);
			}

			return; // Accepted has its own referrer email; skip the generic one.
		}

		// ── 2. "Invited" → email the candidate ──
		if ($new_stage === 'invited' && !empty($candidate->email)) {
			$invitation_notes = $candidate->notes ?: '';
			$notes_block = $invitation_notes
				? '<div style="background:#fff7ed;border-left:3px solid #c49a3c;padding:12px 16px;margin:16px 0;font-size:14px;">'
					. '<strong>📝 A note from your host:</strong> ' . esc_html($invitation_notes) . '</div>'
				: '';

			CBNexus_Email_Service::send('recruit_invitation', $candidate->email, [
				'candidate_first_name' => $candidate_first,
				'candidate_name'       => $candidate->name,
				'referrer_name'        => $referrer ? $referrer->display_name : 'a CircleBlast member',
				'invitation_notes_block' => $notes_block,
			], [
				'related_type' => 'recruitment_invitation',
				'related_id'   => $candidate->id,
			]);
		}

		// ── 3. "Visited" → NPS-style feedback survey email (once only) ──
		if ($new_stage === 'visited' && !empty($candidate->email)) {
			$opt_key = 'cbnexus_recruit_visited_sent_' . $candidate->id;
			if (!get_option($opt_key)) {
				// Generate tokenized feedback URLs (single-use per question).
				$feedback_urls = self::generate_visit_feedback_urls((int) $candidate->id);

				$followup = $referrer
					? $referrer->display_name
					: 'A member of the CircleBlast Council';

				CBNexus_Email_Service::send('recruit_visited_thankyou', $candidate->email, [
					'candidate_first_name' => $candidate_first,
					'candidate_name'       => $candidate->name,
					'followup_name'        => $followup,
					'fb_yes'               => $feedback_urls['fb_yes'],
					'fb_maybe'             => $feedback_urls['fb_maybe'],
					'fb_later'             => $feedback_urls['fb_later'],
					'fb_no'                => $feedback_urls['fb_no'],
				], [
					'related_type' => 'recruitment_visited',
					'related_id'   => $candidate->id,
				]);

				update_option($opt_key, gmdate('Y-m-d H:i:s'), false);

				if (class_exists('CBNexus_Logger')) {
					CBNexus_Logger::info('Visited feedback survey sent to candidate.', [
						'candidate_id' => $candidate->id,
						'email'        => $candidate->email,
					]);
				}
			}
		}

		// ── 4. Notify referrer on any stage change ──
		if ($referrer) {
			CBNexus_Email_Service::send('recruit_stage_referrer', $referrer->user_email, [
				'referrer_name'        => $referrer->display_name,
				'candidate_name'       => $candidate->name,
				'candidate_company_line' => $company_line,
				'stage_label'          => $stage_labels[$new_stage] ?? $new_stage,
				'stage_detail_block'   => $detail_block,
			], [
				'recipient_id' => $referrer->ID,
				'related_type' => 'recruitment_stage_change',
				'related_id'   => $candidate->id,
			]);
		}
	}

	/**
	 * Convert an accepted candidate into a full CircleBlast member.
	 * Creates WP user, assigns profile data, sends welcome email.
	 *
	 * @return int|null New user ID, or null on failure.
	 */
	private static function convert_candidate_to_member(object $candidate): ?int {
		// If no email, we can't create an account.
		if (empty($candidate->email)) {
			if (class_exists('CBNexus_Logger')) {
				CBNexus_Logger::warning('Cannot auto-create member for accepted candidate — no email.', [
					'candidate_id' => $candidate->id,
					'candidate'    => $candidate->name,
				]);
			}
			return null;
		}

		// Don't create a duplicate if email already exists as a user.
		if (email_exists($candidate->email)) {
			if (class_exists('CBNexus_Logger')) {
				CBNexus_Logger::info('Accepted candidate already has a WP account; skipping auto-create.', [
					'candidate_id' => $candidate->id,
					'email'        => $candidate->email,
				]);
			}
			return null;
		}

		// Split name into first/last.
		$name_parts = explode(' ', trim($candidate->name), 2);
		$first_name = $name_parts[0] ?? '';
		$last_name  = $name_parts[1] ?? '';

		$user_data = [
			'user_email'   => $candidate->email,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'display_name' => trim($candidate->name),
		];

		$profile_data = [
			'cb_company'     => $candidate->company ?: '',
			'cb_industry'    => $candidate->industry ?: '',
			'cb_referred_by' => $candidate->referrer_id ? get_userdata($candidate->referrer_id)->display_name ?? '' : '',
			'cb_ambassador_id' => $candidate->referrer_id ?: '',
		];

		$result = CBNexus_Member_Service::create_member($user_data, $profile_data, 'cb_member');

		if (!$result['success']) {
			if (class_exists('CBNexus_Logger')) {
				CBNexus_Logger::error('Failed to auto-create member from accepted candidate.', [
					'candidate_id' => $candidate->id,
					'errors'       => $result['errors'] ?? [],
				]);
			}
			return null;
		}

		$user_id = $result['user_id'];

		// Send the welcome email.
		$profile = CBNexus_Member_Repository::get_profile($user_id);
		if ($profile) {
			CBNexus_Email_Service::send_welcome($user_id, $profile);
		}

		return $user_id;
	}

	/**
	 * Generate tokenized one-click feedback URLs for the visit survey.
	 * Single question: "Interested in joining?" with 4 answer options.
	 * Uses user_id=0 since prospects aren't WP users yet.
	 */
	private static function generate_visit_feedback_urls(int $candidate_id): array {
		$answers = ['yes', 'maybe', 'later', 'no'];
		$urls = [];
		foreach ($answers as $answer) {
			$token = CBNexus_Token_Service::generate(0, 'visit_feedback', [
				'candidate_id' => $candidate_id,
				'answer'       => $answer,
			], 30, false);
			$urls['fb_' . $answer] = CBNexus_Token_Service::url($token);
		}
		return $urls;
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
					<div>
						<label>Guest / Prospect Attendees</label>
						<input type="text" name="guest_attendees" value="" class="cbnexus-input" style="width:100%;" placeholder="Enter guest names, comma-separated (matched against recruitment pipeline)" />
						<p style="font-size:12px;color:#6b7280;margin:4px 0 0;">Names matching candidates in the pipeline (stages: Referral–Invited) will automatically move to "Visited" and trigger a thank-you email.</p>
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

		// ── Guest attendees → match against recruitment pipeline ──
		$guest_raw = sanitize_text_field(wp_unslash($_POST['guest_attendees'] ?? ''));
		if ($guest_raw !== '') {
			self::match_guest_attendees_to_pipeline($guest_raw);
		}

		wp_safe_redirect(self::admin_url('archivist', ['circleup_id' => $id, 'pa_notice' => 'circleup_saved']));
		exit;
	}

	/**
	 * Match comma-separated guest names against the recruitment pipeline.
	 * Candidates in pre-visited stages (referral, contacted, invited) whose name
	 * fuzzy-matches a guest name are auto-transitioned to "visited", triggering
	 * the thank-you email and referrer notification (once per candidate).
	 */
	private static function match_guest_attendees_to_pipeline(string $guest_csv): void {
		$names = array_filter(array_map('trim', explode(',', $guest_csv)));
		if (empty($names)) { return; }

		global $wpdb;
		$table = $wpdb->prefix . 'cb_candidates';

		// Get all candidates in stages that precede "visited".
		$pre_visited = ['referral', 'contacted', 'invited'];
		$placeholders = implode(',', array_fill(0, count($pre_visited), '%s'));
		$candidates = $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM {$table} WHERE stage IN ({$placeholders})",
			...$pre_visited
		));

		if (empty($candidates)) { return; }

		foreach ($names as $guest_name) {
			$guest_lower = mb_strtolower($guest_name);

			foreach ($candidates as $c) {
				$candidate_lower = mb_strtolower(trim($c->name));

				// Match: exact, or guest name contained in candidate name, or vice versa.
				$match = ($guest_lower === $candidate_lower)
					|| (mb_strlen($guest_lower) >= 3 && mb_strpos($candidate_lower, $guest_lower) !== false)
					|| (mb_strlen($candidate_lower) >= 3 && mb_strpos($guest_lower, $candidate_lower) !== false);

				if (!$match) { continue; }

				$old_stage = $c->stage;

				// Update the candidate stage.
				$wpdb->update($table, [
					'stage'      => 'visited',
					'updated_at' => gmdate('Y-m-d H:i:s'),
				], ['id' => $c->id], ['%s', '%s'], ['%d']);

				// Trigger the automation (thank-you email + referrer notification).
				// The one-time guard inside run_recruitment_automations prevents duplicates.
				self::run_recruitment_automations($c, $old_stage, 'visited');

				if (class_exists('CBNexus_Logger')) {
					CBNexus_Logger::info('Guest attendee matched to pipeline candidate; auto-transitioned to visited.', [
						'guest_name'   => $guest_name,
						'candidate_id' => $c->id,
						'candidate'    => $c->name,
						'from_stage'   => $old_stage,
					]);
				}

				break; // One match per guest name is enough.
			}
		}
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
			'member_created'     => 'Member created. Welcome email sent.',
			'member_updated'     => 'Member profile updated.',
			'candidate_added'    => 'Candidate added to pipeline.',
			'candidate_updated'  => 'Candidate stage updated.',
			'candidate_saved'    => 'Candidate updated.',
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
		'recruit_stage_referrer'   => ['name' => 'Referrer Stage Update',    'group' => 'Recruitment'],
		'recruit_invitation'       => ['name' => 'Candidate Invitation',     'group' => 'Recruitment'],
		'recruit_accepted'         => ['name' => 'Candidate Accepted',       'group' => 'Recruitment'],
		'recruit_visited_thankyou' => ['name' => 'Visit Thank You',          'group' => 'Recruitment'],
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