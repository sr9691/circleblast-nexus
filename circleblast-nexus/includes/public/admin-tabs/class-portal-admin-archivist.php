<?php
/**
 * Portal Admin – Archivist (Meeting Notes) Tab
 *
 * Extracted from class-portal-admin.php for maintainability.
 */

defined('ABSPATH') || exit;

final class CBNexus_Portal_Admin_Archivist {

	public static function render(): void {
		if (!current_user_can('cbnexus_manage_circleup')) {
			echo '<div class="cbnexus-card"><p>Permission denied.</p></div>';
			return;
		}

		// Sub-views.
		if (isset($_GET['circleup_id'])) {
			self::render_edit(absint($_GET['circleup_id']));
			return;
		}
		if (isset($_GET['admin_action']) && $_GET['admin_action'] === 'new_circleup') {
			self::render_add();
			return;
		}

		$notice = sanitize_key($_GET['pa_notice'] ?? '');
		CBNexus_Portal_Admin::render_notice($notice);

		$meetings = CBNexus_CircleUp_Repository::get_meetings();
		?>
		<div class="cbnexus-card">
			<div class="cbnexus-admin-header-row">
				<h2>CircleUp Meetings</h2>
				<a href="<?php echo esc_url(CBNexus_Portal_Admin::admin_url('archivist', ['admin_action' => 'new_circleup'])); ?>" class="cbnexus-btn cbnexus-btn-accent">+ Add Meeting</a>
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
							<td><?php CBNexus_Portal_Admin::status_pill($m->status); ?></td>
							<td><?php echo esc_html($item_count); ?></td>
							<td>
								<a href="<?php echo esc_url(CBNexus_Portal_Admin::admin_url('archivist', ['circleup_id' => $m->id])); ?>" class="cbnexus-link">Review</a>
							</td>
						</tr>
					<?php endforeach; endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	private static function render_add(): void {
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
				<a href="<?php echo esc_url(CBNexus_Portal_Admin::admin_url('archivist')); ?>" class="cbnexus-btn">Cancel</a>
			</form>
		</div>
		<?php
	}

	private static function render_edit(int $id): void {
		$meeting = CBNexus_CircleUp_Repository::get_meeting($id);
		if (!$meeting) {
			echo '<div class="cbnexus-card"><p>Meeting not found.</p></div>';
			return;
		}

		$items    = CBNexus_CircleUp_Repository::get_items($id);
		$members  = CBNexus_Member_Repository::get_all_members('active');
		$attendees = CBNexus_CircleUp_Repository::get_attendees($id);
		$attendee_ids = array_column($attendees, 'member_id');

		global $wpdb;
		$invited_recruits = $wpdb->get_results(
			"SELECT id, name, stage FROM {$wpdb->prefix}cb_candidates WHERE stage = 'invited' ORDER BY name ASC"
		) ?: [];
		$notice = sanitize_key($_GET['pa_notice'] ?? '');
		$base = CBNexus_Portal_Admin::admin_url('archivist', ['circleup_id' => $id]);
		?>
		<?php CBNexus_Portal_Admin::render_notice($notice); ?>

		<div class="cbnexus-card">
			<div class="cbnexus-admin-header-row">
				<h2><?php echo esc_html($meeting->title); ?></h2>
				<a href="<?php echo esc_url(CBNexus_Portal_Admin::admin_url('archivist')); ?>" class="cbnexus-btn">← Back</a>
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
						<textarea name="curated_summary" rows="5"><?php echo esc_textarea($meeting->curated_summary ?: $meeting->ai_summary ?? ''); ?></textarea>
					</div>
					<div>
						<label>Transcript</label>
						<textarea name="full_transcript" rows="8" placeholder="Paste or edit the meeting transcript here…"><?php echo esc_textarea($meeting->full_transcript ?? ''); ?></textarea>
						<p style="font-size:12px;color:#6b7280;margin:4px 0 0;">Paste your Fireflies or manual transcript here. Once saved, use "Run AI Extraction" to generate the curated summary and items.</p>
					</div>
					<div>
						<label>Attendees</label>
						<div class="cbnexus-admin-checkbox-grid">
							<?php foreach ($members as $m) : ?>
								<label><input type="checkbox" name="attendees[]" value="<?php echo esc_attr($m['user_id']); ?>" <?php echo in_array((int) $m['user_id'], array_map('intval', $attendee_ids), true) ? 'checked' : ''; ?> /> <?php echo esc_html($m['display_name']); ?></label>
							<?php endforeach; ?>
						</div>
					</div>
					<?php if (!empty($invited_recruits)) : ?>
					<div>
						<label>Invited Recruits <span style="font-size:12px;color:#6b7280;font-weight:normal;">(pipeline stage: Invited)</span></label>
						<div class="cbnexus-admin-checkbox-grid">
							<?php foreach ($invited_recruits as $r) : ?>
								<label style="color:#92400e;"><input type="checkbox" name="guest_recruit_ids[]" value="<?php echo esc_attr($r->id); ?>" /> <?php echo esc_html($r->name); ?> <span style="font-size:11px;color:#b45309;">★ Invited</span></label>
							<?php endforeach; ?>
						</div>
						<p style="font-size:12px;color:#6b7280;margin:4px 0 0;">Checking a recruit here will automatically move them to "Visited" stage and trigger their thank-you email.</p>
					</div>
					<?php endif; ?>
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

	// ─── Action Handlers ────────────────────────────────────────────────

	public static function handle_create_circleup(): void {
		if (!wp_verify_nonce(wp_unslash($_POST['_wpnonce'] ?? ''), 'cbnexus_portal_create_circleup')) { return; }
		if (!current_user_can('cbnexus_manage_circleup')) { return; }

		$id = CBNexus_CircleUp_Repository::create_meeting([
			'title'            => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
			'meeting_date'     => sanitize_text_field(wp_unslash($_POST['meeting_date'] ?? '')),
			'duration_minutes' => absint($_POST['duration_minutes'] ?? 60),
			'full_transcript'  => wp_unslash($_POST['full_transcript'] ?? ''),
			'status'           => 'draft',
		]);

		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('archivist', ['circleup_id' => $id, 'pa_notice' => 'circleup_created']));
		exit;
	}

	public static function handle_save_circleup(): void {
		if (!wp_verify_nonce(wp_unslash($_POST['_wpnonce'] ?? ''), 'cbnexus_portal_save_circleup')) { return; }
		if (!current_user_can('cbnexus_manage_circleup')) { return; }

		$id = absint($_POST['circleup_id'] ?? 0);
		CBNexus_CircleUp_Repository::update_meeting($id, [
			'curated_summary' => wp_unslash($_POST['curated_summary'] ?? ''),
			'full_transcript' => wp_unslash($_POST['full_transcript'] ?? ''),
		]);

		// Sync attendees.
		$attendee_ids = array_map('absint', (array) ($_POST['attendees'] ?? []));
		global $wpdb;
		$wpdb->delete($wpdb->prefix . 'cb_circleup_attendees', ['circleup_meeting_id' => $id], ['%d']);
		foreach ($attendee_ids as $aid) {
			if ($aid > 0) {
				CBNexus_CircleUp_Repository::add_attendee($id, $aid, 'present');
			}
		}

		// Guest attendees → match against recruitment pipeline.
		$guest_raw = sanitize_text_field(wp_unslash($_POST['guest_attendees'] ?? ''));
		if ($guest_raw !== '') {
			CBNexus_Portal_Admin_Recruitment::match_guest_attendees_to_pipeline($guest_raw);
		}

		// Invited recruits checked as attending → transition to "visited".
		$recruit_ids = array_map('absint', (array) ($_POST['guest_recruit_ids'] ?? []));
		if (!empty($recruit_ids)) {
			CBNexus_Portal_Admin_Recruitment::transition_checked_recruits_to_visited($recruit_ids);
		}

		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('archivist', ['circleup_id' => $id, 'pa_notice' => 'circleup_saved']));
		exit;
	}

	public static function handle_extract(): void {
		$id = absint($_GET['cbnexus_portal_extract']);
		if (!wp_verify_nonce(wp_unslash($_GET['_panonce'] ?? ''), 'cbnexus_portal_extract_' . $id)) { return; }
		if (!current_user_can('cbnexus_manage_circleup')) { return; }

		$result = CBNexus_AI_Extractor::extract($id);

		if (!empty($result['success'])) {
			$notice = 'extraction_done';
		} else {
			$notice = 'extraction_failed';
			$errors = implode(' ', $result['errors'] ?? ['Unknown error.']);
			set_transient('cbnexus_extract_error_' . $id, $errors, 60);
		}

		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('archivist', ['circleup_id' => $id, 'pa_notice' => $notice]));
		exit;
	}

	public static function handle_publish(): void {
		$id = absint($_GET['cbnexus_portal_publish']);
		if (!wp_verify_nonce(wp_unslash($_GET['_panonce'] ?? ''), 'cbnexus_portal_publish_' . $id)) { return; }
		if (!current_user_can('cbnexus_publish_circleup')) { return; }

		CBNexus_CircleUp_Repository::update_meeting($id, [
			'status'       => 'published',
			'published_by' => get_current_user_id(),
			'published_at' => gmdate('Y-m-d H:i:s'),
		]);

		$meeting = CBNexus_CircleUp_Repository::get_meeting($id);
		if (!$meeting) {
			wp_safe_redirect(CBNexus_Portal_Admin::admin_url('archivist', ['circleup_id' => $id, 'pa_notice' => 'error']));
			exit;
		}

		$items    = CBNexus_CircleUp_Repository::get_items($id);
		$approved = array_filter($items, fn($i) => $i->status === 'approved');
		$wins     = array_filter($approved, fn($i) => $i->item_type === 'win');
		$insights = array_filter($approved, fn($i) => $i->item_type === 'insight');
		$actions  = array_filter($approved, fn($i) => $i->item_type === 'action');

		$summary_text = $meeting->curated_summary ?: $meeting->ai_summary ?: '';
		if ($summary_text) {
			$summary_text = '<p style="font-size:15px;color:#333;line-height:1.6;">' . nl2br(esc_html(wp_trim_words($summary_text, 80))) . '</p>';
		}

		$all_members = CBNexus_Member_Repository::get_all_members('active');
		foreach ($all_members as $m) {
			$uid = (int) $m['user_id'];

			$view_token    = CBNexus_Token_Service::generate($uid, 'view_circleup', ['meeting_id' => $id], 30, true);
			$forward_token = CBNexus_Token_Service::generate($uid, 'forward_circleup', ['meeting_id' => $id], 30, true);
			$share_token   = CBNexus_Token_Service::generate($uid, 'quick_share', [], 30, true);

			$my_actions = array_filter($actions, fn($i) => (int) ($i->assigned_to ?? 0) === $uid);
			$action_items_block = '';
			if (!empty($my_actions)) {
				$action_items_block = '<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:16px;margin:16px 0;">';
				$action_items_block .= '<p style="margin:0 0 8px;font-weight:600;font-size:14px;">✅ Your Action Items</p>';
				foreach ($my_actions as $ai) {
					$update_token = CBNexus_Token_Service::generate($uid, 'update_action', ['item_id' => (int) $ai->id], 30, true);
					$action_items_block .= '<p style="margin:4px 0;font-size:14px;">&bull; ' . esc_html($ai->content);
					$action_items_block .= ' <a href="' . esc_url(CBNexus_Token_Service::url($update_token)) . '" style="color:#5b2d6e;font-weight:600;font-size:13px;">Update status →</a></p>';
				}
				$action_items_block .= '</div>';
			}

			CBNexus_Email_Service::send('circleup_summary', $m['user_email'], [
				'first_name'         => $m['first_name'],
				'title'              => $meeting->title,
				'meeting_date'       => date_i18n('F j, Y', strtotime($meeting->meeting_date)),
				'summary_text'       => $summary_text,
				'wins_count'         => count($wins),
				'insights_count'     => count($insights),
				'actions_count'      => count($actions),
				'view_url'           => CBNexus_Token_Service::url($view_token),
				'forward_url'        => CBNexus_Token_Service::url($forward_token),
				'quick_share_url'    => CBNexus_Token_Service::url($share_token),
				'action_items_block' => $action_items_block,
			], ['recipient_id' => $uid, 'related_id' => $id, 'related_type' => 'circleup_summary']);
		}

		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('archivist', ['circleup_id' => $id, 'pa_notice' => 'published']));
		exit;
	}
}
