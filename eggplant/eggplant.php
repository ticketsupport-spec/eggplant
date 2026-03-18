<?php

/**
 * Plugin Name: Eggplant
 * Plugin URI: https://example.com/
 * Description: A sample WordPress plugin scaffold.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com/
 * License: GPL2
 */

// Define constants
if ( !defined( 'EGGPLANT_VERSION' ) ) {
    define( 'EGGPLANT_VERSION', '1.0.0' );
}

// Include the main class
require_once plugin_dir_path( __FILE__ ) . 'includes/class-eggplant.php';

// Initialize the plugin
add_action( 'plugins_loaded', 'run_eggplant' );
function run_eggplant() {
    $plugin = new Eggplant();
}