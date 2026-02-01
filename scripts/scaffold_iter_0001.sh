#!/usr/bin/env bash
set -euo pipefail

PLUGIN_DIR="circleblast-nexus"

if [[ -d "$PLUGIN_DIR" ]]; then
  echo "✔ Found existing $PLUGIN_DIR/"
else
  mkdir -p "$PLUGIN_DIR"
  echo "✔ Created $PLUGIN_DIR/"
fi

mkdir -p \
  "$PLUGIN_DIR/core/Logging" \
  "$PLUGIN_DIR/core/Migrations" \
  "$PLUGIN_DIR/modules" \
  "$PLUGIN_DIR/migrations"

write_file () {
  local path="$1"
  local content="$2"
  if [[ -f "$path" ]]; then
    echo "↪ Skipping existing file: $path"
    return 0
  fi
  printf "%s" "$content" > "$path"
  echo "✔ Wrote $path"
}

write_file "$PLUGIN_DIR/circleblast-nexus.php" '<?php
/**
 * Plugin Name: CircleBlast Nexus
 * Description: Private member network platform (members, matching, meetings, archive, analytics).
 * Version: 0.1.0
 * Author: CircleBlast
 */

if (!defined('"'"'ABSPATH'"'"')) { exit; }

define('"'"'CBN_VERSION'"'"', '"'"'0.1.0'"'"');
define('"'"'CBN_PATH'"'"', plugin_dir_path(__FILE__));
define('"'"'CBN_URL'"'"', plugin_dir_url(__FILE__));

require_once CBN_PATH . '"'"'core/Plugin.php'"'"';

register_activation_hook(__FILE__, ['"'"'CircleBlast\\Nexus\\Core\\Plugin'"'"', '"'"'activate'"'"']);
register_deactivation_hook(__FILE__, ['"'"'CircleBlast\\Nexus\\Core\\Plugin'"'"', '"'"'deactivate'"'"']);

add_action('"'"'plugins_loaded'"'"', function () {
  \CircleBlast\Nexus\Core\Plugin::init();
});
'

write_file "$PLUGIN_DIR/core/Plugin.php" '<?php
namespace CircleBlast\Nexus\Core;

if (!defined('"'"'ABSPATH'"'"')) { exit; }

require_once CBN_PATH . '"'"'core/Logging/Logger.php'"'"';
require_once CBN_PATH . '"'"'core/Migrations/Migrator.php'"'"';
require_once CBN_PATH . '"'"'modules/Modules.php'"'"';

final class Plugin {
  public static function init(): void {
    \CircleBlast\Nexus\Modules\Modules::register();
  }

  public static function activate(): void {
    (new \CircleBlast\Nexus\Core\Migrations\Migrator())->run();
    \CircleBlast\Nexus\Core\Logging\Logger::info('"'"'Activated'"'"', ['"'"'version'"'"' => CBN_VERSION]);
  }

  public static function deactivate(): void {
    \CircleBlast\Nexus\Core\Logging\Logger::info('"'"'Deactivated'"'"', ['"'"'version'"'"' => CBN_VERSION]);
  }
}
'

write_file "$PLUGIN_DIR/core/Logging/Logger.php" '<?php
namespace CircleBlast\Nexus\Core\Logging;

if (!defined('"'"'ABSPATH'"'"')) { exit; }

final class Logger {
  public static function info(string $message, array $context = []): void {
    self::write('"'"'INFO'"'"', $message, $context);
  }

  public static function error(string $message, array $context = []): void {
    self::write('"'"'ERROR'"'"', $message, $context);
  }

  private static function write(string $level, string $message, array $context): void {
    if (!defined('"'"'WP_DEBUG_LOG'"'"') || WP_DEBUG_LOG !== true) { return; }
    $payload = [
      '"'"'ts'"'"' => gmdate('"'"'c'"'"'),
      '"'"'level'"'"' => $level,
      '"'"'message'"'"' => $message,
      '"'"'context'"'"' => $context,
    ];
    error_log('"'"'[circleblast-nexus] '"'"' . wp_json_encode($payload));
  }
}
'

write_file "$PLUGIN_DIR/core/Migrations/Migrator.php" '<?php
namespace CircleBlast\Nexus\Core\Migrations;

if (!defined('"'"'ABSPATH'"'"')) { exit; }

final class Migrator {
  private const OPTION_KEY = '"'"'cbn_schema_version'"'"';
  private const TARGET_VERSION = 1;

  public function run(): void {
    $current = (int) get_option(self::OPTION_KEY, 0);
    if ($current >= self::TARGET_VERSION) { return; }

    $this->apply_0001_init();
    update_option(self::OPTION_KEY, self::TARGET_VERSION, true);
  }

  private function apply_0001_init(): void {
    require_once CBN_PATH . '"'"'migrations/0001_init.php'"'"';
    \CircleBlast\Nexus\Migrations\migration_0001_init();
  }
}
'

write_file "$PLUGIN_DIR/migrations/0001_init.php" '<?php
namespace CircleBlast\Nexus\Migrations;

if (!defined('"'"'ABSPATH'"'"')) { exit; }

function migration_0001_init(): void {
  // Intentionally minimal for Iteration 1.
  // Next iteration: create log table + core tables.
}
'

write_file "$PLUGIN_DIR/modules/Modules.php" '<?php
namespace CircleBlast\Nexus\Modules;

if (!defined('"'"'ABSPATH'"'"')) { exit; }

final class Modules {
  public static function register(): void {
    // Placeholder: register module hooks here in later iterations.
  }
}
'

write_file "$PLUGIN_DIR/uninstall.php" '<?php
if (!defined('"'"'WP_UNINSTALL_PLUGIN'"'"')) { exit; }
// Intentionally no destructive cleanup by default (requires explicit approval).
'

echo ""
echo "Done. Next:"
echo "  - Update PROGRESS.md (required)"
echo "  - git status && git add -A && git commit -m \"ITER-0001: scaffold plugin skeleton\""
