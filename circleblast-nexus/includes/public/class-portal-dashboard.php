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

	/** Info tooltips for each personal stat card (pulled from Help system). */
	private static function stat_tooltips(): array {
		return CBNexus_Portal_Help::get_tooltips_for('dashboard');
	}

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
		$all_actions = CBNexus_CircleUp_Repository::get_member_actions($uid);
		$open_actions = array_filter($all_actions, fn($a) => $a->status !== 'done');
		$portal_url = CBNexus_Portal_Router::get_portal_url();
		$tips = CBNexus_Portal_Help::get_tooltips_for('dashboard');

		/* Upcoming events (next 3 approved events) */
		$upcoming_events = class_exists('CBNexus_Event_Repository') ? CBNexus_Event_Repository::get_upcoming(3) : [];

		/* Time-aware greeting */
		$hour = (int) current_time('G');
		if ($hour < 12) {
			$greeting = __('Good morning, %s ☀️', 'circleblast-nexus');
		} elseif ($hour < 17) {
			$greeting = __('Good afternoon, %s 👋', 'circleblast-nexus');
		} else {
			$greeting = __('Good evening, %s 🌙', 'circleblast-nexus');
		}
		?>
		<div class="cbnexus-dashboard" id="cbnexus-dashboard">

			<!-- Greeting -->
			<div style="margin-bottom:20px;">
				<h2 style="margin:0 0 2px;font-size:24px;font-weight:700;letter-spacing:-0.5px;">
					<?php printf(esc_html($greeting), esc_html($name)); ?>
				</h2>
				<p class="cbnexus-text-muted" style="margin:0;font-size:14px;"><?php esc_html_e("Here's what's happening in your circle", 'circleblast-nexus'); ?></p>
			</div>

			<!-- My Engagement -->
			<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
				<span style="font-size:16px;">📊</span>
				<h3 style="margin:0;font-size:15px;font-weight:600;"><?php esc_html_e('My Engagement', 'circleblast-nexus'); ?></h3>
			</div>
			<div class="cbnexus-quick-stats">
				<div class="cbnexus-stat-card">
					<button type="button" class="cbnexus-info-btn" aria-label="Info" data-tooltip="<?php echo esc_attr($tips['meetings']); ?>">ⓘ</button>
					<span class="cbnexus-stat-value"><?php echo esc_html($stats['meetings_completed']); ?></span>
					<span class="cbnexus-stat-label"><?php esc_html_e('Meetings', 'circleblast-nexus'); ?></span>
				</div>
				<div class="cbnexus-stat-card">
					<button type="button" class="cbnexus-info-btn" aria-label="Info" data-tooltip="<?php echo esc_attr($tips['met']); ?>">ⓘ</button>
					<span class="cbnexus-stat-value"><?php echo esc_html($stats['unique_members']); ?><span style="font-size:14px;color:var(--cb-text-ter);">/ <?php echo esc_html($stats['total_members']); ?></span></span>
					<span class="cbnexus-stat-label"><?php esc_html_e('Met', 'circleblast-nexus'); ?></span>
				</div>
				<div class="cbnexus-stat-card cbnexus-stat-card--accent">
					<button type="button" class="cbnexus-info-btn" aria-label="Info" data-tooltip="<?php echo esc_attr($tips['circleups']); ?>">ⓘ</button>
					<span class="cbnexus-stat-value"><?php echo esc_html($stats['circleup_attended']); ?></span>
					<span class="cbnexus-stat-label"><?php esc_html_e('CircleUps', 'circleblast-nexus'); ?></span>
				</div>
				<div class="cbnexus-stat-card">
					<button type="button" class="cbnexus-info-btn" aria-label="Info" data-tooltip="<?php echo esc_attr($tips['notes']); ?>">ⓘ</button>
					<span class="cbnexus-stat-value"><?php echo esc_html($stats['notes_rate']); ?>%</span>
					<span class="cbnexus-stat-label"><?php esc_html_e('Notes', 'circleblast-nexus'); ?></span>
				</div>
				<div class="cbnexus-stat-card cbnexus-stat-card--gold">
					<button type="button" class="cbnexus-info-btn" aria-label="Info" data-tooltip="<?php echo esc_attr($tips['contributions']); ?>">ⓘ</button>
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

			<!-- Upcoming Events -->
			<?php if (!empty($upcoming_events)) : ?>
			<div class="cbnexus-card">
				<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
					<h3 style="margin:0;"><?php esc_html_e('Upcoming Events', 'circleblast-nexus'); ?></h3>
					<a href="<?php echo esc_url(add_query_arg('section', 'events', $portal_url)); ?>" class="cbnexus-link" style="font-size:13px;"><?php esc_html_e('View all →', 'circleblast-nexus'); ?></a>
				</div>
				<?php foreach ($upcoming_events as $evt) : ?>
					<div class="cbnexus-row">
						<span style="font-size:15px;margin-right:4px;">📅</span>
						<strong style="flex:1;"><?php echo esc_html($evt->title); ?></strong>
						<span class="cbnexus-text-muted"><?php echo esc_html(date_i18n('M j', strtotime($evt->event_date))); ?><?php if (!empty($evt->event_time)) : ?> · <?php echo esc_html(date_i18n('g:i A', strtotime($evt->event_time))); ?><?php endif; ?></span>
						<?php if (!empty($evt->location)) : ?>
							<span class="cbnexus-pill cbnexus-pill--muted"><?php echo esc_html(wp_trim_words($evt->location, 3, '…')); ?></span>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
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
					<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
						<h3 style="margin:0;"><?php esc_html_e('My Action Items', 'circleblast-nexus'); ?></h3>
						<?php if (!empty($open_actions)) : ?>
							<a href="<?php echo esc_url(add_query_arg('section', 'actions', $portal_url)); ?>" class="cbnexus-link" style="font-size:13px;"><?php esc_html_e('View all →', 'circleblast-nexus'); ?></a>
						<?php endif; ?>
					</div>
					<?php if (empty($open_actions)) : ?>
						<p class="cbnexus-text-muted"><?php esc_html_e("No open action items. You're all caught up! ✨", 'circleblast-nexus'); ?></p>
					<?php else : foreach (array_slice($open_actions, 0, 5) as $a) :
						$is_overdue = $a->due_date && strtotime($a->due_date) < time();
					?>
						<div class="cbnexus-dash-action-row">
							<div style="font-weight:500;"><?php echo esc_html(wp_trim_words($a->content, 12)); ?></div>
							<?php if ($a->due_date) : ?>
								<span class="cbnexus-text-muted<?php echo $is_overdue ? ' cbnexus-dash-action-overdue' : ''; ?>"><?php printf(esc_html__('Due %s', 'circleblast-nexus'), esc_html(date_i18n('M j', strtotime($a->due_date)))); ?></span>
							<?php endif; ?>
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

			<?php self::render_recruitment_card(); ?>
		</div>
		<?php
	}

	// ─── Recruitment Coverage Card ────────────────────────────────────

	/**
	 * Render the "Who We're Looking For" dashboard card.
	 *
	 * Phase 2 of Recruitment Coverage Visibility.
	 * Shows coverage summary + top gap categories for all members.
	 * Admins additionally see who fills covered categories and a manage link.
	 */
	private static function render_recruitment_card(): void {
		if (!class_exists('CBNexus_Recruitment_Coverage_Service')) {
			return;
		}

		$summary = CBNexus_Recruitment_Coverage_Service::get_summary();

		// Don't show the card if there are no categories defined yet.
		if ($summary['total'] === 0) {
			return;
		}

		$gaps      = CBNexus_Recruitment_Coverage_Service::get_focus_categories(5);
		$is_admin  = current_user_can('cbnexus_manage_members');
		$admin_email = get_option('admin_email', '');
		$portal_url  = CBNexus_Portal_Router::get_portal_url();

		$p_dots = [
			'high'   => '#dc2626',
			'medium' => '#d97706',
			'low'    => '#059669',
		];
		?>
		<!-- Who We're Looking For -->
		<div class="cbnexus-card cbnexus-recruit-card">
			<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
				<div style="display:flex;align-items:center;gap:8px;">
					<span style="font-size:18px;">🎯</span>
					<h3 style="margin:0;font-size:16px;font-weight:700;"><?php esc_html_e("Who We're Looking For", 'circleblast-nexus'); ?></h3>
				</div>
				<?php if ($is_admin) : ?>
					<a href="<?php echo esc_url(add_query_arg(['section' => 'manage', 'admin_tab' => 'recruitment'], $portal_url)); ?>" class="cbnexus-link" style="font-size:13px;"><?php esc_html_e('Manage →', 'circleblast-nexus'); ?></a>
				<?php endif; ?>
			</div>

			<!-- Summary line -->
			<div style="font-size:13px;color:var(--cb-text-sec,#6b7280);margin-bottom:14px;">
				<?php
				printf(
					/* translators: 1: covered count, 2: total count, 3: gap count */
					esc_html__('%1$d of %2$d categories covered · %3$d open roles', 'circleblast-nexus'),
					$summary['covered'],
					$summary['total'],
					$summary['gaps']
				);
				?>
			</div>

			<?php if (!empty($gaps)) : ?>
				<!-- Open categories -->
				<?php if (CBNexus_Recruitment_Coverage_Service::has_active_focus()) : ?>
					<div style="font-size:11px;font-weight:600;color:var(--cb-text-ter,#9ca3af);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">🔄 This Month's Focus</div>
				<?php endif; ?>
				<div class="cbnexus-recruit-gaps">
					<?php foreach ($gaps as $gap) : ?>
						<div class="cbnexus-recruit-gap-row">
							<div style="display:flex;align-items:center;gap:8px;flex:1;min-width:0;">
								<span style="width:8px;height:8px;border-radius:50%;background:<?php echo esc_attr($p_dots[$gap->priority] ?? '#d97706'); ?>;flex-shrink:0;"></span>
								<div style="min-width:0;">
									<div style="font-weight:600;font-size:14px;line-height:1.3;"><?php echo esc_html($gap->title); ?></div>
									<div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-top:2px;">
										<?php if ($gap->industry) : ?>
											<span style="font-size:11px;padding:1px 6px;background:#f3eef6;border-radius:8px;color:#5b2d6e;"><?php echo esc_html($gap->industry); ?></span>
										<?php endif; ?>
										<?php if ($gap->description) : ?>
											<span style="font-size:12px;color:var(--cb-text-ter,#9ca3af);"><?php echo esc_html(wp_trim_words($gap->description, 10)); ?></span>
										<?php endif; ?>
									</div>
								</div>
							</div>
							<?php if ($admin_email) : ?>
								<a href="#" class="cbnexus-recruit-refer-link" data-referral-open data-referral-category="<?php echo esc_attr($gap->id); ?>" data-referral-category-title="<?php echo esc_attr($gap->title); ?>"><?php esc_html_e('Know someone?', 'circleblast-nexus'); ?></a>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>

				<div style="margin-top:10px;">
					<a href="<?php echo esc_url(add_query_arg(['section' => 'club', 'coverage' => 'expanded'], $portal_url)); ?>" class="cbnexus-link" style="font-size:13px;"><?php esc_html_e('View all recruitment categories →', 'circleblast-nexus'); ?></a>
				</div>

			<?php else : ?>
				<div style="padding:8px 0;font-size:14px;color:#059669;font-weight:500;">
					✅ <?php esc_html_e('All categories are covered!', 'circleblast-nexus'); ?>
				</div>
			<?php endif; ?>
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