<?php
/**
 * Portal Admin – Settings Tab (super-admin)
 *
 * Color scheme picker + system info.
 * Extracted from class-portal-admin.php for maintainability.
 */

defined('ABSPATH') || exit;

final class CBNexus_Portal_Admin_Settings {

	public static function render(): void {
		if (!current_user_can('cbnexus_manage_plugin_settings')) {
			echo '<div class="cbnexus-card"><p>Permission denied.</p></div>';
			return;
		}

		$notice = sanitize_key($_GET['pa_notice'] ?? '');
		CBNexus_Portal_Admin::render_notice($notice);

		$scheme = CBNexus_Color_Scheme::get_scheme();
		$presets = CBNexus_Color_Scheme::PRESETS;
		$active_preset = $scheme['preset'] ?? 'circleblast';
		$logo_url = CBNexus_Color_Scheme::get_logo_url('small');
		?>

		<!-- Color Scheme Picker -->
		<div class="cbnexus-card">
			<h2>🎨 Color Scheme</h2>
			<p class="cbnexus-admin-meta" style="margin:0 0 16px;">Choose a color scheme for the member portal and emails. Changes apply immediately to all portal pages and future emails.</p>

			<form method="post" id="cbnexus-scheme-form">
				<?php wp_nonce_field('cbnexus_portal_save_color_scheme', '_panonce_scheme'); ?>

				<!-- Preset Selector -->
				<div class="cbnexus-scheme-grid">
					<?php foreach ($presets as $key => $preset) :
						if ($key === 'custom') continue;
						$is_active = ($active_preset === $key);
					?>
					<label class="cbnexus-scheme-option <?php echo $is_active ? 'active' : ''; ?>" data-preset="<?php echo esc_attr($key); ?>">
						<input type="radio" name="scheme_preset" value="<?php echo esc_attr($key); ?>" <?php checked($active_preset, $key); ?> style="display:none;" />
						<div class="cbnexus-scheme-preview">
							<div class="cbnexus-scheme-bar" style="background:<?php echo esc_attr($preset['accent']); ?>;"></div>
							<div class="cbnexus-scheme-swatches">
								<span class="cbnexus-scheme-swatch" style="background:<?php echo esc_attr($preset['accent']); ?>;" title="Primary"></span>
								<span class="cbnexus-scheme-swatch" style="background:<?php echo esc_attr($preset['secondary']); ?>;" title="Secondary"></span>
								<span class="cbnexus-scheme-swatch" style="background:<?php echo esc_attr($preset['green']); ?>;" title="Green"></span>
								<span class="cbnexus-scheme-swatch" style="background:<?php echo esc_attr($preset['bg']); ?>;border:1px solid #ddd;" title="Background"></span>
							</div>
						</div>
						<div class="cbnexus-scheme-label"><?php echo esc_html($preset['label']); ?></div>
						<?php if ($is_active) : ?><span class="cbnexus-scheme-active-badge">Active</span><?php endif; ?>
					</label>
					<?php endforeach; ?>

					<!-- Custom option -->
					<label class="cbnexus-scheme-option <?php echo $active_preset === 'custom' ? 'active' : ''; ?>" data-preset="custom">
						<input type="radio" name="scheme_preset" value="custom" <?php checked($active_preset, 'custom'); ?> style="display:none;" />
						<div class="cbnexus-scheme-preview cbnexus-scheme-custom-preview">
							<div class="cbnexus-scheme-bar" style="background:linear-gradient(90deg, <?php echo esc_attr($scheme['accent']); ?>, <?php echo esc_attr($scheme['secondary']); ?>);"></div>
							<div style="text-align:center;padding:8px;font-size:20px;">🎨</div>
						</div>
						<div class="cbnexus-scheme-label">Custom</div>
						<?php if ($active_preset === 'custom') : ?><span class="cbnexus-scheme-active-badge">Active</span><?php endif; ?>
					</label>
				</div>

				<!-- Custom Colors Panel (shown when "Custom" is selected) -->
				<div id="cbnexus-custom-colors" style="<?php echo $active_preset === 'custom' ? '' : 'display:none;'; ?>margin-top:20px;">
					<div class="cbnexus-card" style="background:var(--cb-bg-deep);margin:0;">
						<h3 style="margin:0 0 12px;">Custom Colors</h3>
						<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;">
							<?php
							$color_fields = [
								'accent'    => 'Primary Color',
								'secondary' => 'Secondary / Accent',
								'green'     => 'Success Green',
								'blue'      => 'Info Blue',
								'red'       => 'Danger Red',
								'bg'        => 'Background',
								'text'      => 'Text Color',
							];
							foreach ($color_fields as $field => $label) :
								$val = $scheme[$field] ?? ($presets['circleblast'][$field] ?? '#333333');
							?>
							<div>
								<label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;"><?php echo esc_html($label); ?></label>
								<div style="display:flex;align-items:center;gap:8px;">
									<input type="color" name="scheme_<?php echo esc_attr($field); ?>" value="<?php echo esc_attr($val); ?>" class="cbnexus-color-input" data-field="<?php echo esc_attr($field); ?>" />
									<input type="text" value="<?php echo esc_attr($val); ?>" class="cbnexus-input cbnexus-color-hex" data-field="<?php echo esc_attr($field); ?>" style="width:90px;font-family:monospace;font-size:13px;" maxlength="7" pattern="#[0-9a-fA-F]{6}" />
								</div>
							</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>

				<!-- Live Preview -->
				<div id="cbnexus-scheme-preview-area" style="margin-top:20px;">
					<h3 style="margin:0 0 12px;">Live Preview</h3>
					<div class="cbnexus-scheme-live-preview" id="cbnexus-live-preview">
						<div class="cbnexus-slp-header" id="slp-header">
							<img src="<?php echo esc_url($logo_url); ?>" width="28" height="28" style="border-radius:4px;" />
							<span style="font-weight:700;font-size:13px;" id="slp-brand-text">CircleBlast</span>
						</div>
						<div class="cbnexus-slp-body" id="slp-body">
							<div class="cbnexus-slp-nav">
								<span class="cbnexus-slp-pill active" id="slp-pill-active">Dashboard</span>
								<span class="cbnexus-slp-pill" id="slp-pill-inactive">Directory</span>
								<span class="cbnexus-slp-pill" id="slp-pill-inactive2">Meetings</span>
							</div>
							<div class="cbnexus-slp-card" id="slp-card">
								<div style="font-weight:600;margin-bottom:6px;" id="slp-card-title">Welcome back!</div>
								<div style="font-size:12px;opacity:0.7;" id="slp-card-meta">Your next meeting is in 3 days</div>
								<div style="margin-top:10px;display:flex;gap:8px;">
									<span class="cbnexus-slp-btn" id="slp-btn-primary">View Details</span>
									<span class="cbnexus-slp-btn-sec" id="slp-btn-secondary">Schedule</span>
								</div>
								<div style="margin-top:10px;display:flex;gap:6px;">
									<span class="cbnexus-slp-badge green" id="slp-badge-green">Active</span>
									<span class="cbnexus-slp-badge gold" id="slp-badge-gold">Pending</span>
									<span class="cbnexus-slp-badge red" id="slp-badge-red">Declined</span>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div style="margin-top:16px;">
					<button type="submit" name="cbnexus_portal_save_color_scheme" value="1" class="cbnexus-btn cbnexus-btn-primary">💾 Save Color Scheme</button>
				</div>
			</form>
		</div>

		<!-- Plugin Info (moved below) -->
		<div class="cbnexus-card">
			<h2>System Settings</h2>

			<div class="cbnexus-admin-form-stack">
				<div>
					<h3>Plugin Info</h3>
					<table class="cbnexus-admin-kv-table">
						<tr><td>Version</td><td><strong><?php echo esc_html(CBNEXUS_VERSION); ?></strong></td></tr>
						<tr><td>PHP</td><td><?php echo esc_html(PHP_VERSION); ?></td></tr>
						<tr><td>WordPress</td><td><?php echo esc_html(get_bloginfo('version')); ?></td></tr>
						<tr><td>Database Prefix</td><td><code><?php global $wpdb; echo esc_html($wpdb->prefix); ?></code></td></tr>
					</table>
				</div>

				<div>
					<h3>Cron Jobs</h3>
					<p class="cbnexus-admin-meta" style="margin:0 0 12px;">Adjust how often each automated task runs. Changes take effect immediately.</p>
					<form method="post">
						<?php wp_nonce_field('cbnexus_portal_save_cron_schedules', '_panonce_cron'); ?>
						<table class="cbnexus-admin-table cbnexus-admin-table-sm">
							<thead><tr>
								<th>Task</th>
								<th>Frequency</th>
								<th>Next Run</th>
							</tr></thead>
							<tbody>
							<?php
							$crons = self::get_cron_definitions();
							$saved_crons = get_option('cbnexus_cron_schedules', []);
							foreach ($crons as $hook => $def) :
								$current_freq = $saved_crons[$hook] ?? $def['default'];
								$next = wp_next_scheduled($hook);
								$next_str = $next ? date_i18n('M j, g:i a', $next) : 'Not scheduled';
							?>
								<tr>
									<td>
										<strong><?php echo esc_html($def['label']); ?></strong>
										<div class="cbnexus-admin-meta"><?php echo esc_html($def['description']); ?></div>
									</td>
									<td>
										<select name="cron_schedule[<?php echo esc_attr($hook); ?>]" class="cbnexus-input cbnexus-input-sm" style="min-width:130px;">
											<?php foreach ($def['options'] as $val => $lbl) : ?>
												<option value="<?php echo esc_attr($val); ?>" <?php selected($current_freq, $val); ?>><?php echo esc_html($lbl); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
									<td class="cbnexus-admin-meta"><?php echo esc_html($next_str); ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
						<div style="margin-top:12px;">
							<button type="submit" name="cbnexus_portal_save_cron_schedules" value="1" class="cbnexus-btn cbnexus-btn-primary">💾 Save Cron Schedules</button>
						</div>
					</form>
				</div>

				<div>
					<h3>API Keys</h3>
					<table class="cbnexus-admin-kv-table">
						<tr>
							<td>Claude API Key</td>
							<td><?php echo defined('CBNEXUS_CLAUDE_API_KEY') ? '<span class="cbnexus-status-pill cbnexus-status-green">Configured</span>' : '<span class="cbnexus-status-pill cbnexus-status-gold">Not set</span>'; ?></td>
						</tr>
						<tr>
							<td>Fireflies Secret</td>
							<td><?php echo defined('CBNEXUS_FIREFLIES_SECRET') ? '<span class="cbnexus-status-pill cbnexus-status-green">Configured</span>' : '<span class="cbnexus-status-pill cbnexus-status-gold">Not set (dev mode)</span>'; ?></td>
						</tr>
					</table>
					<p class="cbnexus-admin-meta" style="margin-top:8px;">API keys are configured in wp-config.php per security policy. They cannot be changed from this interface.</p>
				</div>
			</div>
		</div>

		<script>
		(function() {
			var presets = <?php echo wp_json_encode($presets); ?>;
			var options = document.querySelectorAll('.cbnexus-scheme-option');
			var customPanel = document.getElementById('cbnexus-custom-colors');
			var colorInputs = document.querySelectorAll('.cbnexus-color-input');
			var hexInputs = document.querySelectorAll('.cbnexus-color-hex');

			// Click handler for preset cards
			options.forEach(function(opt) {
				opt.addEventListener('click', function() {
					options.forEach(function(o) { o.classList.remove('active'); });
					opt.classList.add('active');
					opt.querySelector('input[type=radio]').checked = true;

					var preset = opt.dataset.preset;
					customPanel.style.display = preset === 'custom' ? '' : 'none';

					if (preset !== 'custom' && presets[preset]) {
						updatePreview(presets[preset]);
						// Also update custom color inputs to match the preset
						Object.keys(presets[preset]).forEach(function(k) {
							var ci = document.querySelector('.cbnexus-color-input[data-field="'+k+'"]');
							var hi = document.querySelector('.cbnexus-color-hex[data-field="'+k+'"]');
							if (ci) { ci.value = presets[preset][k]; }
							if (hi) { hi.value = presets[preset][k]; }
						});
					}
				});
			});

			// Sync color picker ↔ hex input
			colorInputs.forEach(function(ci) {
				ci.addEventListener('input', function() {
					var hi = document.querySelector('.cbnexus-color-hex[data-field="'+ci.dataset.field+'"]');
					if (hi) hi.value = ci.value;
					updatePreviewFromInputs();
				});
			});
			hexInputs.forEach(function(hi) {
				hi.addEventListener('input', function() {
					if (/^#[0-9a-fA-F]{6}$/.test(hi.value)) {
						var ci = document.querySelector('.cbnexus-color-input[data-field="'+hi.dataset.field+'"]');
						if (ci) ci.value = hi.value;
						updatePreviewFromInputs();
					}
				});
			});

			function updatePreviewFromInputs() {
				var colors = {};
				colorInputs.forEach(function(ci) { colors[ci.dataset.field] = ci.value; });
				updatePreview(colors);
			}

			function updatePreview(c) {
				var h = document.getElementById('slp-header');
				var body = document.getElementById('slp-body');
				var pill = document.getElementById('slp-pill-active');
				var btn = document.getElementById('slp-btn-primary');
				var btnSec = document.getElementById('slp-btn-secondary');
				var badgeGreen = document.getElementById('slp-badge-green');
				var badgeGold = document.getElementById('slp-badge-gold');
				var badgeRed = document.getElementById('slp-badge-red');
				var brand = document.getElementById('slp-brand-text');
				var card = document.getElementById('slp-card');
				var cardTitle = document.getElementById('slp-card-title');
				var cardMeta = document.getElementById('slp-card-meta');

				if (h) h.style.background = c.accent || c['accent'];
				if (brand) brand.style.color = '#fff';
				if (body) body.style.background = c.bg || '#f0f4f8';
				if (card) card.style.background = '#ffffff';
				if (cardTitle) cardTitle.style.color = c.text || '#1e2a3a';
				if (cardMeta) cardMeta.style.color = c.text || '#1e2a3a';
				if (pill) { pill.style.background = c.accent; pill.style.color = '#fff'; }
				if (btn) { btn.style.background = c.accent; btn.style.color = '#fff'; }
				if (btnSec) { btnSec.style.background = c.secondary; btnSec.style.color = '#fff'; }
				if (badgeGreen) badgeGreen.style.background = tint(c.green || '#3d8b4d', 0.85);
				if (badgeGreen) badgeGreen.style.color = c.green || '#3d8b4d';
				if (badgeGold) badgeGold.style.background = tint(c.secondary || '#f09214', 0.85);
				if (badgeGold) badgeGold.style.color = c.secondary || '#f09214';
				if (badgeRed) badgeRed.style.background = tint(c.red || '#c44040', 0.85);
				if (badgeRed) badgeRed.style.color = c.red || '#c44040';
			}

			function tint(hex, ratio) {
				var r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16);
				r = Math.round(r + (255-r)*ratio); g = Math.round(g + (255-g)*ratio); b = Math.round(b + (255-b)*ratio);
				return '#' + [r,g,b].map(function(v) { return ('0'+Math.min(255,v).toString(16)).slice(-2); }).join('');
			}
		})();
		</script>
		<?php
	}

	/**
	 * Cron job definitions: hook => label, description, default frequency, allowed options.
	 */
	private static function get_cron_definitions(): array {
		$freq_daily_weekly_monthly = [
			'daily'   => 'Daily',
			'weekly'  => 'Weekly',
			'monthly' => 'Monthly',
		];
		$freq_daily_weekly = [
			'daily'  => 'Daily',
			'weekly' => 'Weekly',
		];
		$freq_hourly_daily = [
			'hourly' => 'Hourly',
			'daily'  => 'Daily',
		];

		return [
			'cbnexus_log_cleanup' => [
				'label'       => 'Log Cleanup',
				'description' => 'Removes plugin log entries older than 30 days.',
				'default'     => 'daily',
				'options'     => $freq_daily_weekly,
			],
			'cbnexus_meeting_reminders' => [
				'label'       => 'Meeting Reminders',
				'description' => 'Sends reminder emails for upcoming 1:1 meetings.',
				'default'     => 'daily',
				'options'     => $freq_hourly_daily,
			],
			'cbnexus_suggestion_cycle' => [
				'label'       => 'Suggestion Cycle',
				'description' => 'Runs the matching engine and sends new 1:1 suggestions.',
				'default'     => 'monthly',
				'options'     => $freq_daily_weekly_monthly,
			],
			'cbnexus_suggestion_reminders' => [
				'label'       => 'Suggestion Reminders',
				'description' => 'Sends follow-up reminders for unanswered suggestions.',
				'default'     => 'weekly',
				'options'     => $freq_daily_weekly,
			],
			'cbnexus_ai_extraction' => [
				'label'       => 'AI Extraction',
				'description' => 'Processes new CircleUp transcripts through the AI pipeline.',
				'default'     => 'daily',
				'options'     => $freq_hourly_daily,
			],
			'cbnexus_analytics_snapshot' => [
				'label'       => 'Analytics Snapshot',
				'description' => 'Captures club metrics for trend tracking.',
				'default'     => 'daily',
				'options'     => $freq_daily_weekly,
			],
			'cbnexus_monthly_report' => [
				'label'       => 'Monthly Report',
				'description' => 'Sends analytics summary email to admins.',
				'default'     => 'monthly',
				'options'     => $freq_daily_weekly_monthly,
			],
			'cbnexus_event_reminders' => [
				'label'       => 'Event Reminders',
				'description' => 'Sends reminders for upcoming events.',
				'default'     => 'daily',
				'options'     => $freq_hourly_daily,
			],
			'cbnexus_events_digest' => [
				'label'       => 'Events Digest',
				'description' => 'Sends a digest of upcoming events to members.',
				'default'     => 'weekly',
				'options'     => $freq_daily_weekly_monthly,
			],
			'cbnexus_token_cleanup' => [
				'label'       => 'Token Cleanup',
				'description' => 'Removes expired authentication tokens.',
				'default'     => 'daily',
				'options'     => $freq_daily_weekly,
			],
		];
	}

	/**
	 * Handle saving cron schedules.
	 */
	public static function handle_save_cron_schedules(): void {
		if (!isset($_POST['cbnexus_portal_save_cron_schedules'])) { return; }
		if (!wp_verify_nonce(wp_unslash($_POST['_panonce_cron'] ?? ''), 'cbnexus_portal_save_cron_schedules')) { return; }
		if (!current_user_can('cbnexus_manage_plugin_settings')) { return; }

		$defs  = self::get_cron_definitions();
		$input = $_POST['cron_schedule'] ?? [];
		$saved = get_option('cbnexus_cron_schedules', []);

		foreach ($defs as $hook => $def) {
			$new_freq = sanitize_key($input[$hook] ?? $def['default']);

			// Validate against allowed options.
			if (!isset($def['options'][$new_freq])) {
				$new_freq = $def['default'];
			}

			$old_freq = $saved[$hook] ?? $def['default'];

			if ($new_freq !== $old_freq) {
				// Unschedule the old event and reschedule with new frequency.
				$ts = wp_next_scheduled($hook);
				if ($ts) {
					wp_unschedule_event($ts, $hook);
				}
				wp_schedule_event(time(), $new_freq, $hook);
			}

			$saved[$hook] = $new_freq;
		}

		update_option('cbnexus_cron_schedules', $saved, false);

		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('settings', ['pa_notice' => 'cron_saved']));
		exit;
	}

	public static function handle_save_color_scheme(): void {
		if (!wp_verify_nonce(wp_unslash($_POST['_panonce_scheme'] ?? ''), 'cbnexus_portal_save_color_scheme')) { return; }
		if (!current_user_can('cbnexus_manage_plugin_settings')) { return; }

		$preset = sanitize_key($_POST['scheme_preset'] ?? 'circleblast');
		$presets = CBNexus_Color_Scheme::PRESETS;

		if ($preset === 'custom') {
			$data = ['preset' => 'custom'];
			$fields = ['accent', 'secondary', 'green', 'blue', 'red', 'bg', 'text'];
			foreach ($fields as $f) {
				$val = sanitize_hex_color($_POST['scheme_' . $f] ?? '');
				$data[$f] = $val ?: ($presets['circleblast'][$f] ?? '#333333');
			}
		} elseif (isset($presets[$preset])) {
			$data = array_merge(['preset' => $preset], $presets[$preset]);
		} else {
			$data = array_merge(['preset' => 'circleblast'], $presets['circleblast']);
		}

		CBNexus_Color_Scheme::save_scheme($data);

		wp_safe_redirect(CBNexus_Portal_Admin::admin_url('settings', ['pa_notice' => 'scheme_saved']));
		exit;
	}
}