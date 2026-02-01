<?php
namespace CircleBlast\Nexus\Core\Migrations;

if (!defined('ABSPATH')) { exit; }

final class Migrator {
  private const OPTION_KEY = 'cbn_schema_version';
  private const TARGET_VERSION = 1;

  public function run(): void {
    $current = (int) get_option(self::OPTION_KEY, 0);
    if ($current >= self::TARGET_VERSION) { return; }

    $this->apply_0001_init();
    update_option(self::OPTION_KEY, self::TARGET_VERSION, true);
  }

  private function apply_0001_init(): void {
    require_once CBN_PATH . 'migrations/0001_init.php';
    \CircleBlast\Nexus\Migrations\migration_0001_init();
  }
}
