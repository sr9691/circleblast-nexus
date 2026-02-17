<?php
/**
 * Portal Club Dashboard
 *
 * ITER-0016 / UX Refresh: Group-wide analytics and presentation mode
 * matching demo. Gold "Present" button, 6-stat grid with tinted cards,
 * styled rank badges, topic cloud with plum/gold cycling, plum & gold
 * gradient presentation mode with gold typography.
 */

defined('ABSPATH') || exit;

final class CBNexus_Portal_Club {

	public static function init(): void {
		add_action('cbnexus_analytics_snapshot', [__CLASS__, 'take_snapshot']);
	}

	// ─── Render ────────────────────────────────────────────────────────

	public static function render(array $profile): void {
		$present = isset($_GET['present']) && $_GET['present'] === '1';
		$portal_url = CBNexus_Portal_Router::get_portal_url();

		if ($present) {
			self::render_presentation($portal_url);
			return;
		}

		$stats   = self::compute_club_stats();
		$top     = self::get_top_connectors(5);
		$wins    = self::get_recent_wins(6);
		$topics  = self::get_topic_cloud();
		$present_url = add_query_arg(['section' => 'club', 'present' => '1'], $portal_url);
		?>
		<div class="cbnexus-club-dash" id="cbnexus-club">
			<div class="cbnexus-club-header">
				<h2><?php esc_html_e('Club Overview', 'circleblast-nexus'); ?></h2>
				<a href="<?php echo esc_url($present_url); ?>" class="cbnexus-btn cbnexus-btn-gold cbnexus-btn-sm" target="_blank">🖥 <?php esc_html_e('Present', 'circleblast-nexus'); ?></a>
			</div>

			<div class="cbnexus-quick-stats" style="grid-template-columns:repeat(3,1fr);margin-bottom:16px;">
				<div class="cbnexus-stat-card cbnexus-stat-card--accent"><span class="cbnexus-stat-value"><?php echo esc_html($stats['total_members']); ?></span><span class="cbnexus-stat-label"><?php esc_html_e('Members', 'circleblast-nexus'); ?></span></div>
				<div class="cbnexus-stat-card"><span class="cbnexus-stat-value"><?php echo esc_html($stats['meetings_total']); ?></span><span class="cbnexus-stat-label"><?php esc_html_e('1:1 Meetings', 'circleblast-nexus'); ?></span></div>
				<div class="cbnexus-stat-card cbnexus-stat-card--green"><span class="cbnexus-stat-value"><?php echo esc_html($stats['network_density']); ?>%</span><span class="cbnexus-stat-label"><?php esc_html_e('Connected', 'circleblast-nexus'); ?></span></div>
				<div class="cbnexus-stat-card"><span class="cbnexus-stat-value"><?php echo esc_html($stats['new_members']); ?></span><span class="cbnexus-stat-label"><?php esc_html_e('New (90d)', 'circleblast-nexus'); ?></span></div>
				<div class="cbnexus-stat-card"><span class="cbnexus-stat-value"><?php echo esc_html($stats['circleup_count']); ?></span><span class="cbnexus-stat-label"><?php esc_html_e('CircleUps', 'circleblast-nexus'); ?></span></div>
				<div class="cbnexus-stat-card cbnexus-stat-card--gold"><span class="cbnexus-stat-value"><?php echo esc_html($stats['wins_total']); ?></span><span class="cbnexus-stat-label"><?php esc_html_e('Wins', 'circleblast-nexus'); ?></span></div>
			</div>

			<div class="cbnexus-dash-cols">
				<!-- Top Connectors -->
				<div class="cbnexus-card">
					<h3>🌟 <?php esc_html_e('Top Connectors', 'circleblast-nexus'); ?></h3>
					<?php if (empty($top)) : ?><p class="cbnexus-text-muted"><?php esc_html_e('No meeting data yet.', 'circleblast-nexus'); ?></p>
					<?php else : $rank = 0; foreach ($top as $t) : $rank++; ?>
						<div class="cbnexus-row">
							<span class="cbnexus-club-rank <?php echo $rank <= 3 ? 'cbnexus-club-rank--top' : 'cbnexus-club-rank--other'; ?>"><?php echo esc_html($rank); ?></span>
							<strong style="flex:1;"><?php echo esc_html($t->display_name); ?></strong>
							<span class="cbnexus-text-muted"><?php echo esc_html($t->meeting_count); ?></span>
						</div>
					<?php endforeach; endif; ?>
				</div>

				<!-- Topic Cloud -->
				<div class="cbnexus-card">
					<h3>💬 <?php esc_html_e('Topics', 'circleblast-nexus'); ?></h3>
					<?php if (empty($topics)) : ?><p class="cbnexus-text-muted"><?php esc_html_e('No topics yet.', 'circleblast-nexus'); ?></p>
					<?php else : ?>
						<div class="cbnexus-topic-cloud">
							<?php $i = 0; foreach ($topics as $topic => $count) :
								$mod = $i % 3;
								$size = max(12, 20 - $i);
								$fw = $i < 4 ? 600 : 400;
							?>
								<span class="cbnexus-topic-tag cbnexus-topic-tag--<?php echo $mod; ?>" style="font-size:<?php echo esc_attr($size); ?>px;font-weight:<?php echo $fw; ?>;"><?php echo esc_html($topic); ?></span>
							<?php $i++; endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- Recent Wins -->
			<?php if (!empty($wins)) : ?>
			<div class="cbnexus-card">
				<h3>🏆 <?php esc_html_e('Recent Wins', 'circleblast-nexus'); ?></h3>
				<div class="cbnexus-wins-grid">
					<?php foreach ($wins as $w) : ?>
						<div class="cbnexus-win-card">
							<span class="cbnexus-win-icon">🏆</span>
							<p><?php echo esc_html(wp_trim_words($w->content, 25)); ?></p>
							<?php if ($w->speaker_name) : ?><span class="cbnexus-text-muted">— <?php echo esc_html($w->speaker_name); ?></span><?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	// ─── Presentation Mode ─────────────────────────────────────────────

	private static function render_presentation(string $portal_url): void {
		$stats = self::compute_club_stats();
		$wins  = self::get_recent_wins(8);
		$top   = self::get_top_connectors(5);
		$qr_data = urlencode($portal_url);
		$back_url = add_query_arg('section', 'club', $portal_url);
		?>
		<div class="cbnexus-presentation" id="cbnexus-presentation">
			<a href="<?php echo esc_url($back_url); ?>" class="cbnexus-present-close">✕ <?php esc_html_e('Exit', 'circleblast-nexus'); ?></a>

			<div class="cbnexus-present-header">
				<h1><?php esc_html_e('CircleBlast', 'circleblast-nexus'); ?></h1>
				<p class="cbnexus-present-subtitle"><?php echo esc_html(date_i18n('F Y')); ?> <?php esc_html_e('Report', 'circleblast-nexus'); ?></p>
			</div>

			<div class="cbnexus-present-stats">
				<div class="cbnexus-present-stat"><span class="cbnexus-present-num"><?php echo esc_html($stats['total_members']); ?></span><span><?php esc_html_e('Members', 'circleblast-nexus'); ?></span></div>
				<div class="cbnexus-present-stat"><span class="cbnexus-present-num"><?php echo esc_html($stats['meetings_total']); ?></span><span><?php esc_html_e('Meetings', 'circleblast-nexus'); ?></span></div>
				<div class="cbnexus-present-stat"><span class="cbnexus-present-num"><?php echo esc_html($stats['network_density']); ?>%</span><span><?php esc_html_e('Connected', 'circleblast-nexus'); ?></span></div>
				<div class="cbnexus-present-stat"><span class="cbnexus-present-num"><?php echo esc_html($stats['wins_total']); ?></span><span><?php esc_html_e('Wins', 'circleblast-nexus'); ?></span></div>
			</div>

			<?php if (!empty($wins)) : ?>
			<div class="cbnexus-present-section">
				<h2>🏆 <?php esc_html_e('Wins', 'circleblast-nexus'); ?></h2>
				<div class="cbnexus-present-wins">
					<?php foreach ($wins as $w) : ?>
						<div class="cbnexus-present-win">
							<?php echo esc_html(wp_trim_words($w->content, 20)); ?>
							<?php if ($w->speaker_name) : ?><br/><em>— <?php echo esc_html($w->speaker_name); ?></em><?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>

			<?php if (!empty($top)) : ?>
			<div class="cbnexus-present-section">
				<h2>🌟 <?php esc_html_e('Top Connectors', 'circleblast-nexus'); ?></h2>
				<div class="cbnexus-present-leaders">
					<?php $rank = 0; foreach ($top as $t) : $rank++; ?>
						<div class="cbnexus-present-leader">
							<span class="cbnexus-present-rank <?php echo $rank <= 3 ? 'cbnexus-present-rank--top' : 'cbnexus-present-rank--other'; ?>"><?php echo $rank; ?></span>
							<?php echo esc_html($t->display_name); ?>
							<span class="cbnexus-present-leader-count">· <?php echo esc_html($t->meeting_count); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>

			<div class="cbnexus-present-footer">
				<div class="cbnexus-present-qr">
					<img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=<?php echo esc_attr($qr_data); ?>" alt="QR Code" width="120" height="120" />
				</div>
				<p><?php esc_html_e('Scan to open the portal', 'circleblast-nexus'); ?></p>
			</div>
		</div>
		<?php
	}

	// ─── Data Queries ──────────────────────────────────────────────────

	public static function compute_club_stats(): array {
		global $wpdb;

		$members = CBNexus_Member_Repository::get_all_members('active');
		$total   = count($members);

		$new = 0;
		$cutoff = gmdate('Y-m-d', strtotime('-90 days'));
		foreach ($members as $m) {
			if (($m['cb_join_date'] ?? '') >= $cutoff) { $new++; }
		}

		$meetings_total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}cb_meetings WHERE status IN ('completed', 'closed')"
		);

		$unique_pairs = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT CONCAT(LEAST(member_a_id, member_b_id), ':', GREATEST(member_a_id, member_b_id)))
			 FROM {$wpdb->prefix}cb_meetings WHERE status IN ('completed', 'closed')"
		);
		$possible = $total > 1 ? ($total * ($total - 1)) / 2 : 1;
		$density  = round($unique_pairs / $possible * 100);

		$circleup_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}cb_circleup_meetings WHERE status = 'published'"
		);

		$wins_total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}cb_circleup_items WHERE item_type = 'win' AND status = 'approved'"
		);

		return [
			'total_members'   => $total,
			'new_members'     => $new,
			'meetings_total'  => $meetings_total,
			'network_density' => $density,
			'circleup_count'  => $circleup_count,
			'wins_total'      => $wins_total,
		];
	}

	private static function get_top_connectors(int $limit): array {
		global $wpdb;
		return $wpdb->get_results($wpdb->prepare(
			"SELECT u.display_name, sub.meeting_count FROM (
				SELECT user_id, SUM(cnt) as meeting_count FROM (
					SELECT member_a_id as user_id, COUNT(*) as cnt FROM {$wpdb->prefix}cb_meetings WHERE status IN ('completed','closed') GROUP BY member_a_id
					UNION ALL
					SELECT member_b_id as user_id, COUNT(*) as cnt FROM {$wpdb->prefix}cb_meetings WHERE status IN ('completed','closed') GROUP BY member_b_id
				) t GROUP BY user_id
			) sub JOIN {$wpdb->users} u ON sub.user_id = u.ID
			ORDER BY sub.meeting_count DESC LIMIT %d",
			$limit
		)) ?: [];
	}

	private static function get_recent_wins(int $limit): array {
		global $wpdb;
		return $wpdb->get_results($wpdb->prepare(
			"SELECT i.content, u.display_name as speaker_name
			 FROM {$wpdb->prefix}cb_circleup_items i
			 LEFT JOIN {$wpdb->users} u ON i.speaker_id = u.ID
			 WHERE i.item_type = 'win' AND i.status = 'approved'
			 ORDER BY i.created_at DESC LIMIT %d",
			$limit
		)) ?: [];
	}

	private static function get_topic_cloud(): array {
		global $wpdb;
		$items = $wpdb->get_col(
			"SELECT content FROM {$wpdb->prefix}cb_circleup_items
			 WHERE status = 'approved' AND item_type IN ('insight', 'win')
			 ORDER BY created_at DESC LIMIT 100"
		);
		if (empty($items)) { return []; }

		$stop = array_flip(['the','a','an','is','was','are','were','be','been','and','or','but','in','on','at','to','for','of','with','it','this','that','from','by','as','has','had','have','we','our','they','their','i','my','you','your','not','no','so','if','do','did','can','will','would','about','into','just','also','more','some','than','very','all','what','which','when','how','there','been','each','other','out','up','its','new','one','two','well','got','get','way','been','make','like','them','then','over','back','only','come','could','after','first','these','going','being','where','most','made','know','down','time','work','year','need']);
		$freq = [];
		foreach ($items as $text) {
			$words = preg_split('/\W+/', strtolower($text));
			foreach ($words as $w) {
				if (strlen($w) < 4 || isset($stop[$w])) { continue; }
				$freq[$w] = ($freq[$w] ?? 0) + 1;
			}
		}
		arsort($freq);
		return array_slice($freq, 0, 20, true);
	}

	// ─── Analytics Snapshot (WP-Cron) ──────────────────────────────────

	public static function take_snapshot(): void {
		global $wpdb;
		$date  = gmdate('Y-m-d');
		$table = $wpdb->prefix . 'cb_analytics_snapshots';
		$now   = gmdate('Y-m-d H:i:s');

		$stats = self::compute_club_stats();
		$metrics = [
			'total_members'   => $stats['total_members'],
			'meetings_total'  => $stats['meetings_total'],
			'network_density' => $stats['network_density'],
			'wins_total'      => $stats['wins_total'],
		];

		foreach ($metrics as $key => $val) {
			$wpdb->replace($table, [
				'snapshot_date' => $date,
				'scope'         => 'club',
				'member_id'     => 0,
				'metric_key'    => $key,
				'metric_value'  => $val,
				'created_at'    => $now,
			], ['%s', '%s', '%d', '%s', '%f', '%s']);
		}
	}
}
