<?php
/**
 * Member Repository
 *
 * ITER-0004: Data access layer for member profiles.
 * All database operations for member data go through this class.
 * Uses WordPress native wp_users + wp_usermeta tables.
 */

defined('ABSPATH') || exit;

final class CBNexus_Member_Repository {

	/**
	 * All custom meta keys used for member profiles.
	 *
	 * @var string[]
	 */
	private static $meta_keys = [
		'cb_company',
		'cb_title',
		'cb_industry',
		'cb_expertise',
		'cb_looking_for',
		'cb_can_help_with',
		'cb_phone',
		'cb_linkedin',
		'cb_website',
		'cb_bio',
		'cb_photo_url',
		'cb_referred_by',
		'cb_join_date',
		'cb_member_status',
		'cb_onboarding_stage',
		'cb_ambassador_id',
		'cb_notes_admin',
	];

	/**
	 * Create a WordPress user and assign member profile data.
	 *
	 * @param array $user_data    WordPress user fields (user_email, first_name, last_name, etc.).
	 * @param array $profile_data Member profile meta (cb_company, cb_title, etc.).
	 * @param string $role        WordPress role slug. Default 'cb_member'.
	 * @return int|WP_Error User ID on success, WP_Error on failure.
	 */
	public static function create(array $user_data, array $profile_data, string $role = 'cb_member') {
		// Ensure required WP fields.
		if (empty($user_data['user_email'])) {
			return new \WP_Error('missing_email', 'Email address is required.');
		}

		// Generate username from email if not provided.
		if (empty($user_data['user_login'])) {
			$user_data['user_login'] = sanitize_user($user_data['user_email'], true);
		}

		// Generate a random password (member will reset via email).
		if (empty($user_data['user_pass'])) {
			$user_data['user_pass'] = wp_generate_password(24, true, true);
		}

		$user_data['role'] = $role;

		$user_id = wp_insert_user($user_data);

		if (is_wp_error($user_id)) {
			return $user_id;
		}

		// Save all profile meta.
		self::update_profile($user_id, $profile_data);

		return $user_id;
	}

	/**
	 * Update profile meta for an existing member.
	 *
	 * Only updates keys that are in the allowed meta_keys list.
	 *
	 * @param int   $user_id      WordPress user ID.
	 * @param array $profile_data Key-value pairs of meta to update.
	 * @return void
	 */
	public static function update_profile(int $user_id, array $profile_data): void {
		foreach ($profile_data as $key => $value) {
			if (!in_array($key, self::$meta_keys, true)) {
				continue;
			}

			// Tags fields are stored as JSON arrays.
			if (is_array($value)) {
				$value = wp_json_encode($value);
			}

			update_user_meta($user_id, $key, $value);
		}
	}

	/**
	 * Get the full profile for a member.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array|null Profile data array or null if user doesn't exist.
	 */
	public static function get_profile(int $user_id): ?array {
		$user = get_userdata($user_id);
		if (!$user) {
			return null;
		}

		$profile = [
			'user_id'      => $user_id,
			'user_email'   => $user->user_email,
			'first_name'   => $user->first_name,
			'last_name'    => $user->last_name,
			'display_name' => $user->display_name,
			'roles'        => $user->roles,
		];

		foreach (self::$meta_keys as $key) {
			$value = get_user_meta($user_id, $key, true);

			// Decode JSON arrays for tags fields.
			if (in_array($key, ['cb_expertise', 'cb_looking_for', 'cb_can_help_with'], true)) {
				$decoded = json_decode($value, true);
				$value = is_array($decoded) ? $decoded : [];
			}

			$profile[$key] = $value;
		}

		return $profile;
	}

	/**
	 * Get all members with a specific role.
	 *
	 * @param string $role   Role slug. Default 'cb_member'.
	 * @param string $status Optional status filter ('active', 'inactive', 'alumni').
	 * @return array Array of user profiles.
	 */
	public static function get_all(string $role = 'cb_member', string $status = ''): array {
		$args = [
			'role'    => $role,
			'orderby' => 'display_name',
			'order'   => 'ASC',
		];

		if ($status !== '') {
			$args['meta_key']   = 'cb_member_status';
			$args['meta_value'] = $status;
		}

		$users = get_users($args);
		$profiles = [];

		foreach ($users as $user) {
			$profile = self::get_profile($user->ID);
			if ($profile !== null) {
				$profiles[] = $profile;
			}
		}

		return $profiles;
	}

	/**
	 * Get all members across all CB roles.
	 *
	 * @param string $status Optional status filter.
	 * @return array Array of user profiles.
	 */
	public static function get_all_members(string $status = ''): array {
		$all = [];
		foreach (['cb_member', 'cb_admin', 'cb_super_admin'] as $role) {
			$all = array_merge($all, self::get_all($role, $status));
		}

		// Sort by display name.
		usort($all, function ($a, $b) {
			return strcasecmp($a['display_name'], $b['display_name']);
		});

		return $all;
	}

	/**
	 * Check if a user has any CircleBlast role.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return bool
	 */
	public static function is_member(int $user_id): bool {
		$user = get_userdata($user_id);
		if (!$user) {
			return false;
		}

		$cb_roles = ['cb_member', 'cb_admin', 'cb_super_admin'];
		return !empty(array_intersect($user->roles, $cb_roles));
	}

	/**
	 * Get the member status for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string Status string or empty if not set.
	 */
	public static function get_status(int $user_id): string {
		return (string) get_user_meta($user_id, 'cb_member_status', true);
	}

	/**
	 * Update the member status.
	 *
	 * @param int    $user_id    WordPress user ID.
	 * @param string $new_status New status value.
	 * @return bool True if updated.
	 */
	public static function set_status(int $user_id, string $new_status): bool {
		$allowed = ['active', 'inactive', 'alumni'];
		if (!in_array($new_status, $allowed, true)) {
			return false;
		}

		return (bool) update_user_meta($user_id, 'cb_member_status', $new_status);
	}

	/**
	 * Count members by status.
	 *
	 * @return array<string, int> Counts keyed by status.
	 */
	public static function count_by_status(): array {
		global $wpdb;

		$counts = ['active' => 0, 'inactive' => 0, 'alumni' => 0, 'total' => 0];

		$results = $wpdb->get_results(
			"SELECT meta_value AS status, COUNT(*) AS cnt
			 FROM {$wpdb->usermeta}
			 WHERE meta_key = 'cb_member_status'
			 GROUP BY meta_value"
		);

		foreach ($results as $row) {
			if (isset($counts[$row->status])) {
				$counts[$row->status] = (int) $row->cnt;
			}
			$counts['total'] += (int) $row->cnt;
		}

		return $counts;
	}

	/**
	 * Search members by name, company, or expertise.
	 *
	 * @param string $query  Search term.
	 * @param string $status Optional status filter.
	 * @return array Matching profiles.
	 */
	public static function search(string $query, string $status = ''): array {
		$query = trim($query);
		if ($query === '') {
			return self::get_all_members($status);
		}

		$args = [
			'search'         => '*' . $query . '*',
			'search_columns' => ['user_login', 'user_email', 'display_name'],
			'role__in'       => ['cb_member', 'cb_admin', 'cb_super_admin'],
		];

		if ($status !== '') {
			$args['meta_key']   = 'cb_member_status';
			$args['meta_value'] = $status;
		}

		$user_query = new \WP_User_Query($args);
		$users = $user_query->get_results();
		$profiles = [];

		foreach ($users as $user) {
			$profile = self::get_profile($user->ID);
			if ($profile !== null) {
				$profiles[] = $profile;
			}
		}

		return $profiles;
	}

	/**
	 * Get list of valid meta keys.
	 *
	 * @return string[]
	 */
	public static function get_meta_keys(): array {
		return self::$meta_keys;
	}
}
