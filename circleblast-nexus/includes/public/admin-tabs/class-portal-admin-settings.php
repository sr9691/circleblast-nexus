<?php
/**
 * Portal Admin – Settings Tab (super-admin)
 *
 * Extracted from class-portal-admin.php for maintainability.
 */

defined('ABSPATH') || exit;

final class CBNexus_Portal_Admin_Settings {

	public static function render(): void {
		if (!current_user_can('cbnexus_manage_plugin_settings')) {
			echo '<div class="cbnexus-card"><p>Permission denied.</p></div>';
			return;
		}
		?>
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
					<table class="cbnexus-admin-kv-table">
						<?php
						$crons = [
							'cbnexus_log_cleanup'          => 'Log Cleanup',
							'cbnexus_meeting_reminders'    => 'Meeting Reminders',
							'cbnexus_suggestion_cycle'     => 'Suggestion Cycle',
							'cbnexus_suggestion_reminders' => 'Suggestion Reminders',
							'cbnexus_ai_extraction'        => 'AI Extraction',
							'cbnexus_analytics_snapshot'   => 'Analytics Snapshot',
							'cbnexus_monthly_report'       => 'Monthly Report',
							'cbnexus_event_reminders'      => 'Event Reminders',
							'cbnexus_events_digest'        => 'Events Digest',
							'cbnexus_token_cleanup'        => 'Token Cleanup',
						];
						foreach ($crons as $hook => $label) :
							$next = wp_next_scheduled($hook);
							$next_str = $next ? date_i18n('M j, g:i a', $next) : 'Not scheduled';
						?>
							<tr>
								<td><?php echo esc_html($label); ?></td>
								<td class="cbnexus-admin-meta"><?php echo esc_html($next_str); ?></td>
							</tr>
						<?php endforeach; ?>
					</table>
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
		<?php
	}
}
