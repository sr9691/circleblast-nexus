<?php
/**
 * Admin Member Form
 *
 * ITER-0005: Add/edit member form with all 17 profile fields grouped
 * into sections. Handles creation (with welcome email) and updates.
 * All actions protected by nonce verification + capability checks.
 */

defined('ABSPATH') || exit;

final class CBNexus_Admin_Member_Form {

	public static function init(): void {
		add_action('admin_init', [__CLASS__, 'handle_submit']);
	}

	public static function handle_submit(): void {
		if (!isset($_POST['cbnexus_member_submit'])) { return; }

		if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(wp_unslash($_POST['_wpnonce']), 'cbnexus_save_member')) {
			wp_die(__('Security check failed.', 'circleblast-nexus'));
		}

		$editing = isset($_POST['cbnexus_edit_user_id']) && absint($_POST['cbnexus_edit_user_id']) > 0;

		if ($editing && !current_user_can('cbnexus_manage_members')) {
			wp_die(__('You do not have permission to edit members.', 'circleblast-nexus'));
		}
		if (!$editing && !current_user_can('cbnexus_create_members')) {
			wp_die(__('You do not have permission to create members.', 'circleblast-nexus'));
		}

		$editing ? self::process_update() : self::process_create();
	}

	private static function process_create(): void {
		$user_data    = self::extract_user_data();
		$profile_data = self::extract_profile_data();
		$role         = self::extract_role();

		$result = CBNexus_Member_Service::create_member($user_data, $profile_data, $role);

		if (!$result['success']) {
			set_transient('cbnexus_form_errors', $result['errors'], 60);
			set_transient('cbnexus_form_data', $_POST, 60);
			wp_safe_redirect(admin_url('admin.php?page=cbnexus-member-new&cbnexus_notice=error'));
			exit;
		}

		$profile = CBNexus_Member_Repository::get_profile($result['user_id']);
		if ($profile) {
			CBNexus_Email_Service::send_welcome($result['user_id'], $profile);
		}

		wp_safe_redirect(admin_url('admin.php?page=cbnexus-members&cbnexus_notice=created'));
		exit;
	}

	private static function process_update(): void {
		$user_id      = absint($_POST['cbnexus_edit_user_id']);
		$profile_data = self::extract_profile_data();
		$user_data    = self::extract_user_data();

		$wp_update = [
			'ID' => $user_id,
			'first_name'   => $user_data['first_name'] ?? '',
			'last_name'    => $user_data['last_name'] ?? '',
			'display_name' => trim(($user_data['first_name'] ?? '') . ' ' . ($user_data['last_name'] ?? '')),
		];

		if (!empty($user_data['user_email'])) {
			$current = get_userdata($user_id);
			if ($current && $current->user_email !== $user_data['user_email'] && !email_exists($user_data['user_email'])) {
				$wp_update['user_email'] = $user_data['user_email'];
			}
		}

		wp_update_user($wp_update);

		$new_role = self::extract_role();
		$user = get_userdata($user_id);
		if ($user && !in_array($new_role, $user->roles, true)) {
			foreach (['cb_member', 'cb_admin', 'cb_super_admin'] as $r) { $user->remove_role($r); }
			$user->add_role($new_role);
		}

		$result = CBNexus_Member_Service::update_member($user_id, $profile_data, true);

		if (!$result['success']) {
			set_transient('cbnexus_form_errors', $result['errors'], 60);
			wp_safe_redirect(admin_url('admin.php?page=cbnexus-member-new&edit=' . $user_id . '&cbnexus_notice=error'));
			exit;
		}

		wp_safe_redirect(admin_url('admin.php?page=cbnexus-members&cbnexus_notice=updated'));
		exit;
	}

	public static function render_add_page(): void {
		$editing = isset($_GET['edit']) && absint($_GET['edit']) > 0;
		$user_id = $editing ? absint($_GET['edit']) : 0;
		$profile = [];

		if ($editing) {
			if (!current_user_can('cbnexus_manage_members')) { wp_die(__('Permission denied.', 'circleblast-nexus')); }
			$profile = CBNexus_Member_Repository::get_profile($user_id);
			if (!$profile) { wp_die(__('Member not found.', 'circleblast-nexus')); }
		} else {
			if (!current_user_can('cbnexus_create_members')) { wp_die(__('Permission denied.', 'circleblast-nexus')); }
		}

		$errors    = get_transient('cbnexus_form_errors') ?: null; delete_transient('cbnexus_form_errors');
		$form_data = get_transient('cbnexus_form_data') ?: null; delete_transient('cbnexus_form_data');
		$industries = CBNexus_Member_Service::get_industries();
		$title = $editing ? __('Edit Member', 'circleblast-nexus') : __('Add New Member', 'circleblast-nexus');
		?>
		<div class="wrap">
			<h1><?php echo esc_html($title); ?></h1>
			<?php if (!empty($errors)) : ?><div class="notice notice-error"><?php foreach ($errors as $err) : ?><p><?php echo esc_html($err); ?></p><?php endforeach; ?></div><?php endif; ?>

			<form method="post" action="" novalidate>
				<?php wp_nonce_field('cbnexus_save_member'); ?>
				<?php if ($editing) : ?><input type="hidden" name="cbnexus_edit_user_id" value="<?php echo esc_attr($user_id); ?>" /><?php endif; ?>

				<h2><?php esc_html_e('Account Information', 'circleblast-nexus'); ?></h2>
				<table class="form-table">
					<?php self::text_field('first_name', __('First Name', 'circleblast-nexus'), self::val('first_name', $profile, $form_data), true); ?>
					<?php self::text_field('last_name', __('Last Name', 'circleblast-nexus'), self::val('last_name', $profile, $form_data), true); ?>
					<?php self::text_field('user_email', __('Email Address', 'circleblast-nexus'), self::val('user_email', $profile, $form_data), true, 'email'); ?>
					<?php self::select_field('cb_role', __('Role', 'circleblast-nexus'), self::current_role($profile), ['cb_member' => 'Member', 'cb_admin' => 'Admin', 'cb_super_admin' => 'Super Admin']); ?>
				</table>

				<h2><?php esc_html_e('Professional Information', 'circleblast-nexus'); ?></h2>
				<table class="form-table">
					<?php self::text_field('cb_company', __('Company', 'circleblast-nexus'), self::val('cb_company', $profile, $form_data), true); ?>
					<?php self::text_field('cb_title', __('Job Title', 'circleblast-nexus'), self::val('cb_title', $profile, $form_data), true); ?>
					<?php self::select_field('cb_industry', __('Industry', 'circleblast-nexus'), self::val('cb_industry', $profile, $form_data), array_combine($industries, $industries), true); ?>
				</table>

				<h2><?php esc_html_e('Networking', 'circleblast-nexus'); ?></h2>
				<table class="form-table">
					<?php self::text_field('cb_expertise', __('Expertise / Skills', 'circleblast-nexus'), self::val_tags('cb_expertise', $profile, $form_data), false, 'text', __('Comma-separated', 'circleblast-nexus')); ?>
					<?php self::text_field('cb_looking_for', __('Looking For', 'circleblast-nexus'), self::val_tags('cb_looking_for', $profile, $form_data), false, 'text', __('Comma-separated', 'circleblast-nexus')); ?>
					<?php self::text_field('cb_can_help_with', __('Can Help With', 'circleblast-nexus'), self::val_tags('cb_can_help_with', $profile, $form_data), false, 'text', __('Comma-separated', 'circleblast-nexus')); ?>
				</table>

				<h2><?php esc_html_e('Contact Information', 'circleblast-nexus'); ?></h2>
				<table class="form-table">
					<?php self::text_field('cb_phone', __('Phone', 'circleblast-nexus'), self::val('cb_phone', $profile, $form_data), false, 'tel'); ?>
					<?php self::text_field('cb_linkedin', __('LinkedIn URL', 'circleblast-nexus'), self::val('cb_linkedin', $profile, $form_data), false, 'url'); ?>
					<?php self::text_field('cb_website', __('Website', 'circleblast-nexus'), self::val('cb_website', $profile, $form_data), false, 'url'); ?>
				</table>

				<h2><?php esc_html_e('Personal', 'circleblast-nexus'); ?></h2>
				<table class="form-table">
					<?php self::textarea_field('cb_bio', __('Bio / About', 'circleblast-nexus'), self::val('cb_bio', $profile, $form_data)); ?>
					<?php self::text_field('cb_photo_url', __('Profile Photo URL', 'circleblast-nexus'), self::val('cb_photo_url', $profile, $form_data), false, 'url'); ?>
				</table>

				<h2><?php esc_html_e('Admin Information', 'circleblast-nexus'); ?></h2>
				<table class="form-table">
					<?php self::text_field('cb_referred_by', __('Referred By', 'circleblast-nexus'), self::val('cb_referred_by', $profile, $form_data)); ?>
					<?php self::text_field('cb_join_date', __('Join Date', 'circleblast-nexus'), self::val('cb_join_date', $profile, $form_data) ?: gmdate('Y-m-d'), false, 'date'); ?>
					<?php self::select_field('cb_member_status', __('Status', 'circleblast-nexus'), self::val('cb_member_status', $profile, $form_data) ?: 'active', ['active' => 'Active', 'inactive' => 'Inactive', 'alumni' => 'Alumni']); ?>
					<?php self::select_field('cb_onboarding_stage', __('Onboarding Stage', 'circleblast-nexus'), self::val('cb_onboarding_stage', $profile, $form_data) ?: 'access_setup', ['access_setup' => 'Access Setup', 'walkthrough' => 'Walkthrough', 'ignite' => 'Ignite', 'ambassador' => 'Ambassador', 'complete' => 'Complete']); ?>
					<?php self::text_field('cb_ambassador_id', __('Ambassador (User ID)', 'circleblast-nexus'), self::val('cb_ambassador_id', $profile, $form_data)); ?>
					<?php self::textarea_field('cb_notes_admin', __('Admin Notes', 'circleblast-nexus'), self::val('cb_notes_admin', $profile, $form_data)); ?>
				</table>

				<?php submit_button($editing ? __('Update Member', 'circleblast-nexus') : __('Create Member', 'circleblast-nexus'), 'primary', 'cbnexus_member_submit'); ?>
			</form>
		</div>
		<?php
	}

	// ─── Field Helpers ─────────────────────────────────────────────────

	private static function text_field(string $n, string $l, string $v, bool $req = false, string $type = 'text', string $desc = ''): void {
		$r = $req ? ' <span class="description">(' . esc_html__('required', 'circleblast-nexus') . ')</span>' : '';
		echo '<tr><th scope="row"><label for="' . esc_attr($n) . '">' . esc_html($l) . $r . '</label></th><td>';
		echo '<input type="' . esc_attr($type) . '" name="' . esc_attr($n) . '" id="' . esc_attr($n) . '" value="' . esc_attr($v) . '" class="regular-text"' . ($req ? ' required' : '') . ' />';
		if ($desc) { echo '<p class="description">' . esc_html($desc) . '</p>'; }
		echo '</td></tr>';
	}

	private static function textarea_field(string $n, string $l, string $v): void {
		echo '<tr><th scope="row"><label for="' . esc_attr($n) . '">' . esc_html($l) . '</label></th><td>';
		echo '<textarea name="' . esc_attr($n) . '" id="' . esc_attr($n) . '" rows="4" class="large-text">' . esc_textarea($v) . '</textarea></td></tr>';
	}

	private static function select_field(string $n, string $l, string $v, array $opts, bool $req = false): void {
		$r = $req ? ' <span class="description">(' . esc_html__('required', 'circleblast-nexus') . ')</span>' : '';
		echo '<tr><th scope="row"><label for="' . esc_attr($n) . '">' . esc_html($l) . $r . '</label></th><td><select name="' . esc_attr($n) . '" id="' . esc_attr($n) . '"' . ($req ? ' required' : '') . '>';
		echo '<option value="">' . esc_html__('— Select —', 'circleblast-nexus') . '</option>';
		foreach ($opts as $ov => $ol) { echo '<option value="' . esc_attr($ov) . '"' . selected($v, $ov, false) . '>' . esc_html($ol) . '</option>'; }
		echo '</select></td></tr>';
	}

	// ─── Value Helpers ─────────────────────────────────────────────────

	private static function val(string $k, array $p, ?array $f): string {
		if (!empty($f) && isset($f[$k])) { return sanitize_text_field($f[$k]); }
		return (string) ($p[$k] ?? '');
	}

	private static function val_tags(string $k, array $p, ?array $f): string {
		if (!empty($f) && isset($f[$k])) { return sanitize_text_field($f[$k]); }
		$t = $p[$k] ?? [];
		return is_array($t) ? implode(', ', $t) : (string) $t;
	}

	private static function current_role(array $p): string {
		foreach (['cb_super_admin', 'cb_admin', 'cb_member'] as $r) {
			if (in_array($r, $p['roles'] ?? [], true)) { return $r; }
		}
		return 'cb_member';
	}

	private static function extract_user_data(): array {
		return [
			'user_email'   => isset($_POST['user_email']) ? sanitize_email(wp_unslash($_POST['user_email'])) : '',
			'first_name'   => isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '',
			'last_name'    => isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '',
			'display_name' => trim((isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '') . ' ' . (isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '')),
		];
	}

	private static function extract_profile_data(): array {
		$keys = CBNexus_Member_Repository::get_meta_keys();
		$data = [];
		foreach ($keys as $k) { if (isset($_POST[$k])) { $data[$k] = wp_unslash($_POST[$k]); } }
		return $data;
	}

	private static function extract_role(): string {
		$r = isset($_POST['cb_role']) ? sanitize_text_field(wp_unslash($_POST['cb_role'])) : 'cb_member';
		return in_array($r, ['cb_member', 'cb_admin', 'cb_super_admin'], true) ? $r : 'cb_member';
	}
}