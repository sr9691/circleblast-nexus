<?php
/**
 * Portal Dashboard
 *
 * ITER-0015 / UX Refresh: Personal member dashboard with live engagement
 * metrics. Plum & gold themed, matching demo layout: greeting row,
 * 5-stat grid, action-required card with gold highlight, two-column
 * upcoming + action items, recent meeting history with note status pills.
 */

defined('ABSPATH') || exit;

final class CBNexus_Portal_Dashboard {

	/**
	 * Render the personal dashboard (called by portal router).
	 */
	public static function render(array $profile): void {
		$uid  = (int) $profile['user_id'];
		$name = $profile['first_name'] ?: $profile['display_name'];

		$stats = self::compute_stats($uid);
		$pending = CBNexus_Meeting_Repository::get_pending_for_member($uid);
		$needs_notes = CBNexus_Meeting_Repository::get_needs_notes($uid);
		$upcoming = self::get_upcoming($uid);
		$recent_history = self::get_recent_history($uid, 5);
		$actions = CBNexus_CircleUp_Repository::get_member_actions($uid);
		$portal_url = CBNexus_Portal_Router::get_portal_url();
		?>
		<div class="cbnexus-dashboard" id="cbnexus-dashboard">

			<!-- Greeting -->
			<div style="margin-bottom:20px;">
				<h2 style="margin:0 0 2px;font-size:24px;font-weight:700;letter-spacing:-0.5px;">
					<?php printf(esc_html__('Good afternoon, %s 👋', 'circleblast-nexus'), esc_html($name)); ?>
				</h2>
				<p class="cbnexus-text-muted" style="margin:0;font-size:14px;"><?php esc_html_e("Here's what's happening in your circle", 'circleblast-nexus'); ?></p>
			</div>

			<!-- Quick Stats -->
			<div class="cbnexus-quick-stats">
				<div class="cbnexus-stat-card">
					<span class="cbnexus-stat-value"><?php echo esc_html($stats['meetings_completed']); ?></span>
					<span class="cbnexus-stat-label"><?php esc_html_e('Meetings', 'circleblast-nexus'); ?></span>
				</div>
				<div class="cbnexus-stat-card">
					<span class="cbnexus-stat-value"><?php echo esc_html($stats['unique_members']); ?><span style="font-size:14px;color:var(--cb-text-ter);">/ <?php echo esc_html($stats['total_members']); ?></span></span>
					<span class="cbnexus-stat-label"><?php esc_html_e('Met', 'circleblast-nexus'); ?></span>
				</div>
				<div class="cbnexus-stat-card cbnexus-stat-card--accent">
					<span class="cbnexus-stat-value"><?php echo esc_html($stats['circleup_attended']); ?></span>
					<span class="cbnexus-stat-label"><?php esc_html_e('CircleUps', 'circleblast-nexus'); ?></span>
				</div>
				<div class="cbnexus-stat-card">
					<span class="cbnexus-stat-value"><?php echo esc_html($stats['notes_rate']); ?>%</span>
					<span class="cbnexus-stat-label"><?php esc_html_e('Notes', 'circleblast-nexus'); ?></span>
				</div>
				<div class="cbnexus-stat-card cbnexus-stat-card--gold">
					<span class="cbnexus-stat-value"><?php echo esc_html($stats['contributions']); ?></span>
					<span class="cbnexus-stat-label"><?php esc_html_e('Contributions', 'circleblast-nexus'); ?></span>
				</div>
			</div>

			<?php if (!empty($pending) || !empty($needs_notes)) : ?>
			<!-- Needs Your Attention -->
			<div class="cbnexus-card cbnexus-card-highlight" style="padding:16px 20px;">
				<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
					<span style="font-size:16px;">⚡</span>
					<span style="font-size:15px;font-weight:600;"><?php esc_html_e('Needs your attention', 'circleblast-nexus'); ?></span>
				</div>
				<?php if (!empty($pending)) :
					foreach ($pending as $m) :
						$other = CBNexus_Member_Repository::get_profile(CBNexus_Meeting_Repository::get_other_member($m, $uid));
						if (!$other) { continue; }
				?>
					<div class="cbnexus-dash-alert">
						<span class="cbnexus-dash-alert-dot cbnexus-dash-alert-dot--gold"></span>
						<span class="cbnexus-dash-alert-text"><?php printf(esc_html__('%s wants to connect 1:1', 'circleblast-nexus'), esc_html($other['display_name'])); ?></span>
						<a href="<?php echo esc_url(add_query_arg('section', 'meetings', $portal_url)); ?>" class="cbnexus-btn cbnexus-btn-primary cbnexus-btn-sm"><?php esc_html_e('Respond', 'circleblast-nexus'); ?></a>
					</div>
				<?php endforeach; endif; ?>
				<?php if (!empty($needs_notes)) :
					foreach ($needs_notes as $m) :
						$other = CBNexus_Member_Repository::get_profile(CBNexus_Meeting_Repository::get_other_member($m, $uid));
						if (!$other) { continue; }
				?>
					<div class="cbnexus-dash-alert">
						<span class="cbnexus-dash-alert-dot cbnexus-dash-alert-dot--accent"></span>
						<span class="cbnexus-dash-alert-text"><?php printf(esc_html__('Meeting with %s needs notes', 'circleblast-nexus'), esc_html($other['display_name'])); ?></span>
						<a href="<?php echo esc_url(add_query_arg('section', 'meetings', $portal_url)); ?>" class="cbnexus-btn cbnexus-btn-outline cbnexus-btn-sm"><?php esc_html_e('Add Notes', 'circleblast-nexus'); ?></a>
					</div>
				<?php endforeach; endif; ?>
			</div>
			<?php endif; ?>

			<!-- Two-column: Coming Up + Action Items -->
			<div class="cbnexus-dash-cols">
				<div class="cbnexus-card">
					<h3><?php esc_html_e('Coming Up', 'circleblast-nexus'); ?></h3>
					<?php if (empty($upcoming)) : ?>
						<p class="cbnexus-text-muted"><?php esc_html_e('No upcoming meetings.', 'circleblast-nexus'); ?> <a href="<?php echo esc_url(add_query_arg('section', 'directory', $portal_url)); ?>" class="cbnexus-link"><?php esc_html_e('Browse the directory', 'circleblast-nexus'); ?></a></p>
					<?php else : foreach ($upcoming as $m) :
						$other = CBNexus_Member_Repository::get_profile(CBNexus_Meeting_Repository::get_other_member($m, $uid));
						if (!$other) { continue; }
						$pill_class = $m->status === 'scheduled' ? 'cbnexus-pill--blue' : 'cbnexus-pill--green';
					?>
						<div class="cbnexus-row">
							<strong style="flex:1;"><?php echo esc_html($other['display_name']); ?></strong>
							<?php if ($m->scheduled_at) : ?>
								<span class="cbnexus-text-muted"><?php echo esc_html(date_i18n('M j · g:i A', strtotime($m->scheduled_at))); ?></span>
							<?php endif; ?>
							<span class="cbnexus-pill <?php echo esc_attr($pill_class); ?>"><?php echo esc_html(ucfirst($m->status)); ?></span>
						</div>
					<?php endforeach; endif; ?>
				</div>

				<div class="cbnexus-card">
					<h3><?php esc_html_e('My Action Items', 'circleblast-nexus'); ?></h3>
					<?php if (empty($actions)) : ?>
						<p class="cbnexus-text-muted"><?php esc_html_e('No action items assigned to you.', 'circleblast-nexus'); ?></p>
					<?php else : foreach (array_slice($actions, 0, 5) as $a) : ?>
						<div class="cbnexus-dash-action-row">
							<div style="font-weight:500;"><?php echo esc_html(wp_trim_words($a->content, 12)); ?></div>
							<?php if ($a->due_date) : ?><span class="cbnexus-text-muted"><?php printf(esc_html__('Due %s', 'circleblast-nexus'), esc_html($a->due_date)); ?></span><?php endif; ?>
						</div>
					<?php endforeach; endif; ?>
				</div>
			</div>

			<!-- Recent Meetings -->
			<div class="cbnexus-card">
				<h3><?php esc_html_e('Recent Meetings', 'circleblast-nexus'); ?></h3>
				<?php if (empty($recent_history)) : ?>
					<p class="cbnexus-text-muted"><?php esc_html_e('No completed meetings yet.', 'circleblast-nexus'); ?></p>
				<?php else : ?>
					<?php foreach ($recent_history as $m) :
						$other = CBNexus_Member_Repository::get_profile(CBNexus_Meeting_Repository::get_other_member($m, $uid));
						if (!$other) { continue; }
						$has_notes = CBNexus_Meeting_Repository::has_notes((int) $m->id, $uid);
					?>
						<div class="cbnexus-row">
							<strong style="flex:1;"><?php echo esc_html($other['display_name']); ?></strong>
							<span class="cbnexus-text-muted"><?php echo esc_html(date_i18n('M j', strtotime($m->completed_at ?: $m->created_at))); ?></span>
							<?php if ($has_notes) : ?>
								<span class="cbnexus-pill cbnexus-pill--green-soft">✓ <?php esc_html_e('Notes', 'circleblast-nexus'); ?></span>
							<?php else : ?>
								<span class="cbnexus-pill cbnexus-pill--gold-soft"><?php esc_html_e('Pending', 'circleblast-nexus'); ?></span>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
					<div style="margin-top:8px;">
						<a href="<?php echo esc_url(add_query_arg('section', 'meetings', $portal_url)); ?>" class="cbnexus-link"><?php esc_html_e('View all meetings →', 'circleblast-nexus'); ?></a>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	// ─── Stats Computation ─────────────────────────────────────────────

	private static function compute_stats(int $uid): array {
		global $wpdb;

		$completed = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}cb_meetings
			 WHERE (member_a_id = %d OR member_b_id = %d) AND status IN ('completed', 'closed')",
			$uid, $uid
		));

		$unique = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(DISTINCT CASE WHEN member_a_id = %d THEN member_b_id ELSE member_a_id END)
			 FROM {$wpdb->prefix}cb_meetings
			 WHERE (member_a_id = %d OR member_b_id = %d) AND status IN ('completed', 'closed')",
			$uid, $uid, $uid
		));

		$total_members = count(CBNexus_Member_Repository::get_all_members('active'));

		$notes_submitted = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}cb_meeting_notes WHERE author_id = %d", $uid
		));
		$notes_rate = $completed > 0 ? min(100, round($notes_submitted / $completed * 100)) : 0;

		$circleup = CBNexus_CircleUp_Repository::get_attendance_count($uid);

		$contributions = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}cb_circleup_items
			 WHERE speaker_id = %d AND item_type IN ('win', 'insight') AND status = 'approved'",
			$uid
		));

		return [
			'meetings_completed' => $completed,
			'unique_members'     => $unique,
			'total_members'      => max(1, $total_members - 1),
			'circleup_attended'  => $circleup,
			'notes_rate'         => $notes_rate,
			'contributions'      => $contributions,
		];
	}

	private static function get_upcoming(int $uid): array {
		return CBNexus_Meeting_Repository::get_for_member($uid, '', 10);
	}

	private static function get_recent_history(int $uid, int $limit): array {
		global $wpdb;
		return $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}cb_meetings
			 WHERE (member_a_id = %d OR member_b_id = %d) AND status IN ('completed', 'closed')
			 ORDER BY COALESCE(completed_at, updated_at) DESC LIMIT %d",
			$uid, $uid, $limit
		)) ?: [];
	}
}
