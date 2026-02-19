<?php
/**
 * Portal Admin – Analytics Tab (super-admin)
 *
 * Extracted from class-portal-admin.php for maintainability.
 */

defined('ABSPATH') || exit;

final class CBNexus_Portal_Admin_Analytics {

	public static function render(): void {
		if (!current_user_can('cbnexus_export_data')) {
			echo '<div class="cbnexus-card"><p>Permission denied.</p></div>';
			return;
		}

		$member_data = CBNexus_Admin_Analytics::compute_member_engagement();
		$overview    = self::compute_overview();
		$export_url  = wp_nonce_url(CBNexus_Portal_Admin::admin_url('analytics', ['cbnexus_portal_export' => 'members']), 'cbnexus_portal_export', '_panonce');
		?>
		<div class="cbnexus-card">
			<div class="cbnexus-admin-header-row">
				<h2>Club Analytics</h2>
				<a href="<?php echo esc_url($export_url); ?>" class="cbnexus-btn">Export CSV</a>
			</div>

			<div class="cbnexus-admin-stats-row">
				<?php foreach ($overview as $label => $val) : ?>
					<div class="cbnexus-admin-stat">
						<div class="cbnexus-admin-stat-value"><?php echo esc_html($val); ?></div>
						<div class="cbnexus-admin-stat-label"><?php echo esc_html($label); ?></div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="cbnexus-card">
			<h3>Member Engagement</h3>
			<div class="cbnexus-admin-table-wrap">
				<table class="cbnexus-admin-table">
					<thead><tr>
						<th>Member</th>
						<th>Meetings</th>
						<th>Unique</th>
						<th>CircleUp</th>
						<th>Notes %</th>
						<th>Accept %</th>
						<th>Score</th>
						<th>Risk</th>
					</tr></thead>
					<tbody>
					<?php foreach ($member_data as $m) :
						$risk_classes = ['high' => 'red', 'medium' => 'gold', 'low' => 'green'];
						$rc = $risk_classes[$m['risk']] ?? 'muted';
					?>
						<tr>
							<td>
								<strong><?php echo esc_html($m['name']); ?></strong>
								<div class="cbnexus-admin-meta"><?php echo esc_html($m['company']); ?></div>
							</td>
							<td><?php echo esc_html($m['meetings']); ?></td>
							<td><?php echo esc_html($m['unique_met']); ?></td>
							<td><?php echo esc_html($m['circleup']); ?></td>
							<td><?php echo esc_html($m['notes_pct']); ?>%</td>
							<td><?php echo esc_html($m['accept_pct']); ?>%</td>
							<td><strong><?php echo esc_html($m['score']); ?></strong>/100</td>
							<td><span class="cbnexus-status-pill cbnexus-status-<?php echo esc_attr($rc); ?>"><?php echo esc_html(ucfirst($m['risk'])); ?></span></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	private static function compute_overview(): array {
		global $wpdb;
		$members = CBNexus_Member_Repository::get_all_members('active');
		$meetings = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cb_meetings WHERE status IN ('completed','closed')");
		$suggestions = CBNexus_Suggestion_Generator::get_cycle_stats();
		$accept_rate = $suggestions['total'] > 0 ? round($suggestions['accepted'] / $suggestions['total'] * 100) . '%' : '—';
		$member_data = CBNexus_Admin_Analytics::compute_member_engagement();
		$high_risk = count(array_filter($member_data, fn($m) => $m['risk'] === 'high'));

		return [
			'Active Members'     => count($members),
			'Completed Meetings' => $meetings,
			'Acceptance Rate'    => $accept_rate,
			'High Risk'          => $high_risk,
		];
	}
}
