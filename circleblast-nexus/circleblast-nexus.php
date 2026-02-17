<?php
/**
 * Plugin Name: CircleBlast Nexus
 * Description: CircleBlast Nexus WordPress plugin (CircleBlast project).
 * Version: 0.2.0
 * Author: CircleBlast
 * Text Domain: circleblast-nexus
 * Requires PHP: 7.4
 * Requires at least: 5.9
 */

defined('ABSPATH') || exit;

if (!defined('CBNEXUS_VERSION')) {
	define('CBNEXUS_VERSION', '0.2.0');
}
if (!defined('CBNEXUS_PLUGIN_FILE')) {
	define('CBNEXUS_PLUGIN_FILE', __FILE__);
}
if (!defined('CBNEXUS_PLUGIN_DIR')) {
	define('CBNEXUS_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('CBNEXUS_PLUGIN_URL')) {
	define('CBNEXUS_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('CBNEXUS_OPTION_MIGRATIONS')) {
	define('CBNEXUS_OPTION_MIGRATIONS', 'cbnexus_migrations');
}
if (!defined('CBNEXUS_MIN_PHP')) {
	define('CBNEXUS_MIN_PHP', '7.4');
}
if (!defined('CBNEXUS_MIN_WP')) {
	define('CBNEXUS_MIN_WP', '5.9');
}

/**
 * Compatibility check — runs early, before any class loading.
 *
 * @return bool True if environment meets requirements.
 */
function cbnexus_meets_requirements(): bool {
	if (version_compare(PHP_VERSION, CBNEXUS_MIN_PHP, '<')) {
		return false;
	}
	if (version_compare(get_bloginfo('version'), CBNEXUS_MIN_WP, '<')) {
		return false;
	}
	return true;
}

/**
 * Show admin notice if requirements are not met.
 */
function cbnexus_requirements_notice(): void {
	$message = sprintf(
		/* translators: 1: required PHP version, 2: required WP version, 3: current PHP version, 4: current WP version */
		__('CircleBlast Nexus requires PHP %1$s+ and WordPress %2$s+. You are running PHP %3$s and WordPress %4$s. The plugin has been deactivated.', 'circleblast-nexus'),
		CBNEXUS_MIN_PHP,
		CBNEXUS_MIN_WP,
		PHP_VERSION,
		get_bloginfo('version')
	);
	printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($message));
}

// Bail early if requirements are not met.
if (!cbnexus_meets_requirements()) {
	add_action('admin_notices', 'cbnexus_requirements_notice');
	return;
}

/**
 * ITER-0003: Autoloader replaces explicit require_once calls.
 * The autoloader itself is the only file we require manually.
 */
require_once CBNEXUS_PLUGIN_DIR . 'includes/class-autoloader.php';
CBNexus_Autoloader::register();

/**
 * Register cron hooks.
 */
add_action('cbnexus_log_cleanup', ['CBNexus_Log_Retention', 'cleanup']);
add_action('cbnexus_meeting_reminders', ['CBNexus_Meeting_Service', 'send_reminders']);

/**
 * Initialize admin features when in admin context.
 */
if (is_admin()) {
	CBNexus_Admin_Logs::init();
	CBNexus_Admin_Members::init();
	CBNexus_Admin_Member_Form::init();
}

/**
 * ITER-0006: Initialize public portal (shortcode, access control, profile form).
 */
CBNexus_Portal_Router::init();
CBNexus_Portal_Profile::init();
CBNexus_Directory::init();
CBNexus_Portal_Meetings::init();

/**
 * Activation: run migrations and schedule cron (activation-only policy, approved).
 */
function cbnexus_activate(): void {
	// Re-check requirements on activation.
	if (!cbnexus_meets_requirements()) {
		deactivate_plugins(plugin_basename(CBNEXUS_PLUGIN_FILE));
		wp_die(
			esc_html(sprintf(
				__('CircleBlast Nexus requires PHP %1$s+ and WordPress %2$s+.', 'circleblast-nexus'),
				CBNEXUS_MIN_PHP,
				CBNEXUS_MIN_WP
			)),
			__('Plugin Activation Error', 'circleblast-nexus'),
			['back_link' => true]
		);
	}

	if (class_exists('CBNexus_Migration_Runner')) {
		CBNexus_Migration_Runner::run();
	}

	if (class_exists('CBNexus_Log_Retention')) {
		CBNexus_Log_Retention::schedule();
	}

	// ITER-0009: Schedule daily meeting reminders.
	if (!wp_next_scheduled('cbnexus_meeting_reminders')) {
		wp_schedule_event(time(), 'daily', 'cbnexus_meeting_reminders');
	}
}

register_activation_hook(__FILE__, 'cbnexus_activate');

/**
 * Deactivation: unschedule cron jobs.
 */
function cbnexus_deactivate(): void {
	if (class_exists('CBNexus_Log_Retention')) {
		CBNexus_Log_Retention::unschedule();
	}
	wp_clear_scheduled_hook('cbnexus_meeting_reminders');
}

register_deactivation_hook(__FILE__, 'cbnexus_deactivate');
