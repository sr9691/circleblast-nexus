<?php
/**
 * Portal Admin – Feedback Tab
 *
 * Super-admin-only tab within Manage that lists all member feedback
 * submissions with status management and admin notes.
 *
 * @since 1.3.0
 */

defined('ABSPATH') || exit;

final class CBNexus_Portal_Admin_Feedback {

	// ─── Action Handlers ───────────────────────────────────────────

	/**
	 * Handle feedback status update.
	 */
	public static function handle_update_status(): void {
		if (!current_user_can('cbnexus_manage_plugin_settings')) { return; }
		if (!wp_verify_nonce(sanitize_key($_POST['_panonce'] ?? ''), 'cbnexus_update_feedback')) { return; }

		$id          = absint($_POST['feedback_id'] ?? 0);
		$status      = sanitize_key($_POST['status'] ?? '');
		$admin_notes = sanitize_textarea_field(wp_unslash($_POST['admin_notes'] ?? ''));

		if ($id && $status) {
			CBNexus_Feedback_Service::update_status($id, $status, $admin_notes);
		}

		// Redirect back to feedback tab.
		$redirect = CBNexus_Portal_Admin::admin_url('feedback');
		wp_safe_redirect($redirect);
		exit;
	}

	/**
	 * Handle feedback deletion.
	 */
	public static function handle_delete(): void {
		if (!current_user_can('cbnexus_manage_plugin_settings')) { return; }
		if (!wp_verify_nonce(sanitize_key($_GET['_panonce'] ?? ''), 'cbnexus_delete_feedback')) { return; }

		$id = absint($_GET['feedback_id'] ?? 0);
		if ($id) {
			CBNexus_Feedback_Service::delete($id);
		}

		$redirect = CBNexus_Portal_Admin::admin_url('feedback');
		wp_safe_redirect($redirect);
		exit;
	}

	// ─── Render ────────────────────────────────────────────────────

	/**
	 * Render the feedback management view.
	 */
	public static function render(): void {
		if (!current_user_can('cbnexus_manage_plugin_settings')) {
			echo '<div class="cbnexus-card"><p>You do not have permission to view this page.</p></div>';
			return;
		}

		// Check if viewing a single feedback detail.
		$detail_id = absint($_GET['feedback_id'] ?? 0);
		if ($detail_id) {
			self::render_detail($detail_id);
			return;
		}

		// Status filter.
		$filter_status = sanitize_key($_GET['fb_status'] ?? '');
		$filter_type   = sanitize_key($_GET['fb_type'] ?? '');

		$args = ['limit' => 100];
		if ($filter_status) { $args['status'] = $filter_status; }
		if ($filter_type)   { $args['type']   = $filter_type; }

		$items    = CBNexus_Feedback_Service::get_all($args);
		$counts   = CBNexus_Feedback_Service::count_by_status();
		$total    = array_sum($counts);
		$base_url = CBNexus_Portal_Admin::admin_url('feedback');
		?>

		<div class="cbnexus-card">
			<h2>📬 <?php esc_html_e('Member Feedback', 'circleblast-nexus'); ?></h2>
			<p class="cbnexus-text-muted" style="margin-top:2px;"><?php esc_html_e('Feedback, bug reports, and suggestions from members.', 'circleblast-nexus'); ?></p>

			<!-- Status filter pills -->
			<div style="display:flex; gap:6px; flex-wrap:wrap; margin:14px 0;">
				<a href="<?php echo esc_url($base_url); ?>"
				   class="cbnexus-btn <?php echo $filter_status === '' ? 'cbnexus-btn-primary' : 'cbnexus-btn-outline'; ?>"
				   style="font-size:12px; padding:5px 12px;">
					<?php printf(esc_html__('All (%d)', 'circleblast-nexus'), $total); ?>
				</a>
				<?php foreach (CBNexus_Feedback_Service::get_statuses() as $s) :
					$cnt = $counts[$s] ?? 0;
					$url = add_query_arg('fb_status', $s, $base_url);
					$active = $filter_status === $s;
				?>
					<a href="<?php echo esc_url($url); ?>"
					   class="cbnexus-btn <?php echo $active ? 'cbnexus-btn-primary' : 'cbnexus-btn-outline'; ?>"
					   style="font-size:12px; padding:5px 12px;">
						<?php echo esc_html(CBNexus_Feedback_Service::status_label($s)); ?>
						(<?php echo esc_html($cnt); ?>)
					</a>
				<?php endforeach; ?>
			</div>

			<?php if (empty($items)) : ?>
				<p class="cbnexus-text-muted" style="padding:20px 0; text-align:center;">
					<?php esc_html_e('No feedback found.', 'circleblast-nexus'); ?>
				</p>
			<?php else : ?>
				<div class="cbnexus-feedback-list">
					<?php foreach ($items as $item) : ?>
						<?php self::render_item_card($item, $base_url); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render a single feedback card in the list.
	 */
	private static function render_item_card(object $item, string $base_url): void {
		$type_icons = [
			'feedback' => '💬',
			'bug'      => '🐛',
			'idea'     => '💡',
			'question' => '❓',
		];
		$status_colors = [
			'new'         => 'var(--cb-blue)',
			'reviewed'    => 'var(--cb-gold)',
			'in_progress' => 'var(--cb-accent)',
			'resolved'    => 'var(--cb-green)',
			'dismissed'   => 'var(--cb-text-ter)',
		];

		$icon  = $type_icons[$item->type] ?? '💬';
		$color = $status_colors[$item->status] ?? 'var(--cb-text-sec)';
		$detail_url = add_query_arg('feedback_id', $item->id, $base_url);
		$date = date_i18n('M j, Y g:ia', strtotime($item->created_at));
		?>
		<div class="cbnexus-feedback-item" style="display:flex; gap:14px; padding:14px 0; border-bottom:1px solid var(--cb-border-soft);">
			<div style="font-size:22px; flex-shrink:0; padding-top:2px;"><?php echo esc_html($icon); ?></div>
			<div style="flex:1; min-width:0;">
				<div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
					<a href="<?php echo esc_url($detail_url); ?>" style="font-weight:600; color:var(--cb-text); text-decoration:none; font-size:14px;">
						<?php echo esc_html($item->subject ?: '(No subject)'); ?>
					</a>
					<span style="display:inline-block; padding:2px 8px; border-radius:var(--cb-radius-pill); font-size:11px; font-weight:600; color:#fff; background:<?php echo esc_attr($color); ?>;">
						<?php echo esc_html(CBNexus_Feedback_Service::status_label($item->status)); ?>
					</span>
					<span style="font-size:11px; color:var(--cb-text-ter); padding:2px 6px; border:1px solid var(--cb-border-soft); border-radius:var(--cb-radius-pill);">
						<?php echo esc_html(CBNexus_Feedback_Service::type_label($item->type)); ?>
					</span>
				</div>
				<p style="margin:4px 0 0; font-size:13px; color:var(--cb-text-sec); line-height:1.5; overflow:hidden; text-overflow:ellipsis; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;">
					<?php echo esc_html(mb_substr($item->message, 0, 200)); ?>
				</p>
				<div style="margin-top:6px; font-size:12px; color:var(--cb-text-ter);">
					<?php echo esc_html($item->author_name ?? 'Unknown'); ?> · <?php echo esc_html($date); ?>
					<?php if ($item->page_context) : ?>
						· <em><?php echo esc_html($item->page_context); ?> page</em>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a single feedback detail view with status update form.
	 */
	private static function render_detail(int $id): void {
		$item = CBNexus_Feedback_Service::get_by_id($id);
		if (!$item) {
			echo '<div class="cbnexus-card"><p>Feedback not found.</p></div>';
			return;
		}

		$base_url   = CBNexus_Portal_Admin::admin_url('feedback');
		$delete_url = add_query_arg([
			'cbnexus_portal_delete_feedback' => 1,
			'feedback_id' => $id,
			'_panonce'    => wp_create_nonce('cbnexus_delete_feedback'),
		], $base_url);
		$date = date_i18n('F j, Y \a\t g:i A', strtotime($item->created_at));

		$type_icons = [
			'feedback' => '💬',
			'bug'      => '🐛',
			'idea'     => '💡',
			'question' => '❓',
		];
		$icon = $type_icons[$item->type] ?? '💬';
		?>

		<div class="cbnexus-card">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
				<a href="<?php echo esc_url($base_url); ?>" class="cbnexus-btn cbnexus-btn-outline" style="font-size:12px; padding:5px 12px;">← <?php esc_html_e('Back to Feedback', 'circleblast-nexus'); ?></a>
				<a href="<?php echo esc_url($delete_url); ?>" class="cbnexus-btn cbnexus-btn-outline" style="font-size:12px; padding:5px 12px; color:var(--cb-red); border-color:var(--cb-red);" onclick="return confirm('Delete this feedback?');">🗑 <?php esc_html_e('Delete', 'circleblast-nexus'); ?></a>
			</div>

			<div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
				<span style="font-size:24px;"><?php echo esc_html($icon); ?></span>
				<h2 style="margin:0; font-size:18px;"><?php echo esc_html($item->subject ?: '(No subject)'); ?></h2>
			</div>

			<div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px;">
				<span style="font-size:12px; color:var(--cb-text-sec); padding:3px 8px; border:1px solid var(--cb-border-soft); border-radius:var(--cb-radius-pill);">
					<?php echo esc_html(CBNexus_Feedback_Service::type_label($item->type)); ?>
				</span>
				<span style="font-size:12px; color:var(--cb-text-sec);">
					<?php esc_html_e('From:', 'circleblast-nexus'); ?>
					<strong><?php echo esc_html($item->author_name ?? 'Unknown'); ?></strong>
					(<?php echo esc_html($item->author_email ?? ''); ?>)
				</span>
				<span style="font-size:12px; color:var(--cb-text-ter);">
					<?php echo esc_html($date); ?>
				</span>
				<?php if ($item->page_context) : ?>
					<span style="font-size:12px; color:var(--cb-text-ter);">
						· <?php echo esc_html($item->page_context); ?> page
					</span>
				<?php endif; ?>
			</div>

			<div style="background:var(--cb-bg); padding:16px; border-radius:var(--cb-radius-sm); margin-bottom:18px; line-height:1.7; font-size:14px; color:var(--cb-text); white-space:pre-wrap;">
<?php echo esc_html($item->message); ?>
			</div>

			<?php if ($item->resolved_by) :
				$resolver = get_userdata($item->resolved_by);
				$resolved_date = $item->resolved_at ? date_i18n('M j, Y g:ia', strtotime($item->resolved_at)) : '';
			?>
				<p style="font-size:12px; color:var(--cb-text-ter); margin-bottom:8px;">
					<?php echo esc_html(CBNexus_Feedback_Service::status_label($item->status)); ?>
					by <?php echo esc_html($resolver ? $resolver->display_name : '#' . $item->resolved_by); ?>
					<?php if ($resolved_date) echo '· ' . esc_html($resolved_date); ?>
				</p>
			<?php endif; ?>

			<?php if ($item->admin_notes) : ?>
				<div style="background:var(--cb-gold-soft); padding:12px 14px; border-radius:var(--cb-radius-sm); margin-bottom:14px; font-size:13px; border-left:3px solid var(--cb-gold);">
					<strong><?php esc_html_e('Admin Notes:', 'circleblast-nexus'); ?></strong><br>
					<?php echo nl2br(esc_html($item->admin_notes)); ?>
				</div>
			<?php endif; ?>
		</div>

		<!-- Status Update Form -->
		<div class="cbnexus-card">
			<h3><?php esc_html_e('Update Status', 'circleblast-nexus'); ?></h3>
			<form method="post" action="">
				<input type="hidden" name="cbnexus_portal_update_feedback" value="1" />
				<input type="hidden" name="feedback_id" value="<?php echo esc_attr($item->id); ?>" />
				<?php wp_nonce_field('cbnexus_update_feedback', '_panonce'); ?>

				<div style="margin-bottom:12px;">
					<label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;"><?php esc_html_e('Status', 'circleblast-nexus'); ?></label>
					<select name="status" style="width:100%; padding:8px 12px; border:1.5px solid var(--cb-border); border-radius:var(--cb-radius-sm); font-size:14px; font-family:inherit;">
						<?php foreach (CBNexus_Feedback_Service::get_statuses() as $s) : ?>
							<option value="<?php echo esc_attr($s); ?>" <?php selected($s, $item->status); ?>>
								<?php echo esc_html(CBNexus_Feedback_Service::status_label($s)); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div style="margin-bottom:14px;">
					<label style="font-size:13px; font-weight:600; display:block; margin-bottom:4px;"><?php esc_html_e('Admin Notes', 'circleblast-nexus'); ?> <span class="cbnexus-text-muted">(<?php esc_html_e('internal only', 'circleblast-nexus'); ?>)</span></label>
					<textarea name="admin_notes" rows="3" style="width:100%; padding:9px 12px; border:1.5px solid var(--cb-border); border-radius:var(--cb-radius-sm); font-size:14px; font-family:inherit; resize:vertical;"><?php echo esc_textarea($item->admin_notes ?? ''); ?></textarea>
				</div>

				<button type="submit" class="cbnexus-btn cbnexus-btn-primary"><?php esc_html_e('Save Changes', 'circleblast-nexus'); ?></button>
			</form>
		</div>
		<?php
	}
}