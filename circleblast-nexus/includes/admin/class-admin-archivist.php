<?php
/**
 * Admin Archivist
 *
 * ITER-0013: Admin pages for managing CircleUp meetings. The Archivist
 * reviews AI-extracted items, edits curated summaries, and publishes
 * meeting notes with email distribution to all members.
 */

defined('ABSPATH') || exit;

final class CBNexus_Admin_Archivist {

	public static function init(): void {
		add_action('admin_menu', [__CLASS__, 'register_menu']);
		add_action('admin_init', [__CLASS__, 'handle_actions']);
	}

	public static function register_menu(): void {
		add_menu_page(
			__('CircleUp', 'circleblast-nexus'),
			__('CircleUp', 'circleblast-nexus'),
			'cbnexus_manage_members',
			'cbnexus-circleup',
			[__CLASS__, 'render_list_page'],
			'dashicons-megaphone',
			31
		);

		add_submenu_page(
			'cbnexus-circleup',
			__('All Meetings', 'circleblast-nexus'),
			__('All Meetings', 'circleblast-nexus'),
			'cbnexus_manage_members',
			'cbnexus-circleup',
			[__CLASS__, 'render_list_page']
		);

		add_submenu_page(
			'cbnexus-circleup',
			__('Add Meeting', 'circleblast-nexus'),
			__('Add Meeting', 'circleblast-nexus'),
			'cbnexus_manage_members',
			'cbnexus-circleup-add',
			[__CLASS__, 'render_add_page']
		);
	}

	// ─── Actions ───────────────────────────────────────────────────────

	public static function handle_actions(): void {
		if (isset($_POST['cbnexus_create_circleup'])) { self::handle_create(); }
		if (isset($_POST['cbnexus_save_circleup'])) { self::handle_save(); }
		if (isset($_GET['cbnexus_extract'])) { self::handle_extract(); }
		if (isset($_GET['cbnexus_publish'])) { self::handle_publish(); }
	}

	private static function handle_create(): void {
		check_admin_referer('cbnexus_create_circleup');
		if (!current_user_can('cbnexus_manage_members')) { wp_die('Permission denied.'); }

		$id = CBNexus_CircleUp_Repository::create_meeting([
			'meeting_date'    => sanitize_text_field($_POST['meeting_date'] ?? gmdate('Y-m-d')),
			'title'           => sanitize_text_field($_POST['title'] ?? ''),
			'full_transcript' => sanitize_textarea_field(wp_unslash($_POST['full_transcript'] ?? '')),
			'status'          => 'draft',
		]);

		wp_safe_redirect(admin_url('admin.php?page=cbnexus-circleup&cbnexus_notice=' . ($id ? 'created' : 'error')));
		exit;
	}

	private static function handle_save(): void {
		check_admin_referer('cbnexus_save_circleup');
		if (!current_user_can('cbnexus_manage_members')) { wp_die('Permission denied.'); }

		$id = absint($_POST['meeting_id'] ?? 0);

		CBNexus_CircleUp_Repository::update_meeting($id, [
			'title'           => sanitize_text_field($_POST['title'] ?? ''),
			'meeting_date'    => sanitize_text_field($_POST['meeting_date'] ?? ''),
			'curated_summary' => wp_kses_post(wp_unslash($_POST['curated_summary'] ?? '')),
		]);

		// Update item statuses (approve/reject).
		if (!empty($_POST['item_status']) && is_array($_POST['item_status'])) {
			foreach ($_POST['item_status'] as $item_id => $status) {
				CBNexus_CircleUp_Repository::update_item(absint($item_id), [
					'status' => sanitize_key($status),
				]);
			}
		}

		// Update attendees.
		if (!empty($_POST['attendees']) && is_array($_POST['attendees'])) {
			foreach ($_POST['attendees'] as $member_id) {
				CBNexus_CircleUp_Repository::add_attendee($id, absint($member_id));
			}
		}

		wp_safe_redirect(admin_url('admin.php?page=cbnexus-circleup&edit=' . $id . '&cbnexus_notice=saved'));
		exit;
	}

	private static function handle_extract(): void {
		$id = absint($_GET['cbnexus_extract'] ?? 0);
		if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'cbnexus_extract_' . $id)) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }

		$result = CBNexus_AI_Extractor::extract($id);
		$notice = $result['success'] ? 'extracted' : 'extract_error';

		wp_safe_redirect(admin_url('admin.php?page=cbnexus-circleup&edit=' . $id . '&cbnexus_notice=' . $notice));
		exit;
	}

	private static function handle_publish(): void {
		$id = absint($_GET['cbnexus_publish'] ?? 0);
		if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'cbnexus_publish_' . $id)) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }

		CBNexus_CircleUp_Repository::update_meeting($id, [
			'status'       => 'published',
			'published_by' => get_current_user_id(),
			'published_at' => gmdate('Y-m-d H:i:s'),
		]);

		// Send summary email to all active members.
		self::send_summary_email($id);

		wp_safe_redirect(admin_url('admin.php?page=cbnexus-circleup&cbnexus_notice=published'));
		exit;
	}

	// ─── Render: List ──────────────────────────────────────────────────

	public static function render_list_page(): void {
		if (!current_user_can('cbnexus_manage_members')) { wp_die('Permission denied.'); }

		// Edit mode.
		if (isset($_GET['edit'])) {
			self::render_edit_page(absint($_GET['edit']));
			return;
		}

		$meetings = CBNexus_CircleUp_Repository::get_meetings('', 100);
		$notice   = sanitize_key($_GET['cbnexus_notice'] ?? '');
		$notices  = [
			'created'   => __('CircleUp meeting created.', 'circleblast-nexus'),
			'published' => __('Meeting published and summary emailed to all members.', 'circleblast-nexus'),
			'error'     => __('An error occurred.', 'circleblast-nexus'),
		];
		?>
		<div class="wrap">
			<h1><?php esc_html_e('CircleUp Meetings', 'circleblast-nexus'); ?>
				<a href="<?php echo esc_url(admin_url('admin.php?page=cbnexus-circleup-add')); ?>" class="page-title-action"><?php esc_html_e('Add New', 'circleblast-nexus'); ?></a>
			</h1>

			<?php if ($notice && isset($notices[$notice])) : ?>
				<div class="notice notice-<?php echo $notice === 'error' ? 'error' : 'success'; ?> is-dismissible"><p><?php echo esc_html($notices[$notice]); ?></p></div>
			<?php endif; ?>

			<table class="wp-list-table widefat fixed striped">
				<thead><tr>
					<th style="width:100px;"><?php esc_html_e('Date', 'circleblast-nexus'); ?></th>
					<th><?php esc_html_e('Title', 'circleblast-nexus'); ?></th>
					<th style="width:80px;"><?php esc_html_e('Status', 'circleblast-nexus'); ?></th>
					<th style="width:80px;"><?php esc_html_e('Items', 'circleblast-nexus'); ?></th>
					<th style="width:120px;"><?php esc_html_e('Actions', 'circleblast-nexus'); ?></th>
				</tr></thead>
				<tbody>
				<?php if (empty($meetings)) : ?>
					<tr><td colspan="5"><?php esc_html_e('No CircleUp meetings yet.', 'circleblast-nexus'); ?></td></tr>
				<?php else : foreach ($meetings as $m) :
					$items = CBNexus_CircleUp_Repository::get_items((int) $m->id);
					$status_label = ['draft' => 'Draft', 'review' => 'In Review', 'published' => 'Published'];
				?>
					<tr>
						<td><?php echo esc_html($m->meeting_date); ?></td>
						<td><a href="<?php echo esc_url(admin_url('admin.php?page=cbnexus-circleup&edit=' . $m->id)); ?>"><strong><?php echo esc_html($m->title); ?></strong></a></td>
						<td><?php echo esc_html($status_label[$m->status] ?? ucfirst($m->status)); ?></td>
						<td><?php echo esc_html(count($items)); ?></td>
						<td>
							<a href="<?php echo esc_url(admin_url('admin.php?page=cbnexus-circleup&edit=' . $m->id)); ?>"><?php esc_html_e('Edit', 'circleblast-nexus'); ?></a>
							<?php if ($m->status !== 'published') : ?>
								| <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cbnexus-circleup&cbnexus_publish=' . $m->id), 'cbnexus_publish_' . $m->id)); ?>" onclick="return confirm('Publish and email all members?');"><?php esc_html_e('Publish', 'circleblast-nexus'); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	// ─── Render: Add ───────────────────────────────────────────────────

	public static function render_add_page(): void {
		if (!current_user_can('cbnexus_manage_members')) { wp_die('Permission denied.'); }
		?>
		<div class="wrap">
			<h1><?php esc_html_e('Add CircleUp Meeting', 'circleblast-nexus'); ?></h1>
			<form method="post" action="">
				<?php wp_nonce_field('cbnexus_create_circleup'); ?>
				<table class="form-table">
					<tr><th><label for="title"><?php esc_html_e('Title', 'circleblast-nexus'); ?></label></th>
						<td><input type="text" name="title" id="title" class="regular-text" required /></td></tr>
					<tr><th><label for="meeting_date"><?php esc_html_e('Meeting Date', 'circleblast-nexus'); ?></label></th>
						<td><input type="date" name="meeting_date" id="meeting_date" value="<?php echo esc_attr(gmdate('Y-m-d')); ?>" required /></td></tr>
					<tr><th><label for="full_transcript"><?php esc_html_e('Transcript', 'circleblast-nexus'); ?></label></th>
						<td><textarea name="full_transcript" id="full_transcript" rows="15" class="large-text" placeholder="<?php esc_attr_e('Paste transcript here, or leave empty to receive via Fireflies webhook...', 'circleblast-nexus'); ?>"></textarea></td></tr>
				</table>
				<?php submit_button(__('Create Meeting', 'circleblast-nexus'), 'primary', 'cbnexus_create_circleup'); ?>
			</form>
		</div>
		<?php
	}

	// ─── Render: Edit / Review ─────────────────────────────────────────

	private static function render_edit_page(int $id): void {
		$meeting = CBNexus_CircleUp_Repository::get_meeting($id);
		if (!$meeting) { echo '<div class="wrap"><p>Meeting not found.</p></div>'; return; }

		$items     = CBNexus_CircleUp_Repository::get_items($id);
		$attendees = CBNexus_CircleUp_Repository::get_attendees($id);
		$members   = CBNexus_Member_Repository::get_all_members('active');
		$notice    = sanitize_key($_GET['cbnexus_notice'] ?? '');
		$notices   = [
			'saved'         => __('Changes saved.', 'circleblast-nexus'),
			'extracted'     => sprintf(__('AI extraction complete — %d items extracted.', 'circleblast-nexus'), count($items)),
			'extract_error' => __('AI extraction failed. Check the logs and ensure CBNEXUS_CLAUDE_API_KEY is set.', 'circleblast-nexus'),
		];
		$types = ['win' => 'Wins', 'insight' => 'Insights', 'opportunity' => 'Opportunities', 'action' => 'Action Items'];
		$attendee_ids = array_map(fn($a) => (int) $a->member_id, $attendees);
		?>
		<div class="wrap">
			<h1><?php echo esc_html($meeting->title); ?>
				<span style="font-size:14px;color:#666;margin-left:8px;"><?php echo esc_html($meeting->meeting_date); ?> — <?php echo esc_html(ucfirst($meeting->status)); ?></span>
			</h1>

			<?php if ($notice && isset($notices[$notice])) : ?>
				<div class="notice notice-<?php echo str_contains($notice, 'error') ? 'error' : 'success'; ?> is-dismissible"><p><?php echo esc_html($notices[$notice]); ?></p></div>
			<?php endif; ?>

			<!-- Action Buttons -->
			<div style="margin:12px 0;">
				<?php if (!empty($meeting->full_transcript)) : ?>
					<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cbnexus-circleup&cbnexus_extract=' . $id), 'cbnexus_extract_' . $id)); ?>" class="button" onclick="return confirm('Run AI extraction? This will replace existing extracted items.');"><?php esc_html_e('Run AI Extraction', 'circleblast-nexus'); ?></a>
				<?php endif; ?>
				<?php if ($meeting->status !== 'published') : ?>
					<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cbnexus-circleup&cbnexus_publish=' . $id), 'cbnexus_publish_' . $id)); ?>" class="button button-primary" onclick="return confirm('Publish and email summary to all members?');"><?php esc_html_e('Publish & Email', 'circleblast-nexus'); ?></a>
				<?php endif; ?>
			</div>

			<form method="post" action="">
				<?php wp_nonce_field('cbnexus_save_circleup'); ?>
				<input type="hidden" name="meeting_id" value="<?php echo esc_attr($id); ?>" />

				<table class="form-table">
					<tr><th><?php esc_html_e('Title', 'circleblast-nexus'); ?></th>
						<td><input type="text" name="title" value="<?php echo esc_attr($meeting->title); ?>" class="regular-text" /></td></tr>
					<tr><th><?php esc_html_e('Date', 'circleblast-nexus'); ?></th>
						<td><input type="date" name="meeting_date" value="<?php echo esc_attr($meeting->meeting_date); ?>" /></td></tr>
					<tr><th><?php esc_html_e('Curated Summary', 'circleblast-nexus'); ?></th>
						<td><textarea name="curated_summary" rows="8" class="large-text"><?php echo esc_textarea($meeting->curated_summary ?: $meeting->ai_summary); ?></textarea>
						<p class="description"><?php esc_html_e('Edit the AI-generated summary before publishing.', 'circleblast-nexus'); ?></p></td></tr>
					<tr><th><?php esc_html_e('Attendees', 'circleblast-nexus'); ?></th>
						<td>
							<fieldset style="max-height:200px;overflow-y:auto;border:1px solid #ddd;padding:8px;border-radius:4px;">
								<?php foreach ($members as $m) : ?>
									<label style="display:block;margin:2px 0;"><input type="checkbox" name="attendees[]" value="<?php echo esc_attr($m['user_id']); ?>" <?php checked(in_array((int) $m['user_id'], $attendee_ids, true)); ?> /> <?php echo esc_html($m['display_name']); ?></label>
								<?php endforeach; ?>
							</fieldset>
						</td></tr>
				</table>

				<!-- Extracted Items -->
				<?php if (!empty($items)) : ?>
					<h2><?php esc_html_e('Extracted Items', 'circleblast-nexus'); ?></h2>
					<?php foreach ($types as $type => $label) :
						$typed = array_filter($items, fn($i) => $i->item_type === $type);
						if (empty($typed)) { continue; }
					?>
						<h3><?php echo esc_html($label); ?> (<?php echo count($typed); ?>)</h3>
						<table class="widefat fixed striped" style="margin-bottom:16px;">
							<thead><tr>
								<th><?php esc_html_e('Content', 'circleblast-nexus'); ?></th>
								<th style="width:120px;"><?php esc_html_e('Speaker', 'circleblast-nexus'); ?></th>
								<?php if ($type === 'action') : ?><th style="width:120px;"><?php esc_html_e('Assigned To', 'circleblast-nexus'); ?></th><?php endif; ?>
								<th style="width:120px;"><?php esc_html_e('Status', 'circleblast-nexus'); ?></th>
							</tr></thead>
							<tbody>
							<?php foreach ($typed as $item) : ?>
								<tr>
									<td><?php echo esc_html($item->content); ?></td>
									<td><?php echo esc_html($item->speaker_name ?: '—'); ?></td>
									<?php if ($type === 'action') : ?><td><?php echo esc_html($item->assigned_to ? get_userdata($item->assigned_to)->display_name ?? '—' : '—'); ?></td><?php endif; ?>
									<td>
										<select name="item_status[<?php echo esc_attr($item->id); ?>]">
											<option value="draft" <?php selected($item->status, 'draft'); ?>><?php esc_html_e('Draft', 'circleblast-nexus'); ?></option>
											<option value="approved" <?php selected($item->status, 'approved'); ?>><?php esc_html_e('Approved', 'circleblast-nexus'); ?></option>
											<option value="rejected" <?php selected($item->status, 'rejected'); ?>><?php esc_html_e('Rejected', 'circleblast-nexus'); ?></option>
										</select>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endforeach; ?>
				<?php elseif (!empty($meeting->full_transcript)) : ?>
					<p><em><?php esc_html_e('No items extracted yet. Click "Run AI Extraction" above.', 'circleblast-nexus'); ?></em></p>
				<?php endif; ?>

				<?php submit_button(__('Save Changes', 'circleblast-nexus'), 'primary', 'cbnexus_save_circleup'); ?>
			</form>

			<!-- Transcript (collapsible) -->
			<?php if (!empty($meeting->full_transcript)) : ?>
				<details style="margin-top:20px;">
					<summary style="cursor:pointer;font-weight:600;"><?php esc_html_e('Full Transcript', 'circleblast-nexus'); ?></summary>
					<pre style="white-space:pre-wrap;background:#f8f8f8;padding:16px;border:1px solid #ddd;border-radius:4px;max-height:500px;overflow-y:auto;font-size:13px;"><?php echo esc_html($meeting->full_transcript); ?></pre>
				</details>
			<?php endif; ?>
		</div>
		<?php
	}

	// ─── Summary Email ─────────────────────────────────────────────────

	private static function send_summary_email(int $meeting_id): void {
		$meeting = CBNexus_CircleUp_Repository::get_meeting($meeting_id);
		if (!$meeting) { return; }

		$items    = CBNexus_CircleUp_Repository::get_items($meeting_id);
		$approved = array_filter($items, fn($i) => $i->status === 'approved');

		$wins     = array_filter($approved, fn($i) => $i->item_type === 'win');
		$insights = array_filter($approved, fn($i) => $i->item_type === 'insight');
		$actions  = array_filter($approved, fn($i) => $i->item_type === 'action');

		$summary_text = $meeting->curated_summary ?: $meeting->ai_summary ?: '';
		if ($summary_text) {
			$summary_text = '<p style="font-size:15px;color:#333;line-height:1.6;">' . nl2br(esc_html(wp_trim_words($summary_text, 80))) . '</p>';
		}

		$members = CBNexus_Member_Repository::get_all_members('active');

		foreach ($members as $m) {
			$uid = (int) $m['user_id'];

			// Generate per-member token links (multi-use, 30-day).
			$view_token    = CBNexus_Token_Service::generate($uid, 'view_circleup', ['meeting_id' => $meeting_id], 30, true);
			$forward_token = CBNexus_Token_Service::generate($uid, 'forward_circleup', ['meeting_id' => $meeting_id], 30, true);
			$share_token   = CBNexus_Token_Service::generate($uid, 'quick_share', [], 30, true);

			// Build action items block for items assigned to this member.
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
			], ['recipient_id' => $uid, 'related_id' => $meeting_id, 'related_type' => 'circleup_summary']);
		}
	}
}
