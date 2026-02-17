<?php
/**
 * Portal Meetings
 *
 * ITER-0009: Member-facing meetings page. Shows pending requests,
 * upcoming/scheduled meetings, completed meetings needing notes,
 * and meeting history. Handles request/accept/decline/schedule/
 * complete/notes via AJAX.
 */

defined('ABSPATH') || exit;

final class CBNexus_Portal_Meetings {

	public static function init(): void {
		// AJAX actions (logged-in members only).
		$actions = [
			'cbnexus_request_meeting',
			'cbnexus_respond_meeting',
			'cbnexus_schedule_meeting',
			'cbnexus_complete_meeting',
			'cbnexus_submit_notes',
			'cbnexus_cancel_meeting',
		];
		foreach ($actions as $action) {
			add_action('wp_ajax_' . $action, [__CLASS__, 'handle_' . str_replace('cbnexus_', '', $action)]);
		}

		add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
	}

	public static function enqueue_scripts(): void {
		global $post;
		if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'cbnexus_portal')) { return; }

		wp_enqueue_script('cbnexus-meetings', CBNEXUS_PLUGIN_URL . 'assets/js/meetings.js', [], CBNEXUS_VERSION, true);
		wp_localize_script('cbnexus-meetings', 'cbnexusMtg', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce'    => wp_create_nonce('cbnexus_meetings'),
		]);
	}

	// ─── Render ────────────────────────────────────────────────────────

	/**
	 * Render the meetings section (called by portal router).
	 */
	public static function render(array $profile): void {
		$user_id = $profile['user_id'];
		$pending = CBNexus_Meeting_Repository::get_pending_for_member($user_id);
		$needs_notes = CBNexus_Meeting_Repository::get_needs_notes($user_id);
		$all = CBNexus_Meeting_Repository::get_for_member($user_id);

		// Split into categories.
		$upcoming = array_filter($all, fn($m) => in_array($m->status, ['accepted', 'scheduled']));
		$sent_pending = array_filter($all, fn($m) => $m->status === 'pending' && (int) $m->member_a_id === $user_id);
		$history = array_filter($all, fn($m) => in_array($m->status, ['completed', 'closed', 'declined', 'cancelled']));
		?>
		<div class="cbnexus-meetings" id="cbnexus-meetings">
			<?php if (!empty($pending)) : ?>
				<div class="cbnexus-card">
					<h2 class="cbnexus-section-title"><?php printf(esc_html__('Action Required (%d)', 'circleblast-nexus'), count($pending)); ?></h2>
					<?php foreach ($pending as $m) : self::render_pending_card($m, $user_id); endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if (!empty($needs_notes)) : ?>
				<div class="cbnexus-card">
					<h2 class="cbnexus-section-title"><?php esc_html_e('Submit Meeting Notes', 'circleblast-nexus'); ?></h2>
					<?php foreach ($needs_notes as $m) : self::render_notes_card($m, $user_id); endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if (!empty($upcoming)) : ?>
				<div class="cbnexus-card">
					<h2 class="cbnexus-section-title"><?php esc_html_e('Upcoming', 'circleblast-nexus'); ?></h2>
					<?php foreach ($upcoming as $m) : self::render_meeting_row($m, $user_id, true); endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if (!empty($sent_pending)) : ?>
				<div class="cbnexus-card">
					<h2 class="cbnexus-section-title"><?php esc_html_e('Awaiting Response', 'circleblast-nexus'); ?></h2>
					<?php foreach ($sent_pending as $m) : self::render_meeting_row($m, $user_id, false); endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="cbnexus-card">
				<h2 class="cbnexus-section-title"><?php esc_html_e('Meeting History', 'circleblast-nexus'); ?></h2>
				<?php if (empty($history)) : ?>
					<p class="cbnexus-text-muted"><?php esc_html_e('No past meetings yet. Visit the Directory to request your first 1:1!', 'circleblast-nexus'); ?></p>
				<?php else : ?>
					<?php foreach ($history as $m) : self::render_meeting_row($m, $user_id, false); endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private static function render_pending_card(object $m, int $user_id): void {
		$other = CBNexus_Member_Repository::get_profile(CBNexus_Meeting_Repository::get_other_member($m, $user_id));
		if (!$other) { return; }
		?>
		<div class="cbnexus-meeting-action-card" data-meeting-id="<?php echo esc_attr($m->id); ?>">
			<div class="cbnexus-meeting-info">
				<strong><?php echo esc_html($other['display_name']); ?></strong>
				<span class="cbnexus-text-muted"><?php echo esc_html(($other['cb_title'] ?? '') . ' at ' . ($other['cb_company'] ?? '')); ?></span>
				<span class="cbnexus-text-muted"><?php printf(esc_html__('Requested %s', 'circleblast-nexus'), esc_html(self::relative_time($m->created_at))); ?></span>
			</div>
			<div class="cbnexus-meeting-actions">
				<button class="cbnexus-btn cbnexus-btn-primary cbnexus-btn-sm cbnexus-action-btn" data-action="respond_meeting" data-meeting-id="<?php echo esc_attr($m->id); ?>" data-response="accepted"><?php esc_html_e('Accept', 'circleblast-nexus'); ?></button>
				<button class="cbnexus-btn cbnexus-btn-sm cbnexus-btn-outline-dark cbnexus-action-btn" data-action="respond_meeting" data-meeting-id="<?php echo esc_attr($m->id); ?>" data-response="declined"><?php esc_html_e('Decline', 'circleblast-nexus'); ?></button>
			</div>
		</div>
		<?php
	}

	private static function render_notes_card(object $m, int $user_id): void {
		$other = CBNexus_Member_Repository::get_profile(CBNexus_Meeting_Repository::get_other_member($m, $user_id));
		if (!$other) { return; }
		?>
		<div class="cbnexus-meeting-notes-card" data-meeting-id="<?php echo esc_attr($m->id); ?>">
			<div class="cbnexus-meeting-info">
				<strong><?php printf(esc_html__('Meeting with %s', 'circleblast-nexus'), esc_html($other['display_name'])); ?></strong>
				<?php if ($m->completed_at) : ?><span class="cbnexus-text-muted"><?php printf(esc_html__('Completed %s', 'circleblast-nexus'), esc_html(self::relative_time($m->completed_at))); ?></span><?php endif; ?>
			</div>
			<form class="cbnexus-notes-form" data-meeting-id="<?php echo esc_attr($m->id); ?>">
				<div class="cbnexus-form-field"><label><?php esc_html_e('Wins', 'circleblast-nexus'); ?></label><textarea name="wins" rows="2" placeholder="<?php esc_attr_e('What went well? Any breakthroughs?', 'circleblast-nexus'); ?>"></textarea></div>
				<div class="cbnexus-form-field"><label><?php esc_html_e('Insights', 'circleblast-nexus'); ?></label><textarea name="insights" rows="2" placeholder="<?php esc_attr_e('What did you learn?', 'circleblast-nexus'); ?>"></textarea></div>
				<div class="cbnexus-form-field"><label><?php esc_html_e('Action Items', 'circleblast-nexus'); ?></label><textarea name="action_items" rows="2" placeholder="<?php esc_attr_e('Next steps or follow-ups?', 'circleblast-nexus'); ?>"></textarea></div>
				<div class="cbnexus-form-field"><label><?php esc_html_e('Rating (1-5)', 'circleblast-nexus'); ?></label>
					<div class="cbnexus-rating-input">
						<?php for ($i = 1; $i <= 5; $i++) : ?>
							<label><input type="radio" name="rating" value="<?php echo $i; ?>" /> <?php echo $i; ?></label>
						<?php endfor; ?>
					</div>
				</div>
				<button type="submit" class="cbnexus-btn cbnexus-btn-primary cbnexus-btn-sm"><?php esc_html_e('Submit Notes', 'circleblast-nexus'); ?></button>
			</form>
		</div>
		<?php
	}

	private static function render_meeting_row(object $m, int $user_id, bool $show_actions): void {
		$other = CBNexus_Member_Repository::get_profile(CBNexus_Meeting_Repository::get_other_member($m, $user_id));
		if (!$other) { return; }

		$status_labels = [
			'pending' => __('Pending', 'circleblast-nexus'), 'accepted' => __('Accepted', 'circleblast-nexus'),
			'scheduled' => __('Scheduled', 'circleblast-nexus'), 'completed' => __('Completed', 'circleblast-nexus'),
			'closed' => __('Closed', 'circleblast-nexus'), 'declined' => __('Declined', 'circleblast-nexus'),
			'cancelled' => __('Cancelled', 'circleblast-nexus'), 'suggested' => __('Suggested', 'circleblast-nexus'),
		];
		$status_colors = [
			'pending' => '#ecc94b', 'accepted' => '#48bb78', 'scheduled' => '#4299e1',
			'completed' => '#9f7aea', 'closed' => '#a0aec0', 'declined' => '#fc8181', 'cancelled' => '#a0aec0',
		];
		?>
		<div class="cbnexus-meeting-row" data-meeting-id="<?php echo esc_attr($m->id); ?>">
			<div class="cbnexus-meeting-info">
				<strong><?php echo esc_html($other['display_name']); ?></strong>
				<?php if ($m->scheduled_at && $m->status === 'scheduled') : ?>
					<span class="cbnexus-text-muted"><?php echo esc_html(date_i18n('M j, Y g:i A', strtotime($m->scheduled_at))); ?></span>
				<?php endif; ?>
			</div>
			<span class="cbnexus-status-pill" style="background:<?php echo esc_attr($status_colors[$m->status] ?? '#a0aec0'); ?>">
				<?php echo esc_html($status_labels[$m->status] ?? ucfirst($m->status)); ?>
			</span>
			<?php if ($show_actions) : ?>
				<div class="cbnexus-meeting-actions">
					<?php if ($m->status === 'accepted') : ?>
						<input type="datetime-local" class="cbnexus-schedule-input" data-meeting-id="<?php echo esc_attr($m->id); ?>" />
						<button class="cbnexus-btn cbnexus-btn-sm cbnexus-btn-primary cbnexus-action-btn" data-action="schedule_meeting" data-meeting-id="<?php echo esc_attr($m->id); ?>"><?php esc_html_e('Schedule', 'circleblast-nexus'); ?></button>
					<?php endif; ?>
					<?php if ($m->status === 'scheduled') : ?>
						<button class="cbnexus-btn cbnexus-btn-sm cbnexus-btn-primary cbnexus-action-btn" data-action="complete_meeting" data-meeting-id="<?php echo esc_attr($m->id); ?>"><?php esc_html_e('Mark Complete', 'circleblast-nexus'); ?></button>
					<?php endif; ?>
					<?php if (in_array($m->status, ['accepted', 'scheduled', 'pending'])) : ?>
						<button class="cbnexus-btn cbnexus-btn-sm cbnexus-btn-outline-dark cbnexus-action-btn" data-action="cancel_meeting" data-meeting-id="<?php echo esc_attr($m->id); ?>"><?php esc_html_e('Cancel', 'circleblast-nexus'); ?></button>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function relative_time(string $datetime): string {
		$diff = time() - strtotime($datetime);
		if ($diff < 3600) { return sprintf(__('%d min ago', 'circleblast-nexus'), max(1, intdiv($diff, 60))); }
		if ($diff < 86400) { return sprintf(__('%d hours ago', 'circleblast-nexus'), intdiv($diff, 3600)); }
		if ($diff < 604800) { return sprintf(__('%d days ago', 'circleblast-nexus'), intdiv($diff, 86400)); }
		return date_i18n('M j, Y', strtotime($datetime));
	}

	// ─── AJAX Handlers ─────────────────────────────────────────────────

	private static function verify_ajax(): ?int {
		check_ajax_referer('cbnexus_meetings', 'nonce');
		$uid = get_current_user_id();
		if (!$uid || !CBNexus_Member_Repository::is_member($uid)) {
			wp_send_json_error('Access denied.', 403);
		}
		return $uid;
	}

	public static function handle_request_meeting(): void {
		$uid = self::verify_ajax();
		$target = absint($_POST['target_id'] ?? 0);
		$message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));

		$result = CBNexus_Meeting_Service::request_meeting($uid, $target, $message);
		$result['success'] ? wp_send_json_success($result) : wp_send_json_error($result);
	}

	public static function handle_respond_meeting(): void {
		$uid = self::verify_ajax();
		$meeting_id = absint($_POST['meeting_id'] ?? 0);
		$response   = sanitize_key($_POST['response'] ?? '');
		$message    = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));

		$result = ($response === 'accepted')
			? CBNexus_Meeting_Service::accept($meeting_id, $uid, $message)
			: CBNexus_Meeting_Service::decline($meeting_id, $uid, $message);
		$result['success'] ? wp_send_json_success($result) : wp_send_json_error($result);
	}

	public static function handle_schedule_meeting(): void {
		$uid = self::verify_ajax();
		$meeting_id   = absint($_POST['meeting_id'] ?? 0);
		$scheduled_at = sanitize_text_field(wp_unslash($_POST['scheduled_at'] ?? ''));

		$result = CBNexus_Meeting_Service::schedule($meeting_id, $uid, $scheduled_at);
		$result['success'] ? wp_send_json_success($result) : wp_send_json_error($result);
	}

	public static function handle_complete_meeting(): void {
		$uid = self::verify_ajax();
		$meeting_id = absint($_POST['meeting_id'] ?? 0);

		$result = CBNexus_Meeting_Service::complete($meeting_id, $uid);
		$result['success'] ? wp_send_json_success($result) : wp_send_json_error($result);
	}

	public static function handle_submit_notes(): void {
		$uid = self::verify_ajax();
		$meeting_id = absint($_POST['meeting_id'] ?? 0);

		$result = CBNexus_Meeting_Service::submit_notes($meeting_id, $uid, [
			'wins'         => sanitize_textarea_field(wp_unslash($_POST['wins'] ?? '')),
			'insights'     => sanitize_textarea_field(wp_unslash($_POST['insights'] ?? '')),
			'action_items' => sanitize_textarea_field(wp_unslash($_POST['action_items'] ?? '')),
			'rating'       => absint($_POST['rating'] ?? 0),
		]);
		$result['success'] ? wp_send_json_success($result) : wp_send_json_error($result);
	}

	public static function handle_cancel_meeting(): void {
		$uid = self::verify_ajax();
		$meeting_id = absint($_POST['meeting_id'] ?? 0);

		$result = CBNexus_Meeting_Service::cancel($meeting_id, $uid);
		$result['success'] ? wp_send_json_success($result) : wp_send_json_error($result);
	}
}
