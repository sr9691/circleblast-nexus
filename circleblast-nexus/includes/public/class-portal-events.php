<?php
/**
 * Portal Events
 *
 * Member-facing events calendar with multiple views (week, month, quarter, year),
 * event submission form, RSVP, and detail view.
 */

defined('ABSPATH') || exit;

final class CBNexus_Portal_Events {

	public static function init(): void {
		add_action('wp_ajax_cbnexus_event_rsvp', [__CLASS__, 'ajax_rsvp']);
		add_action('init', [__CLASS__, 'handle_submit']);
	}

	public static function handle_submit(): void {
		if (!isset($_POST['cbnexus_submit_event'])) { return; }
		if (!is_user_logged_in()) { return; }
		if (!wp_verify_nonce(wp_unslash($_POST['_wpnonce'] ?? ''), 'cbnexus_submit_event')) { return; }

		$uid = get_current_user_id();
		if (!CBNexus_Member_Repository::is_member($uid)) { return; }

		$result = CBNexus_Event_Service::submit($uid, [
			'title'            => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
			'description'      => sanitize_textarea_field(wp_unslash($_POST['description'] ?? '')),
			'event_date'       => sanitize_text_field(wp_unslash($_POST['event_date'] ?? '')),
			'event_time'       => sanitize_text_field(wp_unslash($_POST['event_time'] ?? '')),
			'end_date'         => sanitize_text_field(wp_unslash($_POST['end_date'] ?? '')),
			'end_time'         => sanitize_text_field(wp_unslash($_POST['end_time'] ?? '')),
			'location'         => sanitize_text_field(wp_unslash($_POST['location'] ?? '')),
			'location_url'     => esc_url_raw(wp_unslash($_POST['location_url'] ?? '')),
			'audience'         => sanitize_key(wp_unslash($_POST['audience'] ?? 'all')),
			'category'         => sanitize_text_field(wp_unslash($_POST['category'] ?? '')),
			'registration_url' => esc_url_raw(wp_unslash($_POST['registration_url'] ?? '')),
			'reminder_notes'   => sanitize_textarea_field(wp_unslash($_POST['reminder_notes'] ?? '')),
			'cost'             => sanitize_text_field(wp_unslash($_POST['cost'] ?? '')),
			'max_attendees'    => sanitize_text_field(wp_unslash($_POST['max_attendees'] ?? '')),
		]);

		$portal_url = CBNexus_Portal_Router::get_portal_url();
		$redirect = add_query_arg('section', 'events', $portal_url);

		if ($result['success']) {
			$notice = current_user_can('cbnexus_manage_members') ? 'submitted_approved' : 'submitted_pending';
			$redirect = add_query_arg('event_notice', $notice, $redirect);
		} else {
			$redirect = add_query_arg('event_notice', 'error', $redirect);
		}

		wp_safe_redirect($redirect);
		exit;
	}

	// ─── Render ────────────────────────────────────────────────────────

	public static function render(array $profile): void {
		// Sub-views
		if (isset($_GET['event_id'])) {
			self::render_detail(absint($_GET['event_id']), $profile);
			return;
		}
		if (isset($_GET['event_action']) && $_GET['event_action'] === 'new') {
			self::render_submit_form($profile);
			return;
		}

		$notice = sanitize_key($_GET['event_notice'] ?? '');
		$view   = sanitize_key($_GET['cal_view'] ?? 'month');
		if (!in_array($view, ['week', 'month', 'quarter', 'year'], true)) { $view = 'month'; }

		$portal_url = CBNexus_Portal_Router::get_portal_url();
		$base_url   = add_query_arg('section', 'events', $portal_url);

		// Calculate date ranges based on view
		$ref_date = !empty($_GET['cal_date']) ? sanitize_text_field($_GET['cal_date']) : gmdate('Y-m-d');
		$range    = self::get_date_range($view, $ref_date);

		$events = CBNexus_Event_Repository::query([
			'status'    => 'approved',
			'from_date' => $range['from'],
			'to_date'   => $range['to'],
			'order'     => 'ASC',
		]);

		// Group events by date
		$grouped = [];
		foreach ($events as $e) {
			$grouped[$e->event_date][] = $e;
		}
		?>

		<?php if ($notice === 'submitted_approved') : ?>
			<div class="cbnexus-notice cbnexus-notice-success"><?php esc_html_e('Event published!', 'circleblast-nexus'); ?></div>
		<?php elseif ($notice === 'submitted_pending') : ?>
			<div class="cbnexus-notice cbnexus-notice-success"><?php esc_html_e('Event submitted for approval. An admin will review it shortly.', 'circleblast-nexus'); ?></div>
		<?php endif; ?>

		<div class="cbnexus-events-page">
			<!-- Header -->
			<div class="cbnexus-events-header">
				<h2>📅 <?php esc_html_e('Events', 'circleblast-nexus'); ?></h2>
				<a href="<?php echo esc_url(add_query_arg(['section' => 'events', 'event_action' => 'new'], $portal_url)); ?>" class="cbnexus-btn cbnexus-btn-primary cbnexus-btn-sm" style="border-radius:10px;">+ <?php esc_html_e('Submit Event', 'circleblast-nexus'); ?></a>
			</div>

			<!-- View Switcher -->
			<div class="cbnexus-cal-controls">
				<div class="cbnexus-cal-nav">
					<a href="<?php echo esc_url(add_query_arg(['cal_view' => $view, 'cal_date' => $range['prev']], $base_url)); ?>" class="cbnexus-btn cbnexus-btn-outline cbnexus-btn-sm">←</a>
					<span class="cbnexus-cal-label"><?php echo esc_html($range['label']); ?></span>
					<a href="<?php echo esc_url(add_query_arg(['cal_view' => $view, 'cal_date' => $range['next']], $base_url)); ?>" class="cbnexus-btn cbnexus-btn-outline cbnexus-btn-sm">→</a>
				</div>
				<div class="cbnexus-cal-views">
					<?php foreach (['week' => 'W', 'month' => 'M', 'quarter' => 'Q', 'year' => 'Y'] as $v => $lbl) : ?>
						<a href="<?php echo esc_url(add_query_arg(['cal_view' => $v, 'cal_date' => $ref_date], $base_url)); ?>"
						   class="cbnexus-cal-view-btn <?php echo $v === $view ? 'active' : ''; ?>"><?php echo esc_html($lbl); ?></a>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Calendar Grid -->
			<?php if ($view === 'month') : ?>
				<?php self::render_month_grid($range, $grouped, $portal_url); ?>
			<?php else : ?>
				<?php self::render_list_view($events, $portal_url); ?>
			<?php endif; ?>

			<!-- Upcoming List (always shown below) -->
			<?php if (!empty($events)) : ?>
			<div class="cbnexus-events-list" style="margin-top:16px;">
				<?php foreach ($events as $e) :
					$counts = CBNexus_Event_Repository::get_rsvp_counts((int) $e->id);
					$detail_url = add_query_arg(['section' => 'events', 'event_id' => $e->id], $portal_url);
					$cat_label = CBNexus_Event_Service::CATEGORIES[$e->category] ?? $e->category;
				?>
				<a href="<?php echo esc_url($detail_url); ?>" class="cbnexus-event-card" style="text-decoration:none;color:inherit;">
					<div class="cbnexus-event-date-badge">
						<span class="cbnexus-event-month"><?php echo esc_html(date_i18n('M', strtotime($e->event_date))); ?></span>
						<span class="cbnexus-event-day"><?php echo esc_html(date_i18n('j', strtotime($e->event_date))); ?></span>
					</div>
					<div class="cbnexus-event-info">
						<h3><?php echo esc_html($e->title); ?></h3>
						<span class="cbnexus-text-muted">
							<?php echo esc_html(date_i18n('l, g:i A', strtotime($e->event_date . ' ' . ($e->event_time ?: '00:00')))); ?>
							<?php if ($e->location) : ?> · <?php echo esc_html($e->location); ?><?php endif; ?>
						</span>
						<div style="display:flex;gap:6px;margin-top:6px;">
							<?php if ($cat_label) : ?><span class="cbnexus-pill cbnexus-pill--accent-soft"><?php echo esc_html($cat_label); ?></span><?php endif; ?>
							<?php if ($counts['going'] > 0) : ?><span class="cbnexus-pill cbnexus-pill--green-soft"><?php echo esc_html($counts['going']); ?> going</span><?php endif; ?>
							<?php if ($e->audience === 'members') : ?><span class="cbnexus-pill cbnexus-pill--gold-soft">Members only</span><?php endif; ?>
						</div>
					</div>
				</a>
				<?php endforeach; ?>
			</div>
			<?php elseif (empty($events)) : ?>
				<div class="cbnexus-card"><p class="cbnexus-text-muted"><?php esc_html_e('No events in this period.', 'circleblast-nexus'); ?></p></div>
			<?php endif; ?>
		</div>
		<?php
	}

	// ─── Month Grid ────────────────────────────────────────────────────

	private static function render_month_grid(array $range, array $grouped, string $portal_url): void {
		$year  = (int) date('Y', strtotime($range['from']));
		$month = (int) date('m', strtotime($range['from']));
		$first_dow = (int) date('w', mktime(0, 0, 0, $month, 1, $year));
		$days_in_month = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
		$today = gmdate('Y-m-d');
		?>
		<div class="cbnexus-cal-grid">
			<div class="cbnexus-cal-header-row">
				<?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d) : ?>
					<div class="cbnexus-cal-dow"><?php echo $d; ?></div>
				<?php endforeach; ?>
			</div>
			<div class="cbnexus-cal-body">
				<?php for ($i = 0; $i < $first_dow; $i++) : ?><div class="cbnexus-cal-cell cbnexus-cal-empty"></div><?php endfor; ?>
				<?php for ($d = 1; $d <= $days_in_month; $d++) :
					$date_str = sprintf('%04d-%02d-%02d', $year, $month, $d);
					$is_today = ($date_str === $today);
					$has_events = isset($grouped[$date_str]);
				?>
					<div class="cbnexus-cal-cell <?php echo $is_today ? 'cbnexus-cal-today' : ''; ?> <?php echo $has_events ? 'cbnexus-cal-has-event' : ''; ?>">
						<span class="cbnexus-cal-day-num"><?php echo $d; ?></span>
						<?php if ($has_events) : foreach (array_slice($grouped[$date_str], 0, 2) as $evt) : ?>
							<a href="<?php echo esc_url(add_query_arg(['section' => 'events', 'event_id' => $evt->id], $portal_url)); ?>"
							   class="cbnexus-cal-event-dot" title="<?php echo esc_attr($evt->title); ?>"><?php echo esc_html(mb_substr($evt->title, 0, 12)); ?></a>
						<?php endforeach; if (count($grouped[$date_str]) > 2) : ?>
							<span class="cbnexus-cal-more">+<?php echo count($grouped[$date_str]) - 2; ?></span>
						<?php endif; endif; ?>
					</div>
				<?php endfor; ?>
			</div>
		</div>
		<?php
	}

	// ─── List View (for week/quarter/year) ─────────────────────────────

	private static function render_list_view(array $events, string $portal_url): void {
		if (empty($events)) { return; }
		// Already rendered in the main list below
	}

	// ─── Event Detail ──────────────────────────────────────────────────

	private static function render_detail(int $id, array $profile): void {
		$event = CBNexus_Event_Repository::get($id);
		if (!$event || $event->status !== 'approved') {
			echo '<div class="cbnexus-card"><p>' . esc_html__('Event not found.', 'circleblast-nexus') . '</p></div>';
			return;
		}

		$portal_url = CBNexus_Portal_Router::get_portal_url();
		$back_url   = add_query_arg('section', 'events', $portal_url);
		$counts     = CBNexus_Event_Repository::get_rsvp_counts($id);
		$my_rsvp    = CBNexus_Event_Repository::get_member_rsvp($id, $profile['user_id']);
		$rsvps      = CBNexus_Event_Repository::get_rsvps($id);
		$is_past    = $event->event_date < gmdate('Y-m-d');
		$cat_label  = CBNexus_Event_Service::CATEGORIES[$event->category] ?? $event->category;
		?>
		<a href="<?php echo esc_url($back_url); ?>" class="cbnexus-back-link">&larr; <?php esc_html_e('Back to Events', 'circleblast-nexus'); ?></a>

		<div class="cbnexus-card">
			<div style="display:flex;align-items:flex-start;gap:16px;">
				<div class="cbnexus-event-date-badge" style="flex-shrink:0;">
					<span class="cbnexus-event-month"><?php echo esc_html(date_i18n('M', strtotime($event->event_date))); ?></span>
					<span class="cbnexus-event-day"><?php echo esc_html(date_i18n('j', strtotime($event->event_date))); ?></span>
				</div>
				<div style="flex:1;">
					<h2 style="margin:0 0 4px;font-size:20px;"><?php echo esc_html($event->title); ?></h2>
					<p class="cbnexus-text-muted" style="margin:0 0 8px;">
						<?php echo esc_html(date_i18n('l, F j, Y', strtotime($event->event_date))); ?>
						<?php if ($event->event_time) : ?> · <?php echo esc_html(date_i18n('g:i A', strtotime($event->event_time))); ?><?php endif; ?>
						<?php if ($event->end_time) : ?> – <?php echo esc_html(date_i18n('g:i A', strtotime($event->end_time))); ?><?php endif; ?>
					</p>
					<div style="display:flex;gap:6px;flex-wrap:wrap;">
						<?php if ($cat_label) : ?><span class="cbnexus-pill cbnexus-pill--accent-soft"><?php echo esc_html($cat_label); ?></span><?php endif; ?>
						<?php if ($event->audience === 'members') : ?><span class="cbnexus-pill cbnexus-pill--gold-soft">Members only</span><?php endif; ?>
						<?php if ($event->cost) : ?><span class="cbnexus-pill"><?php echo esc_html($event->cost); ?></span><?php endif; ?>
						<span class="cbnexus-pill cbnexus-pill--green-soft"><?php echo esc_html($counts['going']); ?> going</span>
					</div>
				</div>
			</div>
		</div>

		<?php if ($event->description) : ?>
		<div class="cbnexus-card">
			<h3><?php esc_html_e('Details', 'circleblast-nexus'); ?></h3>
			<p style="white-space:pre-line;font-size:14px;line-height:1.7;color:var(--cb-text-sec);"><?php echo esc_html($event->description); ?></p>
		</div>
		<?php endif; ?>

		<div class="cbnexus-card">
			<div style="display:flex;flex-wrap:wrap;gap:16px;">
				<?php if ($event->location) : ?>
					<div><strong>📍 <?php esc_html_e('Location', 'circleblast-nexus'); ?></strong><br/><?php echo esc_html($event->location); ?>
					<?php if ($event->location_url) : ?> <a href="<?php echo esc_url($event->location_url); ?>" target="_blank" style="font-size:13px;">Map →</a><?php endif; ?></div>
				<?php endif; ?>
				<?php if ($event->organizer_name) : ?>
					<div><strong>👤 <?php esc_html_e('Organizer', 'circleblast-nexus'); ?></strong><br/><?php echo esc_html($event->organizer_name); ?></div>
				<?php endif; ?>
				<?php if ($event->max_attendees) : ?>
					<div><strong>👥 <?php esc_html_e('Spots', 'circleblast-nexus'); ?></strong><br/><?php echo esc_html($counts['going'] . ' / ' . $event->max_attendees); ?></div>
				<?php endif; ?>
			</div>
		</div>

		<?php if ($event->registration_url) : ?>
		<div class="cbnexus-card" style="background:var(--cb-accent-soft);">
			<a href="<?php echo esc_url($event->registration_url); ?>" target="_blank" class="cbnexus-btn cbnexus-btn-primary" style="border-radius:10px;">🔗 <?php esc_html_e('Register', 'circleblast-nexus'); ?></a>
		</div>
		<?php endif; ?>

		<!-- RSVP -->
		<?php if (!$is_past) : ?>
		<div class="cbnexus-card">
			<h3><?php esc_html_e('RSVP', 'circleblast-nexus'); ?></h3>
			<div class="cbnexus-rsvp-buttons" data-event="<?php echo esc_attr($id); ?>">
				<?php foreach (['going' => '✅ Going', 'maybe' => '🤔 Maybe', 'not_going' => '❌ Can\'t make it'] as $val => $lbl) : ?>
					<button type="button" class="cbnexus-btn cbnexus-btn-sm cbnexus-rsvp-btn <?php echo $my_rsvp === $val ? 'cbnexus-btn-primary' : 'cbnexus-btn-outline'; ?>" data-rsvp="<?php echo esc_attr($val); ?>"><?php echo $lbl; ?></button>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<!-- Attendees -->
		<?php $going = array_filter($rsvps, fn($r) => $r->status === 'going'); ?>
		<?php if (!empty($going)) : ?>
		<div class="cbnexus-card">
			<h3>✅ <?php printf(esc_html__('Going (%d)', 'circleblast-nexus'), count($going)); ?></h3>
			<div style="display:flex;flex-wrap:wrap;gap:8px;">
				<?php foreach ($going as $r) : ?>
					<span class="cbnexus-pill"><?php echo esc_html($r->display_name); ?></span>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>
		<?php
	}

	// ─── Submit Form ───────────────────────────────────────────────────

	private static function render_submit_form(array $profile): void {
		$portal_url = CBNexus_Portal_Router::get_portal_url();
		$back_url   = add_query_arg('section', 'events', $portal_url);
		?>
		<a href="<?php echo esc_url($back_url); ?>" class="cbnexus-back-link">&larr; <?php esc_html_e('Back to Events', 'circleblast-nexus'); ?></a>
		<div class="cbnexus-card">
			<h2 style="margin:0 0 16px;">📅 <?php esc_html_e('Submit an Event', 'circleblast-nexus'); ?></h2>
			<form method="post">
				<?php wp_nonce_field('cbnexus_submit_event'); ?>

				<div class="cbnexus-form-row">
					<div class="cbnexus-form-field"><label><?php esc_html_e('Title', 'circleblast-nexus'); ?> *</label>
						<input type="text" name="title" required /></div>
					<div class="cbnexus-form-field"><label><?php esc_html_e('Category', 'circleblast-nexus'); ?></label>
						<select name="category">
							<option value=""><?php esc_html_e('— Select —', 'circleblast-nexus'); ?></option>
							<?php foreach (CBNexus_Event_Service::CATEGORIES as $k => $v) : ?>
								<option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($v); ?></option>
							<?php endforeach; ?>
						</select></div>
				</div>

				<div class="cbnexus-form-field"><label><?php esc_html_e('Description', 'circleblast-nexus'); ?></label>
					<textarea name="description" rows="3" placeholder="<?php esc_attr_e('What\'s this event about?', 'circleblast-nexus'); ?>"></textarea></div>

				<div class="cbnexus-form-row">
					<div class="cbnexus-form-field"><label><?php esc_html_e('Date', 'circleblast-nexus'); ?> *</label>
						<input type="date" name="event_date" required /></div>
					<div class="cbnexus-form-field"><label><?php esc_html_e('Start Time', 'circleblast-nexus'); ?></label>
						<input type="time" name="event_time" /></div>
					<div class="cbnexus-form-field"><label><?php esc_html_e('End Time', 'circleblast-nexus'); ?></label>
						<input type="time" name="end_time" /></div>
				</div>

				<div class="cbnexus-form-row">
					<div class="cbnexus-form-field"><label><?php esc_html_e('Location', 'circleblast-nexus'); ?></label>
						<input type="text" name="location" placeholder="<?php esc_attr_e('Venue name or address', 'circleblast-nexus'); ?>" /></div>
					<div class="cbnexus-form-field"><label><?php esc_html_e('Map / Directions Link', 'circleblast-nexus'); ?></label>
						<input type="url" name="location_url" placeholder="https://maps.google.com/..." /></div>
				</div>

				<div class="cbnexus-form-row">
					<div class="cbnexus-form-field"><label><?php esc_html_e('Who is this for?', 'circleblast-nexus'); ?></label>
						<select name="audience">
							<option value="all"><?php esc_html_e('Everyone', 'circleblast-nexus'); ?></option>
							<option value="members"><?php esc_html_e('Members Only', 'circleblast-nexus'); ?></option>
							<option value="public"><?php esc_html_e('Open to Public', 'circleblast-nexus'); ?></option>
						</select></div>
					<div class="cbnexus-form-field"><label><?php esc_html_e('Cost', 'circleblast-nexus'); ?></label>
						<input type="text" name="cost" placeholder="<?php esc_attr_e('Free, $25, etc.', 'circleblast-nexus'); ?>" /></div>
					<div class="cbnexus-form-field"><label><?php esc_html_e('Max Attendees', 'circleblast-nexus'); ?></label>
						<input type="number" name="max_attendees" min="1" placeholder="<?php esc_attr_e('Leave blank for unlimited', 'circleblast-nexus'); ?>" /></div>
				</div>

				<div class="cbnexus-form-field"><label><?php esc_html_e('Registration Link', 'circleblast-nexus'); ?></label>
					<input type="url" name="registration_url" placeholder="https://..." /></div>

				<div class="cbnexus-form-field"><label><?php esc_html_e('Notes for Reminder Email', 'circleblast-nexus'); ?></label>
					<textarea name="reminder_notes" rows="2" placeholder="<?php esc_attr_e('Anything members should know before the event (what to bring, parking, etc.)', 'circleblast-nexus'); ?>"></textarea></div>

				<button type="submit" name="cbnexus_submit_event" value="1" class="cbnexus-btn cbnexus-btn-primary" style="border-radius:14px;">
					<?php esc_html_e('Submit Event', 'circleblast-nexus'); ?>
				</button>
			</form>
		</div>
		<?php
	}

	// ─── AJAX RSVP ─────────────────────────────────────────────────────

	public static function ajax_rsvp(): void {
		check_ajax_referer('cbnexus_events', 'nonce');
		$uid = get_current_user_id();
		if (!$uid || !CBNexus_Member_Repository::is_member($uid)) {
			wp_send_json_error('Access denied.', 403);
		}

		$event_id = absint($_POST['event_id'] ?? 0);
		$status   = sanitize_key($_POST['rsvp_status'] ?? 'going');
		if (!in_array($status, ['going', 'maybe', 'not_going'], true)) { $status = 'going'; }

		CBNexus_Event_Repository::rsvp($event_id, $uid, $status);
		$counts = CBNexus_Event_Repository::get_rsvp_counts($event_id);

		wp_send_json_success(['counts' => $counts, 'my_rsvp' => $status]);
	}

	// ─── Date Range Helper ─────────────────────────────────────────────

	private static function get_date_range(string $view, string $ref): array {
		$ts = strtotime($ref) ?: time();

		switch ($view) {
			case 'week':
				$start = strtotime('monday this week', $ts);
				$end   = strtotime('+6 days', $start);
				$prev  = gmdate('Y-m-d', strtotime('-7 days', $start));
				$next  = gmdate('Y-m-d', strtotime('+7 days', $start));
				$label = date_i18n('M j', $start) . ' – ' . date_i18n('M j, Y', $end);
				break;

			case 'quarter':
				$q = ceil(date('n', $ts) / 3);
				$y = date('Y', $ts);
				$start = mktime(0, 0, 0, ($q - 1) * 3 + 1, 1, $y);
				$end   = mktime(0, 0, 0, $q * 3 + 1, 0, $y);
				$prev_q = $q === 1 ? 4 : $q - 1;
				$prev_y = $q === 1 ? $y - 1 : $y;
				$next_q = $q === 4 ? 1 : $q + 1;
				$next_y = $q === 4 ? $y + 1 : $y;
				$prev  = gmdate('Y-m-d', mktime(0, 0, 0, ($prev_q - 1) * 3 + 1, 1, $prev_y));
				$next  = gmdate('Y-m-d', mktime(0, 0, 0, ($next_q - 1) * 3 + 1, 1, $next_y));
				$label = 'Q' . $q . ' ' . $y;
				break;

			case 'year':
				$y = date('Y', $ts);
				$start = mktime(0, 0, 0, 1, 1, $y);
				$end   = mktime(0, 0, 0, 12, 31, $y);
				$prev  = ($y - 1) . '-01-01';
				$next  = ($y + 1) . '-01-01';
				$label = $y;
				break;

			default: // month
				$y = date('Y', $ts);
				$m = date('n', $ts);
				$start = mktime(0, 0, 0, $m, 1, $y);
				$end   = mktime(0, 0, 0, $m + 1, 0, $y);
				$prev  = gmdate('Y-m-d', mktime(0, 0, 0, $m - 1, 1, $y));
				$next  = gmdate('Y-m-d', mktime(0, 0, 0, $m + 1, 1, $y));
				$label = date_i18n('F Y', $start);
		}

		return [
			'from'  => gmdate('Y-m-d', $start),
			'to'    => gmdate('Y-m-d', $end),
			'prev'  => $prev,
			'next'  => $next,
			'label' => $label,
		];
	}
}
