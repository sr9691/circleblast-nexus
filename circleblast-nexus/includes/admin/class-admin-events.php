<?php
/**
 * Admin Events
 *
 * Manage events from wp-admin: approve/reject submissions,
 * create events, edit, and trigger reminders.
 */

defined('ABSPATH') || exit;

final class CBNexus_Admin_Events {

	public static function init(): void {
		add_action('admin_menu', [__CLASS__, 'register_menu']);
		add_action('admin_init', [__CLASS__, 'handle_actions']);
	}

	public static function register_menu(): void {
		add_submenu_page(
			'cbnexus-members',
			__('Events', 'circleblast-nexus'),
			__('Events', 'circleblast-nexus'),
			'cbnexus_manage_members',
			'cbnexus-events',
			[__CLASS__, 'render_page']
		);
	}

	public static function handle_actions(): void {
		if (isset($_GET['cbnexus_approve_event'])) { self::handle_approve(); }
		if (isset($_GET['cbnexus_cancel_event'])) { self::handle_cancel(); }
		if (isset($_GET['cbnexus_delete_event'])) { self::handle_delete(); }
		if (isset($_POST['cbnexus_admin_save_event'])) { self::handle_save(); }
	}

	private static function handle_approve(): void {
		$id = absint($_GET['cbnexus_approve_event'] ?? 0);
		if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'cbnexus_approve_' . $id)) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }

		CBNexus_Event_Service::approve($id, get_current_user_id());
		wp_safe_redirect(admin_url('admin.php?page=cbnexus-events&cbnexus_notice=approved'));
		exit;
	}

	private static function handle_cancel(): void {
		$id = absint($_GET['cbnexus_cancel_event'] ?? 0);
		if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'cbnexus_cancel_' . $id)) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }

		// If pending, treat cancel as deny (notifies organizer). If approved, just cancel.
		$event = CBNexus_Event_Repository::get($id);
		if ($event && $event->status === 'pending') {
			CBNexus_Event_Service::deny($id, get_current_user_id());
		} else {
			CBNexus_Event_Repository::update($id, ['status' => 'cancelled']);
		}
		wp_safe_redirect(admin_url('admin.php?page=cbnexus-events&cbnexus_notice=cancelled'));
		exit;
	}

	private static function handle_delete(): void {
		$id = absint($_GET['cbnexus_delete_event'] ?? 0);
		if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'cbnexus_delete_event_' . $id)) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }

		CBNexus_Event_Repository::delete($id);
		wp_safe_redirect(admin_url('admin.php?page=cbnexus-events&cbnexus_notice=deleted'));
		exit;
	}

	private static function handle_save(): void {
		check_admin_referer('cbnexus_admin_save_event');
		if (!current_user_can('cbnexus_manage_members')) { wp_die('Permission denied.'); }

		$id = absint($_POST['event_id'] ?? 0);
		$data = [
			'title'            => sanitize_text_field($_POST['title'] ?? ''),
			'description'      => sanitize_textarea_field(wp_unslash($_POST['description'] ?? '')),
			'event_date'       => sanitize_text_field($_POST['event_date'] ?? ''),
			'event_time'       => sanitize_text_field($_POST['event_time'] ?? ''),
			'end_time'         => sanitize_text_field($_POST['end_time'] ?? ''),
			'location'         => sanitize_text_field($_POST['location'] ?? ''),
			'audience'         => sanitize_key($_POST['audience'] ?? 'all'),
			'category'         => sanitize_key($_POST['category'] ?? ''),
			'registration_url' => esc_url_raw($_POST['registration_url'] ?? ''),
			'reminder_notes'   => sanitize_textarea_field(wp_unslash($_POST['reminder_notes'] ?? '')),
			'cost'             => sanitize_text_field($_POST['cost'] ?? ''),
			'guest_cost'       => sanitize_text_field($_POST['guest_cost'] ?? ''),
		];

		if ($id) {
			CBNexus_Event_Repository::update($id, $data);
		} else {
			$data['organizer_id'] = get_current_user_id();
			$data['status'] = 'approved';
			CBNexus_Event_Repository::create($data);
		}

		wp_safe_redirect(admin_url('admin.php?page=cbnexus-events&cbnexus_notice=saved'));
		exit;
	}

	// ─── Render ────────────────────────────────────────────────────────

	public static function render_page(): void {
		$action = sanitize_key($_GET['action'] ?? '');
		$notice = sanitize_key($_GET['cbnexus_notice'] ?? '');

		if ($action === 'edit' || $action === 'new') {
			self::render_form($action === 'edit' ? absint($_GET['id'] ?? 0) : 0);
			return;
		}

		$notices = [
			'approved'  => 'Event approved.',
			'cancelled' => 'Event cancelled.',
			'deleted'   => 'Event deleted.',
			'saved'     => 'Event saved.',
		];

		$pending  = CBNexus_Event_Repository::query(['status' => 'pending', 'order' => 'ASC']);
		$upcoming = CBNexus_Event_Repository::query(['status' => 'approved', 'from_date' => gmdate('Y-m-d'), 'order' => 'ASC', 'limit' => 30]);
		$past     = CBNexus_Event_Repository::query(['status' => 'approved', 'to_date' => gmdate('Y-m-d', strtotime('-1 day')), 'order' => 'DESC', 'limit' => 20]);
		?>
		<div class="wrap">
			<h1><?php esc_html_e('Events', 'circleblast-nexus'); ?>
				<a href="<?php echo esc_url(admin_url('admin.php?page=cbnexus-events&action=new')); ?>" class="page-title-action"><?php esc_html_e('Add New', 'circleblast-nexus'); ?></a>
			</h1>

			<?php if (isset($notices[$notice])) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html($notices[$notice]); ?></p></div>
			<?php endif; ?>

			<?php if (!empty($pending)) : ?>
				<h2><?php printf(esc_html__('Pending Approval (%d)', 'circleblast-nexus'), count($pending)); ?></h2>
				<?php self::render_table($pending, true); ?>
			<?php endif; ?>

			<h2><?php esc_html_e('Upcoming Events', 'circleblast-nexus'); ?></h2>
			<?php if (empty($upcoming)) : ?>
				<p><?php esc_html_e('No upcoming events.', 'circleblast-nexus'); ?></p>
			<?php else : ?>
				<?php self::render_table($upcoming, false); ?>
			<?php endif; ?>

			<?php if (!empty($past)) : ?>
				<h2><?php esc_html_e('Past Events', 'circleblast-nexus'); ?></h2>
				<?php self::render_table($past, false); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_table(array $events, bool $show_approve): void {
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead><tr>
				<th style="width:100px;"><?php esc_html_e('Date', 'circleblast-nexus'); ?></th>
				<th><?php esc_html_e('Title', 'circleblast-nexus'); ?></th>
				<th><?php esc_html_e('Organizer', 'circleblast-nexus'); ?></th>
				<th><?php esc_html_e('Audience', 'circleblast-nexus'); ?></th>
				<th><?php esc_html_e('RSVPs', 'circleblast-nexus'); ?></th>
				<th><?php esc_html_e('Actions', 'circleblast-nexus'); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ($events as $e) :
				$counts = CBNexus_Event_Repository::get_rsvp_counts((int) $e->id);
			?>
				<tr>
					<td>
						<?php echo esc_html(date_i18n('M j, Y', strtotime($e->event_date))); ?>
						<?php if ($e->event_time) : ?><br/><small><?php echo esc_html(date_i18n('g:i A', strtotime($e->event_time))); ?></small><?php endif; ?>
					</td>
					<td>
						<strong><?php echo esc_html($e->title); ?></strong>
						<?php if ($e->category) : ?><br/><small><?php echo esc_html(CBNexus_Event_Service::CATEGORIES[$e->category] ?? $e->category); ?></small><?php endif; ?>
					</td>
					<td><?php echo esc_html($e->organizer_name ?? '—'); ?></td>
					<td><?php echo esc_html(ucfirst($e->audience)); ?></td>
					<td><?php echo esc_html($counts['going']); ?> going</td>
					<td>
						<a href="<?php echo esc_url(admin_url('admin.php?page=cbnexus-events&action=edit&id=' . $e->id)); ?>"><?php esc_html_e('Edit', 'circleblast-nexus'); ?></a>
						<?php if ($show_approve) : ?>
							| <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cbnexus-events&cbnexus_approve_event=' . $e->id), 'cbnexus_approve_' . $e->id)); ?>" style="color:#059669;font-weight:600;"><?php esc_html_e('Approve', 'circleblast-nexus'); ?></a>
						<?php endif; ?>
						| <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cbnexus-events&cbnexus_delete_event=' . $e->id), 'cbnexus_delete_event_' . $e->id)); ?>" style="color:#dc2626;" onclick="return confirm('Delete this event?');"><?php esc_html_e('Delete', 'circleblast-nexus'); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private static function render_form(int $id): void {
		$event = $id ? CBNexus_Event_Repository::get($id) : null;
		?>
		<div class="wrap">
			<h1>
				<a href="<?php echo esc_url(admin_url('admin.php?page=cbnexus-events')); ?>">&larr; <?php esc_html_e('Events', 'circleblast-nexus'); ?></a>
				/ <?php echo $event ? esc_html__('Edit Event', 'circleblast-nexus') : esc_html__('New Event', 'circleblast-nexus'); ?>
			</h1>
			<form method="post" style="max-width:700px;">
				<?php wp_nonce_field('cbnexus_admin_save_event'); ?>
				<?php if ($id) : ?><input type="hidden" name="event_id" value="<?php echo esc_attr($id); ?>" /><?php endif; ?>

				<table class="form-table">
					<tr><th><label><?php esc_html_e('Title', 'circleblast-nexus'); ?> *</label></th>
						<td><input type="text" name="title" value="<?php echo esc_attr($event->title ?? ''); ?>" class="large-text" required /></td></tr>
					<tr><th><label><?php esc_html_e('Date', 'circleblast-nexus'); ?> *</label></th>
						<td><input type="date" name="event_date" value="<?php echo esc_attr($event->event_date ?? ''); ?>" required /></td></tr>
					<tr><th><label><?php esc_html_e('Time', 'circleblast-nexus'); ?></label></th>
						<td><input type="time" name="event_time" value="<?php echo esc_attr($event->event_time ?? ''); ?>" style="width:120px;" />
							<span style="margin:0 8px;">to</span>
							<input type="time" name="end_time" value="<?php echo esc_attr($event->end_time ?? ''); ?>" style="width:120px;" /></td></tr>
					<tr><th><label><?php esc_html_e('Description', 'circleblast-nexus'); ?></label></th>
						<td><textarea name="description" rows="4" class="large-text"><?php echo esc_textarea($event->description ?? ''); ?></textarea></td></tr>
					<tr><th><label><?php esc_html_e('Location', 'circleblast-nexus'); ?></label></th>
						<td><input type="text" name="location" value="<?php echo esc_attr($event->location ?? ''); ?>" class="large-text" /></td></tr>
					<tr><th><label><?php esc_html_e('Category', 'circleblast-nexus'); ?></label></th>
						<td><select name="category"><option value="">—</option>
							<?php foreach (CBNexus_Event_Service::CATEGORIES as $k => $v) : ?>
								<option value="<?php echo esc_attr($k); ?>" <?php selected($event->category ?? '', $k); ?>><?php echo esc_html($v); ?></option>
							<?php endforeach; ?></select></td></tr>
					<tr><th><label><?php esc_html_e('Audience', 'circleblast-nexus'); ?></label></th>
						<td><select name="audience">
							<option value="all" <?php selected($event->audience ?? '', 'all'); ?>><?php esc_html_e('Everyone', 'circleblast-nexus'); ?></option>
							<option value="members" <?php selected($event->audience ?? '', 'members'); ?>><?php esc_html_e('Members Only', 'circleblast-nexus'); ?></option>
							<option value="public" <?php selected($event->audience ?? '', 'public'); ?>><?php esc_html_e('Public', 'circleblast-nexus'); ?></option>
						</select></td></tr>
					<tr><th><label><?php esc_html_e('Registration URL', 'circleblast-nexus'); ?></label></th>
						<td><input type="url" name="registration_url" value="<?php echo esc_attr($event->registration_url ?? ''); ?>" class="large-text" /></td></tr>
					<tr><th><label><?php esc_html_e('Member Cost', 'circleblast-nexus'); ?></label></th>
						<td><input type="text" name="cost" value="<?php echo esc_attr($event->cost ?? ''); ?>" style="width:200px;" placeholder="Free, $25, etc." /></td></tr>
					<tr><th><label><?php esc_html_e('Guest Cost', 'circleblast-nexus'); ?></label></th>
						<td><input type="text" name="guest_cost" value="<?php echo esc_attr($event->guest_cost ?? ''); ?>" style="width:200px;" placeholder="Free, $35, etc." /></td></tr>
					<tr><th><label><?php esc_html_e('Reminder Notes', 'circleblast-nexus'); ?></label></th>
						<td><textarea name="reminder_notes" rows="2" class="large-text" placeholder="Notes to include in the reminder email"><?php echo esc_textarea($event->reminder_notes ?? ''); ?></textarea>
						<p class="description"><?php esc_html_e('These notes will be included in the automatic reminder email sent the day before the event.', 'circleblast-nexus'); ?></p></td></tr>
				</table>

				<p class="submit">
					<button type="submit" name="cbnexus_admin_save_event" value="1" class="button button-primary"><?php echo $event ? esc_html__('Update Event', 'circleblast-nexus') : esc_html__('Create Event', 'circleblast-nexus'); ?></button>
					<a href="<?php echo esc_url(admin_url('admin.php?page=cbnexus-events')); ?>" class="button"><?php esc_html_e('Cancel', 'circleblast-nexus'); ?></a>
				</p>
			</form>
		</div>
		<?php
	}
}
