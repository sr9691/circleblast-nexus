<?php
/**
 * Portal Meetings
 *
 * ITER-0009 / UX Refresh: Member-facing meetings page matching demo.
 * Gold-highlighted new request card, notes form with structured fields
 * and rating buttons, pill-based status badges, updated card styling.
 */

defined('ABSPATH') || exit;

final class CBNexus_Portal_Meetings {

	public static function init(): void {
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

	public static function render(array $profile): void {
		$user_id = $profile['user_id'];
		$suggested = CBNexus_Meeting_Repository::get_suggested_for_member($user_id);
		$pending = CBNexus_Meeting_Repository::get_pending_for_member($user_id);
		$needs_notes = CBNexus_Meeting_Repository::get_needs_notes($user_id);
		$all = CBNexus_Meeting_Repository::get_for_member($user_id);

		$upcoming = array_filter($all, fn($m) => in_array($m->status, ['accepted', 'scheduled']));
		$sent_pending = array_filter($all, fn($m) => $m->status === 'pending' && (int) $m->member_a_id === $user_id);
		$history = array_filter($all, fn($m) => in_array($m->status, ['completed', 'closed', 'declined', 'cancelled']));
		?>
		<div class="cbnexus-meetings" id="cbnexus-meetings">
			<?php if (!empty($suggested)) : foreach ($suggested as $m) : self::render_suggested_card($m, $user_id); endforeach; endif; ?>
			<?php if (!empty($pending)) : foreach ($pending as $m) : self::render_pending_card($m, $user_id); endforeach; endif; ?>
			<?php if (!empty($needs_notes)) : foreach ($needs_notes as $m) : self::render_notes_card($m, $user_id); endforeach; endif; ?>

			<?php if (!empty($upcoming)) : ?>
				<div class="cbnexus-card">
					<h3><?php esc_html_e('Upcoming', 'circleblast-nexus'); ?></h3>
					<?php foreach ($upcoming as $m) : self::render_meeting_row($m, $user_id, true); endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if (!empty($sent_pending)) : ?>
				<div class="cbnexus-card">
					<h3><?php esc_html_e('Awaiting Response', 'circleblast-nexus'); ?></h3>
					<?php foreach ($sent_pending as $m) : self::render_meeting_row($m, $user_id, false); endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="cbnexus-card">
				<h3><?php esc_html_e('History', 'circleblast-nexus'); ?></h3>
				<?php if (empty($history)) : ?>
					<p class="cbnexus-text-muted"><?php esc_html_e('No past meetings yet. Visit the Directory to request your first 1:1!', 'circleblast-nexus'); ?></p>
				<?php else : ?>
					<?php foreach ($history as $m) : self::render_meeting_row($m, $user_id, false); endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private static function render_suggested_card(object $m, int $user_id): void {
		$other = CBNexus_Member_Repository::get_profile(CBNexus_Meeting_Repository::get_other_member($m, $user_id));
		if (!$other) { return; }
		$bio_snippet = mb_substr($other['cb_bio'] ?? '', 0, 100);
		?>
		<div class="cbnexus-card" style="padding:16px 20px;border-left:3px solid var(--cb-gold, #c49a3c);" data-meeting-id="<?php echo esc_attr($m->id); ?>">
			<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
				<span style="font-size:16px;">🎯</span>
				<span style="font-weight:700;font-size:15px;"><?php esc_html_e('Suggested Match', 'circleblast-nexus'); ?></span>
				<span class="cbnexus-pill cbnexus-pill--gold-soft"><?php esc_html_e('Auto-matched', 'circleblast-nexus'); ?></span>
			</div>
			<div class="cbnexus-meeting-action-card" style="border:none;padding:0;margin:0;background:transparent;">
				<div class="cbnexus-meeting-info" style="display:flex;align-items:center;gap:12px;flex:1;">
					<div>
						<div style="font-weight:600;"><?php echo esc_html($other['display_name']); ?></div>
						<span class="cbnexus-text-muted"><?php echo esc_html(($other['cb_title'] ?? '') . ' at ' . ($other['cb_company'] ?? '')); ?></span>
						<?php if ($bio_snippet) : ?><br/><span class="cbnexus-text-muted" style="font-size:13px;"><?php echo esc_html($bio_snippet); ?><?php echo strlen($other['cb_bio'] ?? '') > 100 ? '…' : ''; ?></span><?php endif; ?>
						<br/><span class="cbnexus-text-muted" style="font-size:12px;"><?php printf(esc_html__('Suggested %s', 'circleblast-nexus'), esc_html(self::relative_time($m->created_at))); ?></span>
					</div>
				</div>
				<div class="cbnexus-meeting-actions">
					<button class="cbnexus-btn cbnexus-btn-primary cbnexus-btn-sm cbnexus-action-btn" data-action="respond_meeting" data-meeting-id="<?php echo esc_attr($m->id); ?>" data-response="accepted"><?php esc_html_e('Accept', 'circleblast-nexus'); ?></button>
					<button class="cbnexus-btn cbnexus-btn-outline cbnexus-btn-sm cbnexus-action-btn" data-action="respond_meeting" data-meeting-id="<?php echo esc_attr($m->id); ?>" data-response="declined"><?php esc_html_e('Decline', 'circleblast-nexus'); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	private static function render_pending_card(object $m, int $user_id): void {
		$other = CBNexus_Member_Repository::get_profile(CBNexus_Meeting_Repository::get_other_member($m, $user_id));
		if (!$other) { return; }
		?>
		<div class="cbnexus-card cbnexus-card-highlight" style="padding:16px 20px;" data-meeting-id="<?php echo esc_attr($m->id); ?>">
			<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
				<span style="font-size:16px;">🤝</span>
				<span style="font-weight:700;font-size:15px;"><?php esc_html_e('New Request', 'circleblast-nexus'); ?></span>
			</div>
			<div class="cbnexus-meeting-action-card" style="border:none;padding:0;margin:0;background:transparent;">
				<div class="cbnexus-meeting-info" style="display:flex;align-items:center;gap:12px;flex:1;">
					<div>
						<div style="font-weight:600;"><?php echo esc_html($other['display_name']); ?></div>
						<span class="cbnexus-text-muted"><?php echo esc_html(($other['cb_title'] ?? '') . ' at ' . ($other['cb_company'] ?? '')); ?></span><br/>
						<span class="cbnexus-text-muted"><?php printf(esc_html__('Requested %s', 'circleblast-nexus'), esc_html(self::relative_time($m->created_at))); ?></span>
					</div>
				</div>
				<div class="cbnexus-meeting-actions">
					<button class="cbnexus-btn cbnexus-btn-primary cbnexus-btn-sm cbnexus-action-btn" data-action="respond_meeting" data-meeting-id="<?php echo esc_attr($m->id); ?>" data-response="accepted"><?php esc_html_e('Accept', 'circleblast-nexus'); ?></button>
					<button class="cbnexus-btn cbnexus-btn-outline cbnexus-btn-sm cbnexus-action-btn" data-action="respond_meeting" data-meeting-id="<?php echo esc_attr($m->id); ?>" data-response="declined"><?php esc_html_e('Decline', 'circleblast-nexus'); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	private static function render_notes_card(object $m, int $user_id): void {
		$other = CBNexus_Member_Repository::get_profile(CBNexus_Meeting_Repository::get_other_member($m, $user_id));
		if (!$other) { return; }
		?>
		<div class="cbnexus-card" data-meeting-id="<?php echo esc_attr($m->id); ?>">
			<div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
				<span style="font-size:16px;">📝</span>
				<h3 style="margin:0;"><?php esc_html_e('Meeting Notes Due', 'circleblast-nexus'); ?></h3>
			</div>
			<div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
				<div>
					<span style="font-weight:600;"><?php echo esc_html($other['display_name']); ?></span><br/>
					<?php if ($m->completed_at) : ?><span class="cbnexus-text-muted"><?php printf(esc_html__('Completed %s', 'circleblast-nexus'), esc_html(self::relative_time($m->completed_at))); ?></span><?php endif; ?>
				</div>
			</div>
			<form class="cbnexus-notes-form" data-meeting-id="<?php echo esc_attr($m->id); ?>">
				<?php
				$fields = [
					'wins'         => [__('Wins', 'circleblast-nexus'), __('What went well?', 'circleblast-nexus')],
					'insights'     => [__('Insights', 'circleblast-nexus'), __('What did you learn?', 'circleblast-nexus')],
					'action_items' => [__('Actions', 'circleblast-nexus'), __('Next steps?', 'circleblast-nexus')],
				];
				foreach ($fields as $key => $meta) : ?>
					<div class="cbnexus-form-field">
						<label><?php echo esc_html($meta[0]); ?></label>
						<textarea name="<?php echo esc_attr($key); ?>" rows="2" placeholder="<?php echo esc_attr($meta[1]); ?>"></textarea>
					</div>
				<?php endforeach; ?>
				<div class="cbnexus-form-field">
					<label><?php esc_html_e('Rating', 'circleblast-nexus'); ?></label>
					<div class="cbnexus-rating-input">
						<?php for ($i = 1; $i <= 5; $i++) : ?>
							<button type="button" class="cbnexus-rating-btn" data-rating="<?php echo $i; ?>"><?php echo $i; ?></button>
						<?php endfor; ?>
						<input type="hidden" name="rating" value="" />
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

		$pill_classes = [
			'pending'   => 'cbnexus-pill--gold-soft', 'accepted' => 'cbnexus-pill--green',
			'scheduled' => 'cbnexus-pill--blue',      'completed' => 'cbnexus-pill--accent-soft',
			'closed'    => 'cbnexus-pill--muted',     'declined'  => 'cbnexus-pill--muted',
			'cancelled' => 'cbnexus-pill--muted',     'suggested' => 'cbnexus-pill--gold-soft',
		];
		$pill = $pill_classes[$m->status] ?? 'cbnexus-pill--muted';
		?>
		<div class="cbnexus-row" data-meeting-id="<?php echo esc_attr($m->id); ?>">
			<div style="flex:1;">
				<div style="font-weight:600;"><?php echo esc_html($other['display_name']); ?></div>
				<?php if ($m->scheduled_at && $m->status === 'scheduled') : ?>
					<span class="cbnexus-text-muted"><?php echo esc_html(date_i18n('M j · g:i A', strtotime($m->scheduled_at))); ?></span>
				<?php endif; ?>
			</div>
			<span class="cbnexus-pill <?php echo esc_attr($pill); ?>"><?php echo esc_html(ucfirst($m->status)); ?></span>
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
						<button class="cbnexus-btn cbnexus-btn-sm cbnexus-btn-outline cbnexus-action-btn" data-action="cancel_meeting" data-meeting-id="<?php echo esc_attr($m->id); ?>"><?php esc_html_e('Cancel', 'circleblast-nexus'); ?></button>
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

		$meeting = CBNexus_Meeting_Repository::get($meeting_id);

		if ($response === 'accepted' && $meeting && $meeting->source === 'auto'
			&& in_array($meeting->status, ['suggested', 'pending'])) {
			$result = CBNexus_Meeting_Service::accept_suggestion($meeting_id, $uid);
		} elseif ($response === 'accepted') {
			$result = CBNexus_Meeting_Service::accept($meeting_id, $uid, $message);
		} else {
			// For decline on suggested, handle directly.
			if ($meeting && $meeting->status === 'suggested') {
				CBNexus_Meeting_Repository::update($meeting_id, ['status' => 'declined']);
				CBNexus_Meeting_Repository::record_response($meeting_id, $uid, 'declined', '');
				$result = ['success' => true];
			} else {
				$result = CBNexus_Meeting_Service::decline($meeting_id, $uid, $message);
			}
		}

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
