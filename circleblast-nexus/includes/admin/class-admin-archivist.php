<?php
/**
 * Admin Archivist (WP-Admin)
 *
 * ITER-0013: WP-Admin page for CircleUp meeting management.
 * Provides a lightweight admin interface listing CircleUp meetings
 * with links to the full portal-based Archivist workflow.
 *
 * The heavy editing/review/publish workflow lives in the portal admin tab
 * (CBNexus_Portal_Admin_Archivist). This WP-Admin page provides quick
 * overview access and the AI extraction cron callback.
 */

defined('ABSPATH') || exit;

final class CBNexus_Admin_Archivist {

	public static function init(): void {
		add_action('admin_menu', [__CLASS__, 'register_menu']);
	}

	public static function register_menu(): void {
		add_submenu_page(
			'cbnexus-members',
			__('CircleUp Archivist', 'circleblast-nexus'),
			__('CircleUp', 'circleblast-nexus'),
			'cbnexus_manage_circleup',
			'cbnexus-archivist',
			[__CLASS__, 'render_page']
		);
	}

	public static function render_page(): void {
		if (!current_user_can('cbnexus_manage_circleup')) {
			wp_die('Permission denied.');
		}

		$meetings   = CBNexus_CircleUp_Repository::get_meetings();
		$portal_url = CBNexus_Portal_Router::get_portal_url();
		$admin_url  = $portal_url ? trailingslashit($portal_url) . '?section=admin&admin_tab=archivist' : '';
		?>
		<div class="wrap">
			<h1>
				<?php esc_html_e('CircleUp Archivist', 'circleblast-nexus'); ?>
				<?php if ($admin_url) : ?>
					<a href="<?php echo esc_url($admin_url); ?>" class="page-title-action"><?php esc_html_e('Open in Portal', 'circleblast-nexus'); ?></a>
				<?php endif; ?>
			</h1>
			<p class="description"><?php esc_html_e('Manage CircleUp meetings, review extracted items, and publish summaries. For the full editing workflow, use the portal Archivist tab.', 'circleblast-nexus'); ?></p>

			<table class="wp-list-table widefat fixed striped">
				<thead><tr>
					<th><?php esc_html_e('Date', 'circleblast-nexus'); ?></th>
					<th><?php esc_html_e('Title', 'circleblast-nexus'); ?></th>
					<th style="width:100px;"><?php esc_html_e('Status', 'circleblast-nexus'); ?></th>
					<th style="width:70px;"><?php esc_html_e('Items', 'circleblast-nexus'); ?></th>
					<th style="width:120px;"><?php esc_html_e('Actions', 'circleblast-nexus'); ?></th>
				</tr></thead>
				<tbody>
				<?php if (empty($meetings)) : ?>
					<tr><td colspan="5"><?php esc_html_e('No CircleUp meetings found.', 'circleblast-nexus'); ?></td></tr>
				<?php else : foreach ($meetings as $m) :
					$items = CBNexus_CircleUp_Repository::get_items($m->id);
					$edit_url = $admin_url ? $admin_url . '&circleup_id=' . (int) $m->id : '';
				?>
					<tr>
						<td><?php echo esc_html(date_i18n('M j, Y', strtotime($m->meeting_date))); ?></td>
						<td><strong><?php echo esc_html($m->title); ?></strong></td>
						<td>
							<?php
							$status_colors = [
								'draft'     => '#eab308',
								'review'    => '#3b82f6',
								'published' => '#22c55e',
							];
							$color = $status_colors[$m->status] ?? '#6b7280';
							printf(
								'<span style="color:%s;font-weight:600;">%s</span>',
								esc_attr($color),
								esc_html(ucfirst($m->status))
							);
							?>
						</td>
						<td><?php echo esc_html(count($items)); ?></td>
						<td>
							<?php if ($edit_url) : ?>
								<a href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Review →', 'circleblast-nexus'); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
