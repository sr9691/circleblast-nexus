<?php
/**
 * Portal Admin – Meeting Notes Tab
 *
 * Manages CircleUp meeting records: create, edit summary/transcript/attendees,
 * manage extracted items (approve/reject/edit/add), parse structured summaries,
 * optionally run AI extraction, and publish with email distribution.
 *
 * AI-related features (Run AI Extraction button, AI-specific hints) are hidden
 * when no Claude API key is configured.
 */

defined('ABSPATH') || exit;

final class CBNexus_Portal_Admin_Archivist {

	/**
	 * Check whether a Claude API key is configured (constant or DB).
	 */
	public static function has_ai(): bool {
		if (defined('CBNEXUS_CLAUDE_API_KEY') && CBNEXUS_CLAUDE_API_KEY !== '') {
			return true;
		}
		$db_keys = get_option('cbnexus_api_keys', []);
		return !empty($db_keys['claude_api_key']);
	}

	// ─── Render: List ─────────────────────────────────────────────────

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

	// ─── Render: Add ──────────────────────────────────────────────────

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
						<label>Transcript / Meeting Notes</label>
						<textarea name="full_transcript" rows="8" placeholder="Paste meeting notes or transcript here… Action items and key insights will be automatically extracted when you save."></textarea>
					</div>
				</div>
				<button type="submit" name="cbnexus_portal_create_circleup" value="1" class="cbnexus-btn cbnexus-btn-accent">Create Meeting</button>
				<a href="<?php echo esc_url(CBNexus_Portal_Admin::admin_url('archivist')); ?>" class="cbnexus-btn">Cancel</a>
			</form>
		</div>
		<?php
	}

	// ─── Render: Edit ─────────────────────────────────────────────────

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
		$has_ai   = self::has_ai();

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
						<label>Transcript / Meeting Notes</label>
						<textarea name="full_transcript" rows="8" placeholder="Paste meeting notes or transcript here…"><?php echo esc_textarea($meeting->full_transcript ?? ''); ?></textarea>
						<p style="font-size:12px;color:#6b7280;margin:4px 0 0;">
							Paste your meeting notes here. When saved, the system will automatically extract action items and key insights from structured summaries.
							<?php if ($has_ai) : ?>
								You can also use "Run AI Extraction" below for deeper analysis of full transcripts.
							<?php endif; ?>
						</p>
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

		<!-- Meeting Items with Approve/Reject/Edit -->
		<div class="cbnexus-card">
			<div class="cbnexus-admin-header-row">
				<h3 style="margin:0;">Meeting Items (<?php echo count($items); ?>)</h3>
			</div>

			<?php if (!empty($items)) : ?>
				<form method="post" action="">
					<?php wp_nonce_field('cbnexus_portal_update_items'); ?>
					<input type="hidden" name="circleup_id" value="<?php echo esc_attr($id); ?>" />
					<?php
					$grouped = [];
					foreach ($items as $item) { $grouped[$item->item_type][] = $item; }
					$type_labels = ['action' => '✅ Action Items', 'win' => '🏆 Wins', 'insight' => '💡 Insights', 'opportunity' => '🤝 Opportunities'];
					foreach (['action', 'win', 'insight', 'opportunity'] as $type) :
						if (empty($grouped[$type])) { continue; }
					?>
						<h4 style="margin:16px 0 8px;"><?php echo esc_html($type_labels[$type]); ?> (<?php echo count($grouped[$type]); ?>)</h4>
						<div class="cbnexus-admin-table-wrap">
							<table class="cbnexus-admin-table cbnexus-admin-table-sm">
								<thead><tr>
									<th>Content</th>
									<?php if ($type === 'action') : ?><th style="width:140px;">Assigned To</th><?php endif; ?>
									<th style="width:100px;">Speaker</th>
									<th style="width:110px;">Status</th>
									<th style="width:50px;"></th>
								</tr></thead>
								<tbody>
								<?php foreach ($grouped[$type] as $item) : ?>
									<tr>
										<td>
											<input type="text" name="item_content[<?php echo esc_attr($item->id); ?>]" value="<?php echo esc_attr($item->content); ?>" style="width:100%;border:1px solid #e5e7eb;border-radius:6px;padding:6px 8px;font-size:13px;" />
										</td>
										<?php if ($type === 'action') : ?>
										<td>
											<select name="item_assigned[<?php echo esc_attr($item->id); ?>]" style="width:100%;border:1px solid #e5e7eb;border-radius:6px;padding:4px 6px;font-size:12px;">
												<option value="">— Unassigned —</option>
												<?php foreach ($members as $m) : ?>
													<option value="<?php echo esc_attr($m['user_id']); ?>" <?php selected((int) ($item->assigned_to ?? 0), (int) $m['user_id']); ?>><?php echo esc_html($m['display_name']); ?></option>
												<?php endforeach; ?>
											</select>
										</td>
										<?php endif; ?>
										<td>
											<select name="item_speaker[<?php echo esc_attr($item->id); ?>]" style="width:100%;border:1px solid #e5e7eb;border-radius:6px;padding:4px 6px;font-size:12px;">
												<option value="">—</option>
												<?php foreach ($members as $m) : ?>
													<option value="<?php echo esc_attr($m['user_id']); ?>" <?php selected((int) ($item->speaker_id ?? 0), (int) $m['user_id']); ?>><?php echo esc_html($m['display_name']); ?></option>
												<?php endforeach; ?>
											</select>
										</td>
										<td>
											<select name="item_status[<?php echo esc_attr($item->id); ?>]" style="width:100%;border:1px solid #e5e7eb;border-radius:6px;padding:4px 6px;font-size:12px;">
												<option value="draft" <?php selected($item->status, 'draft'); ?>>Draft</option>
												<option value="approved" <?php selected($item->status, 'approved'); ?>>Approved</option>
												<option value="rejected" <?php selected($item->status, 'rejected'); ?>>Rejected</option>
											</select>
										</td>
										<td>
											<label title="Delete this item" style="cursor:pointer;font-size:16px;color:#dc2626;">
												<input type="checkbox" name="item_delete[]" value="<?php echo esc_attr($item->id); ?>" style="display:none;" onclick="this.parentElement.style.opacity=this.checked?'0.4':'1';" />
												🗑
											</label>
										</td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endforeach; ?>

					<div style="margin-top:12px;display:flex;gap:8px;align-items:center;">
						<button type="submit" name="cbnexus_portal_update_items" value="1" class="cbnexus-btn cbnexus-btn-accent">Save Items</button>
						<button type="button" onclick="if(confirm('Approve all draft items?')){document.querySelectorAll('select[name^=item_status]').forEach(s=>{if(s.value==='draft')s.value='approved'});}" class="cbnexus-btn">Approve All Drafts</button>
					</div>
				</form>
			<?php else : ?>
				<p class="cbnexus-text-muted" style="margin:12px 0 0;">No items yet. Add items manually below, or paste a structured meeting summary above and save to auto-extract items.</p>
			<?php endif; ?>
		</div>

		<!-- Add Item Form -->
		<div class="cbnexus-card">
			<h3 style="margin:0 0 12px;">Add Item</h3>
			<form method="post" action="">
				<?php wp_nonce_field('cbnexus_portal_add_item'); ?>
				<input type="hidden" name="circleup_id" value="<?php echo esc_attr($id); ?>" />
				<div class="cbnexus-admin-form-stack">
					<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
						<div>
							<label>Type</label>
							<select name="new_item_type" style="width:100%;">
								<option value="action">✅ Action Item</option>
								<option value="win">🏆 Win</option>
								<option value="insight">💡 Insight</option>
								<option value="opportunity">🤝 Opportunity</option>
							</select>
						</div>
						<div>
							<label>Speaker</label>
							<select name="new_item_speaker" style="width:100%;">
								<option value="">— None —</option>
								<?php foreach ($members as $m) : ?>
									<option value="<?php echo esc_attr($m['user_id']); ?>"><?php echo esc_html($m['display_name']); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
					<div>
						<label>Content *</label>
						<textarea name="new_item_content" rows="2" required placeholder="Describe the item…"></textarea>
					</div>
					<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
						<div>
							<label>Assigned To <span style="font-size:12px;color:#6b7280;font-weight:normal;">(for actions)</span></label>
							<select name="new_item_assigned" style="width:100%;">
								<option value="">— Unassigned —</option>
								<?php foreach ($members as $m) : ?>
									<option value="<?php echo esc_attr($m['user_id']); ?>"><?php echo esc_html($m['display_name']); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div>
							<label>Due Date <span style="font-size:12px;color:#6b7280;font-weight:normal;">(optional)</span></label>
							<input type="date" name="new_item_due_date" />
						</div>
					</div>
				</div>
				<button type="submit" name="cbnexus_portal_add_item" value="1" class="cbnexus-btn cbnexus-btn-accent" style="margin-top:8px;">+ Add Item</button>
			</form>
		</div>

		<!-- Actions -->
		<div class="cbnexus-card">
			<h3>Actions</h3>
			<div class="cbnexus-admin-button-row">
				<?php if ($has_ai && $meeting->full_transcript) : ?>
					<a href="<?php echo esc_url(wp_nonce_url(add_query_arg('cbnexus_portal_extract', $id, $base), 'cbnexus_portal_extract_' . $id, '_panonce')); ?>" class="cbnexus-btn" onclick="return confirm('Run AI extraction? This will replace existing items.');">🤖 Run AI Extraction</a>
				<?php endif; ?>
				<?php if ($meeting->full_transcript) : ?>
					<a href="<?php echo esc_url(wp_nonce_url(add_query_arg('cbnexus_portal_parse', $id, $base), 'cbnexus_portal_parse_' . $id, '_panonce')); ?>" class="cbnexus-btn" onclick="return confirm('Parse summary for items? New items will be added without removing existing ones.');">📋 Parse Summary for Items</a>
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

		$transcript = wp_unslash($_POST['full_transcript'] ?? '');

		$id = CBNexus_CircleUp_Repository::create_meeting([
			'title'            => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
			'meeting_date'     => sanitize_text_field(wp_unslash($_POST['meeting_date'] ?? '')),
			'duration_minutes' => absint($_POST['duration_minutes'] ?? 60),
			'full_transcript'  => $transcript,
			'status'           => 'draft',
		]);

		// Auto-parse if transcript looks structured.
		if ($id && trim($transcript) !== '') {
			self::auto_parse_summary($id, $transcript);
		}

		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('archivist', ['circleup_id' => $id, 'pa_notice' => 'circleup_created']));
		exit;
	}

	public static function handle_save_circleup(): void {
		if (!wp_verify_nonce(wp_unslash($_POST['_wpnonce'] ?? ''), 'cbnexus_portal_save_circleup')) { return; }
		if (!current_user_can('cbnexus_manage_circleup')) { return; }

		$id = absint($_POST['circleup_id'] ?? 0);
		$transcript = wp_unslash($_POST['full_transcript'] ?? '');

		// Check if the transcript content actually changed.
		$existing = CBNexus_CircleUp_Repository::get_meeting($id);
		$transcript_changed = $existing && ($existing->full_transcript ?? '') !== $transcript;

		CBNexus_CircleUp_Repository::update_meeting($id, [
			'curated_summary' => wp_unslash($_POST['curated_summary'] ?? ''),
			'full_transcript' => $transcript,
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

		// Auto-parse if transcript changed and meeting has no items yet.
		$current_items = CBNexus_CircleUp_Repository::get_items($id);
		if ($transcript_changed && trim($transcript) !== '' && empty($current_items)) {
			self::auto_parse_summary($id, $transcript);
		}

		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('archivist', ['circleup_id' => $id, 'pa_notice' => 'circleup_saved']));
		exit;
	}

	/**
	 * Handle manual "Parse Summary for Items" button click.
	 */
	public static function handle_parse(): void {
		$id = absint($_GET['cbnexus_portal_parse']);
		if (!wp_verify_nonce(wp_unslash($_GET['_panonce'] ?? ''), 'cbnexus_portal_parse_' . $id)) { return; }
		if (!current_user_can('cbnexus_manage_circleup')) { return; }

		$meeting = CBNexus_CircleUp_Repository::get_meeting($id);
		if (!$meeting || empty($meeting->full_transcript)) {
			wp_safe_redirect(CBNexus_Portal_Admin::admin_url('archivist', ['circleup_id' => $id, 'pa_notice' => 'error']));
			exit;
		}

		// Parse from transcript first, then also try curated summary.
		$text = $meeting->full_transcript;
		$result = CBNexus_Summary_Parser::parse($text, $id);

		if (count($result['items']) < 2 && !empty($meeting->curated_summary)) {
			$summary_result = CBNexus_Summary_Parser::parse($meeting->curated_summary, $id);
			$result['items'] = array_merge($result['items'], $summary_result['items']);
		}

		if (!empty($result['items'])) {
			CBNexus_CircleUp_Repository::insert_items($id, $result['items']);
		}

		if (empty($meeting->curated_summary) && !empty($result['summary'])) {
			CBNexus_CircleUp_Repository::update_meeting($id, [
				'curated_summary' => $result['summary'],
			]);
		}

		$count = count($result['items']);
		$notice = $count > 0 ? 'items_parsed' : 'no_items_parsed';

		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('archivist', ['circleup_id' => $id, 'pa_notice' => $notice]));
		exit;
	}

	/**
	 * Handle adding a single manual item.
	 */
	public static function handle_add_item(): void {
		if (!wp_verify_nonce(wp_unslash($_POST['_wpnonce'] ?? ''), 'cbnexus_portal_add_item')) { return; }
		if (!current_user_can('cbnexus_manage_circleup')) { return; }

		$id = absint($_POST['circleup_id'] ?? 0);
		$content = sanitize_textarea_field(wp_unslash($_POST['new_item_content'] ?? ''));
		if ($id === 0 || $content === '') {
			wp_safe_redirect(CBNexus_Portal_Admin::admin_url('archivist', ['circleup_id' => $id, 'pa_notice' => 'error']));
			exit;
		}

		$type = sanitize_key($_POST['new_item_type'] ?? 'insight');
		if (!in_array($type, ['win', 'insight', 'opportunity', 'action'], true)) {
			$type = 'insight';
		}

		CBNexus_CircleUp_Repository::insert_items($id, [[
			'item_type'   => $type,
			'content'     => $content,
			'speaker_id'  => absint($_POST['new_item_speaker'] ?? 0) ?: null,
			'assigned_to' => absint($_POST['new_item_assigned'] ?? 0) ?: null,
			'due_date'    => sanitize_text_field($_POST['new_item_due_date'] ?? '') ?: null,
			'status'      => 'draft',
		]]);

		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('archivist', ['circleup_id' => $id, 'pa_notice' => 'item_added']));
		exit;
	}

	/**
	 * Handle bulk update of items (content, status, speaker, assigned_to, delete).
	 */
	public static function handle_update_items(): void {
		if (!wp_verify_nonce(wp_unslash($_POST['_wpnonce'] ?? ''), 'cbnexus_portal_update_items')) { return; }
		if (!current_user_can('cbnexus_manage_circleup')) { return; }

		$id = absint($_POST['circleup_id'] ?? 0);

		// Delete checked items.
		$delete_ids = array_map('absint', (array) ($_POST['item_delete'] ?? []));
		foreach ($delete_ids as $did) {
			if ($did > 0) {
				global $wpdb;
				$wpdb->delete($wpdb->prefix . 'cb_circleup_items', ['id' => $did], ['%d']);
			}
		}

		// Update remaining items.
		$statuses  = is_array($_POST['item_status'] ?? null) ? array_map('sanitize_key', wp_unslash($_POST['item_status'])) : [];
		$contents  = is_array($_POST['item_content'] ?? null) ? array_map(function ($v) { return sanitize_textarea_field(wp_unslash($v)); }, $_POST['item_content']) : [];
		$speakers  = is_array($_POST['item_speaker'] ?? null) ? array_map('absint', wp_unslash($_POST['item_speaker'])) : [];
		$assigned  = is_array($_POST['item_assigned'] ?? null) ? array_map('absint', wp_unslash($_POST['item_assigned'])) : [];

		foreach ($statuses as $item_id => $status) {
			$item_id = absint($item_id);
			if ($item_id === 0 || in_array($item_id, $delete_ids, true)) { continue; }

			$data = ['status' => $status];
			if (isset($contents[$item_id]))  { $data['content']     = $contents[$item_id]; }
			if (isset($speakers[$item_id]))  { $data['speaker_id']  = $speakers[$item_id] ?: null; }
			if (isset($assigned[$item_id]))  { $data['assigned_to'] = $assigned[$item_id] ?: null; }

			CBNexus_CircleUp_Repository::update_item($item_id, $data);
		}

		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('archivist', ['circleup_id' => $id, 'pa_notice' => 'items_updated']));
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

	// ─── Auto-Parse Helper ──────────────────────────────────────────────

	/**
	 * Automatically parse a structured summary and insert items if parseable.
	 */
	private static function auto_parse_summary(int $meeting_id, string $text): void {
		if (!CBNexus_Summary_Parser::looks_parseable($text)) {
			return;
		}

		$result = CBNexus_Summary_Parser::parse($text, $meeting_id);

		if (!empty($result['items'])) {
			CBNexus_CircleUp_Repository::insert_items($meeting_id, $result['items']);
		}

		$meeting = CBNexus_CircleUp_Repository::get_meeting($meeting_id);
		if ($meeting && empty($meeting->curated_summary) && !empty($result['summary'])) {
			CBNexus_CircleUp_Repository::update_meeting($meeting_id, [
				'curated_summary' => $result['summary'],
			]);
		}
	}
}