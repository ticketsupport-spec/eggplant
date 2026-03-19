<?php

/**
 * The file that defines the core plugin class
 *
 * Loads all dependencies and initialises the front-end and admin features.
 *
 * @since 1.0.0
 * @package Eggplant
 */

class Eggplant {

  /**
   * Loads all dependencies and kicks off all features.
   *
   * @since 1.0.0
   */
  public function run(): void {
    $this->load_dependencies();
    $this->init_features();
  }

  private function load_dependencies(): void {
    require_once EGGPLANT_PLUGIN_DIR . 'includes/class-eggplant-settings.php';
    require_once EGGPLANT_PLUGIN_DIR . 'includes/class-eggplant-db.php';
    require_once EGGPLANT_PLUGIN_DIR . 'includes/class-eggplant-db-migrator.php';
    require_once EGGPLANT_PLUGIN_DIR . 'includes/class-eggplant-frontend.php';
    require_once EGGPLANT_PLUGIN_DIR . 'includes/class-eggplant-admin.php';
  }

  private function init_features(): void {
    Eggplant_DB_Migrator::init();

    new Eggplant_Frontend();

    if ( is_admin() ) {
      new Eggplant_Admin();
    }
  }

}
