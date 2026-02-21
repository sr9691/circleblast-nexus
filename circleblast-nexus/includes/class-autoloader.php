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
		'CBNexus_Portal_Meetings'   => 'includes/public/class-portal-meetings.php',

		// Meetings (ITER-0008)
		'CBNexus_Meeting_Repository' => 'includes/meetings/class-meeting-repository.php',
		'CBNexus_Meeting_Service'    => 'includes/meetings/class-meeting-service.php',

		// Matching (ITER-0010)
		'CBNexus_Matching_Rules'       => 'includes/matching/class-matching-rules.php',
		'CBNexus_Matching_Engine'      => 'includes/matching/class-matching-engine.php',
		'CBNexus_Suggestion_Generator' => 'includes/matching/class-suggestion-generator.php',
		'CBNexus_Admin_Matching'       => 'includes/admin/class-admin-matching.php',

		// CircleUp (ITER-0012)
		'CBNexus_CircleUp_Repository'  => 'includes/circleup/class-circleup-repository.php',
		'CBNexus_Fireflies_Webhook'    => 'includes/circleup/class-fireflies-webhook.php',
		'CBNexus_AI_Extractor'         => 'includes/circleup/class-ai-extractor.php',
		'CBNexus_Summary_Parser'       => 'includes/circleup/class-summary-parser.php',
		'CBNexus_Admin_Archivist'      => 'includes/admin/class-admin-archivist.php',

		// Public Portal — CircleUp & Dashboard (ITER-0014, ITER-0015)
		'CBNexus_Portal_CircleUp'  => 'includes/public/class-portal-circleup.php',
		'CBNexus_Portal_Dashboard' => 'includes/public/class-portal-dashboard.php',

		// Club Dashboard (ITER-0016)
		'CBNexus_Portal_Club' => 'includes/public/class-portal-club.php',

		// Portal Admin & Super Admin (role-gated in-portal admin pages)
		'CBNexus_Portal_Admin'       => 'includes/public/class-portal-admin.php',
		'CBNexus_Portal_Super_Admin' => 'includes/public/class-portal-super-admin.php',
		'CBNexus_Portal_Help'        => 'includes/public/class-portal-help.php',

		// Admin Analytics & Recruitment (ITER-0017)
		'CBNexus_Admin_Analytics'   => 'includes/admin/class-admin-analytics.php',
		'CBNexus_Admin_Recruitment' => 'includes/admin/class-admin-recruitment.php',

		// Events
		'CBNexus_Event_Repository'           => 'includes/events/class-event-repository.php',
		'CBNexus_Event_Service'              => 'includes/events/class-event-service.php',
		'CBNexus_Color_Scheme'               => 'includes/class-color-scheme.php',
		'CBNexus_Portal_Events'              => 'includes/public/class-portal-events.php',
		'CBNexus_Admin_Events'               => 'includes/admin/class-admin-events.php',

		// Email Template Manager
		'CBNexus_Admin_Email_Templates'      => 'includes/admin/class-admin-email-templates.php',

		// Recruitment Categories
		'CBNexus_Admin_Recruitment_Categories' => 'includes/admin/class-admin-recruitment-categories.php',

		// Recruitment Coverage (Phase 1)
		'CBNexus_Recruitment_Coverage_Service' => 'includes/recruitment/class-recruitment-coverage-service.php',

		// Tokens (universal email action system)
		'CBNexus_Token_Service' => 'includes/tokens/class-token-service.php',
		'CBNexus_Token_Router'  => 'includes/tokens/class-token-router.php',

		// REST API
		'CBNexus_Members_API'    => 'includes/api/class-members-api.php',
		'CBNexus_Events_ICS_API' => 'includes/api/class-events-ics-api.php',

		// Referral Form
		'CBNexus_Referral_Form'  => 'includes/public/class-referral-form.php',
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