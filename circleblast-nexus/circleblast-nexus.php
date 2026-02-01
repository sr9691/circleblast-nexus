<?php
/**
 * Plugin Name: CircleBlast Nexus
 * Description: Private member network platform (members, matching, meetings, archive, analytics).
 * Version: 0.1.0
 * Author: CircleBlast
 */

if (!defined('ABSPATH')) { exit; }

define('CBN_VERSION', '0.1.0');
define('CBN_PATH', plugin_dir_path(__FILE__));
define('CBN_URL', plugin_dir_url(__FILE__));

require_once CBN_PATH . 'core/Plugin.php';

register_activation_hook(__FILE__, ['CircleBlast\\Nexus\\Core\\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['CircleBlast\\Nexus\\Core\\Plugin', 'deactivate']);

add_action('plugins_loaded', function () {
  \CircleBlast\Nexus\Core\Plugin::init();
});
