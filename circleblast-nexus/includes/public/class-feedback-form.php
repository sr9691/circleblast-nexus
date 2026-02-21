<?php
/**
 * Feedback Form – AJAX handler + modal HTML renderer.
 *
 * Provides a lightweight "Send Feedback" form accessible from the portal
 * header via a small icon button. Stores submissions in cb_feedback and
 * emails super admins.
 *
 * @since 1.3.0
 */

defined('ABSPATH') || exit;

final class CBNexus_Feedback_Form {

	/**
	 * Register AJAX hooks and enqueue scripts.
	 */
	public static function init(): void {
		add_action('wp_ajax_cbnexus_submit_feedback', [__CLASS__, 'ajax_submit']);
		add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
	}

	/**
	 * Enqueue feedback JS on portal pages.
	 */
	public static function enqueue_scripts(): void {
		global $post;
		if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'cbnexus_portal')) {
			return;
		}

		wp_enqueue_script(
			'cbnexus-feedback',
			CBNEXUS_PLUGIN_URL . 'assets/js/feedback.js',
			[],
			CBNEXUS_VERSION,
			true
		);

		wp_localize_script('cbnexus-feedback', 'cbnexusFeedback', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce'    => wp_create_nonce('cbnexus_submit_feedback'),
		]);
	}

	// ─── AJAX Handler ──────────────────────────────────────────────────

	/**
	 * Handle the AJAX feedback submission.
	 */
	public static function ajax_submit(): void {
		check_ajax_referer('cbnexus_submit_feedback');

		if (!is_user_logged_in()) {
			wp_send_json_error('You must be logged in.', 403);
		}

		$user_id = get_current_user_id();

		// Check membership.
		if (class_exists('CBNexus_Member_Repository') && !CBNexus_Member_Repository::is_member($user_id)) {
			wp_send_json_error('Only members can submit feedback.', 403);
		}

		$type         = sanitize_key(wp_unslash($_POST['type'] ?? 'feedback'));
		$subject      = sanitize_text_field(wp_unslash($_POST['subject'] ?? ''));
		$message      = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
		$page_context = sanitize_key(wp_unslash($_POST['page_context'] ?? ''));

		if (empty($message)) {
			wp_send_json_error('Please include a message.');
		}

		// Auto-generate subject from message if not provided.
		if (empty($subject)) {
			$subject = mb_substr(wp_strip_all_tags($message), 0, 80);
			if (mb_strlen($message) > 80) {
				$subject .= '…';
			}
		}

		$feedback_id = CBNexus_Feedback_Service::submit($user_id, $type, $subject, $message, $page_context);

		if ($feedback_id) {
			wp_send_json_success(['message' => 'Thank you for your feedback!']);
		} else {
			wp_send_json_error('Something went wrong. Please try again.');
		}
	}

	// ─── Modal HTML ────────────────────────────────────────────────────

	/**
	 * Render the feedback modal HTML. Called once per portal page load,
	 * typically at the end of the portal container.
	 */
	public static function render_modal(): void {
		if (!is_user_logged_in()) {
			return;
		}
		?>
		<!-- Feedback Modal Overlay -->
		<div id="cbnexus-feedback-overlay" class="cbnexus-feedback-overlay"></div>

		<!-- Feedback Modal -->
		<div id="cbnexus-feedback-modal" class="cbnexus-feedback-modal">
			<div class="cbnexus-feedback-header">
				<h3><?php esc_html_e('Send Feedback', 'circleblast-nexus'); ?></h3>
				<button type="button" class="cbnexus-feedback-close" data-feedback-close aria-label="<?php esc_attr_e('Close', 'circleblast-nexus'); ?>">&times;</button>
			</div>
			<p class="cbnexus-feedback-subtitle"><?php esc_html_e('Help us improve! Report a bug, suggest a feature, or share any thoughts.', 'circleblast-nexus'); ?></p>

			<div id="cbnexus-feedback-msg" class="cbnexus-feedback-msg" style="display:none;"></div>

			<form id="cbnexus-feedback-form" class="cbnexus-feedback-form" autocomplete="off">
				<div class="cbnexus-feedback-field">
					<label><?php esc_html_e('Type', 'circleblast-nexus'); ?></label>
					<div class="cbnexus-feedback-type-pills">
						<label class="cbnexus-feedback-pill active">
							<input type="radio" name="type" value="feedback" checked />
							<span>💬 <?php esc_html_e('Feedback', 'circleblast-nexus'); ?></span>
						</label>
						<label class="cbnexus-feedback-pill">
							<input type="radio" name="type" value="bug" />
							<span>🐛 <?php esc_html_e('Bug', 'circleblast-nexus'); ?></span>
						</label>
						<label class="cbnexus-feedback-pill">
							<input type="radio" name="type" value="idea" />
							<span>💡 <?php esc_html_e('Idea', 'circleblast-nexus'); ?></span>
						</label>
						<label class="cbnexus-feedback-pill">
							<input type="radio" name="type" value="question" />
							<span>❓ <?php esc_html_e('Question', 'circleblast-nexus'); ?></span>
						</label>
					</div>
				</div>

				<div class="cbnexus-feedback-field">
					<label for="cbnexus-feedback-subject"><?php esc_html_e('Subject', 'circleblast-nexus'); ?> <span class="cbnexus-text-muted">(<?php esc_html_e('optional', 'circleblast-nexus'); ?>)</span></label>
					<input type="text" id="cbnexus-feedback-subject" name="subject" placeholder="<?php esc_attr_e('Brief summary', 'circleblast-nexus'); ?>" />
				</div>

				<div class="cbnexus-feedback-field">
					<label for="cbnexus-feedback-message"><?php esc_html_e('Message', 'circleblast-nexus'); ?> <span class="cbnexus-required">*</span></label>
					<textarea id="cbnexus-feedback-message" name="message" rows="4" required placeholder="<?php esc_attr_e('What\'s on your mind?', 'circleblast-nexus'); ?>"></textarea>
				</div>

				<!-- Hidden field to capture current page context -->
				<input type="hidden" name="page_context" id="cbnexus-feedback-context" value="" />

				<div class="cbnexus-feedback-submit">
					<button type="submit" class="cbnexus-btn cbnexus-btn-primary"><?php esc_html_e('Send Feedback', 'circleblast-nexus'); ?></button>
					<button type="button" class="cbnexus-btn cbnexus-btn-outline" data-feedback-close><?php esc_html_e('Cancel', 'circleblast-nexus'); ?></button>
				</div>
			</form>
		</div>
		<?php
	}
}
