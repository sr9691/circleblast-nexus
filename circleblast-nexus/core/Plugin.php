<?php
namespace CircleBlast\Nexus\Core;

if (!defined('ABSPATH')) { exit; }

require_once CBN_PATH . 'core/Logging/Logger.php';
require_once CBN_PATH . 'core/Migrations/Migrator.php';
require_once CBN_PATH . 'modules/Modules.php';

final class Plugin {
  public static function init(): void {
    \CircleBlast\Nexus\Modules\Modules::register();
  }

  public static function activate(): void {
    (new \CircleBlast\Nexus\Core\Migrations\Migrator())->run();
    \CircleBlast\Nexus\Core\Logging\Logger::info('Activated', ['version' => CBN_VERSION]);
  }

  public static function deactivate(): void {
    \CircleBlast\Nexus\Core\Logging\Logger::info('Deactivated', ['version' => CBN_VERSION]);
  }
}
