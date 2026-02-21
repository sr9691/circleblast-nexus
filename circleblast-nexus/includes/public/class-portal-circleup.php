<?php
/**
 * Portal CircleUp Archive
 *
 * ITER-0014 / UX Refresh: Member-facing archive matching demo.
 * Quick Share form with emoji type selector, timeline cards with
 * gold dots, pill badges for win/insight counts, back-link navigation.
 *
 * v1.2.0 – Page header, collapsible Quick Share, timeline connector,
 *           attendee count on cards, grammar-safe pill labels.
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

	public static function render(array $profile): void {
		if (isset($_GET['circleup_id']) && absint($_GET['circleup_id']) > 0) {
			self::render_meeting_detail(absint($_GET['circleup_id']), $profile);
			return;
		}
		if (isset($_GET['circleup_view']) && $_GET['circleup_view'] === 'actions') {
			self::render_action_items($profile);
			return;
		}

		$meetings   = CBNexus_CircleUp_Repository::get_meetings('published', 50);
		$portal_url = CBNexus_Portal_Router::get_portal_url();
		$actions_url = add_query_arg(['section' => 'actions'], $portal_url);
		?>
		<div class="cbnexus-circleup-archive" id="cbnexus-circleup">

			<!-- Page header -->
			<div class="cbnexus-cu-header">
				<h2><?php esc_html_e('CircleUp Archive', 'circleblast-nexus'); ?></h2>
				<p class="cbnexus-text-muted"><?php esc_html_e('Wins, insights, and discussion highlights from every group meeting.', 'circleblast-nexus'); ?></p>
			</div>

			<!-- Search + Actions bar -->
			<div class="cbnexus-cu-toolbar">
				<div class="cbnexus-cu-search-bar">
					<span class="cbnexus-cu-search-icon">🔍</span>
					<input type="text" id="cbnexus-cu-search" placeholder="<?php esc_attr_e('Search wins, insights, discussions...', 'circleblast-nexus'); ?>" />
				</div>
				<a href="<?php echo esc_url($actions_url); ?>" class="cbnexus-btn cbnexus-btn-outline cbnexus-btn-sm">✅ <?php esc_html_e('My Actions', 'circleblast-nexus'); ?></a>
			</div>
			<div id="cbnexus-cu-results" style="display:none;"></div>

			<!-- Quick Share (collapsible) -->
			<div class="cbnexus-card cbnexus-cu-submit-card">
				<button type="button" class="cbnexus-cu-submit-toggle" id="cbnexus-cu-toggle" aria-expanded="false">
					<span class="cbnexus-cu-submit-toggle-text">
						<span style="font-size:15px;">💬</span>
						<span style="font-weight:600;font-size:14px;"><?php esc_html_e('Quick Share', 'circleblast-nexus'); ?></span>
						<span class="cbnexus-text-muted" style="font-size:13px;font-weight:400;"><?php esc_html_e('— Share a win, insight, or opportunity', 'circleblast-nexus'); ?></span>
					</span>
					<span class="cbnexus-cu-submit-chevron" aria-hidden="true">›</span>
				</button>
				<div class="cbnexus-cu-submit-body" id="cbnexus-cu-submit-body" hidden>
					<form id="cbnexus-cu-submit-form">
						<div class="cbnexus-cu-submit-type-row">
							<label class="cbnexus-cu-type-option">
								<input type="radio" name="cu_type" value="win" checked />
								<span class="cbnexus-cu-type-chip cbnexus-cu-type-chip--win">🏆 <?php esc_html_e('Win', 'circleblast-nexus'); ?></span>
							</label>
							<label class="cbnexus-cu-type-option">
								<input type="radio" name="cu_type" value="insight" />
								<span class="cbnexus-cu-type-chip cbnexus-cu-type-chip--insight">💡 <?php esc_html_e('Insight', 'circleblast-nexus'); ?></span>
							</label>
							<label class="cbnexus-cu-type-option">
								<input type="radio" name="cu_type" value="opportunity" />
								<span class="cbnexus-cu-type-chip cbnexus-cu-type-chip--opportunity">🤝 <?php esc_html_e('Opportunity', 'circleblast-nexus'); ?></span>
							</label>
						</div>
						<p class="cbnexus-cu-submit-hint" id="cbnexus-cu-hint"><?php esc_html_e('e.g. "Closed a deal from a referral" or "Got promoted"', 'circleblast-nexus'); ?></p>
						<textarea id="cbnexus-cu-content" rows="3" placeholder="<?php esc_attr_e('What happened?', 'circleblast-nexus'); ?>" required></textarea>
						<div class="cbnexus-cu-submit-footer">
							<div id="cbnexus-cu-submit-msg" style="display:none;"></div>
							<button type="submit" class="cbnexus-btn cbnexus-btn-primary cbnexus-btn-sm"><?php esc_html_e('Share', 'circleblast-nexus'); ?></button>
						</div>
					</form>
				</div>
			</div>

			<!-- Timeline -->
			<?php if (empty($meetings)) : ?>
				<div class="cbnexus-cu-empty">
					<div class="cbnexus-cu-empty-icon">📢</div>
					<h3><?php esc_html_e('No meetings yet', 'circleblast-nexus'); ?></h3>
					<p class="cbnexus-text-muted"><?php esc_html_e('Published CircleUp meeting summaries will appear here.', 'circleblast-nexus'); ?></p>
				</div>
			<?php else : ?>
				<div class="cbnexus-cu-timeline">
				<?php foreach ($meetings as $m) :
					$items    = CBNexus_CircleUp_Repository::get_items((int) $m->id);
					$approved = array_filter($items, fn($i) => $i->status === 'approved');
					$wins     = count(array_filter($approved, fn($i) => $i->item_type === 'win'));
					$insights = count(array_filter($approved, fn($i) => $i->item_type === 'insight'));
					$attendees = CBNexus_CircleUp_Repository::get_attendees((int) $m->id);
					$att_count = count($attendees);
					$detail_url = add_query_arg(['section' => 'circleup', 'circleup_id' => $m->id], $portal_url);
				?>
					<a href="<?php echo esc_url($detail_url); ?>" class="cbnexus-cu-timeline-card">
						<div class="cbnexus-cu-timeline-dot-col">
							<span class="cbnexus-cu-timeline-dot"></span>
							<span class="cbnexus-cu-timeline-line"></span>
						</div>
						<div class="cbnexus-cu-timeline-content">
							<span class="cbnexus-cu-timeline-date-text"><?php echo esc_html(date_i18n('M j, Y', strtotime($m->meeting_date))); ?></span>
							<h3 class="cbnexus-cu-timeline-title"><?php echo esc_html($m->title); ?></h3>
							<?php if ($m->curated_summary) : ?>
								<p class="cbnexus-cu-summary"><?php echo esc_html(wp_trim_words($m->curated_summary, 25)); ?></p>
							<?php endif; ?>
							<div class="cbnexus-cu-stats-row">
								<?php if ($wins) : ?>
									<span class="cbnexus-pill cbnexus-pill--gold-soft"><?php
										/* translators: %d: number of wins */
										echo esc_html(sprintf(_n('%d win', '%d wins', $wins, 'circleblast-nexus'), $wins));
									?></span>
								<?php endif; ?>
								<?php if ($insights) : ?>
									<span class="cbnexus-pill cbnexus-pill--accent-soft"><?php
										echo esc_html(sprintf(_n('%d insight', '%d insights', $insights, 'circleblast-nexus'), $insights));
									?></span>
								<?php endif; ?>
								<?php if ($att_count) : ?>
									<span class="cbnexus-pill cbnexus-pill--muted"><?php
										echo esc_html(sprintf(_n('%d attendee', '%d attendees', $att_count, 'circleblast-nexus'), $att_count));
									?></span>
								<?php endif; ?>
								<?php if ($m->duration_minutes) : ?>
									<span class="cbnexus-pill cbnexus-pill--muted"><?php echo esc_html($m->duration_minutes); ?> <?php esc_html_e('min', 'circleblast-nexus'); ?></span>
								<?php endif; ?>
							</div>
						</div>
					</a>
				<?php endforeach; ?>
				</div>
			<?php endif; ?>
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
		$types      = ['win' => __('Wins', 'circleblast-nexus'), 'insight' => __('Insights', 'circleblast-nexus'), 'opportunity' => __('Opportunities', 'circleblast-nexus'), 'action' => __('Action Items', 'circleblast-nexus')];
		$type_icons = ['win' => '🏆', 'insight' => '💡', 'opportunity' => '🤝', 'action' => '✅'];
		?>
		<div class="cbnexus-cu-detail">
			<a href="<?php echo esc_url($back_url); ?>" class="cbnexus-back-link">&larr; <?php esc_html_e('Back to Archive', 'circleblast-nexus'); ?></a>

			<div class="cbnexus-card">
				<h2 style="margin:0 0 4px;font-size:21px;letter-spacing:-0.3px;"><?php echo esc_html($meeting->title); ?></h2>
				<div class="cbnexus-cu-detail-meta">
					<span><?php echo esc_html(date_i18n('F j, Y', strtotime($meeting->meeting_date))); ?></span>
					<?php if ($meeting->duration_minutes) : ?><span>·</span><span><?php echo esc_html($meeting->duration_minutes); ?> <?php esc_html_e('minutes', 'circleblast-nexus'); ?></span><?php endif; ?>
					<?php if (!empty($attendees)) : ?><span>·</span><span><?php
						echo esc_html(sprintf(_n('%d attendee', '%d attendees', count($attendees), 'circleblast-nexus'), count($attendees)));
					?></span><?php endif; ?>
				</div>
				<?php if (!empty($attendees)) : ?>
					<div class="cbnexus-cu-attendee-pills">
						<?php foreach ($attendees as $a) : ?>
							<span class="cbnexus-pill cbnexus-pill--muted" style="font-size:12px;"><?php echo esc_html($a->display_name); ?></span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<?php if ($meeting->curated_summary) : ?>
				<div class="cbnexus-card">
					<h3><?php esc_html_e('Summary', 'circleblast-nexus'); ?></h3>
					<p style="margin:0;font-size:14px;color:var(--cb-text-sec);line-height:1.7;"><?php echo nl2br(esc_html($meeting->curated_summary)); ?></p>
				</div>
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
								<?php if (!empty($item->assigned_to_name)) : ?><span class="cbnexus-text-muted"> → <?php echo esc_html($item->assigned_to_name); ?><?php if ($item->due_date) : ?> (<?php esc_html_e('Due:', 'circleblast-nexus'); ?> <?php echo esc_html($item->due_date); ?>)<?php endif; ?></span><?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render My Actions as a standalone portal section (top-level nav tab).
	 */
	public static function render_actions_page(array $profile): void {
		$uid     = (int) $profile['user_id'];
		$actions = CBNexus_CircleUp_Repository::get_member_actions($uid);
		$open    = array_filter($actions, fn($a) => !in_array($a->status, ['done'], true));
		$done    = array_filter($actions, fn($a) => $a->status === 'done');

		// Debug: raw query to verify assigned_to values in DB.
		global $wpdb;
		$debug_rows = $wpdb->get_results($wpdb->prepare(
			"SELECT id, assigned_to, status, item_type FROM {$wpdb->prefix}cb_circleup_items WHERE item_type = 'action' AND assigned_to IS NOT NULL LIMIT 20",
		));
		echo '<!-- DEBUG My Actions: uid=' . esc_html($uid) . ' actions_found=' . count($actions) . ' -->';
		echo '<!-- DB action items with assigned_to: ';
		foreach ($debug_rows as $dr) {
			echo 'id=' . esc_html($dr->id) . ' assigned=' . esc_html($dr->assigned_to) . ' status=' . esc_html($dr->status) . ' | ';
		}
		echo ' -->';

		$status_pills = [
			'draft'       => ['label' => 'Draft',       'class' => 'cbnexus-status-gold'],
			'approved'    => ['label' => 'Open',        'class' => 'cbnexus-status-blue'],
			'pending'     => ['label' => 'Pending',     'class' => 'cbnexus-status-gold'],
			'in_progress' => ['label' => 'In Progress', 'class' => 'cbnexus-status-blue'],
			'done'        => ['label' => 'Done',        'class' => 'cbnexus-status-green'],
		];
		?>
		<div class="cbnexus-circleup-archive">
			<div class="cbnexus-cu-header">
				<h2><?php esc_html_e('My Action Items', 'circleblast-nexus'); ?></h2>
				<p class="cbnexus-text-muted"><?php printf(esc_html__('%d open · %d completed', 'circleblast-nexus'), count($open), count($done)); ?></p>
			</div>

			<?php if (empty($actions)) : ?>
				<div class="cbnexus-card">
					<p class="cbnexus-text-muted"><?php esc_html_e('No action items assigned to you.', 'circleblast-nexus'); ?></p>
				</div>
			<?php else : ?>

				<?php if (!empty($open)) : ?>
				<div class="cbnexus-card">
					<h3><?php esc_html_e('Open', 'circleblast-nexus'); ?></h3>
					<table class="cbnexus-cu-actions-table">
						<thead><tr><th><?php esc_html_e('Action', 'circleblast-nexus'); ?></th><th><?php esc_html_e('From', 'circleblast-nexus'); ?></th><th><?php esc_html_e('Due', 'circleblast-nexus'); ?></th><th><?php esc_html_e('Status', 'circleblast-nexus'); ?></th></tr></thead>
						<tbody>
						<?php foreach ($open as $a) :
							$pill = $status_pills[$a->status] ?? $status_pills['approved'];
						?>
							<tr>
								<td><?php echo esc_html($a->content); ?></td>
								<td class="cbnexus-text-muted"><?php echo esc_html($a->meeting_title); ?></td>
								<td><?php echo $a->due_date ? esc_html(date_i18n('M j', strtotime($a->due_date))) : '—'; ?></td>
								<td><span class="cbnexus-status-pill <?php echo esc_attr($pill['class']); ?>"><?php echo esc_html($pill['label']); ?></span></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endif; ?>

				<?php if (!empty($done)) : ?>
				<div class="cbnexus-card">
					<h3><?php esc_html_e('Completed', 'circleblast-nexus'); ?></h3>
					<table class="cbnexus-cu-actions-table">
						<thead><tr><th><?php esc_html_e('Action', 'circleblast-nexus'); ?></th><th><?php esc_html_e('From', 'circleblast-nexus'); ?></th><th><?php esc_html_e('Status', 'circleblast-nexus'); ?></th></tr></thead>
						<tbody>
						<?php foreach ($done as $a) : ?>
							<tr style="opacity:0.6;">
								<td><?php echo esc_html($a->content); ?></td>
								<td class="cbnexus-text-muted"><?php echo esc_html($a->meeting_title); ?></td>
								<td><span class="cbnexus-status-pill cbnexus-status-green"><?php esc_html_e('Done', 'circleblast-nexus'); ?></span></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endif; ?>

			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_action_items(array $profile): void {
		// Redirect to the dedicated actions tab.
		$portal_url = CBNexus_Portal_Router::get_portal_url();
		$actions_url = add_query_arg('section', 'actions', $portal_url);
		?>
		<script>window.location.href = '<?php echo esc_url($actions_url); ?>';</script>
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

		$type_icons = ['win' => '🏆', 'insight' => '💡', 'opportunity' => '🤝', 'action' => '✅'];
		$html = '<div class="cbnexus-card"><h3>' . sprintf(esc_html__('Search Results (%d)', 'circleblast-nexus'), count($items)) . '</h3><ul class="cbnexus-cu-items-list">';
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