<?php
/**
 * Plugin Name: Eggplant
 * Version: 0.1.0
 * Description: Full Eggplant WordPress Plugin.
 * Author: Your Name
 */

defined('EGGPLANT_VERSION') || define('EGGPLANT_VERSION', '0.1.0');

if (!defined('EGGPLANT_PLUGIN_FILE')) {
    define('EGGPLANT_PLUGIN_FILE', __FILE__);
}
if (!defined('EGGPLANT_PLUGIN_DIR')) {
    define('EGGPLANT_PLUGIN_DIR', plugin_dir_path(EGGPLANT_PLUGIN_FILE));
}
if (!defined('EGGPLANT_PLUGIN_URL')) {
    define('EGGPLANT_PLUGIN_URL', plugin_dir_url(EGGPLANT_PLUGIN_FILE));
}
if (!defined('EGGPLANT_PLUGIN_BASENAME')) {
    define('EGGPLANT_PLUGIN_BASENAME', plugin_basename(EGGPLANT_PLUGIN_FILE));
}

register_activation_hook(EGGPLANT_PLUGIN_FILE, 'eggplant_activate');
register_deactivation_hook(EGGPLANT_PLUGIN_FILE, 'eggplant_deactivate');
require_once EGGPLANT_PLUGIN_DIR . 'includes/class-eggplant.php';

function eggplant_activate() {
    // Activation code here
}
function eggplant_deactivate() {
    // Deactivation code here
}

// Run the plugin
if (class_exists('Eggplant')) {
    $eggplant = new Eggplant();
}