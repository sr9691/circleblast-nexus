<?php
namespace CircleBlast\Nexus\Core\Logging;

if (!defined('ABSPATH')) { exit; }

final class Logger {
  public static function info(string $message, array $context = []): void {
    self::write('INFO', $message, $context);
  }

  public static function error(string $message, array $context = []): void {
    self::write('ERROR', $message, $context);
  }

  private static function write(string $level, string $message, array $context): void {
    if (!defined('WP_DEBUG_LOG') || WP_DEBUG_LOG !== true) { return; }
    $payload = [
      'ts' => gmdate('c'),
      'level' => $level,
      'message' => $message,
      'context' => $context,
    ];
    error_log('[circleblast-nexus] ' . wp_json_encode($payload));
  }
}
