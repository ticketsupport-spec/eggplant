<?php

/**
 * Runtime DB upgrade / ensure-tables helper.
 *
 * Runs on every page load (plugins_loaded) and ensures that the three plugin
 * tables exist and match the current schema version.  This makes the plugin
 * resilient to scenarios where the activation hook never fired (e.g. manual
 * file deployment, network-activation on a new sub-site added later, etc.).
 *
 * @since 1.0.0
 * @package Eggplant
 */

class Eggplant_DB_Migrator {

  /**
   * Names of the three plugin tables (without the $wpdb->prefix).
   *
   * @var string[]
   */
  private static array $tables = array(
    'eggplant_time_slots',
    'eggplant_events',
    'eggplant_bookings',
  );

  /**
   * Registers the upgrade routine on the plugins_loaded hook.
   *
   * @since 1.0.0
   */
  public static function init(): void {
    add_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade' ) );
  }

  /**
   * Runs the upgrade routine if needed.
   *
   * Checks:
   *  1. Stored version vs EGGPLANT_DB_VERSION – if they differ, run dbDelta.
   *  2. Physical existence of each table – if any is missing, run dbDelta
   *     regardless of the stored version.
   *
   * On multisite, when called from the network-admin context all sites are
   * iterated; otherwise only the current blog is checked.
   *
   * @since 1.0.0
   */
  public static function maybe_upgrade(): void {
    if ( function_exists( 'is_multisite' ) && is_multisite() && is_network_admin() ) {
      $sites = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
      foreach ( $sites as $blog_id ) {
        switch_to_blog( $blog_id );
        self::upgrade_current_blog();
        restore_current_blog();
      }
    } else {
      self::upgrade_current_blog();
    }
  }

  /**
   * Transient key used to cache the "all tables exist" result so we avoid
   * running SHOW TABLES on every page load once the schema is up-to-date.
   *
   * @var string
   */
  private const TABLES_OK_TRANSIENT = 'eggplant_tables_ok';

  /**
   * Performs the version check and table-existence check for the current blog,
   * running dbDelta when needed.
   *
   * The expensive physical table-existence check is skipped when a short-lived
   * transient confirms the tables were verified recently, keeping normal page
   * loads fast.
   *
   * @since 1.0.0
   */
  private static function upgrade_current_blog(): void {
    $stored_version = get_option( 'eggplant_db_version', '' );

    $needs_upgrade = version_compare( (string) $stored_version, EGGPLANT_DB_VERSION, '<' );

    if ( ! $needs_upgrade && ! get_transient( self::TABLES_OK_TRANSIENT ) ) {
      $needs_upgrade = self::any_table_missing();
      if ( ! $needs_upgrade ) {
        // Cache for one hour so subsequent requests skip the SHOW TABLES queries.
        set_transient( self::TABLES_OK_TRANSIENT, '1', HOUR_IN_SECONDS );
      }
    }

    if ( $needs_upgrade ) {
      delete_transient( self::TABLES_OK_TRANSIENT );
      require_once EGGPLANT_PLUGIN_DIR . 'includes/class-eggplant-activator.php';
      Eggplant_Activator::create_tables();
    }
  }

  /**
   * Returns true if at least one of the plugin tables does not exist in the DB.
   *
   * @since 1.0.0
   * @return bool
   */
  private static function any_table_missing(): bool {
    global $wpdb;
    foreach ( self::$tables as $table ) {
      $full_name = $wpdb->prefix . $table;
      $like      = $wpdb->esc_like( $full_name );
      // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
      $exists = $wpdb->get_var( "SHOW TABLES LIKE '{$like}'" );
      if ( $exists !== $full_name ) {
        return true;
      }
    }
    return false;
  }

}
