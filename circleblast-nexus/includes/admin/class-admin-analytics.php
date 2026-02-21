<?php
/**
 * Admin Analytics
 *
 * ITER-0017: Admin analytics dashboard with per-member engagement scores,
 * churn risk flags, suggestion acceptance rates, and CSV export.
 * Includes automated monthly report email via WP-Cron.
 */

defined('ABSPATH') || exit;

final class CBNexus_Admin_Analytics {

	public static function init(): void {
		add_action('admin_menu', [__CLASS__, 'register_menu']);
		add_action('admin_init', [__CLASS__, 'handle_export']);
		add_action('cbnexus_monthly_report', [__CLASS__, 'send_monthly_report']);
	}

	public static function register_menu(): void {
		add_submenu_page(
			'cbnexus-members',
			__('Analytics', 'circleblast-nexus'),
			__('Analytics', 'circleblast-nexus'),
			'cbnexus_manage_members',
			'cbnexus-analytics',
			[__CLASS__, 'render_page']
		);
	}

	public static function render_page(): void {
		if (!current_user_can('cbnexus_manage_members')) { wp_die('Permission denied.'); }

		$member_data = self::compute_member_engagement();
		$club_stats  = self::compute_overview();
		$export_url  = wp_nonce_url(admin_url('admin.php?page=cbnexus-analytics&cbnexus_export=members'), 'cbnexus_export');
		?>
		<div class="wrap">
			<h1><?php esc_html_e('Analytics', 'circleblast-nexus'); ?>
				<a href="<?php echo esc_url($export_url); ?>" class="page-title-action"><?php esc_html_e('Export CSV', 'circleblast-nexus'); ?></a>
			</h1>

			<!-- Overview Cards -->
			<div style="display:flex;gap:12px;margin:16px 0;flex-wrap:wrap;">
				<?php foreach ($club_stats as $label => $val) : ?>
					<div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:12px 20px;text-align:center;min-width:130px;">
						<div style="font-size:24px;font-weight:700;color:#2563eb;"><?php echo esc_html($val); ?></div>
						<div style="font-size:12px;color:#666;"><?php echo esc_html($label); ?></div>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- Member Engagement Table -->
			<table class="wp-list-table widefat fixed striped">
				<thead><tr>
					<th><?php esc_html_e('Member', 'circleblast-nexus'); ?></th>
					<th style="width:70px;"><?php esc_html_e('Meetings', 'circleblast-nexus'); ?></th>
					<th style="width:70px;"><?php esc_html_e('Unique', 'circleblast-nexus'); ?></th>
					<th style="width:70px;"><?php esc_html_e('CircleUp', 'circleblast-nexus'); ?></th>
					<th style="width:80px;"><?php esc_html_e('Notes %', 'circleblast-nexus'); ?></th>
					<th style="width:80px;"><?php esc_html_e('Accept %', 'circleblast-nexus'); ?></th>
					<th style="width:90px;"><?php esc_html_e('Engagement', 'circleblast-nexus'); ?></th>
					<th style="width:80px;"><?php esc_html_e('Risk', 'circleblast-nexus'); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ($member_data as $m) :
					$risk_color = match ($m['risk']) { 'high' => '#e53e3e', 'medium' => '#ecc94b', default => '#48bb78' };
				?>
					<tr>
						<td><strong><?php echo esc_html($m['name']); ?></strong><br><span style="color:#666;font-size:12px;"><?php echo esc_html($m['company']); ?></span></td>
						<td><?php echo esc_html($m['meetings']); ?></td>
						<td><?php echo esc_html($m['unique_met']); ?></td>
						<td><?php echo esc_html($m['circleup']); ?></td>
						<td><?php echo esc_html($m['notes_pct']); ?>%</td>
						<td><?php echo esc_html($m['accept_pct']); ?>%</td>
						<td><strong><?php echo esc_html($m['score']); ?></strong>/100</td>
						<td><span style="color:<?php echo esc_attr($risk_color); ?>;font-weight:600;"><?php echo esc_html(ucfirst($m['risk'])); ?></span></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	// ─── Data Computation ──────────────────────────────────────────────

	private static function compute_overview(): array {
		global $wpdb;
		$members = CBNexus_Member_Repository::get_all_members('active');
		$meetings = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cb_meetings WHERE status IN ('completed','closed')");
		$suggestions = CBNexus_Suggestion_Generator::get_cycle_stats();
		$accept_rate = $suggestions['total'] > 0 ? round($suggestions['accepted'] / $suggestions['total'] * 100) . '%' : '—';

		return [
			'Active Members'    => count($members),
			'Completed Meetings' => $meetings,
			'Auto Suggestions'  => $suggestions['total'],
			'Acceptance Rate'   => $accept_rate,
		];
	}

	public static function compute_member_engagement(): array {
		global $wpdb;
		$members = CBNexus_Member_Repository::get_all_members('active');
		$data = [];

		foreach ($members as $m) {
			$uid = (int) $m['user_id'];

			$meetings = (int) $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}cb_meetings WHERE (member_a_id=%d OR member_b_id=%d) AND status IN ('completed','closed')", $uid, $uid
			));

			$unique = (int) $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(DISTINCT CASE WHEN member_a_id=%d THEN member_b_id ELSE member_a_id END) FROM {$wpdb->prefix}cb_meetings WHERE (member_a_id=%d OR member_b_id=%d) AND status IN ('completed','closed')", $uid, $uid, $uid
			));

			$circleup = CBNexus_CircleUp_Repository::get_attendance_count($uid);

			$notes_sub = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}cb_meeting_notes WHERE author_id=%d", $uid));
			$notes_pct = $meetings > 0 ? min(100, round($notes_sub / $meetings * 100)) : 0;

			$resp_total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}cb_meeting_responses WHERE responder_id=%d", $uid));
			$resp_acc = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}cb_meeting_responses WHERE responder_id=%d AND response='accepted'", $uid));
			$accept_pct = $resp_total > 0 ? round($resp_acc / $resp_total * 100) : 100;

			// Engagement score (0-100): weighted composite.
			$score = min(100, round(
				min($meetings, 10) * 3 +
				min($unique, 8) * 3 +
				min($circleup, 6) * 4 +
				$notes_pct * 0.12 +
				$accept_pct * 0.10
			));

			// Churn risk.
			$last_activity = $wpdb->get_var($wpdb->prepare(
				"SELECT MAX(updated_at) FROM {$wpdb->prefix}cb_meetings WHERE (member_a_id=%d OR member_b_id=%d)", $uid, $uid
			));
			$days_inactive = $last_activity ? (time() - strtotime($last_activity)) / 86400 : 999;
			$risk = ($days_inactive > 90 || $score < 20) ? 'high' : (($days_inactive > 45 || $score < 40) ? 'medium' : 'low');

			$data[] = [
				'user_id' => $uid, 'name' => $m['display_name'], 'company' => $m['cb_company'] ?? '',
				'meetings' => $meetings, 'unique_met' => $unique, 'circleup' => $circleup,
				'notes_pct' => $notes_pct, 'accept_pct' => $accept_pct, 'score' => $score, 'risk' => $risk,
			];
		}

		usort($data, fn($a, $b) => $b['score'] <=> $a['score']);
		return $data;
	}

	// ─── CSV Export ────────────────────────────────────────────────────

	public static function handle_export(): void {
		if (!isset($_GET['cbnexus_export'])) { return; }
		if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'cbnexus_export')) { return; }
		if (!current_user_can('cbnexus_manage_members')) { return; }

		$data = self::compute_member_engagement();

		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename=circleblast-analytics-' . gmdate('Y-m-d') . '.csv');

		$out = fopen('php://output', 'w');
		fputcsv($out, ['Name', 'Company', 'Meetings', 'Unique Met', 'CircleUp', 'Notes %', 'Accept %', 'Engagement', 'Risk']);
		foreach ($data as $row) {
			fputcsv($out, [$row['name'], $row['company'], $row['meetings'], $row['unique_met'], $row['circleup'], $row['notes_pct'], $row['accept_pct'], $row['score'], $row['risk']]);
		}
		fclose($out);
		exit;
	}

	// ─── Automated Monthly Report ──────────────────────────────────────

	public static function send_monthly_report(): void {
		$recipients = array_merge(
			CBNexus_Member_Repository::get_all('cb_admin'),
			CBNexus_Member_Repository::get_all('cb_super_admin')
		);

		$member_data = self::compute_member_engagement();
		$high_risk = count(array_filter($member_data, fn($m) => $m['risk'] === 'high'));
		$overview  = self::compute_overview();
		$portal_url = CBNexus_Portal_Router::get_portal_url();

		foreach ($recipients as $r) {
			$profile = CBNexus_Member_Repository::get_profile((int) $r->ID);
			if (!$profile) { continue; }

			CBNexus_Email_Service::send('monthly_admin_report', $profile['user_email'], [
				'first_name'     => $profile['first_name'],
				'total_members'  => $overview['Active Members'],
				'total_meetings' => $overview['Completed Meetings'],
				'accept_rate'    => $overview['Acceptance Rate'],
				'high_risk_count' => $high_risk,
				'portal_url'     => $portal_url,
			], ['recipient_id' => (int) $r->ID, 'related_type' => 'monthly_report']);
		}
	}
}