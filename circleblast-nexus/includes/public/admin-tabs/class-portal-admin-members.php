<?php
/**
 * Portal Admin – Members Tab
 *
 * Extracted from class-portal-admin.php for maintainability.
 * Handles member listing, add/edit form, status transitions, and CSV export.
 */

defined('ABSPATH') || exit;

final class CBNexus_Portal_Admin_Members {

	// ─── Render ─────────────────────────────────────────────────────────

	public static function render(): void {
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
		$base   = CBNexus_Portal_Admin::admin_url('members');
		?>
		<?php CBNexus_Portal_Admin::render_notice($notice); ?>

		<div class="cbnexus-card">
			<div class="cbnexus-admin-header-row">
				<h2>Members (<?php echo esc_html($counts['total']); ?>)</h2>
				<?php if (current_user_can('cbnexus_create_members')) : ?>
					<a href="<?php echo esc_url(CBNexus_Portal_Admin::admin_url('members', ['new_member' => '1'])); ?>" class="cbnexus-btn cbnexus-btn-primary cbnexus-btn-sm">+ Add Member</a>
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
							<td><?php CBNexus_Portal_Admin::status_pill($status); ?></td>
							<td class="cbnexus-admin-meta"><?php echo esc_html($m['cb_join_date'] ?? '—'); ?></td>
							<td class="cbnexus-admin-actions-cell">
								<a href="<?php echo esc_url(CBNexus_Portal_Admin::admin_url('members', ['edit_member' => $uid])); ?>" class="cbnexus-link">Edit</a>
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
				<a href="<?php echo esc_url(CBNexus_Portal_Admin::admin_url('members')); ?>" class="cbnexus-btn cbnexus-btn-outline cbnexus-btn-sm">← Back</a>
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
					<a href="<?php echo esc_url(CBNexus_Portal_Admin::admin_url('members')); ?>" class="cbnexus-btn cbnexus-btn-outline">Cancel</a>
				</div>
			</form>
		</div>
		<?php
	}

	// ─── Action Handlers ────────────────────────────────────────────────

	public static function handle_save_member(): void {
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
			$wp_update = [
				'ID'           => $edit_uid,
				'first_name'   => $user_data['first_name'],
				'last_name'    => $user_data['last_name'],
				'display_name' => $user_data['display_name'],
			];
			$current = get_userdata($edit_uid);
			if ($current && $current->user_email !== $user_data['user_email'] && !empty($user_data['user_email']) && !email_exists($user_data['user_email'])) {
				$wp_update['user_email'] = $user_data['user_email'];
			}
			wp_update_user($wp_update);

			$user = get_userdata($edit_uid);
			if ($user && !in_array($role, $user->roles, true)) {
				foreach (['cb_member', 'cb_admin', 'cb_super_admin'] as $r) { $user->remove_role($r); }
				$user->add_role($role);
			}

			$result = CBNexus_Member_Service::update_member($edit_uid, $profile_data, true);

			if (!$result['success']) {
				set_transient('cbnexus_pa_member_errors', $result['errors'], 60);
				wp_safe_redirect(CBNexus_Portal_Admin::admin_url('members', ['edit_member' => $edit_uid]));
				exit;
			}

			wp_safe_redirect(CBNexus_Portal_Admin::admin_url('members', ['pa_notice' => 'member_updated']));
			exit;

		} else {
			$result = CBNexus_Member_Service::create_member($user_data, $profile_data, $role);

			if (!$result['success']) {
				set_transient('cbnexus_pa_member_errors', $result['errors'], 60);
				set_transient('cbnexus_pa_member_flash', $_POST, 60);
				wp_safe_redirect(CBNexus_Portal_Admin::admin_url('members', ['new_member' => '1']));
				exit;
			}

			$new_profile = CBNexus_Member_Repository::get_profile($result['user_id']);
			if ($new_profile) {
				CBNexus_Email_Service::send_welcome($result['user_id'], $new_profile);
			}

			wp_safe_redirect(CBNexus_Portal_Admin::admin_url('members', ['pa_notice' => 'member_created']));
			exit;
		}
	}

	public static function handle_member_status(): void {
		$action = sanitize_key($_GET['cbnexus_portal_member_action']);
		$uid    = absint($_GET['uid']);

		if (!wp_verify_nonce(wp_unslash($_GET['_panonce']), 'cbnexus_pa_member_' . $uid)) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }

		$map = ['activate' => 'active', 'deactivate' => 'inactive', 'alumni' => 'alumni'];
		if (!isset($map[$action])) { return; }

		$result = CBNexus_Member_Service::transition_status($uid, $map[$action]);

		// On activation, send the reactivation email with password reset link.
		if ($result['success'] && $action === 'activate') {
			$user = get_userdata($uid);
			if ($user) {
				$profile = array_merge(
					CBNexus_Member_Repository::get_profile($uid),
					[
						'user_email'   => $user->user_email,
						'first_name'   => $user->first_name,
						'last_name'    => $user->last_name,
						'display_name' => $user->display_name,
					]
				);
				CBNexus_Email_Service::send_reactivation($uid, $profile);
			}
		}

		$notice = $result['success'] ? 'status_updated' : 'error';
		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('members', ['pa_notice' => $notice]));
		exit;
	}

	public static function handle_export(): void {
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

	// ─── Helpers ────────────────────────────────────────────────────────

	private static function member_action_url(string $action, int $uid): string {
		$base = CBNexus_Portal_Admin::admin_url('members');
		$url  = add_query_arg([
			'cbnexus_portal_member_action' => $action,
			'uid' => $uid,
		], $base);
		return wp_nonce_url($url, 'cbnexus_pa_member_' . $uid, '_panonce');
	}
}
