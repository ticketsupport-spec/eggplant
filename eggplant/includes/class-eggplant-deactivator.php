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
  }

}
