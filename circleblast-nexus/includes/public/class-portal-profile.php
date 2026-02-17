<?php
/**
 * Portal Profile
 *
 * ITER-0006: Member-facing profile edit page. Members can update
 * their own editable fields (bio, expertise, looking_for, can_help_with,
 * contact info, photo). Admin-only fields are hidden.
 */

defined('ABSPATH') || exit;

final class CBNexus_Portal_Profile {

	/**
	 * Initialize profile form handling.
	 */
	public static function init(): void {
		add_action('init', [__CLASS__, 'handle_submit']);
	}

	/**
	 * Handle profile form submission.
	 */
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

		// Collect member-editable fields from POST.
		$profile_data = [];
		$editable_keys = self::get_editable_keys();

		foreach ($editable_keys as $key) {
			if (isset($_POST[$key])) {
				$profile_data[$key] = wp_unslash($_POST[$key]);
			}
		}

		// Update via service (is_admin=false filters to member-editable only).
		$result = CBNexus_Member_Service::update_member($user_id, $profile_data, false);

		// Also update WP first/last name if provided.
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

		// Redirect back with notice.
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

	/**
	 * Render the profile edit section.
	 *
	 * @param array $profile Current user's profile.
	 */
	public static function render(array $profile): void {
		$notice = isset($_GET['profile_notice']) ? sanitize_key($_GET['profile_notice']) : '';
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

		<div class="cbnexus-card">
			<h2><?php esc_html_e('My Profile', 'circleblast-nexus'); ?></h2>
			<p class="cbnexus-text-muted"><?php esc_html_e('Update your profile information visible to other members.', 'circleblast-nexus'); ?></p>

			<form method="post" action="" class="cbnexus-profile-form">
				<?php wp_nonce_field('cbnexus_update_profile'); ?>

				<!-- Name -->
				<div class="cbnexus-form-section">
					<h3><?php esc_html_e('Basic Information', 'circleblast-nexus'); ?></h3>
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
					<div class="cbnexus-form-field">
						<label><?php esc_html_e('Email', 'circleblast-nexus'); ?></label>
						<input type="email" value="<?php echo esc_attr($profile['user_email']); ?>" disabled />
						<p class="cbnexus-field-help"><?php esc_html_e('Contact an admin to change your email address.', 'circleblast-nexus'); ?></p>
					</div>
				</div>

				<!-- Professional -->
				<div class="cbnexus-form-section">
					<h3><?php esc_html_e('Professional', 'circleblast-nexus'); ?></h3>
					<div class="cbnexus-form-row">
						<div class="cbnexus-form-field">
							<label for="cb_company"><?php esc_html_e('Company', 'circleblast-nexus'); ?></label>
							<input type="text" id="cb_company" name="cb_company" value="<?php echo esc_attr($profile['cb_company']); ?>" />
						</div>
						<div class="cbnexus-form-field">
							<label for="cb_title"><?php esc_html_e('Job Title', 'circleblast-nexus'); ?></label>
							<input type="text" id="cb_title" name="cb_title" value="<?php echo esc_attr($profile['cb_title']); ?>" />
						</div>
					</div>
					<div class="cbnexus-form-field">
						<label for="cb_industry"><?php esc_html_e('Industry', 'circleblast-nexus'); ?></label>
						<select id="cb_industry" name="cb_industry">
							<option value=""><?php esc_html_e('— Select —', 'circleblast-nexus'); ?></option>
							<?php foreach (CBNexus_Member_Service::get_industries() as $ind) : ?>
								<option value="<?php echo esc_attr($ind); ?>" <?php selected($profile['cb_industry'], $ind); ?>><?php echo esc_html($ind); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<!-- Networking -->
				<div class="cbnexus-form-section">
					<h3><?php esc_html_e('Networking', 'circleblast-nexus'); ?></h3>
					<div class="cbnexus-form-field">
						<label for="cb_expertise"><?php esc_html_e('Expertise / Skills', 'circleblast-nexus'); ?></label>
						<input type="text" id="cb_expertise" name="cb_expertise" value="<?php echo esc_attr(self::tags_to_string($profile['cb_expertise'])); ?>" />
						<p class="cbnexus-field-help"><?php esc_html_e('Comma-separated (e.g., Marketing, Strategy, Sales)', 'circleblast-nexus'); ?></p>
					</div>
					<div class="cbnexus-form-field">
						<label for="cb_looking_for"><?php esc_html_e('Looking For', 'circleblast-nexus'); ?></label>
						<input type="text" id="cb_looking_for" name="cb_looking_for" value="<?php echo esc_attr(self::tags_to_string($profile['cb_looking_for'])); ?>" />
						<p class="cbnexus-field-help"><?php esc_html_e('What kind of connections or help are you looking for?', 'circleblast-nexus'); ?></p>
					</div>
					<div class="cbnexus-form-field">
						<label for="cb_can_help_with"><?php esc_html_e('Can Help With', 'circleblast-nexus'); ?></label>
						<input type="text" id="cb_can_help_with" name="cb_can_help_with" value="<?php echo esc_attr(self::tags_to_string($profile['cb_can_help_with'])); ?>" />
						<p class="cbnexus-field-help"><?php esc_html_e('How can you help other members?', 'circleblast-nexus'); ?></p>
					</div>
				</div>

				<!-- Contact -->
				<div class="cbnexus-form-section">
					<h3><?php esc_html_e('Contact Information', 'circleblast-nexus'); ?></h3>
					<div class="cbnexus-form-field">
						<label for="cb_phone"><?php esc_html_e('Phone', 'circleblast-nexus'); ?></label>
						<input type="tel" id="cb_phone" name="cb_phone" value="<?php echo esc_attr($profile['cb_phone']); ?>" />
					</div>
					<div class="cbnexus-form-field">
						<label for="cb_linkedin"><?php esc_html_e('LinkedIn URL', 'circleblast-nexus'); ?></label>
						<input type="url" id="cb_linkedin" name="cb_linkedin" value="<?php echo esc_attr($profile['cb_linkedin']); ?>" />
					</div>
					<div class="cbnexus-form-field">
						<label for="cb_website"><?php esc_html_e('Website', 'circleblast-nexus'); ?></label>
						<input type="url" id="cb_website" name="cb_website" value="<?php echo esc_attr($profile['cb_website']); ?>" />
					</div>
				</div>

				<!-- Bio & Photo -->
				<div class="cbnexus-form-section">
					<h3><?php esc_html_e('About You', 'circleblast-nexus'); ?></h3>
					<div class="cbnexus-form-field">
						<label for="cb_bio"><?php esc_html_e('Bio', 'circleblast-nexus'); ?></label>
						<textarea id="cb_bio" name="cb_bio" rows="5"><?php echo esc_textarea($profile['cb_bio']); ?></textarea>
					</div>
					<div class="cbnexus-form-field">
						<label for="cb_photo_url"><?php esc_html_e('Profile Photo URL', 'circleblast-nexus'); ?></label>
						<input type="url" id="cb_photo_url" name="cb_photo_url" value="<?php echo esc_attr($profile['cb_photo_url']); ?>" />
						<p class="cbnexus-field-help"><?php esc_html_e('Paste a URL to your profile photo (e.g., LinkedIn photo link).', 'circleblast-nexus'); ?></p>
					</div>
				</div>

				<div class="cbnexus-form-actions">
					<button type="submit" name="cbnexus_profile_submit" value="1" class="cbnexus-btn cbnexus-btn-primary">
						<?php esc_html_e('Save Changes', 'circleblast-nexus'); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Convert tags array to comma-separated string.
	 */
	private static function tags_to_string($tags): string {
		return is_array($tags) ? implode(', ', $tags) : (string) $tags;
	}

	/**
	 * Get list of meta keys that members can edit.
	 */
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
}
