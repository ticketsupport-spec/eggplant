<?php

/**
 * Fired during plugin activation
 *
 * @since 1.0.0
 * @package Eggplant
 */

class Eggplant_Activator {

  /**
   * Runs on plugin activation: creates DB tables and default settings.
   *
   * Supports multisite network activation: when $network_wide is true the
   * tables are created for every site in the network.
   *
   * @since 1.0.0
   * @param bool $network_wide Whether the plugin is being activated network-wide.
   */
  public static function activate( bool $network_wide = false ): void {
    if ( $network_wide && function_exists( 'is_multisite' ) && is_multisite() ) {
      $sites = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
      foreach ( $sites as $blog_id ) {
        switch_to_blog( $blog_id );
        self::create_tables();
        self::set_default_settings();
        restore_current_blog();
      }
    } else {
      self::create_tables();
      self::set_default_settings();
    }
    flush_rewrite_rules();
  }

  /**
   * Build the SQL statements and run dbDelta for the three plugin tables,
   * then persist the current DB schema version.
   *
   * This method is intentionally public so the DB-migrator can call it
   * without duplicating the schema definitions.
   *
   * @since 1.0.0
   */
  public static function create_tables(): void {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Time slots table
    $sql_slots = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}eggplant_time_slots (
      id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      slot_date   DATE NOT NULL,
      start_time  TIME NOT NULL,
      end_time    TIME NOT NULL,
      label       VARCHAR(200) DEFAULT '',
      status      ENUM('available','booked','held') NOT NULL DEFAULT 'available',
      created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY slot_date (slot_date),
      KEY status (status)
    ) $charset_collate;";

    // Carousel events table
    $sql_events = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}eggplant_events (
      id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      title       VARCHAR(255) NOT NULL DEFAULT '',
      description TEXT,
      image_url   TEXT,
      link_url    TEXT,
      start_date  DATE,
      end_date    DATE,
      sort_order  INT NOT NULL DEFAULT 0,
      active      TINYINT(1) NOT NULL DEFAULT 1,
      created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY active (active)
    ) $charset_collate;";

    // Booking requests table
    $sql_bookings = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}eggplant_bookings (
      id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      name         VARCHAR(200) NOT NULL DEFAULT '',
      email        VARCHAR(200) NOT NULL DEFAULT '',
      phone        VARCHAR(50)  DEFAULT '',
      event_type   VARCHAR(200) DEFAULT '',
      event_date   DATE,
      time_slot_id BIGINT UNSIGNED,
      message      TEXT,
      status       ENUM('new','reviewed','approved','declined') NOT NULL DEFAULT 'new',
      created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY status (status),
      KEY event_date (event_date)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql_slots );
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $wpdb->last_error ) ) {
      error_log( 'Eggplant dbDelta error (eggplant_time_slots): ' . $wpdb->last_error );
    }
    dbDelta( $sql_events );
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $wpdb->last_error ) ) {
      error_log( 'Eggplant dbDelta error (eggplant_events): ' . $wpdb->last_error );
    }
    dbDelta( $sql_bookings );
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $wpdb->last_error ) ) {
      error_log( 'Eggplant dbDelta error (eggplant_bookings): ' . $wpdb->last_error );
    }

    update_option( 'eggplant_db_version', EGGPLANT_DB_VERSION );
  }

  private static function set_default_settings(): void {
    $defaults = array(
      'portal_title'         => 'Event Center',
      'bg_color'             => '#000000',
      'primary_color'        => '#e63946',
      'secondary_color'      => '#457b9d',
      'text_color'           => '#f1faee',
      'available_color'      => '#2a9d8f',
      'booked_color'         => '#e63946',
      'held_color'           => '#f4a261',
      'carousel_speed'       => 5000,
      'carousel_autoplay'    => 1,
      'custom_css'           => '',
      'contact_email'        => get_option( 'admin_email' ),
      'show_booking_form'    => 1,
    );
    if ( ! get_option( 'eggplant_settings' ) ) {
      add_option( 'eggplant_settings', $defaults );
    }
  }

}
