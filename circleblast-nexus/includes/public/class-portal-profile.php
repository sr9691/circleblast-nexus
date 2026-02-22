<?php
/**
 * Portal Profile
 *
 * ITER-0006 / UX Refresh: Member-facing profile edit page matching demo.
 * Avatar header with rounded initials badge, form sections organized
 * into cards with uppercase section labels, plum-themed form inputs.
 */

defined('ABSPATH') || exit;

final class CBNexus_Portal_Profile {

	public static function init(): void {
		add_action('init', [__CLASS__, 'handle_submit']);
	}

	public static function handle_submit(): void {
		if (!isset($_POST['cbnexus_profile_submit'])) {
			return;
		}

		if (!is_user_logged_in()) {
			return;
		}

		if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(wp_unslash($_POST['_wpnonce']), 'cbnexus_update_profile')) {
			wp_die(__('Security check failed.', 'circleblast-nexus'));
		}

		$user_id = get_current_user_id();

		if (!CBNexus_Member_Repository::is_member($user_id)) {
			return;
		}

		$profile_data = [];
		$editable_keys = self::get_editable_keys();

		foreach ($editable_keys as $key) {
			if (isset($_POST[$key])) {
				$profile_data[$key] = wp_unslash($_POST[$key]);
			}
		}

		$result = CBNexus_Member_Service::update_member($user_id, $profile_data, false);

		$first = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
		$last  = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';

		if ($first !== '' || $last !== '') {
			wp_update_user([
				'ID'           => $user_id,
				'first_name'   => $first,
				'last_name'    => $last,
				'display_name' => trim($first . ' ' . $last),
			]);
		}

		$portal_url = CBNexus_Portal_Router::get_portal_url();
		$redirect   = add_query_arg('section', 'profile', $portal_url);

		if ($result['success']) {
			$redirect = add_query_arg('profile_notice', 'updated', $redirect);
		} else {
			$redirect = add_query_arg('profile_notice', 'error', $redirect);
		}

		wp_safe_redirect($redirect);
		exit;
	}

	public static function render(array $profile): void {
		$notice = isset($_GET['profile_notice']) ? sanitize_key($_GET['profile_notice']) : '';
		$initials = self::get_initials($profile);
		$display = $profile['display_name'] ?? '';
		$subtitle = ($profile['cb_title'] ?? '') . ($profile['cb_company'] ? ' · ' . $profile['cb_company'] : '');
		?>
		<?php if ($notice === 'updated') : ?>
			<div class="cbnexus-notice cbnexus-notice-success">
				<?php esc_html_e('Your profile has been updated.', 'circleblast-nexus'); ?>
			</div>
		<?php elseif ($notice === 'error') : ?>
			<div class="cbnexus-notice cbnexus-notice-error">
				<?php esc_html_e('There was an error updating your profile. Please try again.', 'circleblast-nexus'); ?>
			</div>
		<?php endif; ?>

		<!-- Profile Header -->
		<div class="cbnexus-profile-edit-header">
			<div class="cbnexus-profile-edit-avatar">
				<span><?php echo esc_html($initials); ?></span>
			</div>
			<div class="cbnexus-profile-edit-name">
				<h2><?php echo esc_html($display); ?></h2>
				<span class="cbnexus-text-muted"><?php echo esc_html($subtitle); ?></span>
			</div>
		</div>

		<form method="post" action="" class="cbnexus-profile-form">
			<?php wp_nonce_field('cbnexus_update_profile'); ?>

			<!-- Personal -->
			<div class="cbnexus-card">
				<div class="cbnexus-form-section-label"><?php esc_html_e('Your Details', 'circleblast-nexus'); ?></div>
				<div class="cbnexus-form-row">
					<div class="cbnexus-form-field">
						<label for="first_name"><?php esc_html_e('First Name', 'circleblast-nexus'); ?></label>
						<input type="text" id="first_name" name="first_name" value="<?php echo esc_attr($profile['first_name']); ?>" />
					</div>
					<div class="cbnexus-form-field">
						<label for="last_name"><?php esc_html_e('Last Name', 'circleblast-nexus'); ?></label>
						<input type="text" id="last_name" name="last_name" value="<?php echo esc_attr($profile['last_name']); ?>" />
					</div>
				</div>
				<div class="cbnexus-form-row">
					<div class="cbnexus-form-field">
						<label><?php esc_html_e('Email', 'circleblast-nexus'); ?></label>
						<input type="email" value="<?php echo esc_attr($profile['user_email']); ?>" disabled />
					</div>
					<div class="cbnexus-form-field">
						<label for="cb_phone"><?php esc_html_e('Phone', 'circleblast-nexus'); ?></label>
						<input type="tel" id="cb_phone" name="cb_phone" value="<?php echo esc_attr($profile['cb_phone']); ?>" />
					</div>
				</div>
			</div>

			<!-- Professional -->
			<div class="cbnexus-card">
				<div class="cbnexus-form-section-label"><?php esc_html_e('Professional', 'circleblast-nexus'); ?></div>
				<div class="cbnexus-form-row">
					<div class="cbnexus-form-field">
						<label for="cb_company"><?php esc_html_e('Company', 'circleblast-nexus'); ?></label>
						<input type="text" id="cb_company" name="cb_company" value="<?php echo esc_attr($profile['cb_company']); ?>" />
					</div>
					<div class="cbnexus-form-field">
						<label for="cb_title"><?php esc_html_e('Title', 'circleblast-nexus'); ?></label>
						<input type="text" id="cb_title" name="cb_title" value="<?php echo esc_attr($profile['cb_title']); ?>" />
					</div>
				</div>
				<?php
				// Show read-only Category if assigned.
				$member_cats = $profile['cb_member_categories'] ?? [];
				if (!empty($member_cats)) :
					$cat_id = is_array($member_cats) ? (int) ($member_cats[0] ?? 0) : 0;
					if ($cat_id > 0) :
						global $wpdb;
						$cat_title = $wpdb->get_var($wpdb->prepare(
							"SELECT title FROM {$wpdb->prefix}cb_recruitment_categories WHERE id = %d",
							$cat_id
						));
						if ($cat_title) :
				?>
					<div class="cbnexus-form-field">
						<label><?php esc_html_e('Category', 'circleblast-nexus'); ?></label>
						<div style="padding:10px 0;"><span class="cbnexus-tag cbnexus-tag-category"><?php echo esc_html($cat_title); ?></span></div>
					</div>
				<?php endif; endif; endif; ?>
				<div class="cbnexus-form-row">
					<div class="cbnexus-form-field">
						<label for="cb_industry"><?php esc_html_e('Industry', 'circleblast-nexus'); ?></label>
						<select id="cb_industry" name="cb_industry">
							<option value=""><?php esc_html_e('— Select —', 'circleblast-nexus'); ?></option>
							<?php foreach (CBNexus_Member_Service::get_industries() as $ind) : ?>
								<option value="<?php echo esc_attr($ind); ?>" <?php selected($profile['cb_industry'], $ind); ?>><?php echo esc_html($ind); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="cbnexus-form-field">
						<label for="cb_linkedin"><?php esc_html_e('LinkedIn', 'circleblast-nexus'); ?></label>
						<input type="url" id="cb_linkedin" name="cb_linkedin" value="<?php echo esc_attr($profile['cb_linkedin']); ?>" />
					</div>
				</div>
				<div class="cbnexus-form-field">
					<label for="cb_website"><?php esc_html_e('Website', 'circleblast-nexus'); ?></label>
					<input type="url" id="cb_website" name="cb_website" value="<?php echo esc_attr($profile['cb_website'] ?? ''); ?>" />
				</div>
			</div>

			<!-- About You -->
			<div class="cbnexus-card">
				<div class="cbnexus-form-section-label"><?php esc_html_e('About You', 'circleblast-nexus'); ?></div>
				<div class="cbnexus-form-field">
					<label for="cb_expertise"><?php esc_html_e('Expertise', 'circleblast-nexus'); ?></label>
					<input type="text" id="cb_expertise" name="cb_expertise" value="<?php echo esc_attr(self::tags_to_string($profile['cb_expertise'])); ?>" />
				</div>
				<div class="cbnexus-form-field">
					<label for="cb_looking_for"><?php esc_html_e('Looking For', 'circleblast-nexus'); ?></label>
					<input type="text" id="cb_looking_for" name="cb_looking_for" value="<?php echo esc_attr(self::tags_to_string($profile['cb_looking_for'])); ?>" />
				</div>
				<div class="cbnexus-form-field">
					<label for="cb_can_help_with"><?php esc_html_e('Can Help With', 'circleblast-nexus'); ?></label>
					<input type="text" id="cb_can_help_with" name="cb_can_help_with" value="<?php echo esc_attr(self::tags_to_string($profile['cb_can_help_with'])); ?>" />
				</div>
				<div class="cbnexus-form-field">
					<label for="cb_bio"><?php esc_html_e('Bio', 'circleblast-nexus'); ?></label>
					<textarea id="cb_bio" name="cb_bio" rows="3"><?php echo esc_textarea($profile['cb_bio']); ?></textarea>
				</div>
			</div>

			<!-- Preferences -->
			<div class="cbnexus-card">
				<div class="cbnexus-form-section-label"><?php esc_html_e('Preferences', 'circleblast-nexus'); ?></div>
				<div class="cbnexus-form-field">
					<label for="cb_matching_frequency"><?php esc_html_e('1:1 Matching Frequency', 'circleblast-nexus'); ?></label>
					<select id="cb_matching_frequency" name="cb_matching_frequency">
						<option value="monthly" <?php selected($profile['cb_matching_frequency'] ?? 'monthly', 'monthly'); ?>><?php esc_html_e('Monthly', 'circleblast-nexus'); ?></option>
						<option value="quarterly" <?php selected($profile['cb_matching_frequency'] ?? '', 'quarterly'); ?>><?php esc_html_e('Quarterly', 'circleblast-nexus'); ?></option>
						<option value="paused" <?php selected($profile['cb_matching_frequency'] ?? '', 'paused'); ?>><?php esc_html_e('Paused', 'circleblast-nexus'); ?></option>
					</select>
					<span class="cbnexus-text-muted" style="font-size:12px;display:block;margin-top:4px;"><?php esc_html_e('How often you\'d like to be paired. "Paused" = no new suggestions.', 'circleblast-nexus'); ?></span>
				</div>
				<div class="cbnexus-form-row">
					<div class="cbnexus-form-field">
						<label for="cb_email_digest"><?php esc_html_e('Events Digest', 'circleblast-nexus'); ?></label>
						<select id="cb_email_digest" name="cb_email_digest">
							<option value="yes" <?php selected($profile['cb_email_digest'] ?? 'yes', 'yes'); ?>><?php esc_html_e('Yes', 'circleblast-nexus'); ?></option>
							<option value="no" <?php selected($profile['cb_email_digest'] ?? '', 'no'); ?>><?php esc_html_e('No', 'circleblast-nexus'); ?></option>
						</select>
						<span class="cbnexus-text-muted" style="font-size:12px;display:block;margin-top:4px;"><?php esc_html_e('Weekly email with upcoming events.', 'circleblast-nexus'); ?></span>
					</div>
					<div class="cbnexus-form-field">
						<label for="cb_email_reminders"><?php esc_html_e('Reminder Emails', 'circleblast-nexus'); ?></label>
						<select id="cb_email_reminders" name="cb_email_reminders">
							<option value="yes" <?php selected($profile['cb_email_reminders'] ?? 'yes', 'yes'); ?>><?php esc_html_e('Yes', 'circleblast-nexus'); ?></option>
							<option value="no" <?php selected($profile['cb_email_reminders'] ?? '', 'no'); ?>><?php esc_html_e('No', 'circleblast-nexus'); ?></option>
						</select>
						<span class="cbnexus-text-muted" style="font-size:12px;display:block;margin-top:4px;"><?php esc_html_e('Follow-up nudges for unanswered suggestions and meeting reminders.', 'circleblast-nexus'); ?></span>
					</div>
				</div>
			</div>

			<button type="submit" name="cbnexus_profile_submit" value="1" class="cbnexus-btn cbnexus-btn-primary" style="border-radius:14px;">
				<?php esc_html_e('Save Changes', 'circleblast-nexus'); ?>
			</button>
		</form>
		<?php
	}

	private static function tags_to_string($tags): string {
		return is_array($tags) ? implode(', ', $tags) : (string) $tags;
	}

	private static function get_editable_keys(): array {
		$schema = CBNexus_Member_Service::get_meta_schema();
		$keys = [];
		foreach ($schema as $key => $def) {
			if (!empty($def['editable_by_member'])) {
				$keys[] = $key;
			}
		}
		return $keys;
	}

	private static function get_initials(array $m): string {
		$first = $m['first_name'] ?? '';
		$last  = $m['last_name'] ?? '';
		if ($first && $last) {
			return strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
		}
		$display = $m['display_name'] ?? '?';
		return strtoupper(mb_substr($display, 0, 2));
	}
}