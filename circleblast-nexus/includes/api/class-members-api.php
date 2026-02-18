<?php
/**
 * Members API
 *
 * REST API endpoint that returns a list of members and their emails.
 * Secured with a static bearer token stored in wp-config.php.
 *
 * Usage:
 *   GET /wp-json/cbnexus/v1/members
 *   Headers: Authorization: Bearer <token>
 *
 * Optional query params:
 *   ?status=active|inactive|alumni  (default: active)
 *   ?fields=name,email,company      (comma-separated, limits response fields)
 *
 * Configure in wp-config.php:
 *   define('CBNEXUS_API_TOKEN', 'your-secret-token-here');
 */

defined('ABSPATH') || exit;

final class CBNexus_Members_API {

	public static function init(): void {
		add_action('rest_api_init', [__CLASS__, 'register_routes']);
	}

	public static function register_routes(): void {
		register_rest_route('cbnexus/v1', '/members', [
			'methods'             => 'GET',
			'callback'            => [__CLASS__, 'get_members'],
			'permission_callback' => [__CLASS__, 'check_auth'],
		]);

		register_rest_route('cbnexus/v1', '/members/(?P<id>\d+)', [
			'methods'             => 'GET',
			'callback'            => [__CLASS__, 'get_member'],
			'permission_callback' => [__CLASS__, 'check_auth'],
			'args'                => [
				'id' => [
					'required'          => true,
					'validate_callback' => fn($v) => is_numeric($v) && $v > 0,
				],
			],
		]);
	}

	/**
	 * Authenticate via Bearer token.
	 */
	public static function check_auth(\WP_REST_Request $request): bool {
		$api_token = defined('CBNEXUS_API_TOKEN') ? CBNEXUS_API_TOKEN : '';

		if (empty($api_token)) {
			return false;
		}

		$auth = $request->get_header('Authorization');
		if (!$auth) {
			return false;
		}

		// Accept "Bearer <token>" format.
		if (stripos($auth, 'Bearer ') === 0) {
			$provided = trim(substr($auth, 7));
		} else {
			$provided = trim($auth);
		}

		return hash_equals($api_token, $provided);
	}

	/**
	 * GET /cbnexus/v1/members
	 */
	public static function get_members(\WP_REST_Request $request): \WP_REST_Response {
		$status = sanitize_key($request->get_param('status') ?: 'active');
		$fields = $request->get_param('fields');

		if (!in_array($status, ['active', 'inactive', 'alumni', 'all'], true)) {
			$status = 'active';
		}

		$members = ($status === 'all')
			? array_merge(
				CBNexus_Member_Repository::get_all_members('active'),
				CBNexus_Member_Repository::get_all_members('inactive'),
				CBNexus_Member_Repository::get_all_members('alumni')
			)
			: CBNexus_Member_Repository::get_all_members($status);

		$allowed_fields = self::parse_fields($fields);

		$result = array_map(fn($m) => self::format_member($m, $allowed_fields), $members);

		return new \WP_REST_Response([
			'count'   => count($result),
			'status'  => $status,
			'members' => $result,
		], 200);
	}

	/**
	 * GET /cbnexus/v1/members/{id}
	 */
	public static function get_member(\WP_REST_Request $request): \WP_REST_Response {
		$id     = (int) $request->get_param('id');
		$fields = $request->get_param('fields');

		$member = CBNexus_Member_Repository::get_profile($id);

		if (!$member) {
			return new \WP_REST_Response(['error' => 'Member not found.'], 404);
		}

		$allowed_fields = self::parse_fields($fields);

		return new \WP_REST_Response(self::format_member($member, $allowed_fields), 200);
	}

	/**
	 * Format a member record for API output.
	 */
	private static function format_member(array $m, ?array $fields): array {
		$full = [
			'id'            => $m['user_id'] ?? 0,
			'name'          => $m['display_name'] ?? '',
			'first_name'    => $m['first_name'] ?? '',
			'last_name'     => $m['last_name'] ?? '',
			'email'         => $m['user_email'] ?? '',
			'company'       => $m['cb_company'] ?? '',
			'title'         => $m['cb_title'] ?? '',
			'industry'      => $m['cb_industry'] ?? '',
			'expertise'     => $m['cb_expertise'] ?? [],
			'looking_for'   => $m['cb_looking_for'] ?? [],
			'can_help_with' => $m['cb_can_help_with'] ?? [],
			'phone'         => $m['cb_phone'] ?? '',
			'linkedin'      => $m['cb_linkedin'] ?? '',
			'website'       => $m['cb_website'] ?? '',
			'status'        => $m['cb_member_status'] ?? 'active',
			'join_date'     => $m['cb_join_date'] ?? '',
		];

		if ($fields === null) {
			return $full;
		}

		return array_intersect_key($full, array_flip($fields));
	}

	/**
	 * Parse comma-separated fields parameter.
	 */
	private static function parse_fields(?string $fields): ?array {
		if ($fields === null || $fields === '') {
			return null;
		}

		$allowed = ['id', 'name', 'first_name', 'last_name', 'email', 'company', 'title', 'industry', 'expertise', 'looking_for', 'can_help_with', 'phone', 'linkedin', 'website', 'status', 'join_date'];

		$requested = array_map('trim', explode(',', $fields));
		$valid = array_intersect($requested, $allowed);

		// Always include id.
		if (!in_array('id', $valid, true)) {
			array_unshift($valid, 'id');
		}

		return $valid ?: null;
	}
}
