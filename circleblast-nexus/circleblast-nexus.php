<?php
/**
 * Plugin Name: CircleBlast Nexus
 * Description: CircleBlast Nexus WordPress plugin (CircleBlast project).
 * Version: 0.1.0
 * Author: CircleBlast
 * Text Domain: circleblast-nexus
 */

defined('ABSPATH') || exit;

if (!defined('CBNEXUS_VERSION')) {
	define('CBNEXUS_VERSION', '0.1.0');
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

/**
 * Minimal includes for Iteration 2.
 * (Keep includes explicit and small; no autoloader yet.)
 */
require_once CBNEXUS_PLUGIN_DIR . 'includes/logging/class-logger.php';
require_once CBNEXUS_PLUGIN_DIR . 'includes/migrations/class-migration-runner.php';

/**
 * Activation: run migrations (activation-only policy, approved).
 */
function cbnexus_activate(): void {
	if (class_exists('CBNexus_Migration_Runner')) {
		CBNexus_Migration_Runner::run();
	}
}
register_activation_hook(__FILE__, 'cbnexus_activate');

/**
 * Deactivation placeholder (keep for future use; do not remove behavior).
 */
function cbnexus_deactivate(): void {
	// Intentionally left minimal.
}
register_deactivation_hook(__FILE__, 'cbnexus_deactivate');
