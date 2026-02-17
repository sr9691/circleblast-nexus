<?php
/**
 * Admin Members Page
 *
 * ITER-0005: WP admin page for listing, searching, and managing members.
 * Supports status filtering, search by name/company, and bulk status changes.
 * All actions require nonce verification and capability checks per SECURITY.md.
 */

defined('ABSPATH') || exit;

final class CBNexus_Admin_Members {

	/**
	 * Hook into WordPress admin.
	 */
	public static function init(): void {
		add_action('admin_menu', [__CLASS__, 'register_menu']);
		add_action('admin_init', [__CLASS__, 'handle_actions']);
	}

	/**
	 * Register the admin menu pages.
	 */
	public static function register_menu(): void {
		add_menu_page(
			__('CircleBlast Members', 'circleblast-nexus'),
			__('CB Members', 'circleblast-nexus'),
			'cbnexus_manage_members',
			'cbnexus-members',
			[__CLASS__, 'render_list_page'],
			'dashicons-groups',
			30
		);

		add_submenu_page(
			'cbnexus-members',
			__('All Members', 'circleblast-nexus'),
			__('All Members', 'circleblast-nexus'),
			'cbnexus_manage_members',
			'cbnexus-members',
			[__CLASS__, 'render_list_page']
		);

		add_submenu_page(
			'cbnexus-members',
			__('Add New Member', 'circleblast-nexus'),
			__('Add New', 'circleblast-nexus'),
			'cbnexus_create_members',
			'cbnexus-member-new',
			['CBNexus_Admin_Member_Form', 'render_add_page']
		);
	}

	/**
	 * Handle admin actions (bulk status change, single status change).
	 */
	public static function handle_actions(): void {
		// Single status change.
		if (isset($_GET['cbnexus_action'], $_GET['user_id'], $_GET['_wpnonce'])) {
			self::handle_single_action();
		}

		// Bulk action.
		if (isset($_POST['cbnexus_bulk_action'], $_POST['member_ids'], $_POST['_wpnonce'])) {
			self::handle_bulk_action();
		}
	}

	/**
	 * Handle single member actions (activate, deactivate, alumni).
	 */
	private static function handle_single_action(): void {
		$action  = sanitize_text_field(wp_unslash($_GET['cbnexus_action']));
		$user_id = absint($_GET['user_id']);

		if (!wp_verify_nonce(wp_unslash($_GET['_wpnonce']), 'cbnexus_member_action_' . $user_id)) {
			wp_die(__('Security check failed.', 'circleblast-nexus'));
		}

		if (!current_user_can('cbnexus_manage_members')) {
			wp_die(__('You do not have permission to perform this action.', 'circleblast-nexus'));
		}

		$status_map = [
			'activate'   => 'active',
			'deactivate' => 'inactive',
			'alumni'     => 'alumni',
		];

		if (!isset($status_map[$action])) {
			return;
		}

		$result = CBNexus_Member_Service::transition_status($user_id, $status_map[$action]);

		$redirect = admin_url('admin.php?page=cbnexus-members');
		if ($result['success']) {
			$redirect = add_query_arg('cbnexus_notice', 'status_updated', $redirect);
		} else {
			$redirect = add_query_arg('cbnexus_notice', 'status_error', $redirect);
			$redirect = add_query_arg('cbnexus_error', urlencode(implode(' ', $result['errors'] ?? [])), $redirect);
		}

		wp_safe_redirect($redirect);
		exit;
	}

	/**
	 * Handle bulk status change.
	 */
	private static function handle_bulk_action(): void {
		if (!wp_verify_nonce(wp_unslash($_POST['_wpnonce']), 'cbnexus_bulk_members')) {
			wp_die(__('Security check failed.', 'circleblast-nexus'));
		}

		if (!current_user_can('cbnexus_manage_members')) {
			wp_die(__('You do not have permission to perform this action.', 'circleblast-nexus'));
		}

		$action = sanitize_text_field(wp_unslash($_POST['cbnexus_bulk_action']));
		$ids    = array_map('absint', (array) $_POST['member_ids']);

		$status_map = [
			'bulk_activate'   => 'active',
			'bulk_deactivate' => 'inactive',
			'bulk_alumni'     => 'alumni',
		];

		if (!isset($status_map[$action]) || empty($ids)) {
			return;
		}

		$success = 0;
		foreach ($ids as $user_id) {
			$result = CBNexus_Member_Service::transition_status($user_id, $status_map[$action]);
			if ($result['success']) {
				$success++;
			}
		}

		$redirect = admin_url('admin.php?page=cbnexus-members');
		$redirect = add_query_arg('cbnexus_notice', 'bulk_updated', $redirect);
		$redirect = add_query_arg('cbnexus_count', $success, $redirect);

		wp_safe_redirect($redirect);
		exit;
	}

	/**
	 * Render the member list page.
	 */
	public static function render_list_page(): void {
		if (!current_user_can('cbnexus_manage_members')) {
			wp_die(__('You do not have permission to access this page.', 'circleblast-nexus'));
		}

		// Read filters.
		$filter_status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
		$search_query  = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

		// Get members.
		if ($search_query !== '') {
			$members = CBNexus_Member_Repository::search($search_query, $filter_status);
		} else {
			$members = CBNexus_Member_Repository::get_all_members($filter_status);
		}

		$counts = CBNexus_Member_Repository::count_by_status();

		// Show admin notices.
		self::render_notices();

		$base_url = admin_url('admin.php?page=cbnexus-members');
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e('CircleBlast Members', 'circleblast-nexus'); ?></h1>
			<?php if (current_user_can('cbnexus_create_members')) : ?>
				<a href="<?php echo esc_url(admin_url('admin.php?page=cbnexus-member-new')); ?>" class="page-title-action">
					<?php esc_html_e('Add New Member', 'circleblast-nexus'); ?>
				</a>
			<?php endif; ?>
			<hr class="wp-header-end">

			<!-- Status filter tabs -->
			<ul class="subsubsub">
				<li>
					<a href="<?php echo esc_url($base_url); ?>" <?php echo $filter_status === '' ? 'class="current"' : ''; ?>>
						<?php printf(esc_html__('All (%d)', 'circleblast-nexus'), $counts['total']); ?>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url(add_query_arg('status', 'active', $base_url)); ?>" <?php echo $filter_status === 'active' ? 'class="current"' : ''; ?>>
						<?php printf(esc_html__('Active (%d)', 'circleblast-nexus'), $counts['active']); ?>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url(add_query_arg('status', 'inactive', $base_url)); ?>" <?php echo $filter_status === 'inactive' ? 'class="current"' : ''; ?>>
						<?php printf(esc_html__('Inactive (%d)', 'circleblast-nexus'), $counts['inactive']); ?>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url(add_query_arg('status', 'alumni', $base_url)); ?>" <?php echo $filter_status === 'alumni' ? 'class="current"' : ''; ?>>
						<?php printf(esc_html__('Alumni (%d)', 'circleblast-nexus'), $counts['alumni']); ?>
					</a>
				</li>
			</ul>

			<!-- Search -->
			<form method="get" action="<?php echo esc_url($base_url); ?>">
				<input type="hidden" name="page" value="cbnexus-members" />
				<?php if ($filter_status !== '') : ?>
					<input type="hidden" name="status" value="<?php echo esc_attr($filter_status); ?>" />
				<?php endif; ?>
				<p class="search-box">
					<label class="screen-reader-text" for="cbnexus-member-search"><?php esc_html_e('Search Members', 'circleblast-nexus'); ?></label>
					<input type="search" id="cbnexus-member-search" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="<?php esc_attr_e('Search by name or email...', 'circleblast-nexus'); ?>" />
					<input type="submit" class="button" value="<?php esc_attr_e('Search', 'circleblast-nexus'); ?>" />
				</p>
			</form>

			<!-- Bulk actions form -->
			<form method="post" action="<?php echo esc_url($base_url); ?>">
				<?php wp_nonce_field('cbnexus_bulk_members'); ?>
				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<select name="cbnexus_bulk_action">
							<option value=""><?php esc_html_e('Bulk Actions', 'circleblast-nexus'); ?></option>
							<option value="bulk_activate"><?php esc_html_e('Set Active', 'circleblast-nexus'); ?></option>
							<option value="bulk_deactivate"><?php esc_html_e('Set Inactive', 'circleblast-nexus'); ?></option>
							<option value="bulk_alumni"><?php esc_html_e('Set Alumni', 'circleblast-nexus'); ?></option>
						</select>
						<input type="submit" class="button action" value="<?php esc_attr_e('Apply', 'circleblast-nexus'); ?>" />
					</div>
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php printf(esc_html(_n('%d member', '%d members', count($members), 'circleblast-nexus')), count($members)); ?>
						</span>
					</div>
				</div>

				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<td class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all" /></td>
							<th style="width: 200px;"><?php esc_html_e('Name', 'circleblast-nexus'); ?></th>
							<th><?php esc_html_e('Email', 'circleblast-nexus'); ?></th>
							<th><?php esc_html_e('Company', 'circleblast-nexus'); ?></th>
							<th style="width: 150px;"><?php esc_html_e('Industry', 'circleblast-nexus'); ?></th>
							<th style="width: 90px;"><?php esc_html_e('Status', 'circleblast-nexus'); ?></th>
							<th style="width: 100px;"><?php esc_html_e('Joined', 'circleblast-nexus'); ?></th>
							<th style="width: 120px;"><?php esc_html_e('Role', 'circleblast-nexus'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if (empty($members)) : ?>
							<tr><td colspan="8"><?php esc_html_e('No members found.', 'circleblast-nexus'); ?></td></tr>
						<?php else : ?>
							<?php foreach ($members as $m) : ?>
								<?php echo self::render_member_row($m); ?>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</form>
		</div>
		<?php
	}

	/**
	 * Render a single member table row.
	 *
	 * @param array $m Member profile array.
	 * @return string HTML output.
	 */
	private static function render_member_row(array $m): string {
		$user_id = $m['user_id'];
		$status  = $m['cb_member_status'] ?? 'active';
		$edit_url = admin_url('admin.php?page=cbnexus-member-new&edit=' . $user_id);

		// Build row actions.
		$actions = [];
		$actions[] = '<a href="' . esc_url($edit_url) . '">' . esc_html__('Edit', 'circleblast-nexus') . '</a>';

		if ($status !== 'active') {
			$actions[] = '<a href="' . esc_url(self::action_url('activate', $user_id)) . '">' . esc_html__('Activate', 'circleblast-nexus') . '</a>';
		}
		if ($status !== 'inactive') {
			$actions[] = '<a href="' . esc_url(self::action_url('deactivate', $user_id)) . '">' . esc_html__('Deactivate', 'circleblast-nexus') . '</a>';
		}
		if ($status !== 'alumni') {
			$actions[] = '<a href="' . esc_url(self::action_url('alumni', $user_id)) . '">' . esc_html__('Alumni', 'circleblast-nexus') . '</a>';
		}

		$role_label = self::role_label($m['roles'] ?? []);

		$status_badge = self::status_badge($status);

		ob_start();
		?>
		<tr>
			<th class="check-column">
				<input type="checkbox" name="member_ids[]" value="<?php echo esc_attr($user_id); ?>" />
			</th>
			<td>
				<strong><a href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html($m['display_name']); ?></a></strong>
				<div class="row-actions"><?php echo implode(' | ', $actions); ?></div>
			</td>
			<td><?php echo esc_html($m['user_email']); ?></td>
			<td><?php echo esc_html($m['cb_company'] ?? ''); ?></td>
			<td><?php echo esc_html($m['cb_industry'] ?? ''); ?></td>
			<td><?php echo $status_badge; ?></td>
			<td><?php echo esc_html($m['cb_join_date'] ?? ''); ?></td>
			<td><?php echo esc_html($role_label); ?></td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Build a nonced action URL for single-member actions.
	 *
	 * @param string $action  Action name.
	 * @param int    $user_id User ID.
	 * @return string URL.
	 */
	private static function action_url(string $action, int $user_id): string {
		return wp_nonce_url(
			admin_url('admin.php?page=cbnexus-members&cbnexus_action=' . $action . '&user_id=' . $user_id),
			'cbnexus_member_action_' . $user_id
		);
	}

	/**
	 * Format role array into a display label.
	 *
	 * @param array $roles WordPress role slugs.
	 * @return string Display label.
	 */
	private static function role_label(array $roles): string {
		$labels = [
			'cb_super_admin' => 'Super Admin',
			'cb_admin'       => 'Admin',
			'cb_member'      => 'Member',
		];

		foreach ($labels as $slug => $label) {
			if (in_array($slug, $roles, true)) {
				return $label;
			}
		}

		return 'Unknown';
	}

	/**
	 * Render a colored status badge.
	 *
	 * @param string $status Member status.
	 * @return string HTML span.
	 */
	private static function status_badge(string $status): string {
		$colors = [
			'active'   => '#28a745',
			'inactive' => '#dc3545',
			'alumni'   => '#6c757d',
		];
		$color = $colors[$status] ?? '#999';
		$label = ucfirst($status);

		return '<span style="display: inline-block; padding: 2px 8px; border-radius: 3px; background-color: ' . esc_attr($color) . '; color: #fff; font-size: 12px;">'
			. esc_html($label) . '</span>';
	}

	/**
	 * Render admin notices for action feedback.
	 */
	private static function render_notices(): void {
		if (!isset($_GET['cbnexus_notice'])) {
			return;
		}

		$notice = sanitize_text_field(wp_unslash($_GET['cbnexus_notice']));

		switch ($notice) {
			case 'created':
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Member created successfully. Welcome email sent.', 'circleblast-nexus') . '</p></div>';
				break;

			case 'updated':
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Member updated successfully.', 'circleblast-nexus') . '</p></div>';
				break;

			case 'status_updated':
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Member status updated.', 'circleblast-nexus') . '</p></div>';
				break;

			case 'bulk_updated':
				$count = isset($_GET['cbnexus_count']) ? absint($_GET['cbnexus_count']) : 0;
				printf(
					'<div class="notice notice-success is-dismissible"><p>' . esc_html__('%d member(s) updated.', 'circleblast-nexus') . '</p></div>',
					$count
				);
				break;

			case 'status_error':
			case 'error':
				$error = isset($_GET['cbnexus_error']) ? sanitize_text_field(wp_unslash($_GET['cbnexus_error'])) : __('An error occurred.', 'circleblast-nexus');
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error) . '</p></div>';
				break;
		}
	}
}
