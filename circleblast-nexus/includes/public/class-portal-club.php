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

	/** Info tooltips for each member-facing stat card. */
	private static function stat_tooltips(): array {
		return [
			'members'   => 'Total active members in the group.',
			'meetings'  => 'Total 1:1 meetings completed across all members.',
			'connected' => 'Percentage of all possible member pairs who have actually met. Higher = stronger network.',
			'new'       => 'Members who joined in the last 90 days.',
			'circleups' => 'Published CircleUp group meeting summaries.',
			'wins'      => 'Total wins shared across all CircleUp meetings.',
		];
	}

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
		$tips = self::stat_tooltips();
		?>
		<div class="cbnexus-club-dash" id="cbnexus-club">
			<div class="cbnexus-club-header">
				<h2><?php esc_html_e('Club Overview', 'circleblast-nexus'); ?></h2>
				<a href="<?php echo esc_url($present_url); ?>" class="cbnexus-btn cbnexus-btn-gold cbnexus-btn-sm" target="_blank">🖥 <?php esc_html_e('Present', 'circleblast-nexus'); ?></a>
			</div>

			<div class="cbnexus-quick-stats" style="grid-template-columns:repeat(3,1fr);margin-bottom:16px;">
				<div class="cbnexus-stat-card cbnexus-stat-card--accent">
					<button type="button" class="cbnexus-info-btn" aria-label="Info" data-tooltip="<?php echo esc_attr($tips['members']); ?>">ⓘ</button>
					<span class="cbnexus-stat-value"><?php echo esc_html($stats['total_members']); ?></span><span class="cbnexus-stat-label"><?php esc_html_e('Members', 'circleblast-nexus'); ?></span>
				</div>
				<div class="cbnexus-stat-card">
					<button type="button" class="cbnexus-info-btn" aria-label="Info" data-tooltip="<?php echo esc_attr($tips['meetings']); ?>">ⓘ</button>
					<span class="cbnexus-stat-value"><?php echo esc_html($stats['meetings_total']); ?></span><span class="cbnexus-stat-label"><?php esc_html_e('1:1 Meetings', 'circleblast-nexus'); ?></span>
				</div>
				<div class="cbnexus-stat-card cbnexus-stat-card--green">
					<button type="button" class="cbnexus-info-btn" aria-label="Info" data-tooltip="<?php echo esc_attr($tips['connected']); ?>">ⓘ</button>
					<span class="cbnexus-stat-value"><?php echo esc_html($stats['network_density']); ?>%</span><span class="cbnexus-stat-label"><?php esc_html_e('Connected', 'circleblast-nexus'); ?></span>
				</div>
				<div class="cbnexus-stat-card">
					<button type="button" class="cbnexus-info-btn" aria-label="Info" data-tooltip="<?php echo esc_attr($tips['new']); ?>">ⓘ</button>
					<span class="cbnexus-stat-value"><?php echo esc_html($stats['new_members']); ?></span><span class="cbnexus-stat-label"><?php esc_html_e('New (90d)', 'circleblast-nexus'); ?></span>
				</div>
				<div class="cbnexus-stat-card">
					<button type="button" class="cbnexus-info-btn" aria-label="Info" data-tooltip="<?php echo esc_attr($tips['circleups']); ?>">ⓘ</button>
					<span class="cbnexus-stat-value"><?php echo esc_html($stats['circleup_count']); ?></span><span class="cbnexus-stat-label"><?php esc_html_e('CircleUps', 'circleblast-nexus'); ?></span>
				</div>
				<div class="cbnexus-stat-card cbnexus-stat-card--gold">
					<button type="button" class="cbnexus-info-btn" aria-label="Info" data-tooltip="<?php echo esc_attr($tips['wins']); ?>">ⓘ</button>
					<span class="cbnexus-stat-value"><?php echo esc_html($stats['wins_total']); ?></span><span class="cbnexus-stat-label"><?php esc_html_e('Wins', 'circleblast-nexus'); ?></span>
				</div>
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

			<?php self::render_coverage_card(); ?>

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

	// ─── Recruitment Coverage Card ────────────────────────────────────

	private static function render_coverage_card(): void {
		if (!class_exists('CBNexus_Recruitment_Coverage_Service')) {
			return;
		}

		$summary = CBNexus_Recruitment_Coverage_Service::get_summary();
		if ($summary['total'] === 0) {
			return;
		}

		$all      = CBNexus_Recruitment_Coverage_Service::get_full_coverage();
		$pipeline = CBNexus_Recruitment_Coverage_Service::get_pipeline_summary();
		$is_admin = current_user_can('cbnexus_manage_members');
		$portal_url = CBNexus_Portal_Router::get_portal_url();

		$status_icons  = ['covered' => '🟢', 'partial' => '🟡', 'gap' => '🔴'];
		$status_labels = ['covered' => 'Covered', 'partial' => 'Partial', 'gap' => 'Open'];
		?>
		<div class="cbnexus-card">
			<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:14px;">
				<h3 style="margin:0;">🎯 <?php esc_html_e('Recruitment Coverage', 'circleblast-nexus'); ?></h3>
				<?php if ($is_admin) : ?>
					<a href="<?php echo esc_url(add_query_arg(['section' => 'manage', 'admin_tab' => 'recruitment'], $portal_url)); ?>" class="cbnexus-link" style="font-size:13px;"><?php esc_html_e('Manage →', 'circleblast-nexus'); ?></a>
				<?php endif; ?>
			</div>

			<!-- Summary bar -->
			<div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;margin-bottom:16px;padding:12px 16px;background:var(--cb-bg-deep,#f8f6fa);border-radius:8px;">
				<div style="flex:1;min-width:120px;">
					<div style="font-size:28px;font-weight:800;color:var(--cb-text);"><?php echo esc_html($summary['coverage_pct']); ?>%</div>
					<div style="font-size:12px;color:var(--cb-text-sec);"><?php esc_html_e('Coverage', 'circleblast-nexus'); ?></div>
				</div>
				<div style="display:flex;gap:14px;">
					<div style="text-align:center;">
						<div style="font-size:20px;font-weight:700;color:#059669;"><?php echo esc_html($summary['covered']); ?></div>
						<div style="font-size:11px;color:var(--cb-text-ter);"><?php esc_html_e('Filled', 'circleblast-nexus'); ?></div>
					</div>
					<?php if ($summary['partial'] > 0) : ?>
					<div style="text-align:center;">
						<div style="font-size:20px;font-weight:700;color:#d97706;"><?php echo esc_html($summary['partial']); ?></div>
						<div style="font-size:11px;color:var(--cb-text-ter);"><?php esc_html_e('Partial', 'circleblast-nexus'); ?></div>
					</div>
					<?php endif; ?>
					<div style="text-align:center;">
						<div style="font-size:20px;font-weight:700;color:#dc2626;"><?php echo esc_html($summary['gaps']); ?></div>
						<div style="font-size:11px;color:var(--cb-text-ter);"><?php esc_html_e('Open', 'circleblast-nexus'); ?></div>
					</div>
					<div style="text-align:center;">
						<div style="font-size:20px;font-weight:700;color:#5b2d6e;"><?php echo esc_html($pipeline['total']); ?></div>
						<div style="font-size:11px;color:var(--cb-text-ter);"><?php esc_html_e('In Pipeline', 'circleblast-nexus'); ?></div>
					</div>
				</div>
			</div>

			<?php if ($pipeline['total'] > 0) :
				$stages = ['referral' => 'Referral', 'contacted' => 'Contacted', 'invited' => 'Invited', 'visited' => 'Visited', 'decision' => 'Decision'];
				$colors = ['#8b7a94', '#a78bba', '#7c5b99', '#5b2d6e', '#3d1a4a'];
			?>
			<!-- Pipeline breakdown -->
			<div style="margin-bottom:16px;padding:12px 16px;border:1px solid var(--cb-border,#e5e7eb);border-radius:8px;">
				<div style="font-size:13px;font-weight:600;margin-bottom:10px;">📋 <?php esc_html_e('Recruit Pipeline', 'circleblast-nexus'); ?></div>
				<div style="display:flex;gap:8px;flex-wrap:wrap;">
					<?php $ci = 0; foreach ($stages as $key => $label) : ?>
						<div style="flex:1;min-width:60px;text-align:center;padding:8px 4px;background:var(--cb-bg-deep,#f8f6fa);border-radius:6px;">
							<div style="font-size:18px;font-weight:700;color:<?php echo esc_attr($colors[$ci]); ?>;"><?php echo esc_html($pipeline[$key]); ?></div>
							<div style="font-size:10px;color:var(--cb-text-ter);"><?php echo esc_html($label); ?></div>
						</div>
					<?php $ci++; endforeach; ?>
				</div>
			</div>
			<?php endif; ?>

			<!-- Category grid -->
			<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px;">
				<?php foreach ($all as $cat) :
					$status = $cat->coverage_status ?? 'gap';
					$icon   = $status_icons[$status] ?? '🔴';
					$label  = $status_labels[$status] ?? 'Open';
				?>
					<div style="display:flex;align-items:center;gap:8px;padding:8px 12px;border:1px solid var(--cb-border,#e5e7eb);border-radius:8px;background:var(--cb-card,#fff);<?php echo $status === 'gap' ? 'border-style:dashed;opacity:0.75;' : ''; ?>">
						<span style="font-size:12px;flex-shrink:0;"><?php echo $icon; ?></span>
						<div style="flex:1;min-width:0;">
							<div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo esc_html($cat->title); ?></div>
							<?php if ($status !== 'gap' && !empty($cat->members)) : ?>
								<div style="font-size:11px;color:var(--cb-text-ter);">
									<?php echo esc_html(implode(', ', array_column($cat->members, 'display_name'))); ?>
								</div>
							<?php elseif ($status === 'gap') : ?>
								<div style="font-size:11px;color:var(--cb-text-ter);font-style:italic;"><?php echo esc_html($label); ?><?php if ($cat->priority === 'high') : ?> <span style="color:#dc2626;">●</span><?php endif; ?></div>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	// ─── Presentation Mode ─────────────────────────────────────────────

	private static function render_presentation(string $portal_url): void {
		$stats = self::compute_club_stats();
		$wins  = self::get_recent_wins(8);
		$top   = self::get_top_connectors(5);
		$topics = self::get_topic_cloud();
		$qr_data = urlencode($portal_url);
		$back_url = add_query_arg('section', 'club', $portal_url);

		// Get color scheme for inline presentation styles.
		$scheme = CBNexus_Color_Scheme::get_scheme();
		$accent    = $scheme['accent']    ?? '#2b5a94';
		$secondary = $scheme['secondary'] ?? '#f09214';
		$bg_deep   = CBNexus_Color_Scheme::get_full_palette()['--cb-bg-deep'] ?? '#e8e0ee';
		?>
		<style>
			/* Presentation mode — fully standalone, uses active color scheme */
			.cbnexus-present-wrap {
				position: fixed; inset: 0; z-index: 99999;
				background: linear-gradient(135deg,
					<?php echo esc_attr($accent); ?> 0%,
					<?php echo esc_attr(self::darken_hex($accent, 20)); ?> 50%,
					<?php echo esc_attr(self::darken_hex($accent, 35)); ?> 100%);
				color: #fff; overflow-y: auto;
				font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
			}
			.cbnexus-present-inner { max-width: 960px; margin: 0 auto; padding: 48px 32px; }
			.cbnexus-present-exit {
				position: fixed; top: 16px; right: 20px; z-index: 100000;
				background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.2);
				color: rgba(255,255,255,.8); font-size: 14px; padding: 8px 18px;
				border-radius: 10px; cursor: pointer; font-family: inherit; text-decoration: none;
				transition: background .2s;
			}
			.cbnexus-present-exit:hover { background: rgba(255,255,255,.22); color: #fff; }
			.cbnexus-present-title {
				text-align: center; font-size: clamp(40px, 6vw, 64px); font-weight: 800; margin: 0 0 4px;
				background: linear-gradient(90deg, <?php echo esc_attr($secondary); ?>, <?php echo esc_attr(self::lighten_hex($secondary, 25)); ?>, <?php echo esc_attr($secondary); ?>);
				-webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
			}
			.cbnexus-present-date { text-align: center; font-size: 20px; color: rgba(255,255,255,.5); margin: 0 0 40px; }
			.cbnexus-present-kpis { display: flex; justify-content: center; gap: 48px; flex-wrap: wrap; margin: 0 0 48px; }
			.cbnexus-present-kpi { text-align: center; }
			.cbnexus-present-kpi-num { display: block; font-size: clamp(40px, 5vw, 56px); font-weight: 800; color: <?php echo esc_attr($secondary); ?>; }
			.cbnexus-present-kpi-label { font-size: 15px; color: rgba(255,255,255,.5); text-transform: uppercase; letter-spacing: 1px; }
			.cbnexus-present-divider { border: 0; border-top: 1px solid rgba(255,255,255,.1); margin: 40px 0; }
			.cbnexus-present-sh { font-size: 28px; font-weight: 700; margin: 0 0 20px; }
			.cbnexus-present-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; }
			@media (max-width: 700px) { .cbnexus-present-cols { grid-template-columns: 1fr; } }
			.cbnexus-present-wgrid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 14px; }
			.cbnexus-present-wcard {
				background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1);
				border-radius: 14px; padding: 18px; font-size: 17px; line-height: 1.5;
			}
			.cbnexus-present-wcard em { color: rgba(255,255,255,.45); font-size: 13px; }
			.cbnexus-present-ldr { font-size: 20px; padding: 10px 0; display: flex; align-items: center; gap: 10px; }
			.cbnexus-present-ldr-rank {
				display: inline-flex; align-items: center; justify-content: center;
				width: 34px; height: 34px; border-radius: 10px; font-weight: 700; font-size: 16px; flex-shrink: 0;
			}
			.cbnexus-present-ldr-rank--top { background: rgba(255,255,255,.1); color: <?php echo esc_attr($secondary); ?>; }
			.cbnexus-present-ldr-rank--other { background: rgba(255,255,255,.04); color: rgba(255,255,255,.45); }
			.cbnexus-present-ldr-count { color: rgba(255,255,255,.4); margin-left: auto; }
			.cbnexus-present-tcl { display: flex; flex-wrap: wrap; gap: 8px; }
			.cbnexus-present-tag {
				padding: 6px 14px; border-radius: 20px; font-weight: 500;
				background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.1);
			}
			.cbnexus-present-tag--hi { background: rgba(<?php echo esc_attr(self::hex_to_rgb_str($secondary)); ?>, .15); color: <?php echo esc_attr($secondary); ?>; border-color: rgba(<?php echo esc_attr(self::hex_to_rgb_str($secondary)); ?>, .25); }
			.cbnexus-present-qrwrap { text-align: center; margin-top: 48px; padding-top: 32px; border-top: 1px solid rgba(255,255,255,.1); }
			.cbnexus-present-qrbox {
				width: 120px; height: 120px; margin: 0 auto 8px; border-radius: 14px;
				background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1);
				display: flex; align-items: center; justify-content: center; overflow: hidden;
			}
			.cbnexus-present-qrbox img { width: 100%; height: 100%; object-fit: contain; }
			.cbnexus-present-qrlabel { color: rgba(255,255,255,.4); font-size: 15px; }
		</style>
		<div class="cbnexus-present-wrap" id="cbnexus-present-wrap">
			<a href="<?php echo esc_url($back_url); ?>" class="cbnexus-present-exit">✕ Exit</a>
			<div class="cbnexus-present-inner">

				<h1 class="cbnexus-present-title">CircleBlast</h1>
				<p class="cbnexus-present-date"><?php echo esc_html(date_i18n('F Y')); ?> Report</p>

				<div class="cbnexus-present-kpis">
					<div class="cbnexus-present-kpi"><span class="cbnexus-present-kpi-num"><?php echo esc_html($stats['total_members']); ?></span><span class="cbnexus-present-kpi-label">Members</span></div>
					<div class="cbnexus-present-kpi"><span class="cbnexus-present-kpi-num"><?php echo esc_html($stats['meetings_total']); ?></span><span class="cbnexus-present-kpi-label">Meetings</span></div>
					<div class="cbnexus-present-kpi"><span class="cbnexus-present-kpi-num"><?php echo esc_html($stats['network_density']); ?>%</span><span class="cbnexus-present-kpi-label">Connected</span></div>
					<div class="cbnexus-present-kpi"><span class="cbnexus-present-kpi-num"><?php echo esc_html($stats['wins_total']); ?></span><span class="cbnexus-present-kpi-label">Wins</span></div>
				</div>

				<?php if (!empty($wins)) : ?>
				<hr class="cbnexus-present-divider" />
				<h2 class="cbnexus-present-sh">🏆 Wins</h2>
				<div class="cbnexus-present-wgrid">
					<?php foreach ($wins as $w) : ?>
						<div class="cbnexus-present-wcard">
							<?php echo esc_html(wp_trim_words($w->content, 20)); ?>
							<?php if ($w->speaker_name) : ?><br/><em>— <?php echo esc_html($w->speaker_name); ?></em><?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

				<hr class="cbnexus-present-divider" />
				<div class="cbnexus-present-cols">
					<?php if (!empty($top)) : ?>
					<div>
						<h2 class="cbnexus-present-sh">🌟 Top Connectors</h2>
						<?php $rank = 0; foreach ($top as $t) : $rank++; ?>
							<div class="cbnexus-present-ldr">
								<span class="cbnexus-present-ldr-rank <?php echo $rank <= 3 ? 'cbnexus-present-ldr-rank--top' : 'cbnexus-present-ldr-rank--other'; ?>"><?php echo $rank; ?></span>
								<span><?php echo esc_html($t->display_name); ?></span>
								<span class="cbnexus-present-ldr-count"><?php echo esc_html($t->meeting_count); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>

					<?php if (!empty($topics)) : ?>
					<div>
						<h2 class="cbnexus-present-sh">💬 Topics</h2>
						<div class="cbnexus-present-tcl">
							<?php $i = 0; foreach ($topics as $topic => $count) : ?>
								<span class="cbnexus-present-tag <?php echo $i < 5 ? 'cbnexus-present-tag--hi' : ''; ?>" style="font-size:<?php echo max(13, 19 - $i); ?>px;"><?php echo esc_html($topic); ?></span>
							<?php $i++; endforeach; ?>
						</div>
					</div>
					<?php endif; ?>
				</div>

				<div class="cbnexus-present-qrwrap">
					<div class="cbnexus-present-qrbox">
						<img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=<?php echo esc_attr($qr_data); ?>" alt="QR Code" width="120" height="120" />
					</div>
					<p class="cbnexus-present-qrlabel">Scan to open the portal</p>
				</div>

			</div>
		</div>
		<?php
	}

	/** Darken a hex color by a percentage. */
	private static function darken_hex(string $hex, int $pct): string {
		$hex = ltrim($hex, '#');
		$r = max(0, hexdec(substr($hex, 0, 2)) - (int) round(255 * $pct / 100));
		$g = max(0, hexdec(substr($hex, 2, 2)) - (int) round(255 * $pct / 100));
		$b = max(0, hexdec(substr($hex, 4, 2)) - (int) round(255 * $pct / 100));
		return sprintf('#%02x%02x%02x', $r, $g, $b);
	}

	/** Lighten a hex color by a percentage. */
	private static function lighten_hex(string $hex, int $pct): string {
		$hex = ltrim($hex, '#');
		$r = min(255, hexdec(substr($hex, 0, 2)) + (int) round(255 * $pct / 100));
		$g = min(255, hexdec(substr($hex, 2, 2)) + (int) round(255 * $pct / 100));
		$b = min(255, hexdec(substr($hex, 4, 2)) + (int) round(255 * $pct / 100));
		return sprintf('#%02x%02x%02x', $r, $g, $b);
	}

	/** Convert hex to "r,g,b" string for use in rgba(). */
	private static function hex_to_rgb_str(string $hex): string {
		$hex = ltrim($hex, '#');
		return hexdec(substr($hex, 0, 2)) . ',' . hexdec(substr($hex, 2, 2)) . ',' . hexdec(substr($hex, 4, 2));
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