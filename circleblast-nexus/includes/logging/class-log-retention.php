<?php
/**
 * Log Retention
 *
 * Iteration 3: Scheduled cleanup (WP-Cron), no UI/settings.
 */

defined('ABSPATH') || exit;

final class CBNexus_Log_Retention {

	public const CRON_HOOK = 'cbnexus_log_cleanup';
	public const RETENTION_DAYS = 30;

	/**
	 * Schedule daily cleanup if not already scheduled.
	 */
	public static function schedule(): void {
		if (wp_next_scheduled(self::CRON_HOOK)) {
			return;
		}

		// Run shortly after activation (next minute), then daily.
		wp_schedule_event(time() + 60, 'daily', self::CRON_HOOK);
	}

	/**
	 * Unschedule cleanup on deactivation.
	 */
	public static function unschedule(): void {
		$ts = wp_next_scheduled(self::CRON_HOOK);
		if ($ts) {
			wp_unschedule_event($ts, self::CRON_HOOK);
		}
	}

	/**
	 * Delete log rows older than retention window. Never throws.
	 */
	public static function cleanup(): void {
		try {
			global $wpdb;

			$table = $wpdb->prefix . 'cbnexus_log';

			$found = $wpdb->get_var($wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$table
			));

			if ($found !== $table) {
				return;
			}

			$cutoff_gmt = gmdate('Y-m-d H:i:s', time() - (self::RETENTION_DAYS * DAY_IN_SECONDS));

			// created_at_gmt is indexed (Migration 001).
			$wpdb->query($wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at_gmt < %s",
				$cutoff_gmt
			));
		} catch (Throwable $e) {
			// Do not break cron; best-effort only.
			if (class_exists('CBNexus_Logger')) {
				CBNexus_Logger::warning('Log cleanup failed.', [
					'exception_class' => get_class($e),
				], 'circleblast-nexus');
			}
		}
	}
}
