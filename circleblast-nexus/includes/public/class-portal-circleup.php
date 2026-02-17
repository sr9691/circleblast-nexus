<?php
/**
 * Portal CircleUp Archive
 *
 * ITER-0014: Member-facing archive of published CircleUp meetings.
 * Includes timeline view, individual meeting pages with expandable
 * sections, search across transcripts/items, quick submission form,
 * and personal action item tracker.
 */

defined('ABSPATH') || exit;

final class CBNexus_Portal_CircleUp {

	public static function init(): void {
		add_action('wp_ajax_cbnexus_circleup_search', [__CLASS__, 'ajax_search']);
		add_action('wp_ajax_cbnexus_circleup_submit', [__CLASS__, 'ajax_submit']);
		add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
	}

	public static function enqueue_scripts(): void {
		global $post;
		if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'cbnexus_portal')) { return; }
		wp_enqueue_script('cbnexus-circleup', CBNEXUS_PLUGIN_URL . 'assets/js/circleup.js', [], CBNEXUS_VERSION, true);
		wp_localize_script('cbnexus-circleup', 'cbnexusCU', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce'    => wp_create_nonce('cbnexus_circleup'),
		]);
	}

	// ─── Render ────────────────────────────────────────────────────────

	public static function render(array $profile): void {
		// Individual meeting view.
		if (isset($_GET['circleup_id']) && absint($_GET['circleup_id']) > 0) {
			self::render_meeting_detail(absint($_GET['circleup_id']), $profile);
			return;
		}
		// Action items view.
		if (isset($_GET['circleup_view']) && $_GET['circleup_view'] === 'actions') {
			self::render_action_items($profile);
			return;
		}

		$meetings = CBNexus_CircleUp_Repository::get_meetings('published', 50);
		$portal_url = CBNexus_Portal_Router::get_portal_url();
		?>
		<div class="cbnexus-circleup-archive" id="cbnexus-circleup">
			<!-- Controls -->
			<div class="cbnexus-dir-controls">
				<div class="cbnexus-dir-search">
					<input type="text" id="cbnexus-cu-search" placeholder="<?php esc_attr_e('Search wins, insights, and discussions...', 'circleblast-nexus'); ?>" />
				</div>
				<a href="<?php echo esc_url(add_query_arg(['section' => 'circleup', 'circleup_view' => 'actions'], $portal_url)); ?>" class="cbnexus-btn cbnexus-btn-sm cbnexus-btn-outline-dark"><?php esc_html_e('My Action Items', 'circleblast-nexus'); ?></a>
			</div>

			<!-- Search Results (hidden initially) -->
			<div id="cbnexus-cu-results" style="display:none;"></div>

			<!-- Quick Submit -->
			<div class="cbnexus-card cbnexus-cu-submit-card">
				<h3><?php esc_html_e('Share a Win, Insight, or Opportunity', 'circleblast-nexus'); ?></h3>
				<form id="cbnexus-cu-submit-form">
					<div class="cbnexus-cu-submit-row">
						<select id="cbnexus-cu-type">
							<option value="win"><?php esc_html_e('Win', 'circleblast-nexus'); ?></option>
							<option value="insight"><?php esc_html_e('Insight', 'circleblast-nexus'); ?></option>
							<option value="opportunity"><?php esc_html_e('Opportunity', 'circleblast-nexus'); ?></option>
						</select>
						<input type="text" id="cbnexus-cu-content" placeholder="<?php esc_attr_e('What happened?', 'circleblast-nexus'); ?>" required />
						<button type="submit" class="cbnexus-btn cbnexus-btn-primary cbnexus-btn-sm"><?php esc_html_e('Submit', 'circleblast-nexus'); ?></button>
					</div>
					<div id="cbnexus-cu-submit-msg" style="display:none;"></div>
				</form>
			</div>

			<!-- Timeline -->
			<div class="cbnexus-cu-timeline">
				<?php if (empty($meetings)) : ?>
					<div class="cbnexus-card"><p class="cbnexus-text-muted"><?php esc_html_e('No published CircleUp meetings yet.', 'circleblast-nexus'); ?></p></div>
				<?php else : foreach ($meetings as $m) :
					$items = CBNexus_CircleUp_Repository::get_items((int) $m->id);
					$approved = array_filter($items, fn($i) => $i->status === 'approved');
					$wins = count(array_filter($approved, fn($i) => $i->item_type === 'win'));
					$insights = count(array_filter($approved, fn($i) => $i->item_type === 'insight'));
					$detail_url = add_query_arg(['section' => 'circleup', 'circleup_id' => $m->id], $portal_url);
				?>
					<div class="cbnexus-cu-timeline-card">
						<div class="cbnexus-cu-timeline-date"><?php echo esc_html(date_i18n('M j, Y', strtotime($m->meeting_date))); ?></div>
						<div class="cbnexus-card">
							<h3><a href="<?php echo esc_url($detail_url); ?>"><?php echo esc_html($m->title); ?></a></h3>
							<?php if ($m->curated_summary) : ?>
								<p class="cbnexus-cu-summary"><?php echo esc_html(wp_trim_words($m->curated_summary, 40)); ?></p>
							<?php endif; ?>
							<div class="cbnexus-cu-stats-row">
								<?php if ($wins) : ?><span class="cbnexus-cu-stat"><strong><?php echo $wins; ?></strong> <?php esc_html_e('wins', 'circleblast-nexus'); ?></span><?php endif; ?>
								<?php if ($insights) : ?><span class="cbnexus-cu-stat"><strong><?php echo $insights; ?></strong> <?php esc_html_e('insights', 'circleblast-nexus'); ?></span><?php endif; ?>
								<?php if ($m->duration_minutes) : ?><span class="cbnexus-cu-stat"><?php echo esc_html($m->duration_minutes); ?> <?php esc_html_e('min', 'circleblast-nexus'); ?></span><?php endif; ?>
							</div>
							<a href="<?php echo esc_url($detail_url); ?>" class="cbnexus-btn cbnexus-btn-sm cbnexus-btn-outline-dark"><?php esc_html_e('View Details', 'circleblast-nexus'); ?></a>
						</div>
					</div>
				<?php endforeach; endif; ?>
			</div>
		</div>
		<?php
	}

	private static function render_meeting_detail(int $id, array $profile): void {
		$meeting = CBNexus_CircleUp_Repository::get_meeting($id);
		if (!$meeting || $meeting->status !== 'published') {
			echo '<div class="cbnexus-card"><p>' . esc_html__('Meeting not found.', 'circleblast-nexus') . '</p></div>';
			return;
		}

		$portal_url = CBNexus_Portal_Router::get_portal_url();
		$back_url   = add_query_arg('section', 'circleup', $portal_url);
		$items      = CBNexus_CircleUp_Repository::get_items($id);
		$approved   = array_filter($items, fn($i) => $i->status === 'approved');
		$attendees  = CBNexus_CircleUp_Repository::get_attendees($id);
		$types = ['win' => __('Wins', 'circleblast-nexus'), 'insight' => __('Insights', 'circleblast-nexus'), 'opportunity' => __('Opportunities', 'circleblast-nexus'), 'action' => __('Action Items', 'circleblast-nexus')];
		$type_icons = ['win' => '🏆', 'insight' => '💡', 'opportunity' => '🤝', 'action' => '✅'];
		?>
		<div class="cbnexus-cu-detail">
			<a href="<?php echo esc_url($back_url); ?>" class="cbnexus-back-link">&larr; <?php esc_html_e('Back to Archive', 'circleblast-nexus'); ?></a>

			<div class="cbnexus-card">
				<h2><?php echo esc_html($meeting->title); ?></h2>
				<p class="cbnexus-text-muted"><?php echo esc_html(date_i18n('F j, Y', strtotime($meeting->meeting_date))); ?>
					<?php if ($meeting->duration_minutes) : ?> · <?php echo esc_html($meeting->duration_minutes); ?> <?php esc_html_e('minutes', 'circleblast-nexus'); ?><?php endif; ?>
				</p>
				<?php if (!empty($attendees)) : ?>
					<p class="cbnexus-cu-attendees"><?php esc_html_e('Attendees:', 'circleblast-nexus'); ?> <?php echo esc_html(implode(', ', array_map(fn($a) => $a->display_name, $attendees))); ?></p>
				<?php endif; ?>
			</div>

			<?php if ($meeting->curated_summary) : ?>
				<div class="cbnexus-card"><h3><?php esc_html_e('Summary', 'circleblast-nexus'); ?></h3><p><?php echo nl2br(esc_html($meeting->curated_summary)); ?></p></div>
			<?php endif; ?>

			<?php foreach ($types as $type => $label) :
				$typed = array_filter($approved, fn($i) => $i->item_type === $type);
				if (empty($typed)) { continue; }
			?>
				<div class="cbnexus-card">
					<h3><?php echo esc_html($type_icons[$type] . ' ' . $label); ?> (<?php echo count($typed); ?>)</h3>
					<ul class="cbnexus-cu-items-list">
						<?php foreach ($typed as $item) : ?>
							<li>
								<?php echo esc_html($item->content); ?>
								<?php if ($item->speaker_name) : ?><span class="cbnexus-text-muted"> — <?php echo esc_html($item->speaker_name); ?></span><?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private static function render_action_items(array $profile): void {
		$portal_url = CBNexus_Portal_Router::get_portal_url();
		$back_url   = add_query_arg('section', 'circleup', $portal_url);
		$actions    = CBNexus_CircleUp_Repository::get_member_actions($profile['user_id']);
		?>
		<div class="cbnexus-cu-actions">
			<a href="<?php echo esc_url($back_url); ?>" class="cbnexus-back-link">&larr; <?php esc_html_e('Back to Archive', 'circleblast-nexus'); ?></a>
			<div class="cbnexus-card">
				<h2><?php esc_html_e('My Action Items', 'circleblast-nexus'); ?></h2>
				<?php if (empty($actions)) : ?>
					<p class="cbnexus-text-muted"><?php esc_html_e('No action items assigned to you.', 'circleblast-nexus'); ?></p>
				<?php else : ?>
					<table class="cbnexus-cu-actions-table">
						<thead><tr><th><?php esc_html_e('Action', 'circleblast-nexus'); ?></th><th><?php esc_html_e('From', 'circleblast-nexus'); ?></th><th><?php esc_html_e('Due', 'circleblast-nexus'); ?></th><th><?php esc_html_e('Status', 'circleblast-nexus'); ?></th></tr></thead>
						<tbody>
						<?php foreach ($actions as $a) : ?>
							<tr>
								<td><?php echo esc_html($a->content); ?></td>
								<td><?php echo esc_html($a->meeting_title); ?> (<?php echo esc_html($a->meeting_date); ?>)</td>
								<td><?php echo $a->due_date ? esc_html($a->due_date) : '—'; ?></td>
								<td><?php echo esc_html(ucfirst($a->status)); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	// ─── AJAX ──────────────────────────────────────────────────────────

	public static function ajax_search(): void {
		check_ajax_referer('cbnexus_circleup', 'nonce');
		if (!is_user_logged_in() || !CBNexus_Member_Repository::is_member(get_current_user_id())) {
			wp_send_json_error('Access denied.', 403);
		}

		$query = sanitize_text_field(wp_unslash($_POST['query'] ?? ''));
		if (strlen($query) < 2) { wp_send_json_success(['html' => '']); }

		global $wpdb;
		$like = '%' . $wpdb->esc_like($query) . '%';

		// Search items.
		$items = $wpdb->get_results($wpdb->prepare(
			"SELECT i.*, m.title as meeting_title, m.meeting_date, u.display_name as speaker_name
			 FROM {$wpdb->prefix}cb_circleup_items i
			 JOIN {$wpdb->prefix}cb_circleup_meetings m ON i.circleup_meeting_id = m.id
			 LEFT JOIN {$wpdb->users} u ON i.speaker_id = u.ID
			 WHERE m.status = 'published' AND i.status = 'approved'
			 AND i.content LIKE %s
			 ORDER BY m.meeting_date DESC LIMIT 20",
			$like
		));

		if (empty($items)) {
			wp_send_json_success(['html' => '<p class="cbnexus-text-muted">' . esc_html__('No results found.', 'circleblast-nexus') . '</p>']);
		}

		$html = '<div class="cbnexus-card"><h3>' . sprintf(esc_html__('Search Results (%d)', 'circleblast-nexus'), count($items)) . '</h3><ul class="cbnexus-cu-items-list">';
		$type_icons = ['win' => '🏆', 'insight' => '💡', 'opportunity' => '🤝', 'action' => '✅'];
		foreach ($items as $item) {
			$icon = $type_icons[$item->item_type] ?? '';
			$html .= '<li>' . esc_html($icon . ' ' . $item->content);
			$html .= ' <span class="cbnexus-text-muted">— ' . esc_html($item->meeting_title) . ' (' . esc_html($item->meeting_date) . ')</span>';
			if ($item->speaker_name) { $html .= ' <span class="cbnexus-text-muted">[' . esc_html($item->speaker_name) . ']</span>'; }
			$html .= '</li>';
		}
		$html .= '</ul></div>';

		wp_send_json_success(['html' => $html]);
	}

	public static function ajax_submit(): void {
		check_ajax_referer('cbnexus_circleup', 'nonce');
		$uid = get_current_user_id();
		if (!$uid || !CBNexus_Member_Repository::is_member($uid)) {
			wp_send_json_error('Access denied.', 403);
		}

		$type    = sanitize_key($_POST['item_type'] ?? 'win');
		$content = sanitize_textarea_field(wp_unslash($_POST['content'] ?? ''));
		if (empty($content)) { wp_send_json_error(['errors' => ['Content is required.']]); }
		if (!in_array($type, ['win', 'insight', 'opportunity'], true)) { $type = 'win'; }

		// Find most recent published meeting, or create a standalone bucket.
		$meetings = CBNexus_CircleUp_Repository::get_meetings('published', 1);
		$meeting_id = !empty($meetings) ? (int) $meetings[0]->id : 0;

		if (!$meeting_id) {
			wp_send_json_error(['errors' => ['No published CircleUp meeting to attach this to.']]);
		}

		CBNexus_CircleUp_Repository::insert_items($meeting_id, [[
			'item_type'  => $type,
			'content'    => $content,
			'speaker_id' => $uid,
			'status'     => 'approved',
		]]);

		wp_send_json_success(['message' => __('Submitted! Thank you.', 'circleblast-nexus')]);
	}
}
