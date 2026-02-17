<?php
/**
 * Migration: 002 - Register custom roles
 *
 * ITER-0004: Creates cb_super_admin, cb_admin, cb_member roles
 * with a defined capability matrix.
 *
 * Note: WordPress roles are stored in the options table (wp_user_roles).
 * Re-running this migration is safe — add_role() returns null if the role
 * already exists.
 */

defined('ABSPATH') || exit;

final class CBNexus_Migration_002_Register_Roles {

	/**
	 * Apply migration.
	 *
	 * @return bool True on success.
	 */
	public static function up(): bool {
		$capabilities = self::get_capability_matrix();

		foreach ($capabilities as $role_slug => $role_data) {
			// Remove first so we can update capabilities cleanly on re-activation.
			remove_role($role_slug);
			add_role($role_slug, $role_data['name'], $role_data['caps']);
		}

		// Validate at least the member role was created.
		return wp_roles()->is_role('cb_member');
	}

	/**
	 * Define the capability matrix for all custom roles.
	 *
	 * All roles inherit WordPress's 'read' capability.
	 * Custom capabilities use the 'cbnexus_' prefix.
	 *
	 * @return array<string, array{name: string, caps: array<string, bool>}>
	 */
	private static function get_capability_matrix(): array {
		// Base capabilities every member gets.
		$member_caps = [
			// WordPress core.
			'read'                           => true,

			// Profile.
			'cbnexus_edit_own_profile'       => true,
			'cbnexus_view_directory'         => true,
			'cbnexus_view_member_profiles'   => true,

			// 1:1 Meetings.
			'cbnexus_request_meeting'        => true,
			'cbnexus_respond_to_meeting'     => true,
			'cbnexus_submit_meeting_notes'   => true,
			'cbnexus_view_own_meetings'      => true,

			// CircleUp.
			'cbnexus_view_circleup_archive'  => true,
			'cbnexus_submit_circleup_item'   => true,

			// Dashboard.
			'cbnexus_view_own_dashboard'     => true,
			'cbnexus_view_club_dashboard'    => true,
		];

		// Admin adds member management and meeting oversight.
		$admin_caps = array_merge($member_caps, [
			'cbnexus_manage_members'         => true,
			'cbnexus_create_members'         => true,
			'cbnexus_edit_any_profile'       => true,
			'cbnexus_view_admin_analytics'   => true,
			'cbnexus_manage_meetings'        => true,
			'cbnexus_manage_matching_rules'  => true,
			'cbnexus_run_matching_cycle'     => true,
			'cbnexus_manage_circleup'        => true,
			'cbnexus_publish_circleup'       => true,
			'cbnexus_view_logs'              => true,
		]);

		// Super admin gets everything including destructive operations.
		$super_admin_caps = array_merge($admin_caps, [
			'cbnexus_delete_members'         => true,
			'cbnexus_manage_roles'           => true,
			'cbnexus_manage_plugin_settings' => true,
			'cbnexus_export_data'            => true,
			'cbnexus_manage_recruitment'     => true,
		]);

		return [
			'cb_member' => [
				'name' => 'CircleBlast Member',
				'caps' => $member_caps,
			],
			'cb_admin' => [
				'name' => 'CircleBlast Admin',
				'caps' => $admin_caps,
			],
			'cb_super_admin' => [
				'name' => 'CircleBlast Super Admin',
				'caps' => $super_admin_caps,
			],
		];
	}
}
