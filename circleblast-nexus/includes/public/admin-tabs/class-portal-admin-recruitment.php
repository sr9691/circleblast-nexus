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
						<th>Referred By</th>
						<th>Stage</th>
						<th>Notes</th>
						<th>Updated</th>
						<th>Actions</th>
					</tr></thead>
					<tbody>
					<?php if (empty($candidates)) : ?>
						<tr><td colspan="7" class="cbnexus-admin-empty">No candidates yet.</td></tr>
					<?php else : foreach ($candidates as $c) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html($c->name); ?></strong>
								<?php if ($c->email) : ?><div class="cbnexus-admin-meta"><?php echo esc_html($c->email); ?></div><?php endif; ?>
							</td>
							<td><?php echo esc_html($c->company ?: '—'); ?></td>
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
			'referrer_id' => absint($_POST['referrer_id'] ?? 0) ?: null,
			'stage'       => $new_stage,
			'notes'       => sanitize_textarea_field(wp_unslash($_POST['notes'] ?? '')),
			'updated_at'  => gmdate('Y-m-d H:i:s'),
		], ['id' => $id], ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s'], ['%d']);

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
			'referrer_id' => absint($_POST['referrer_id'] ?? 0) ?: null,
			'stage'       => 'referral',
			'notes'       => sanitize_textarea_field(wp_unslash($_POST['notes'] ?? '')),
			'created_at'  => $now,
			'updated_at'  => $now,
		], ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']);

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
}
