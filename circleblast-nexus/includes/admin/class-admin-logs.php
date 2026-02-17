<?php
/**
 * Admin Logs Page
 *
 * ITER-0003: WP admin page showing cbnexus_log entries with
 * filtering by level and date range. Read-only diagnostic view.
 */

defined('ABSPATH') || exit;

final class CBNexus_Admin_Logs {

	/**
	 * Hook into WordPress admin.
	 */
	public static function init(): void {
		add_action('admin_menu', [__CLASS__, 'register_menu']);
	}

	/**
	 * Register the admin menu page under Tools.
	 */
	public static function register_menu(): void {
		add_management_page(
			__('CircleBlast Nexus Logs', 'circleblast-nexus'),
			__('CB Nexus Logs', 'circleblast-nexus'),
			'manage_options',
			'cbnexus-logs',
			[__CLASS__, 'render_page']
		);
	}

	/**
	 * Render the logs page.
	 */
	public static function render_page(): void {
		if (!current_user_can('manage_options')) {
			wp_die(__('You do not have permission to access this page.', 'circleblast-nexus'));
		}

		global $wpdb;
		$table = $wpdb->prefix . 'cbnexus_log';

		// Check if table exists.
		$table_exists = $wpdb->get_var($wpdb->prepare(
			"SHOW TABLES LIKE %s",
			$table
		)) === $table;

		if (!$table_exists) {
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__('CircleBlast Nexus Logs', 'circleblast-nexus') . '</h1>';
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__('Log table does not exist. Please deactivate and reactivate the plugin to run migrations.', 'circleblast-nexus');
			echo '</p></div></div>';
			return;
		}

		// Read filters (sanitized).
		$filter_level = isset($_GET['log_level']) ? sanitize_text_field(wp_unslash($_GET['log_level'])) : '';
		$filter_date  = isset($_GET['log_date']) ? sanitize_text_field(wp_unslash($_GET['log_date'])) : '';
		$paged        = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
		$per_page     = 50;
		$offset       = ($paged - 1) * $per_page;

		// Build query.
		$where  = [];
		$params = [];

		$allowed_levels = ['debug', 'info', 'warning', 'error'];
		if ($filter_level !== '' && in_array($filter_level, $allowed_levels, true)) {
			$where[]  = 'level = %s';
			$params[] = $filter_level;
		}

		if ($filter_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date)) {
			$where[]  = 'DATE(created_at_gmt) = %s';
			$params[] = $filter_date;
		}

		$where_sql = '';
		if (!empty($where)) {
			$where_sql = 'WHERE ' . implode(' AND ', $where);
		}

		// Count total rows (for pagination).
		$count_sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";
		if (!empty($params)) {
			$count_sql = $wpdb->prepare($count_sql, $params);
		}
		$total = (int) $wpdb->get_var($count_sql);

		// Fetch rows.
		$query = "SELECT * FROM {$table} {$where_sql} ORDER BY created_at_gmt DESC LIMIT %d OFFSET %d";
		$query_params = array_merge($params, [$per_page, $offset]);
		$rows = $wpdb->get_results($wpdb->prepare($query, $query_params));

		$total_pages = ceil($total / $per_page);

		// Render.
		self::render_html($rows, $total, $paged, $total_pages, $filter_level, $filter_date);
	}

	/**
	 * Render the HTML output.
	 *
	 * @param array  $rows         Log rows from DB.
	 * @param int    $total        Total matching rows.
	 * @param int    $paged        Current page.
	 * @param int    $total_pages  Total pages.
	 * @param string $filter_level Current level filter.
	 * @param string $filter_date  Current date filter.
	 */
	private static function render_html(array $rows, int $total, int $paged, int $total_pages, string $filter_level, string $filter_date): void {
		$base_url = admin_url('tools.php?page=cbnexus-logs');
		?>
		<div class="wrap">
			<h1><?php esc_html_e('CircleBlast Nexus Logs', 'circleblast-nexus'); ?></h1>

			<form method="get" action="<?php echo esc_url($base_url); ?>" style="margin: 15px 0;">
				<input type="hidden" name="page" value="cbnexus-logs" />

				<label for="log_level"><strong><?php esc_html_e('Level:', 'circleblast-nexus'); ?></strong></label>
				<select name="log_level" id="log_level">
					<option value=""><?php esc_html_e('All', 'circleblast-nexus'); ?></option>
					<?php foreach (['debug', 'info', 'warning', 'error'] as $lvl) : ?>
						<option value="<?php echo esc_attr($lvl); ?>" <?php selected($filter_level, $lvl); ?>>
							<?php echo esc_html(ucfirst($lvl)); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<label for="log_date" style="margin-left: 10px;"><strong><?php esc_html_e('Date (UTC):', 'circleblast-nexus'); ?></strong></label>
				<input type="date" name="log_date" id="log_date" value="<?php echo esc_attr($filter_date); ?>" />

				<button type="submit" class="button"><?php esc_html_e('Filter', 'circleblast-nexus'); ?></button>

				<?php if ($filter_level !== '' || $filter_date !== '') : ?>
					<a href="<?php echo esc_url($base_url); ?>" class="button" style="margin-left: 5px;">
						<?php esc_html_e('Clear', 'circleblast-nexus'); ?>
					</a>
				<?php endif; ?>
			</form>

			<p class="description">
				<?php
				printf(
					/* translators: %d: number of log entries */
					esc_html__('Showing %d log entries.', 'circleblast-nexus'),
					$total
				);
				?>
			</p>

			<table class="widefat fixed striped" style="margin-top: 10px;">
				<thead>
					<tr>
						<th style="width: 50px;"><?php esc_html_e('ID', 'circleblast-nexus'); ?></th>
						<th style="width: 160px;"><?php esc_html_e('Timestamp (UTC)', 'circleblast-nexus'); ?></th>
						<th style="width: 80px;"><?php esc_html_e('Level', 'circleblast-nexus'); ?></th>
						<th><?php esc_html_e('Message', 'circleblast-nexus'); ?></th>
						<th style="width: 120px;"><?php esc_html_e('Source', 'circleblast-nexus'); ?></th>
						<th style="width: 60px;"><?php esc_html_e('User', 'circleblast-nexus'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($rows)) : ?>
						<tr>
							<td colspan="6"><?php esc_html_e('No log entries found.', 'circleblast-nexus'); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ($rows as $row) : ?>
							<tr>
								<td><?php echo esc_html($row->id); ?></td>
								<td><?php echo esc_html($row->created_at_gmt); ?></td>
								<td>
									<?php echo esc_html(strtoupper($row->level)); ?>
								</td>
								<td>
									<?php echo esc_html($row->message); ?>
									<?php if (!empty($row->context_json)) : ?>
										<br />
										<small style="color: #666; word-break: break-all;">
											<?php echo esc_html(self::truncate_context($row->context_json, 300)); ?>
										</small>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html($row->source ?? '—'); ?></td>
								<td><?php echo esc_html($row->user_id ?? '—'); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ($total_pages > 1) : ?>
				<div class="tablenav bottom" style="margin-top: 10px;">
					<div class="tablenav-pages">
						<?php
						echo wp_kses_post(paginate_links([
							'base'      => add_query_arg('paged', '%#%'),
							'format'    => '',
							'current'   => $paged,
							'total'     => $total_pages,
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
						]));
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Truncate context JSON for table display.
	 *
	 * @param string $json    JSON string.
	 * @param int    $max_len Max display length.
	 * @return string Truncated string.
	 */
	private static function truncate_context(string $json, int $max_len = 300): string {
		if (strlen($json) <= $max_len) {
			return $json;
		}
		return substr($json, 0, $max_len) . "\xE2\x80\xA6";
	}
}
