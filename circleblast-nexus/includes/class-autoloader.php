<?php
/**
 * Autoloader
 *
 * ITER-0003: Simple class-map autoloader for CBNexus_ prefixed classes.
 * Replaces explicit require_once calls and scales as modules are added.
 *
 * Usage: CBNexus_Autoloader::register();
 *
 * Adding a new class:
 *   1. Add an entry to the $class_map array below.
 *   2. Key = fully-qualified class name, value = path relative to plugin dir.
 */

defined('ABSPATH') || exit;

final class CBNexus_Autoloader {

	/**
	 * Map of class names to file paths (relative to CBNEXUS_PLUGIN_DIR).
	 *
	 * @var array<string, string>
	 */
	private static $class_map = [
		// Logging
		'CBNexus_Logger'            => 'includes/logging/class-logger.php',
		'CBNexus_Log_Retention'     => 'includes/logging/class-log-retention.php',

		// Migrations
		'CBNexus_Migration_Runner'  => 'includes/migrations/class-migration-runner.php',

		// Admin
		'CBNexus_Admin_Logs'        => 'includes/admin/class-admin-logs.php',
		'CBNexus_Admin_Members'     => 'includes/admin/class-admin-members.php',
		'CBNexus_Admin_Member_Form' => 'includes/admin/class-admin-member-form.php',

		// Members (ITER-0004)
		'CBNexus_Member_Repository' => 'includes/members/class-member-repository.php',
		'CBNexus_Member_Service'    => 'includes/members/class-member-service.php',

		// Emails (ITER-0005)
		'CBNexus_Email_Service'     => 'includes/emails/class-email-service.php',

		// Public Portal (ITER-0006)
		'CBNexus_Portal_Router'     => 'includes/public/class-portal-router.php',
		'CBNexus_Portal_Profile'    => 'includes/public/class-portal-profile.php',
		'CBNexus_Directory'         => 'includes/public/class-directory.php',
	];

	/**
	 * Whether the autoloader has been registered.
	 *
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Register the autoloader with spl_autoload_register.
	 */
	public static function register(): void {
		if (self::$registered) {
			return;
		}

		spl_autoload_register([__CLASS__, 'load']);
		self::$registered = true;
	}

	/**
	 * Autoload callback.
	 *
	 * @param string $class Fully-qualified class name.
	 */
	public static function load(string $class): void {
		if (!isset(self::$class_map[$class])) {
			return;
		}

		$file = CBNEXUS_PLUGIN_DIR . self::$class_map[$class];

		if (file_exists($file)) {
			require_once $file;
		}
	}

	/**
	 * Add entries to the class map at runtime (used by modules during registration).
	 *
	 * @param array<string, string> $entries Key = class name, value = relative path.
	 */
	public static function add(array $entries): void {
		foreach ($entries as $class => $path) {
			self::$class_map[$class] = $path;
		}
	}
}
