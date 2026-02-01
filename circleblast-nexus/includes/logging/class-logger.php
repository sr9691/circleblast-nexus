<?php
/**
 * Logger
 *
 * Iteration 2: DB-backed logging when cbnexus_log table exists,
 * with a guaranteed fallback to PHP error_log (preserves behavior).
 */

defined('ABSPATH') || exit;

final class CBNexus_Logger {

	/**
	 * Cached table existence check.
	 *
	 * @var bool|null
	 */
	private static $log_table_exists = null;

	/**
	 * Cached request id for a single PHP request lifecycle.
	 *
	 * @var string|null
	 */
	private static $request_id = null;

	public static function debug(string $message, array $context = []): void {
		self::log('debug', $message, $context);
	}

	public static function info(string $message, array $context = []): void {
		self::log('info', $message, $context);
	}

	public static function warning(string $message, array $context = []): void {
		self::log('warning', $message, $context);
	}

	public static function error(string $message, array $context = []): void {
		self::log('error', $message, $context);
	}

	/**
	 * Main log entrypoint.
	 *
	 * @param string $level
	 * @param string $message
	 * @param array<string,mixed> $context
	 */
	public static function log(string $level, string $message, array $context = []): void {
		$level = self::normalize_level($level);
		$message = self::normalize_message($message);

		$inserted = self::try_insert_db(
			$level,
			$message,
			$context,
			'circleblast-nexus',
			self::request_id()
		);

		if ($inserted) {
			return;
		}

		// FALLBACK: preserve baseline behavior (safe, always available).
		// Keep it intentionally simple and non-fatal.
		$line = '[circleblast-nexus] [' . $level . '] ' . $message;

		// Avoid dumping huge payloads into error logs; context is JSON but clipped.
		if (!empty($context)) {
			$ctx = wp_json_encode($context);
			if (is_string($ctx) && strlen($ctx) > 2000) {
				$ctx = substr($ctx, 0, 2000) . '…';
			}
			$line .= ' ' . $ctx;
		}

		error_log($line);
	}

	private static function normalize_level(string $level): string {
		$level = strtolower(trim($level));
		$allowed = ['debug', 'info', 'warning', 'error'];

		return in_array($level, $allowed, true) ? $level : 'info';
	}

	private static function normalize_message(string $message): string {
		$message = trim($message);
		return ($message !== '') ? $message : '(empty message)';
	}

	private static function request_id(): ?string {
		if (self::$request_id !== null) {
			return self::$request_id;
		}

		if (function_exists('wp_generate_uuid4')) {
			self::$request_id = wp_generate_uuid4();
			return self::$request_id;
		}

		self::$request_id = null;
		return null;
	}

	private static function log_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'cbnexus_log';
	}

	private static function log_table_exists(): bool {
		if (self::$log_table_exists !== null) {
			return (bool) self::$log_table_exists;
		}

		global $wpdb;
		$table = self::log_table_name();

		$found = $wpdb->get_var($wpdb->prepare(
			"SHOW TABLES LIKE %s",
			$table
		));

		self::$log_table_exists = ($found === $table);

		return (bool) self::$log_table_exists;
	}

	/**
	 * Attempt to write to DB. Never throws.
	 *
	 * @param string $level
	 * @param string $message
	 * @param array<string,mixed> $context
	 * @param string|null $source
	 * @param string|null $request_id
	 * @return bool
	 */
	private static function try_insert_db(string $level, string $message, array $context = [], ?string $source = null, ?string $request_id = null): bool {
		try {
			if (!self::log_table_exists()) {
				return false;
			}

			global $wpdb;
			$table = self::log_table_name();

			$user_id = get_current_user_id();
			if (!$user_id) {
				$user_id = null;
			}

			$context_json = null;
			if (!empty($context)) {
				$context_json = wp_json_encode($context);
			}

			$payload = [
				'created_at_gmt' => gmdate('Y-m-d H:i:s'),
				'level'          => substr($level, 0, 20),
				'message'        => $message,
				'context_json'   => $context_json,
				'source'         => $source ? substr($source, 0, 191) : null,
				'request_id'     => $request_id ? substr($request_id, 0, 64) : null,
				'user_id'        => $user_id,
			];

			$formats = [
				'%s', // created_at_gmt
				'%s', // level
				'%s', // message
				'%s', // context_json
				'%s', // source
				'%s', // request_id
				'%d', // user_id
			];

			$ok = $wpdb->insert($table, $payload, $formats);

			return ($ok !== false);
		} catch (Throwable $e) {
			// If DB insert fails for any reason, do not break logging.
			// We intentionally do not re-log here to avoid recursion.
			return false;
		}
	}
}
