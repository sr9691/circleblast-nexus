<?php
/**
 * Portal Admin – Recruitment Tab
 *
 * Extracted from class-portal-admin.php for maintainability.
 * Handles recruitment pipeline, candidate CRUD, stage automations.
 */

defined('ABSPATH') || exit;

final class CBNexus_Portal_Admin_Recruitment {

	private static $recruit_stages = [
		'referral'  => 'Referral',
		'contacted' => 'Contacted',
		'invited'   => 'Invited',
		'visited'   => 'Visited',
		'decision'  => 'Decision',
		'accepted'  => 'Accepted',
		'declined'  => 'Declined',
	];

	// ─── Render ─────────────────────────────────────────────────────────

	public static function render(): void {
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

		$base = CBNexus_Portal_Admin::admin_url('recruitment');
		?>
		<?php CBNexus_Portal_Admin::render_notice($notice); ?>

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
					<div>
						<label>Category</label>
						<select name="category_id">
							<option value="0">—</option>
							<?php
							global $wpdb;
							$cat_table = $wpdb->prefix . 'cb_recruitment_categories';
							$need_cats = $wpdb->get_results("SELECT id, title FROM {$cat_table} ORDER BY sort_order ASC, title ASC") ?: [];
							foreach ($need_cats as $nc) : ?>
								<option value="<?php echo esc_attr($nc->id); ?>"><?php echo esc_html($nc->title); ?></option>
							<?php endforeach; ?>
						</select>
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
						<th>Category</th>
						<th>Referred By</th>
						<th>Stage</th>
						<th>Notes</th>
						<th>Updated</th>
						<th>Actions</th>
					</tr></thead>
					<tbody>
					<?php if (empty($candidates)) : ?>
						<tr><td colspan="8" class="cbnexus-admin-empty">No candidates yet.</td></tr>
					<?php else : foreach ($candidates as $c) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html($c->name); ?></strong>
								<?php if ($c->email) : ?><div class="cbnexus-admin-meta"><?php echo esc_html($c->email); ?></div><?php endif; ?>
							</td>
							<td><?php echo esc_html($c->company ?: '—'); ?></td>
							<td class="cbnexus-admin-meta"><?php
								$cat_name_c = '—';
								if (!empty($c->category_id)) {
									global $wpdb;
									$cat_name_c = $wpdb->get_var($wpdb->prepare(
										"SELECT title FROM {$wpdb->prefix}cb_recruitment_categories WHERE id = %d",
										$c->category_id
									)) ?: '—';
								}
								echo esc_html($cat_name_c);
							?></td>
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
								<a href="<?php echo esc_url(CBNexus_Portal_Admin::admin_url('recruitment', ['edit_candidate' => $c->id])); ?>" class="cbnexus-link">Edit</a>
							</td>
						</tr>
					<?php endforeach; endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
		self::render_recruitment_needs();
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
				<a href="<?php echo esc_url(CBNexus_Portal_Admin::admin_url('recruitment')); ?>" class="cbnexus-btn cbnexus-btn-outline cbnexus-btn-sm">← Back</a>
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
						<label style="display:block;font-weight:600;margin-bottom:4px;">Category</label>
						<?php
						global $wpdb;
						$cat_table_edit = $wpdb->prefix . 'cb_recruitment_categories';
						$need_cats_edit = $wpdb->get_results("SELECT id, title FROM {$cat_table_edit} ORDER BY sort_order ASC, title ASC") ?: [];
						?>
						<select name="category_id" class="cbnexus-input" style="width:100%;">
							<option value="0">—</option>
							<?php foreach ($need_cats_edit as $nc) : ?>
								<option value="<?php echo esc_attr($nc->id); ?>" <?php selected((int) ($c->category_id ?? 0), (int) $nc->id); ?>><?php echo esc_html($nc->title); ?></option>
							<?php endforeach; ?>
						</select>
						<span class="cbnexus-admin-meta" style="display:block;margin-top:4px;">Which recruitment need is this candidate for?</span>
					</div>
					<div>
						<label style="display:block;font-weight:600;margin-bottom:4px;">Notes</label>
						<textarea name="notes" rows="3" class="cbnexus-input" style="width:100%;"><?php echo esc_textarea($c->notes); ?></textarea>
					</div>
				</div>

				<div style="margin-top:16px;display:flex;gap:8px;">
					<button type="submit" name="cbnexus_portal_save_candidate" value="1" class="cbnexus-btn cbnexus-btn-primary">Update Candidate</button>
					<a href="<?php echo esc_url(CBNexus_Portal_Admin::admin_url('recruitment')); ?>" class="cbnexus-btn cbnexus-btn-outline">Cancel</a>
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

	// ─── Action Handlers ────────────────────────────────────────────────

	public static function handle_save_candidate(): void {
		if (!wp_verify_nonce(wp_unslash($_POST['_wpnonce'] ?? ''), 'cbnexus_portal_save_candidate')) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }

		global $wpdb;
		$table = $wpdb->prefix . 'cb_candidates';
		$id    = absint($_POST['candidate_id'] ?? 0);
		$new_stage = sanitize_key($_POST['stage'] ?? 'referral');

		$candidate = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
		if (!$candidate) { return; }

		$old_stage = $candidate->stage;

		$wpdb->update($table, [
			'name'        => sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
			'email'       => sanitize_email($_POST['email'] ?? ''),
			'company'     => sanitize_text_field(wp_unslash($_POST['company'] ?? '')),
			'industry'    => sanitize_text_field(wp_unslash($_POST['industry'] ?? '')),
			'category_id' => absint($_POST['category_id'] ?? 0) ?: null,
			'referrer_id' => absint($_POST['referrer_id'] ?? 0) ?: null,
			'stage'       => $new_stage,
			'notes'       => sanitize_textarea_field(wp_unslash($_POST['notes'] ?? '')),
			'updated_at'  => gmdate('Y-m-d H:i:s'),
		], ['id' => $id], ['%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s'], ['%d']);

		if ($old_stage !== $new_stage) {
			$updated = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
			if ($updated) {
				self::run_recruitment_automations($updated, $old_stage, $new_stage);
			}
		}

		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('recruitment', ['pa_notice' => 'candidate_saved']));
		exit;
	}

	public static function handle_add_candidate(): void {
		if (!wp_verify_nonce(wp_unslash($_POST['_wpnonce'] ?? ''), 'cbnexus_portal_add_candidate')) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }

		global $wpdb;
		$now = gmdate('Y-m-d H:i:s');

		$wpdb->insert($wpdb->prefix . 'cb_candidates', [
			'name'        => sanitize_text_field($_POST['name'] ?? ''),
			'email'       => sanitize_email($_POST['email'] ?? ''),
			'company'     => sanitize_text_field($_POST['company'] ?? ''),
			'industry'    => sanitize_text_field($_POST['industry'] ?? ''),
			'category_id' => absint($_POST['category_id'] ?? 0) ?: null,
			'referrer_id' => absint($_POST['referrer_id'] ?? 0) ?: null,
			'stage'       => 'referral',
			'notes'       => sanitize_textarea_field(wp_unslash($_POST['notes'] ?? '')),
			'created_at'  => $now,
			'updated_at'  => $now,
		], ['%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s']);

		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('recruitment', ['pa_notice' => 'candidate_added']));
		exit;
	}

	public static function handle_update_candidate(): void {
		if (!wp_verify_nonce(wp_unslash($_POST['_wpnonce'] ?? ''), 'cbnexus_portal_update_candidate')) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }

		global $wpdb;
		$table = $wpdb->prefix . 'cb_candidates';
		$id    = absint($_POST['candidate_id'] ?? 0);
		$new_stage = sanitize_key($_POST['stage'] ?? 'referral');
		$notes = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));

		$candidate = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
		if (!$candidate) { return; }

		$old_stage = $candidate->stage;

		$wpdb->update($table, [
			'stage'      => $new_stage,
			'notes'      => $notes,
			'updated_at' => gmdate('Y-m-d H:i:s'),
		], ['id' => $id], ['%s', '%s', '%s'], ['%d']);

		if ($old_stage !== $new_stage) {
			self::run_recruitment_automations($candidate, $old_stage, $new_stage);
		}

		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('recruitment', ['pa_notice' => 'candidate_updated']));
		exit;
	}

	// ─── Recruitment Automations ────────────────────────────────────────

	/**
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
	public static function run_recruitment_automations(object $candidate, string $old_stage, string $new_stage): void {
		$referrer = $candidate->referrer_id ? get_userdata($candidate->referrer_id) : null;
		$stage_labels = self::$recruit_stages;
		$candidate_first = explode(' ', trim($candidate->name))[0] ?? $candidate->name;
		$company_line = $candidate->company ? ' (' . $candidate->company . ')' : '';

		// ── Stage-specific detail block for referrer emails ──
		$stage_details = [
			'contacted' => 'We\'ve reached out to them to start the conversation.',
			'invited'   => 'An invitation to visit one of our meetings has been sent.',
			'visited'   => 'They attended a meeting — nice work getting them there! We\'ve sent them a quick survey about their interest in joining. In the meantime, please reach out to them personally to get their feedback on:'
				. '<ul style="margin:10px 0 0 20px;padding:0;color:#1e40af;font-size:14px;line-height:1.8;">'
				. '<li><strong>Overall impression</strong> — How did they feel about the group dynamic and format?</li>'
				. '<li><strong>Connections made</strong> — Did they meet anyone they\'d like to stay in touch with?</li>'
				. '<li><strong>Suggestions</strong> — Anything we could do better for visitors?</li>'
				. '<li><strong>Fit</strong> — Do they see themselves contributing to and benefiting from the group?</li>'
				. '</ul>',
			'decision'  => 'The group is making a decision on their membership.',
			'accepted'  => 'They\'ve been accepted! Their member account is being created.',
			'declined'  => 'After careful consideration, we\'ve decided not to proceed at this time.',
		];
		$detail_text = $stage_details[$new_stage] ?? '';
		$html_stages = ['visited'];
		$detail_inner = in_array($new_stage, $html_stages, true)
			? $detail_text
			: esc_html($detail_text);
		$detail_block = $detail_text
			? '<div style="background:#f0f9ff;border-left:3px solid #2563eb;padding:12px 16px;margin:16px 0;font-size:14px;color:#1e40af;">' . $detail_inner . '</div>'
			: '';

		// ── 1. "Accepted" → auto-create member account ──
		if ($new_stage === 'accepted') {
			$created_user_id = self::convert_candidate_to_member($candidate);

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

			if (class_exists('CBNexus_Logger')) {
				CBNexus_Logger::info('Candidate accepted and converted to member.', [
					'candidate_id' => $candidate->id,
					'candidate'    => $candidate->name,
					'new_user_id'  => $created_user_id,
				]);
			}

			return;
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
		if ($new_stage === 'visited') {
			$opt_key = 'cbnexus_recruit_visited_sent_' . $candidate->id;

			if (empty($candidate->email)) {
				if (class_exists('CBNexus_Logger')) {
					CBNexus_Logger::warning('Cannot send visit feedback survey — candidate has no email.', [
						'candidate_id' => $candidate->id,
						'candidate'    => $candidate->name,
					]);
				}
			} elseif (get_option($opt_key)) {
				if (class_exists('CBNexus_Logger')) {
					CBNexus_Logger::info('Visit feedback survey already sent; skipping.', [
						'candidate_id' => $candidate->id,
					]);
				}
			} else {
				$feedback_urls = self::generate_visit_feedback_urls((int) $candidate->id);

				$followup = $referrer
					? $referrer->display_name
					: 'A member of the CircleBlast Council';

				$sent = CBNexus_Email_Service::send('recruit_visited_thankyou', $candidate->email, [
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

				if ($sent) {
					update_option($opt_key, gmdate('Y-m-d H:i:s'), false);
				}

				if (class_exists('CBNexus_Logger')) {
					CBNexus_Logger::info('Visit feedback survey ' . ($sent ? 'sent' : 'FAILED') . '.', [
						'candidate_id' => $candidate->id,
						'email'        => $candidate->email,
						'sent'         => $sent,
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
	 */
	private static function convert_candidate_to_member(object $candidate): ?int {
		if (empty($candidate->email)) {
			if (class_exists('CBNexus_Logger')) {
				CBNexus_Logger::warning('Cannot auto-create member for accepted candidate — no email.', [
					'candidate_id' => $candidate->id,
					'candidate'    => $candidate->name,
				]);
			}
			return null;
		}

		if (email_exists($candidate->email)) {
			if (class_exists('CBNexus_Logger')) {
				CBNexus_Logger::info('Accepted candidate already has a WP account; skipping auto-create.', [
					'candidate_id' => $candidate->id,
					'email'        => $candidate->email,
				]);
			}
			return null;
		}

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

		$profile = CBNexus_Member_Repository::get_profile($user_id);
		if ($profile) {
			CBNexus_Email_Service::send_welcome($user_id, $profile);
		}

		return $user_id;
	}

	/**
	 * Generate tokenized one-click feedback URLs for the visit survey.
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

	/**
	 * Match comma-separated guest names against the recruitment pipeline.
	 */
	public static function match_guest_attendees_to_pipeline(string $guest_csv): void {
		$names = array_filter(array_map('trim', explode(',', $guest_csv)));
		if (empty($names)) { return; }

		global $wpdb;
		$table = $wpdb->prefix . 'cb_candidates';

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

				$match = ($guest_lower === $candidate_lower)
					|| (mb_strlen($guest_lower) >= 3 && mb_strpos($candidate_lower, $guest_lower) !== false)
					|| (mb_strlen($candidate_lower) >= 3 && mb_strpos($guest_lower, $candidate_lower) !== false);

				if (!$match) { continue; }

				$old_stage = $c->stage;

				$wpdb->update($table, [
					'stage'      => 'visited',
					'updated_at' => gmdate('Y-m-d H:i:s'),
				], ['id' => $c->id], ['%s', '%s'], ['%d']);

				self::run_recruitment_automations($c, $old_stage, 'visited');

				if (class_exists('CBNexus_Logger')) {
					CBNexus_Logger::info('Guest attendee matched to pipeline candidate; auto-transitioned to visited.', [
						'guest_name'   => $guest_name,
						'candidate_id' => $c->id,
						'candidate'    => $c->name,
						'from_stage'   => $old_stage,
					]);
				}

				break;
			}
		}
	}

	/**
	 * Transition explicitly checked invited recruits to "visited" stage.
	 */
	public static function transition_checked_recruits_to_visited(array $candidate_ids): void {
		global $wpdb;
		$table = $wpdb->prefix . 'cb_candidates';

		foreach ($candidate_ids as $cid) {
			if ($cid <= 0) { continue; }

			$candidate = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $cid));
			if (!$candidate || $candidate->stage === 'visited') { continue; }

			$old_stage = $candidate->stage;

			$wpdb->update($table, [
				'stage'      => 'visited',
				'updated_at' => gmdate('Y-m-d H:i:s'),
			], ['id' => $cid], ['%s', '%s'], ['%d']);

			self::run_recruitment_automations($candidate, $old_stage, 'visited');

			if (class_exists('CBNexus_Logger')) {
				CBNexus_Logger::info('Invited recruit checked as attending; auto-transitioned to visited.', [
					'candidate_id' => $cid,
					'candidate'    => $candidate->name,
					'from_stage'   => $old_stage,
				]);
			}
		}
	}

	// ═════════════════════════════════════════════════════════════════════
	// Recruitment Needs (Categories)
	// ═════════════════════════════════════════════════════════════════════

	private static function render_recruitment_needs(): void {
		global $wpdb;
		$table      = $wpdb->prefix . 'cb_recruitment_categories';
		$schedule   = get_option('cbnexus_recruit_blast_schedule', 'none');
		$last_blast = get_option('cbnexus_last_recruit_blast', '');
		$industries = CBNexus_Member_Service::get_industries();

		$edit_id     = absint($_GET['edit_need'] ?? 0);
		$editing_cat = $edit_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $edit_id)) : null;
		$p_colors    = ['high' => 'var(--cb-red)', 'medium' => 'var(--cb-gold)', 'low' => 'var(--cb-green)'];

		// Use coverage service for computed status.
		$categories = class_exists('CBNexus_Recruitment_Coverage_Service')
			? CBNexus_Recruitment_Coverage_Service::get_full_coverage()
			: [];
		$summary = class_exists('CBNexus_Recruitment_Coverage_Service')
			? CBNexus_Recruitment_Coverage_Service::get_summary()
			: ['total' => 0, 'covered' => 0, 'partial' => 0, 'gaps' => 0, 'coverage_pct' => 0];

		$status_icons = [
			'covered' => '✅',
			'partial' => '🟡',
			'gap'     => '🔍',
		];
		$status_labels = [
			'covered' => 'Filled',
			'partial' => 'Partial',
			'gap'     => 'Open',
		];
		$recruit_stage_labels = [
			'referral'  => 'Referral',
			'contacted' => 'Contacted',
			'invited'   => 'Invited',
			'visited'   => 'Visited',
			'decision'  => 'Decision',
		];
		?>

		<div class="cbnexus-card" style="margin-top:20px;">
			<div class="cbnexus-admin-header-row">
				<h2>🎯 Recruitment Needs</h2>
			</div>
			<p class="cbnexus-admin-meta" style="margin-bottom:12px;">Define what types of members the group is looking for. Coverage is computed automatically based on member assignments.</p>

			<?php if ($summary['total'] > 0) : ?>
			<!-- Coverage Summary Bar -->
			<div style="display:flex;gap:16px;align-items:center;padding:12px 16px;background:#f8f5fa;border-radius:10px;margin-bottom:16px;flex-wrap:wrap;">
				<span style="font-weight:700;color:var(--cbnexus-plum,#4a154b);font-size:15px;"><?php echo esc_html($summary['coverage_pct']); ?>% Covered</span>
				<span style="font-size:13px;color:#059669;">✅ <?php echo esc_html($summary['covered']); ?> Filled</span>
				<?php if ($summary['partial'] > 0) : ?>
					<span style="font-size:13px;color:#d97706;">🟡 <?php echo esc_html($summary['partial']); ?> Partial</span>
				<?php endif; ?>
				<span style="font-size:13px;color:#dc2626;">🔍 <?php echo esc_html($summary['gaps']); ?> Open</span>
				<span style="font-size:13px;color:#6b7280;">of <?php echo esc_html($summary['total']); ?> total</span>
			</div>
			<?php endif; ?>

			<!-- Categories Table -->
			<div class="cbnexus-admin-table-wrap">
				<table class="cbnexus-admin-table">
					<thead><tr>
						<th>Role / Category</th><th>Industry</th><th>Priority</th><th>Status</th><th>Filled By</th><th>Pipeline</th><th>Actions</th>
					</tr></thead>
					<tbody>
					<?php if (empty($categories)) : ?>
						<tr><td colspan="7" class="cbnexus-admin-empty">No categories defined yet.</td></tr>
					<?php else : foreach ($categories as $cat) :
						$is_covered = $cat->coverage_status === 'covered';
					?>
						<tr<?php echo $is_covered ? ' style="opacity:0.6;"' : ''; ?>>
							<td>
								<strong><?php echo esc_html($cat->title); ?></strong>
								<?php if ($cat->description) : ?><br/><span class="cbnexus-admin-meta"><?php echo esc_html(wp_trim_words($cat->description, 15)); ?></span><?php endif; ?>
							</td>
							<td class="cbnexus-admin-meta"><?php echo esc_html($cat->industry ?: '—'); ?></td>
							<td><span style="color:<?php echo esc_attr($p_colors[$cat->priority] ?? 'var(--cb-text-sec)'); ?>;font-weight:600;text-transform:uppercase;font-size:11px;"><?php echo esc_html($cat->priority); ?></span></td>
							<td>
								<span style="white-space:nowrap;">
									<?php echo esc_html($status_icons[$cat->coverage_status] ?? ''); ?>
									<?php echo esc_html($status_labels[$cat->coverage_status] ?? ''); ?>
								</span>
								<div class="cbnexus-admin-meta" style="font-size:11px;"><?php echo esc_html($cat->member_count); ?> / <?php echo esc_html($cat->target_count); ?></div>
							</td>
							<td>
								<?php if (!empty($cat->members)) : ?>
									<?php foreach ($cat->members as $mem) : ?>
										<span style="display:inline-block;padding:2px 8px;background:#f3eef6;border-radius:10px;font-size:12px;color:#5b2d6e;font-weight:500;margin:2px;"><?php echo esc_html($mem['display_name']); ?></span>
									<?php endforeach; ?>
								<?php else : ?>
									<span class="cbnexus-admin-meta">—</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if (!empty($cat->pipeline_candidates)) : ?>
									<?php foreach ($cat->pipeline_candidates as $pc) : ?>
										<div style="font-size:12px;margin-bottom:2px;">
											<span style="display:inline-block;padding:1px 6px;background:#eff6ff;border-radius:8px;font-size:11px;color:#1d4ed8;"><?php echo esc_html($recruit_stage_labels[$pc->stage] ?? $pc->stage); ?></span>
											<?php echo esc_html($pc->name); ?>
										</div>
									<?php endforeach; ?>
								<?php else : ?>
									<span class="cbnexus-admin-meta">—</span>
								<?php endif; ?>
							</td>
							<td class="cbnexus-admin-actions-cell">
								<a href="<?php echo esc_url(CBNexus_Portal_Admin::admin_url('recruitment', ['edit_need' => $cat->id])); ?>" class="cbnexus-link">Edit</a>
								<a href="<?php echo esc_url(wp_nonce_url(CBNexus_Portal_Admin::admin_url('recruitment', ['cbnexus_portal_delete_need' => $cat->id]), 'cbnexus_portal_need_' . $cat->id, '_panonce')); ?>" class="cbnexus-link cbnexus-link-red" onclick="return confirm('Delete this category?');">Delete</a>
							</td>
						</tr>
					<?php endforeach; endif; ?>
					</tbody>
				</table>
			</div>

			<!-- Send Blast -->
			<div style="margin-top:12px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
				<a href="<?php echo esc_url(wp_nonce_url(CBNexus_Portal_Admin::admin_url('recruitment', ['cbnexus_portal_send_needs_blast' => '1']), 'cbnexus_portal_needs_blast', '_panonce')); ?>" class="cbnexus-btn cbnexus-btn-accent" onclick="return confirm('Send recruitment needs to all active members?');">📧 Send to Members</a>
				<?php if ($last_blast) : ?><span class="cbnexus-admin-meta">Last sent: <?php echo esc_html(date_i18n('M j, Y', strtotime($last_blast))); ?></span><?php endif; ?>
				<form method="post" style="display:flex;align-items:center;gap:8px;margin-left:auto;">
					<?php wp_nonce_field('cbnexus_portal_save_needs_schedule', '_panonce_schedule'); ?>
					<label class="cbnexus-admin-meta" style="white-space:nowrap;">Auto-send:</label>
					<select name="needs_schedule" class="cbnexus-input" style="width:auto;">
						<option value="none" <?php selected($schedule, 'none'); ?>>Manual only</option>
						<option value="weekly" <?php selected($schedule, 'weekly'); ?>>Weekly</option>
						<option value="monthly" <?php selected($schedule, 'monthly'); ?>>Monthly</option>
					</select>
					<button type="submit" name="cbnexus_portal_save_needs_schedule" value="1" class="cbnexus-btn cbnexus-btn-outline cbnexus-btn-sm">Save</button>
				</form>
			</div>
		</div>

		<!-- Add / Edit Form -->
		<div class="cbnexus-card" style="margin-top:12px;">
			<h3><?php echo $editing_cat ? '✏️ Edit Category' : '➕ Add Category'; ?></h3>
			<form method="post" style="max-width:600px;">
				<?php if ($editing_cat) : ?>
					<?php wp_nonce_field('cbnexus_portal_update_need', '_panonce'); ?>
					<input type="hidden" name="need_id" value="<?php echo esc_attr($editing_cat->id); ?>" />
				<?php else : ?>
					<?php wp_nonce_field('cbnexus_portal_add_need', '_panonce'); ?>
				<?php endif; ?>
				<div style="display:flex;flex-direction:column;gap:12px;margin-top:12px;">
					<div>
						<label style="display:block;font-weight:600;margin-bottom:4px;">Title / Role *</label>
						<input type="text" name="need_title" value="<?php echo esc_attr($editing_cat->title ?? ''); ?>" class="cbnexus-input" style="width:100%;" required placeholder="e.g. Financial Advisor, Healthcare Executive" />
					</div>
					<div>
						<label style="display:block;font-weight:600;margin-bottom:4px;">Description</label>
						<textarea name="need_description" rows="2" class="cbnexus-input" style="width:100%;" placeholder="What qualities or background are we looking for?"><?php echo esc_textarea($editing_cat->description ?? ''); ?></textarea>
					</div>
					<div style="display:flex;gap:12px;">
						<div style="flex:1;">
							<label style="display:block;font-weight:600;margin-bottom:4px;">Industry</label>
							<select name="need_industry" class="cbnexus-input">
								<option value="">— Any —</option>
								<?php foreach ($industries as $ind) : ?>
									<option value="<?php echo esc_attr($ind); ?>" <?php selected($editing_cat->industry ?? '', $ind); ?>><?php echo esc_html($ind); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div style="flex:1;">
							<label style="display:block;font-weight:600;margin-bottom:4px;">Priority</label>
							<select name="need_priority" class="cbnexus-input">
								<option value="high" <?php selected($editing_cat->priority ?? '', 'high'); ?>>🔴 High</option>
								<option value="medium" <?php selected($editing_cat->priority ?? 'medium', 'medium'); ?>>🟡 Medium</option>
								<option value="low" <?php selected($editing_cat->priority ?? '', 'low'); ?>>🟢 Low</option>
							</select>
						</div>
					</div>
					<div style="width:120px;">
						<label style="display:block;font-weight:600;margin-bottom:4px;">Target Count</label>
						<input type="number" name="need_target_count" min="1" max="10" value="<?php echo esc_attr($editing_cat->target_count ?? 1); ?>" class="cbnexus-input" style="width:100%;" />
						<span class="cbnexus-admin-meta" style="display:block;margin-top:4px;">Members needed</span>
					</div>
				</div>
				<div style="margin-top:16px;display:flex;gap:8px;">
					<button type="submit" name="<?php echo $editing_cat ? 'cbnexus_portal_update_need' : 'cbnexus_portal_add_need'; ?>" value="1" class="cbnexus-btn cbnexus-btn-primary"><?php echo $editing_cat ? 'Update' : 'Add Category'; ?></button>
					<?php if ($editing_cat) : ?>
						<a href="<?php echo esc_url(CBNexus_Portal_Admin::admin_url('recruitment')); ?>" class="cbnexus-btn cbnexus-btn-outline">Cancel</a>
					<?php endif; ?>
				</div>
			</form>
		</div>

		<?php self::render_focus_settings(); ?>
		<?php
	}

	// ─── Monthly Focus Settings ──────────────────────────────────────

	/**
	 * Render the Monthly Focus configuration card.
	 */
	private static function render_focus_settings(): void {
		if (!class_exists('CBNexus_Recruitment_Coverage_Service')) {
			return;
		}

		$settings   = CBNexus_Recruitment_Coverage_Service::get_focus_settings();
		$focus_meta = CBNexus_Recruitment_Coverage_Service::get_focus_meta();
		$focus_ids  = $focus_meta['category_ids'] ?? [];
		$has_focus  = CBNexus_Recruitment_Coverage_Service::has_active_focus();
		$next_run   = wp_next_scheduled('cbnexus_recruitment_focus_rotate');

		// Get the current focus category titles for display.
		$focus_titles = [];
		if (!empty($focus_ids)) {
			global $wpdb;
			$placeholders = implode(',', array_fill(0, count($focus_ids), '%d'));
			$rows = $wpdb->get_results($wpdb->prepare(
				"SELECT id, title, priority FROM {$wpdb->prefix}cb_recruitment_categories WHERE id IN ({$placeholders}) ORDER BY FIELD(priority, 'high','medium','low')",
				...$focus_ids
			));
			$focus_titles = $rows ?: [];
		}

		$p_dots = ['high' => '#dc2626', 'medium' => '#d97706', 'low' => '#059669'];
		?>
		<div class="cbnexus-card" style="margin-top:12px;">
			<h3>🔄 Monthly Recruitment Focus</h3>
			<p class="cbnexus-admin-meta" style="margin:0 0 14px;">
				Each month, two days before the CircleUp meeting (4th Wednesday), a set of recruitment categories is randomly selected as the group's focus.
				These focus categories replace the default "Who We're Looking For" content on the Home tab, Directory, Club Stats, and in email prompts.
				Once coverage reaches the threshold below, focus rotation pauses automatically.
			</p>

			<?php if ($has_focus && !empty($focus_titles)) : ?>
			<!-- Current Focus -->
			<div style="margin-bottom:16px;padding:12px 16px;background:#faf6fc;border:1px solid #e9e3ed;border-radius:8px;">
				<div style="font-size:12px;font-weight:600;color:#5b2d6e;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">Current Focus (since <?php echo esc_html(date_i18n('M j', strtotime($focus_meta['rotated_at']))); ?>)</div>
				<div style="display:flex;gap:8px;flex-wrap:wrap;">
					<?php foreach ($focus_titles as $ft) : ?>
						<span style="display:inline-flex;align-items:center;gap:5px;padding:4px 12px;background:#fff;border:1px solid #e9e3ed;border-radius:8px;font-size:13px;font-weight:500;">
							<span style="width:7px;height:7px;border-radius:50%;background:<?php echo esc_attr($p_dots[$ft->priority] ?? '#d97706'); ?>;"></span>
							<?php echo esc_html($ft->title); ?>
						</span>
					<?php endforeach; ?>
				</div>
				<?php if ($focus_meta['next_circleup']) : ?>
					<div class="cbnexus-admin-meta" style="margin-top:8px;">Next CircleUp: <?php echo esc_html(date_i18n('l, M j', strtotime($focus_meta['next_circleup']))); ?></div>
				<?php endif; ?>
			</div>
			<?php elseif (!empty($focus_meta['rotated_at']) && !empty($focus_meta['skipped'])) : ?>
			<div style="margin-bottom:16px;padding:10px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;font-size:13px;color:#166534;">
				✅ Focus rotation paused — coverage is above the threshold.
			</div>
			<?php else : ?>
			<div style="margin-bottom:16px;padding:10px 16px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;font-size:13px;color:#92400e;">
				No focus categories selected yet. The cron job will pick them automatically, or you can trigger a rotation manually below.
			</div>
			<?php endif; ?>

			<!-- Settings Form -->
			<form method="post" style="max-width:500px;">
				<?php wp_nonce_field('cbnexus_portal_save_focus_settings', '_panonce_focus'); ?>
				<div style="display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap;margin-bottom:14px;">
					<div>
						<label style="display:block;font-weight:600;margin-bottom:4px;font-size:13px;">Categories per month</label>
						<input type="number" name="focus_count" min="1" max="10" value="<?php echo esc_attr($settings['count']); ?>" class="cbnexus-input" style="width:80px;" />
						<span class="cbnexus-admin-meta" style="display:block;margin-top:3px;">How many categories to highlight each cycle</span>
					</div>
					<div>
						<label style="display:block;font-weight:600;margin-bottom:4px;font-size:13px;">Coverage pause threshold</label>
						<div style="display:flex;align-items:center;gap:4px;">
							<input type="number" name="focus_threshold" min="50" max="100" step="5" value="<?php echo esc_attr($settings['coverage_threshold']); ?>" class="cbnexus-input" style="width:80px;" />
							<span style="font-size:14px;font-weight:500;">%</span>
						</div>
						<span class="cbnexus-admin-meta" style="display:block;margin-top:3px;">Stop rotating when coverage reaches this level</span>
					</div>
				</div>
				<div style="display:flex;gap:8px;align-items:center;">
					<button type="submit" name="cbnexus_portal_save_focus_settings" value="1" class="cbnexus-btn cbnexus-btn-primary">Save Focus Settings</button>
					<a href="<?php echo esc_url(wp_nonce_url(CBNexus_Portal_Admin::admin_url('recruitment', ['cbnexus_portal_rotate_focus' => '1']), 'cbnexus_portal_rotate_focus', '_panonce')); ?>" class="cbnexus-btn cbnexus-btn-outline" onclick="return confirm('Rotate focus categories now? This will replace the current selection.');">🔄 Rotate Now</a>
				</div>
			</form>

			<?php if ($next_run) : ?>
			<div class="cbnexus-admin-meta" style="margin-top:10px;">
				Next automatic rotation: <?php echo esc_html(date_i18n('l, M j · g:i a', $next_run)); ?>
				· <a href="<?php echo esc_url(add_query_arg(['section' => 'manage', 'admin_tab' => 'settings'], CBNexus_Portal_Router::get_portal_url())); ?>" class="cbnexus-link">Adjust schedule →</a>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle saving focus settings.
	 */
	public static function handle_save_focus_settings(): void {
		if (!wp_verify_nonce(wp_unslash($_POST['_panonce_focus'] ?? ''), 'cbnexus_portal_save_focus_settings')) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }

		CBNexus_Recruitment_Coverage_Service::save_focus_settings([
			'count'              => absint($_POST['focus_count'] ?? 3),
			'coverage_threshold' => absint($_POST['focus_threshold'] ?? 80),
		]);

		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('recruitment', ['pa_notice' => 'focus_saved']));
		exit;
	}

	/**
	 * Handle manual focus rotation.
	 */
	public static function handle_rotate_focus(): void {
		if (!wp_verify_nonce(wp_unslash($_GET['_panonce'] ?? ''), 'cbnexus_portal_rotate_focus')) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }

		CBNexus_Recruitment_Coverage_Service::rotate_focus();

		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('recruitment', ['pa_notice' => 'focus_rotated']));
		exit;
	}

	// ─── Recruitment Needs Action Handlers ────────────────────────────

	public static function handle_add_need(): void {
		if (!wp_verify_nonce(wp_unslash($_POST['_panonce'] ?? ''), 'cbnexus_portal_add_need')) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }

		global $wpdb;
		$table    = $wpdb->prefix . 'cb_recruitment_categories';
		$now      = gmdate('Y-m-d H:i:s');
		$max_sort = (int) $wpdb->get_var("SELECT MAX(sort_order) FROM {$table}") + 1;

		$wpdb->insert($table, [
			'title'        => sanitize_text_field(wp_unslash($_POST['need_title'] ?? '')),
			'description'  => sanitize_textarea_field(wp_unslash($_POST['need_description'] ?? '')),
			'industry'     => sanitize_text_field($_POST['need_industry'] ?? ''),
			'priority'     => in_array($_POST['need_priority'] ?? '', ['high', 'medium', 'low'], true) ? $_POST['need_priority'] : 'medium',
			'target_count' => max(1, absint($_POST['need_target_count'] ?? 1)),
			'sort_order'   => $max_sort,
			'created_by'   => get_current_user_id(),
			'created_at'   => $now,
			'updated_at'   => $now,
		]);

		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('recruitment', ['pa_notice' => 'need_added']));
		exit;
	}

	public static function handle_update_need(): void {
		$id = absint($_POST['need_id'] ?? 0);
		if (!wp_verify_nonce(wp_unslash($_POST['_panonce'] ?? ''), 'cbnexus_portal_update_need')) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }

		global $wpdb;
		$wpdb->update($wpdb->prefix . 'cb_recruitment_categories', [
			'title'        => sanitize_text_field(wp_unslash($_POST['need_title'] ?? '')),
			'description'  => sanitize_textarea_field(wp_unslash($_POST['need_description'] ?? '')),
			'industry'     => sanitize_text_field($_POST['need_industry'] ?? ''),
			'priority'     => in_array($_POST['need_priority'] ?? '', ['high', 'medium', 'low'], true) ? $_POST['need_priority'] : 'medium',
			'target_count' => max(1, absint($_POST['need_target_count'] ?? 1)),
			'updated_at'   => gmdate('Y-m-d H:i:s'),
		], ['id' => $id]);

		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('recruitment', ['pa_notice' => 'need_updated']));
		exit;
	}

	public static function handle_toggle_need(): void {
		$id = absint($_GET['cbnexus_portal_toggle_need'] ?? 0);
		if (!wp_verify_nonce(wp_unslash($_GET['_panonce'] ?? ''), 'cbnexus_portal_need_' . $id)) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }

		global $wpdb;
		$table   = $wpdb->prefix . 'cb_recruitment_categories';
		$current = (int) $wpdb->get_var($wpdb->prepare("SELECT is_filled FROM {$table} WHERE id = %d", $id));
		$wpdb->update($table, ['is_filled' => $current ? 0 : 1], ['id' => $id]);

		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('recruitment', ['pa_notice' => 'need_toggled']));
		exit;
	}

	public static function handle_delete_need(): void {
		$id = absint($_GET['cbnexus_portal_delete_need'] ?? 0);
		if (!wp_verify_nonce(wp_unslash($_GET['_panonce'] ?? ''), 'cbnexus_portal_need_' . $id)) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }

		global $wpdb;
		$wpdb->delete($wpdb->prefix . 'cb_recruitment_categories', ['id' => $id]);

		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('recruitment', ['pa_notice' => 'need_deleted']));
		exit;
	}

	public static function handle_send_needs_blast(): void {
		if (!wp_verify_nonce(wp_unslash($_GET['_panonce'] ?? ''), 'cbnexus_portal_needs_blast')) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }

		CBNexus_Admin_Recruitment_Categories::send_blast();

		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('recruitment', ['pa_notice' => 'needs_blast_sent']));
		exit;
	}

	public static function handle_save_needs_schedule(): void {
		if (!wp_verify_nonce(wp_unslash($_POST['_panonce_schedule'] ?? ''), 'cbnexus_portal_save_needs_schedule')) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }

		$freq = sanitize_key($_POST['needs_schedule'] ?? 'none');
		update_option('cbnexus_recruit_blast_schedule', $freq);

		wp_clear_scheduled_hook('cbnexus_recruitment_blast');
		if ($freq !== 'none') {
			wp_schedule_event(time(), $freq, 'cbnexus_recruitment_blast');
		}

		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('recruitment', ['pa_notice' => 'needs_schedule_saved']));
		exit;
	}
}