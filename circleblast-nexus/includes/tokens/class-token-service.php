<?php
/**
 * Token Service
 *
 * Universal secure token system for email-based actions.
 * Members can accept meetings, submit notes, update action items,
 * and more — all from a single click in their email, no login required.
 *
 * Tokens are stored in cb_tokens table with:
 *   - Configurable expiry (default 14 days)
 *   - Single-use or multi-use (for forms that may be submitted multiple times)
 *   - Payload storage (JSON) for passing context to the handler
 *   - Automatic cleanup of expired tokens via cron
 */

defined('ABSPATH') || exit;

final class CBNexus_Token_Service {

	const TABLE = 'cb_tokens';

	// ─── Token Generation ──────────────────────────────────────────────

	/**
	 * Generate a secure action token.
	 *
	 * @param int    $user_id      Member user ID.
	 * @param string $action       Action identifier (e.g. 'accept_meeting', 'submit_notes', 'update_action').
	 * @param array  $payload      Extra data stored with the token (meeting_id, item_id, etc.).
	 * @param int    $expires_days Days until expiry (default 14).
	 * @param bool   $multi_use    If true, token is not consumed on first use (for forms).
	 * @return string The token string.
	 */
	public static function generate(int $user_id, string $action, array $payload = [], int $expires_days = 14, bool $multi_use = false): string {
		global $wpdb;

		$token   = wp_generate_password(48, false, false);
		$table   = $wpdb->prefix . self::TABLE;
		$now     = gmdate('Y-m-d H:i:s');
		$expires = gmdate('Y-m-d H:i:s', strtotime("+{$expires_days} days"));

		$wpdb->insert($table, [
			'token'      => hash('sha256', $token),
			'user_id'    => $user_id,
			'action'     => substr($action, 0, 50),
			'payload'    => wp_json_encode($payload),
			'multi_use'  => $multi_use ? 1 : 0,
			'expires_at' => $expires,
			'created_at' => $now,
		], ['%s', '%d', '%s', '%s', '%d', '%s', '%s']);

		return $token;
	}

	/**
	 * Build a full URL with the token as a query parameter.
	 *
	 * @param string $token The raw token string.
	 * @return string Full URL.
	 */
	public static function url(string $token): string {
		return add_query_arg('cbnexus_token', $token, home_url('/'));
	}

	// ─── Token Validation & Consumption ────────────────────────────────

	/**
	 * Validate and consume a token. Returns payload or null if invalid/expired.
	 *
	 * @param string $raw_token The raw token from the URL.
	 * @return array|null {user_id, action, payload} or null.
	 */
	public static function validate(string $raw_token): ?array {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		$hash  = hash('sha256', $raw_token);

		$row = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$table} WHERE token = %s AND expires_at > %s LIMIT 1",
			$hash, gmdate('Y-m-d H:i:s')
		));

		if (!$row) {
			return null;
		}

		// Consume single-use tokens.
		if (!(int) $row->multi_use) {
			$wpdb->delete($table, ['id' => $row->id], ['%d']);
		} else {
			// Track usage count for multi-use.
			$wpdb->query($wpdb->prepare(
				"UPDATE {$table} SET use_count = use_count + 1 WHERE id = %d",
				$row->id
			));
		}

		return [
			'user_id' => (int) $row->user_id,
			'action'  => $row->action,
			'payload' => json_decode($row->payload, true) ?: [],
		];
	}

	/**
	 * Peek at a token without consuming it (for rendering forms).
	 */
	public static function peek(string $raw_token): ?array {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		$hash  = hash('sha256', $raw_token);

		$row = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$table} WHERE token = %s AND expires_at > %s LIMIT 1",
			$hash, gmdate('Y-m-d H:i:s')
		));

		if (!$row) { return null; }

		return [
			'user_id' => (int) $row->user_id,
			'action'  => $row->action,
			'payload' => json_decode($row->payload, true) ?: [],
		];
	}

	// ─── Cleanup ───────────────────────────────────────────────────────

	/**
	 * Delete expired tokens. Called by WP-Cron daily.
	 */
	public static function cleanup(): void {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		$found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
		if ($found !== $table) { return; }

		$wpdb->query($wpdb->prepare(
			"DELETE FROM {$table} WHERE expires_at < %s",
			gmdate('Y-m-d H:i:s')
		));
	}
}
