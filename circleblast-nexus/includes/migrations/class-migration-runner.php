<?php
/**
 * Migration Runner
 *
 * Activation-only migrations (approved policy).
 */

defined('ABSPATH') || exit;

final class CBNexus_Migration_Runner {

	/**
	 * Run all pending migrations.
	 */
	public static function run(): void {
		$migrations = self::get_migrations();

		$applied = get_option(CBNEXUS_OPTION_MIGRATIONS, []);
		if (!is_array($applied)) {
			$applied = [];
		}

		foreach ($migrations as $id => $meta) {
			if (!empty($applied[$id])) {
				continue;
			}

			$ok = self::apply_one($id, $meta);

			// Record applied state only on success.
			if ($ok) {
				$applied[$id] = [
					'applied_at_gmt' => gmdate('Y-m-d H:i:s'),
					'version'        => CBNEXUS_VERSION,
				];
				update_option(CBNEXUS_OPTION_MIGRATIONS, $applied, false);
			} else {
				// Stop at first failure to avoid partial/unknown state.
				break;
			}
		}
	}

	/**
	 * List of available migrations in execution order.
	 *
	 * @return array<string, array{file:string,class:string,method:string}>
	 */
	private static function get_migrations(): array {
		return [
			'001_create_log_table' => [
				'file'   => CBNEXUS_PLUGIN_DIR . 'includes/migrations/versions/001-create-log-table.php',
				'class'  => 'CBNexus_Migration_001_Create_Log_Table',
				'method' => 'up',
			],
		];
	}

	/**
	 * Apply a single migration.
	 *
	 * @param string $id
	 * @param array{file:string,class:string,method:string} $meta
	 * @return bool
	 */
	private static function apply_one(string $id, array $meta): bool {
		try {
			if (empty($meta['file']) || empty($meta['class']) || empty($meta['method'])) {
				self::log_error($id, 'Invalid migration metadata.');
				return false;
			}

			if (!file_exists($meta['file'])) {
				self::log_error($id, 'Migration file not found: ' . $meta['file']);
				return false;
			}

			require_once $meta['file'];

			$class = $meta['class'];
			$method = $meta['method'];

			if (!class_exists($class)) {
				self::log_error($id, 'Migration class not found: ' . $class);
				return false;
			}
			if (!is_callable([$class, $method])) {
				self::log_error($id, 'Migration method not callable: ' . $class . '::' . $method);
				return false;
			}

			$ok = (bool) call_user_func([$class, $method]);

			if (!$ok) {
				self::log_error($id, 'Migration returned false.');
				return false;
			}

			return true;
		} catch (Throwable $e) {
			self::log_error($id, 'Exception: ' . $e->getMessage(), [
				'exception_class' => get_class($e),
			]);
			return false;
		}
	}

	/**
	 * Migration errors should not fatal the site; log safely.
	 *
	 * @param string $migration_id
	 * @param string $message
	 * @param array<string,mixed> $context
	 */
	private static function log_error(string $migration_id, string $message, array $context = []): void {
		if (class_exists('CBNexus_Logger')) {
			CBNexus_Logger::error(
				'[migration:' . $migration_id . '] ' . $message,
				$context
			);
			return;
		}

		// Hard fallback if logger isn't available.
		error_log('[circleblast-nexus] [migration:' . $migration_id . '] ' . $message);
	}
}
