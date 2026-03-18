<?php
/**
 * Plugin Name:       Eggplant
 * Description:       A professional multi-file WordPress plugin scaffold (no Composer required).
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            ticketsupport-spec
 * License:           GPL-2.0-or-later
 * Text Domain:       eggplant
 * Domain Path:       /languages
 */

if (!defined('WPINC')) {
  die;
}

define('EGGPLANT_VERSION', '0.1.0');
define('EGGPLANT_PLUGIN_FILE', __FILE__);
define('EGGPLANT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EGGPLANT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EGGPLANT_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once EGGPLANT_PLUGIN_DIR . 'includes/class-eggplant.php';

function eggplant_activate(): void {
  require_once EGGPLANT_PLUGIN_DIR . 'includes/class-eggplant-activator.php';
  Eggplant_Activator::activate();
}

function eggplant_deactivate(): void {
  require_once EGGPLANT_PLUGIN_DIR . 'includes/class-eggplant-deactivator.php';
  Eggplant_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'eggplant_activate');
register_deactivation_hook(__FILE__, 'eggplant_deactivate');

function eggplant_run(): void {
  $plugin = new Eggplant();
  $plugin->run();
}
eggplant_run();
