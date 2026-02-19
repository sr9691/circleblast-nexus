<?php
/**
 * Portal Admin – Events Tab
 *
 * Extracted from class-portal-admin.php for maintainability.
 */

defined('ABSPATH') || exit;

final class CBNexus_Portal_Admin_Events {

	public static function render(): void {
		$notice = sanitize_key($_GET['pa_notice'] ?? '');
		CBNexus_Portal_Admin::render_notice($notice);

		$edit_id = absint($_GET['edit_event'] ?? 0);
		if ($edit_id || isset($_GET['new_event'])) {
			self::render_event_form($edit_id);
			return;
		}

		$events = CBNexus_Event_Repository::query();
		$upcoming_approved = array_filter($events, fn($e) => $e->status === 'approved' && $e->event_date >= gmdate('Y-m-d'));
		$digest_settings = CBNexus_Event_Service::get_digest_settings();
		?>
		<div class="cbnexus-card">
			<div class="cbnexus-admin-header-row">
				<h2>Events</h2>
				<a href="<?php echo esc_url(CBNexus_Portal_Admin::admin_url('events', ['new_event' => '1'])); ?>" class="cbnexus-btn cbnexus-btn-primary cbnexus-btn-sm">+ Add Event</a>
			</div>
			<form method="post" action="">
				<?php wp_nonce_field('cbnexus_portal_send_events', '_panonce_send'); ?>
				<div class="cbnexus-admin-table-wrap">
					<table class="cbnexus-admin-table">
						<thead><tr>
							<th style="width:40px;"><input type="checkbox" id="cbnexus-check-all-events" title="Select all upcoming" /></th>
							<th>Date</th><th>Event</th><th>Location</th><th>Status</th><th>RSVPs</th><th>Actions</th>
						</tr></thead>
						<tbody>
						<?php if (empty($events)) : ?>
							<tr><td colspan="7" class="cbnexus-admin-empty">No events yet.</td></tr>
						<?php else : foreach ($events as $e) :
							$rsvps = CBNexus_Event_Repository::get_rsvp_counts($e->id);
							$rsvp_total = ($rsvps['going'] ?? 0) + ($rsvps['maybe'] ?? 0);
							$is_upcoming_approved = ($e->status === 'approved' && $e->event_date >= gmdate('Y-m-d'));
						?>
							<tr>
								<td><?php if ($is_upcoming_approved) : ?><input type="checkbox" name="send_event_ids[]" value="<?php echo esc_attr($e->id); ?>" class="cbnexus-event-check" /><?php endif; ?></td>
								<td><?php echo esc_html(date_i18n('M j, Y', strtotime($e->event_date))); ?></td>
								<td><strong><?php echo esc_html($e->title); ?></strong></td>
								<td class="cbnexus-admin-meta"><?php echo esc_html($e->location ?: '—'); ?></td>
								<td><?php CBNexus_Portal_Admin::status_pill($e->status); ?></td>
								<td><?php echo esc_html($rsvp_total); ?></td>
								<td class="cbnexus-admin-actions-cell">
									<a href="<?php echo esc_url(CBNexus_Portal_Admin::admin_url('events', ['edit_event' => $e->id])); ?>" class="cbnexus-link">Edit</a>
									<?php if ($e->status === 'pending') : ?>
										<a href="<?php echo esc_url(wp_nonce_url(CBNexus_Portal_Admin::admin_url('events', ['cbnexus_portal_event_action' => 'approve', 'event_id' => $e->id]), 'cbnexus_portal_event_' . $e->id, '_panonce')); ?>" class="cbnexus-link cbnexus-link-green">Approve</a>
									<?php endif; ?>
									<?php if ($e->status !== 'cancelled') : ?>
										<a href="<?php echo esc_url(wp_nonce_url(CBNexus_Portal_Admin::admin_url('events', ['cbnexus_portal_event_action' => 'cancel', 'event_id' => $e->id]), 'cbnexus_portal_event_' . $e->id, '_panonce')); ?>" class="cbnexus-link cbnexus-link-red">Cancel</a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; endif; ?>
						</tbody>
					</table>
				</div>
				<?php if (!empty($upcoming_approved)) : ?>
					<div style="margin-top:12px;">
						<button type="submit" name="cbnexus_portal_send_events" value="1" class="cbnexus-btn cbnexus-btn-accent" onclick="return confirm('Send event notification email to all active members for the selected events?');">📧 Send Notification for Selected</button>
						<span class="cbnexus-admin-meta" style="margin-left:8px;">Check one or more upcoming events, then click to email all members.</span>
					</div>
				<?php endif; ?>
			</form>
		</div>
		<!-- Digest Settings -->
		<div class="cbnexus-card">
			<h3>📅 Automated Events Digest</h3>
			<form method="post" action="">
				<?php wp_nonce_field('cbnexus_portal_save_digest_settings', '_panonce_digest'); ?>
				<div class="cbnexus-admin-form-stack">
					<div><label><input type="checkbox" name="digest_enabled" value="1" <?php checked($digest_settings['enabled']); ?> /> Enable automated events digest email</label></div>
					<div style="display:flex;gap:16px;flex-wrap:wrap;">
						<div><label>Frequency</label><select name="digest_frequency" class="cbnexus-input"><option value="weekly" <?php selected($digest_settings['frequency'], 'weekly'); ?>>Weekly</option><option value="biweekly" <?php selected($digest_settings['frequency'], 'biweekly'); ?>>Every 2 Weeks</option><option value="monthly" <?php selected($digest_settings['frequency'], 'monthly'); ?>>Monthly</option></select></div>
						<div><label>Day of Week</label><select name="digest_day_of_week" class="cbnexus-input"><?php $days = ['monday'=>'Monday','tuesday'=>'Tuesday','wednesday'=>'Wednesday','thursday'=>'Thursday','friday'=>'Friday','saturday'=>'Saturday','sunday'=>'Sunday']; foreach ($days as $val => $label) : ?><option value="<?php echo esc_attr($val); ?>" <?php selected($digest_settings['day_of_week'], $val); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></div>
						<div><label>Time of Day</label><input type="time" name="digest_time_of_day" value="<?php echo esc_attr($digest_settings['time_of_day']); ?>" class="cbnexus-input" /></div>
						<div><label>Include events in the next</label><select name="digest_lookahead" id="cbnexus-digest-lookahead" class="cbnexus-input"></select></div>
					</div>
					<p class="cbnexus-admin-meta">When enabled, all active members receive an email listing upcoming approved events at the selected frequency. Only sent when there are events to show. Times use your site's timezone (<?php echo esc_html(wp_timezone_string()); ?>).</p>
				</div>
				<button type="submit" name="cbnexus_portal_save_digest_settings" value="1" class="cbnexus-btn cbnexus-btn-primary">Save Digest Settings</button>
			</form>
		</div>
		<script>
		document.getElementById('cbnexus-check-all-events')?.addEventListener('change', function() {
			document.querySelectorAll('.cbnexus-event-check').forEach(function(cb) { cb.checked = this.checked; }.bind(this));
		});
		(function() {
			var freqSelect = document.querySelector('select[name="digest_frequency"]');
			var lookSelect = document.getElementById('cbnexus-digest-lookahead');
			var saved = <?php echo (int) $digest_settings['lookahead_days']; ?>;
			var freqDays = { weekly: 7, biweekly: 14, monthly: 30 };
			function updateLookahead() {
				var base = freqDays[freqSelect.value] || 7; var options = [];
				for (var mult = 1; mult <= 3; mult++) { var d = base * mult; var label = d + (d === 1 ? ' day' : ' days');
					if (d === 7) label = '1 week'; else if (d === 14) label = '2 weeks'; else if (d === 21) label = '3 weeks';
					else if (d === 30) label = '1 month'; else if (d === 60) label = '2 months'; else if (d === 90) label = '3 months';
					options.push({ value: d, label: label }); }
				lookSelect.innerHTML = ''; var matched = false;
				options.forEach(function(o) { var opt = document.createElement('option'); opt.value = o.value; opt.textContent = o.label;
					if (o.value === saved) { opt.selected = true; matched = true; } lookSelect.appendChild(opt); });
				if (!matched) lookSelect.selectedIndex = 0;
			}
			freqSelect.addEventListener('change', function() { saved = 0; updateLookahead(); }); updateLookahead();
		})();
		</script>
		<?php
	}

	private static function render_event_form(int $id): void {
		$event = $id ? CBNexus_Event_Repository::get($id) : null;
		$categories = defined('CBNexus_Event_Service::CATEGORIES') || method_exists('CBNexus_Event_Service', 'get_categories') ? CBNexus_Event_Service::CATEGORIES : [];
		?>
		<div class="cbnexus-card">
			<div class="cbnexus-admin-header-row">
				<h2><?php echo $event ? 'Edit Event' : 'Add New Event'; ?></h2>
				<a href="<?php echo esc_url(CBNexus_Portal_Admin::admin_url('events')); ?>" class="cbnexus-btn cbnexus-btn-outline cbnexus-btn-sm">← Back</a>
			</div>
			<form method="post" style="max-width:600px;">
				<?php wp_nonce_field('cbnexus_portal_save_event', '_panonce'); ?>
				<?php if ($id) : ?><input type="hidden" name="event_id" value="<?php echo esc_attr($id); ?>" /><?php endif; ?>
				<div style="display:flex;flex-direction:column;gap:12px;margin-top:12px;">
					<div><label style="display:block;font-weight:600;margin-bottom:4px;">Title *</label><input type="text" name="title" value="<?php echo esc_attr($event->title ?? ''); ?>" class="cbnexus-input" style="width:100%;" required /></div>
					<div style="display:flex;gap:12px;">
						<div style="flex:1;"><label style="display:block;font-weight:600;margin-bottom:4px;">Date *</label><input type="date" name="event_date" value="<?php echo esc_attr($event->event_date ?? ''); ?>" class="cbnexus-input" required /></div>
						<div><label style="display:block;font-weight:600;margin-bottom:4px;">Start Time</label><input type="time" name="event_time" value="<?php echo esc_attr($event->event_time ?? ''); ?>" class="cbnexus-input" /></div>
						<div><label style="display:block;font-weight:600;margin-bottom:4px;">End Time</label><input type="time" name="end_time" value="<?php echo esc_attr($event->end_time ?? ''); ?>" class="cbnexus-input" /></div>
					</div>
					<div><label style="display:block;font-weight:600;margin-bottom:4px;">Description</label><textarea name="description" rows="3" class="cbnexus-input" style="width:100%;"><?php echo esc_textarea($event->description ?? ''); ?></textarea></div>
					<div><label style="display:block;font-weight:600;margin-bottom:4px;">Location</label><input type="text" name="location" value="<?php echo esc_attr($event->location ?? ''); ?>" class="cbnexus-input" style="width:100%;" /></div>
					<div style="display:flex;gap:12px;">
						<div style="flex:1;"><label style="display:block;font-weight:600;margin-bottom:4px;">Category</label><select name="category" class="cbnexus-input"><option value="">—</option><?php foreach ($categories as $k => $v) : ?><option value="<?php echo esc_attr($k); ?>" <?php selected($event->category ?? '', $k); ?>><?php echo esc_html($v); ?></option><?php endforeach; ?></select></div>
						<div style="flex:1;"><label style="display:block;font-weight:600;margin-bottom:4px;">Audience</label><select name="audience" class="cbnexus-input"><option value="all" <?php selected($event->audience ?? '', 'all'); ?>>Everyone</option><option value="members" <?php selected($event->audience ?? '', 'members'); ?>>Members Only</option><option value="public" <?php selected($event->audience ?? '', 'public'); ?>>Public</option></select></div>
					</div>
					<div style="display:flex;gap:12px;">
						<div style="flex:1;"><label style="display:block;font-weight:600;margin-bottom:4px;">Registration URL</label><input type="url" name="registration_url" value="<?php echo esc_attr($event->registration_url ?? ''); ?>" class="cbnexus-input" style="width:100%;" /></div>
						<div style="width:150px;"><label style="display:block;font-weight:600;margin-bottom:4px;">Member Cost</label><input type="text" name="cost" value="<?php echo esc_attr($event->cost ?? ''); ?>" class="cbnexus-input" placeholder="Free, $25" /></div>
						<div style="width:150px;"><label style="display:block;font-weight:600;margin-bottom:4px;">Guest Cost</label><input type="text" name="guest_cost" value="<?php echo esc_attr($event->guest_cost ?? ''); ?>" class="cbnexus-input" placeholder="Free, $35" /></div>
					</div>
					<div><label style="display:block;font-weight:600;margin-bottom:4px;">Reminder Notes</label><textarea name="reminder_notes" rows="2" class="cbnexus-input" style="width:100%;" placeholder="Notes to include in the reminder email"><?php echo esc_textarea($event->reminder_notes ?? ''); ?></textarea></div>
				</div>
				<div style="margin-top:16px;display:flex;gap:8px;">
					<button type="submit" name="cbnexus_portal_save_event" value="1" class="cbnexus-btn cbnexus-btn-primary"><?php echo $event ? 'Update Event' : 'Create Event'; ?></button>
					<a href="<?php echo esc_url(CBNexus_Portal_Admin::admin_url('events')); ?>" class="cbnexus-btn cbnexus-btn-outline">Cancel</a>
				</div>
			</form>
		</div>
		<?php
	}

	// ─── Action Handlers ────────────────────────────────────────────────

	public static function handle_event_action(): void {
		$action = sanitize_key($_GET['cbnexus_portal_event_action']);
		$id     = absint($_GET['event_id'] ?? 0);
		if (!wp_verify_nonce(wp_unslash($_GET['_panonce'] ?? ''), 'cbnexus_portal_event_' . $id)) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }
		if ($action === 'approve') { CBNexus_Event_Repository::update($id, ['status' => 'approved']); }
		elseif ($action === 'cancel') { CBNexus_Event_Repository::update($id, ['status' => 'cancelled']); }
		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('events', ['pa_notice' => 'event_updated']));
		exit;
	}

	public static function handle_save_event(): void {
		if (!wp_verify_nonce(wp_unslash($_POST['_panonce'] ?? ''), 'cbnexus_portal_save_event')) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }
		$id = absint($_POST['event_id'] ?? 0);
		$data = [
			'title' => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
			'description' => sanitize_textarea_field(wp_unslash($_POST['description'] ?? '')),
			'event_date' => sanitize_text_field($_POST['event_date'] ?? ''),
			'event_time' => sanitize_text_field($_POST['event_time'] ?? ''),
			'end_time' => sanitize_text_field($_POST['end_time'] ?? ''),
			'location' => sanitize_text_field(wp_unslash($_POST['location'] ?? '')),
			'audience' => sanitize_key($_POST['audience'] ?? 'all'),
			'category' => sanitize_key($_POST['category'] ?? ''),
			'registration_url' => esc_url_raw($_POST['registration_url'] ?? ''),
			'reminder_notes' => sanitize_textarea_field(wp_unslash($_POST['reminder_notes'] ?? '')),
			'cost' => sanitize_text_field(wp_unslash($_POST['cost'] ?? '')),
			'guest_cost' => sanitize_text_field(wp_unslash($_POST['guest_cost'] ?? '')),
		];
		if ($id) { CBNexus_Event_Repository::update($id, $data); }
		else { $data['organizer_id'] = get_current_user_id(); $data['status'] = 'approved'; CBNexus_Event_Repository::create($data); }
		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('events', ['pa_notice' => 'event_updated']));
		exit;
	}

	public static function handle_send_events(): void {
		if (!wp_verify_nonce(wp_unslash($_POST['_panonce_send'] ?? ''), 'cbnexus_portal_send_events')) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }
		$event_ids = array_map('absint', (array) ($_POST['send_event_ids'] ?? []));
		$event_ids = array_filter($event_ids);
		if (empty($event_ids)) { wp_safe_redirect(CBNexus_Portal_Admin::admin_url('events', ['pa_notice' => 'no_events_selected'])); exit; }
		$sent = CBNexus_Event_Service::send_on_demand($event_ids);
		set_transient('cbnexus_events_sent_count', $sent, 60);
		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('events', ['pa_notice' => 'events_sent']));
		exit;
	}

	public static function handle_save_digest_settings(): void {
		if (!wp_verify_nonce(wp_unslash($_POST['_panonce_digest'] ?? ''), 'cbnexus_portal_save_digest_settings')) { return; }
		if (!current_user_can('cbnexus_manage_plugin_settings')) { return; }
		CBNexus_Event_Service::save_digest_settings([
			'enabled' => !empty($_POST['digest_enabled']),
			'frequency' => sanitize_key($_POST['digest_frequency'] ?? 'weekly'),
			'lookahead_days' => absint($_POST['digest_lookahead'] ?? 14),
			'day_of_week' => sanitize_key($_POST['digest_day_of_week'] ?? 'monday'),
			'time_of_day' => sanitize_text_field($_POST['digest_time_of_day'] ?? '09:00'),
		]);
		wp_clear_scheduled_hook('cbnexus_events_digest');
		$settings = CBNexus_Event_Service::get_digest_settings();
		if ($settings['enabled']) {
			$recurrence = match ($settings['frequency']) { 'biweekly' => 'biweekly', 'monthly' => 'monthly', default => 'weekly', };
			$wp_recurrence = ($recurrence === 'weekly') ? 'weekly' : 'weekly';
			wp_schedule_event(time(), $wp_recurrence, 'cbnexus_events_digest');
		}
		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('events', ['pa_notice' => 'digest_saved']));
		exit;
	}
}
