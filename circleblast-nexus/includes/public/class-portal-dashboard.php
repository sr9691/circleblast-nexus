<?php
/**
 * Portal Dashboard
 *
 * ITER-0015: Personal member dashboard with live engagement metrics.
 * Replaces the placeholder from ITER-0006 with real data drawn from
 * meetings, CircleUp, and member profile systems.
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

			<!-- Welcome + Quick Stats -->
			<div class="cbnexus-card">
				<h2><?php printf(esc_html__('Welcome back, %s!', 'circleblast-nexus'), esc_html($name)); ?></h2>
				<div class="cbnexus-quick-stats">
					<div class="cbnexus-stat-card">
						<span class="cbnexus-stat-value"><?php echo esc_html($stats['meetings_completed']); ?></span>
						<span class="cbnexus-stat-label"><?php esc_html_e('1:1 Meetings', 'circleblast-nexus'); ?></span>
					</div>
					<div class="cbnexus-stat-card">
						<span class="cbnexus-stat-value"><?php echo esc_html($stats['unique_members']); ?><span style="font-size:14px;color:#718096;">/<?php echo esc_html($stats['total_members']); ?></span></span>
						<span class="cbnexus-stat-label"><?php esc_html_e('Members Met', 'circleblast-nexus'); ?></span>
					</div>
					<div class="cbnexus-stat-card">
						<span class="cbnexus-stat-value"><?php echo esc_html($stats['circleup_attended']); ?></span>
						<span class="cbnexus-stat-label"><?php esc_html_e('CircleUp Attended', 'circleblast-nexus'); ?></span>
					</div>
					<div class="cbnexus-stat-card">
						<span class="cbnexus-stat-value"><?php echo esc_html($stats['notes_rate']); ?>%</span>
						<span class="cbnexus-stat-label"><?php esc_html_e('Notes Completion', 'circleblast-nexus'); ?></span>
					</div>
					<div class="cbnexus-stat-card">
						<span class="cbnexus-stat-value"><?php echo esc_html($stats['contributions']); ?></span>
						<span class="cbnexus-stat-label"><?php esc_html_e('Wins & Insights', 'circleblast-nexus'); ?></span>
					</div>
				</div>
			</div>

			<?php if (!empty($pending) || !empty($needs_notes)) : ?>
			<!-- Action Required -->
			<div class="cbnexus-card cbnexus-card-highlight">
				<h3><?php esc_html_e('Action Required', 'circleblast-nexus'); ?></h3>
				<?php if (!empty($pending)) : ?>
					<div class="cbnexus-dash-alert">
						<span class="dashicons dashicons-warning" style="color:#ecc94b;"></span>
						<?php printf(esc_html(_n('%d meeting request awaiting your response', '%d meeting requests awaiting your response', count($pending), 'circleblast-nexus')), count($pending)); ?>
						<a href="<?php echo esc_url(add_query_arg('section', 'meetings', $portal_url)); ?>" class="cbnexus-btn cbnexus-btn-sm cbnexus-btn-primary" style="margin-left:auto;"><?php esc_html_e('Respond', 'circleblast-nexus'); ?></a>
					</div>
				<?php endif; ?>
				<?php if (!empty($needs_notes)) : ?>
					<div class="cbnexus-dash-alert">
						<span class="dashicons dashicons-edit" style="color:#9f7aea;"></span>
						<?php printf(esc_html(_n('%d meeting needs your notes', '%d meetings need your notes', count($needs_notes), 'circleblast-nexus')), count($needs_notes)); ?>
						<a href="<?php echo esc_url(add_query_arg('section', 'meetings', $portal_url)); ?>" class="cbnexus-btn cbnexus-btn-sm cbnexus-btn-outline-dark" style="margin-left:auto;"><?php esc_html_e('Submit Notes', 'circleblast-nexus'); ?></a>
					</div>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<!-- Two-column: Upcoming + Actions -->
			<div class="cbnexus-dash-cols">
				<div class="cbnexus-card">
					<h3><?php esc_html_e('Upcoming Meetings', 'circleblast-nexus'); ?></h3>
					<?php if (empty($upcoming)) : ?>
						<p class="cbnexus-text-muted"><?php esc_html_e('No upcoming meetings.', 'circleblast-nexus'); ?> <a href="<?php echo esc_url(add_query_arg('section', 'directory', $portal_url)); ?>"><?php esc_html_e('Browse the directory', 'circleblast-nexus'); ?></a></p>
					<?php else : foreach ($upcoming as $m) :
						$other = CBNexus_Member_Repository::get_profile(CBNexus_Meeting_Repository::get_other_member($m, $uid));
						if (!$other) { continue; }
					?>
						<div class="cbnexus-dash-meeting-row">
							<strong><?php echo esc_html($other['display_name']); ?></strong>
							<?php if ($m->scheduled_at) : ?><span class="cbnexus-text-muted"><?php echo esc_html(date_i18n('M j, g:i A', strtotime($m->scheduled_at))); ?></span><?php endif; ?>
							<span class="cbnexus-status-pill" style="background:<?php echo $m->status === 'scheduled' ? '#4299e1' : '#48bb78'; ?>"><?php echo esc_html(ucfirst($m->status)); ?></span>
						</div>
					<?php endforeach; endif; ?>
				</div>

				<div class="cbnexus-card">
					<h3><?php esc_html_e('My Action Items', 'circleblast-nexus'); ?></h3>
					<?php if (empty($actions)) : ?>
						<p class="cbnexus-text-muted"><?php esc_html_e('No action items assigned to you.', 'circleblast-nexus'); ?></p>
					<?php else : foreach (array_slice($actions, 0, 5) as $a) : ?>
						<div class="cbnexus-dash-action-row">
							<span><?php echo esc_html(wp_trim_words($a->content, 12)); ?></span>
							<?php if ($a->due_date) : ?><span class="cbnexus-text-muted"><?php esc_html_e('Due:', 'circleblast-nexus'); ?> <?php echo esc_html($a->due_date); ?></span><?php endif; ?>
						</div>
					<?php endforeach;
						if (count($actions) > 5) : ?>
							<a href="<?php echo esc_url(add_query_arg(['section' => 'circleup', 'circleup_view' => 'actions'], $portal_url)); ?>" class="cbnexus-text-muted"><?php printf(esc_html__('+ %d more', 'circleblast-nexus'), count($actions) - 5); ?></a>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</div>

			<!-- Recent Meeting History -->
			<div class="cbnexus-card">
				<h3><?php esc_html_e('Recent Meeting History', 'circleblast-nexus'); ?></h3>
				<?php if (empty($recent_history)) : ?>
					<p class="cbnexus-text-muted"><?php esc_html_e('No completed meetings yet.', 'circleblast-nexus'); ?></p>
				<?php else : ?>
					<?php foreach ($recent_history as $m) :
						$other = CBNexus_Member_Repository::get_profile(CBNexus_Meeting_Repository::get_other_member($m, $uid));
						if (!$other) { continue; }
						$has_notes = CBNexus_Meeting_Repository::has_notes((int) $m->id, $uid);
					?>
						<div class="cbnexus-dash-meeting-row">
							<strong><?php echo esc_html($other['display_name']); ?></strong>
							<span class="cbnexus-text-muted"><?php echo esc_html(date_i18n('M j, Y', strtotime($m->completed_at ?: $m->created_at))); ?></span>
							<?php if ($has_notes) : ?>
								<span class="cbnexus-text-muted" style="color:#48bb78;">✓ <?php esc_html_e('Notes', 'circleblast-nexus'); ?></span>
							<?php else : ?>
								<span class="cbnexus-text-muted" style="color:#ecc94b;"><?php esc_html_e('Notes pending', 'circleblast-nexus'); ?></span>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
					<a href="<?php echo esc_url(add_query_arg('section', 'meetings', $portal_url)); ?>" class="cbnexus-text-muted"><?php esc_html_e('View all meetings →', 'circleblast-nexus'); ?></a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	// ─── Stats Computation ─────────────────────────────────────────────

	private static function compute_stats(int $uid): array {
		global $wpdb;

		// Meetings completed/closed.
		$completed = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}cb_meetings
			 WHERE (member_a_id = %d OR member_b_id = %d) AND status IN ('completed', 'closed')",
			$uid, $uid
		));

		// Unique members met.
		$unique = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(DISTINCT CASE WHEN member_a_id = %d THEN member_b_id ELSE member_a_id END)
			 FROM {$wpdb->prefix}cb_meetings
			 WHERE (member_a_id = %d OR member_b_id = %d) AND status IN ('completed', 'closed')",
			$uid, $uid, $uid
		));

		$total_members = count(CBNexus_Member_Repository::get_all_members('active'));

		// Notes completion rate.
		$notes_submitted = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}cb_meeting_notes WHERE author_id = %d", $uid
		));
		$notes_rate = $completed > 0 ? min(100, round($notes_submitted / $completed * 100)) : 0;

		// CircleUp attendance.
		$circleup = CBNexus_CircleUp_Repository::get_attendance_count($uid);

		// Wins/insights contributed (items where user is speaker).
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
