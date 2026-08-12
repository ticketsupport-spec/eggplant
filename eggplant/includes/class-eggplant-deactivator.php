<?php

/**
 * Fired during plugin deactivation
 *
 * @since 1.0.0
 * @package Eggplant
 */

class Eggplant_Deactivator {

  /**
   * Runs on plugin deactivation.
   *
   * @since 1.0.0
   */
  public static function deactivate(): void {
    flush_rewrite_rules();
    self::remove_htaccess_rules();
  }

  /**
   * Removes the Eggplant rewrite rules from .htaccess on deactivation.
   *
   * @since 1.3.0
   */
  public static function remove_htaccess_rules(): void {
    $htaccess = get_home_path() . '.htaccess';
    insert_with_markers( $htaccess, 'Eggplant', array() );
  }

}
