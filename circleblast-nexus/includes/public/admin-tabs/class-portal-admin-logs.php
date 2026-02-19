<?php
/**
 * Portal Admin – Logs Tab (super-admin)
 *
 * Extracted from class-portal-admin.php for maintainability.
 */

defined('ABSPATH') || exit;

final class CBNexus_Portal_Admin_Logs {

	public static function render(): void {
		if (!current_user_can('cbnexus_view_logs')) {
			echo '<div class="cbnexus-card"><p>Permission denied.</p></div>';
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'cbnexus_log';
		$found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
		if ($found !== $table) {
			echo '<div class="cbnexus-card"><p>Log table not found.</p></div>';
			return;
		}

		$level = sanitize_key($_GET['log_level'] ?? '');
		$where = $level ? $wpdb->prepare(" WHERE level = %s", $level) : '';
		$logs  = $wpdb->get_results("SELECT * FROM {$table}{$where} ORDER BY created_at_gmt DESC LIMIT 100");
		?>
		<div class="cbnexus-card">
			<h2>Plugin Logs</h2>

			<div class="cbnexus-admin-filters">
				<a href="<?php echo esc_url(CBNexus_Portal_Admin::admin_url('logs')); ?>" class="<?php echo $level === '' ? 'active' : ''; ?>">All</a>
				<?php foreach (['error', 'warning', 'info', 'debug'] as $lv) : ?>
					<a href="<?php echo esc_url(CBNexus_Portal_Admin::admin_url('logs', ['log_level' => $lv])); ?>" class="<?php echo $level === $lv ? 'active' : ''; ?>"><?php echo esc_html(ucfirst($lv)); ?></a>
				<?php endforeach; ?>
			</div>

			<div class="cbnexus-admin-table-wrap">
				<table class="cbnexus-admin-table cbnexus-admin-table-sm">
					<thead><tr>
						<th style="width:140px;">Time</th>
						<th style="width:70px;">Level</th>
						<th>Message</th>
					</tr></thead>
					<tbody>
					<?php if (empty($logs)) : ?>
						<tr><td colspan="3" class="cbnexus-admin-empty">No log entries found.</td></tr>
					<?php else : foreach ($logs as $log) :
						$level_colors = ['error' => 'red', 'warning' => 'gold', 'info' => 'blue', 'debug' => 'muted'];
						$lc = $level_colors[$log->level] ?? 'muted';
					?>
						<tr>
							<td class="cbnexus-admin-meta"><?php echo esc_html($log->created_at_gmt); ?></td>
							<td><span class="cbnexus-status-pill cbnexus-status-<?php echo esc_attr($lc); ?>"><?php echo esc_html(strtoupper($log->level)); ?></span></td>
							<td style="font-size:13px;"><?php echo esc_html($log->message); ?></td>
						</tr>
					<?php endforeach; endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}
}
