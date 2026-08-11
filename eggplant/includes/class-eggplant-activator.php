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
   * Build the SQL statements and run dbDelta for the plugin tables,
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

    // Recurring operational tasks table.
    $sql_tasks = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}eggplant_tasks (
      id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      task_name         VARCHAR(255) NOT NULL DEFAULT '',
      interval_type     VARCHAR(20) NOT NULL DEFAULT 'hours',
      interval_value    INT UNSIGNED NOT NULL DEFAULT 1,
      last_completed_at DATETIME NULL,
      next_due_at       DATETIME NULL,
      active            TINYINT(1) NOT NULL DEFAULT 1,
      created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY active (active),
      KEY next_due_at (next_due_at)
    ) $charset_collate;";

    // Task completion history for attendance / audit tracking.
    $sql_task_completions = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}eggplant_task_completions (
      id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      task_id           BIGINT UNSIGNED NOT NULL,
      staff_identifier  VARCHAR(200) DEFAULT '',
      completed_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY task_id (task_id),
      KEY completed_at (completed_at)
    ) $charset_collate;";

    // Staff directory.
    $sql_staff = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}eggplant_staff (
      id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      staff_identifier     VARCHAR(200) NOT NULL DEFAULT '',
      full_name            VARCHAR(200) NOT NULL DEFAULT '',
      email                VARCHAR(200) DEFAULT '',
      hire_date            DATE NULL,
      hourly_wage          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      overtime_multiplier  DECIMAL(5,2) NOT NULL DEFAULT 1.50,
      status               VARCHAR(20) NOT NULL DEFAULT 'active',
      created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY staff_identifier (staff_identifier),
      KEY status (status)
    ) $charset_collate;";

    // Staff clock entries.
    $sql_staff_checkins = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}eggplant_staff_checkins (
      id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      staff_id          BIGINT UNSIGNED NULL,
      staff_identifier  VARCHAR(200) NOT NULL DEFAULT '',
      clock_in_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      clock_out_at      DATETIME NULL,
      notes             TEXT,
      approved_at       DATETIME NULL,
      approved_by       BIGINT UNSIGNED NULL,
      created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY staff_id (staff_id),
      KEY staff_identifier (staff_identifier),
      KEY clock_in_at (clock_in_at),
      KEY clock_out_at (clock_out_at),
      KEY approved_at (approved_at)
    ) $charset_collate;";

    // Payroll periods.
    $sql_payroll_periods = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}eggplant_payroll_periods (
      id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      period_start  DATE NOT NULL,
      period_end    DATE NOT NULL,
      pay_date      DATE NOT NULL,
      status        VARCHAR(20) NOT NULL DEFAULT 'draft',
      notes         TEXT,
      created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY period_start (period_start),
      KEY pay_date (pay_date),
      KEY status (status)
    ) $charset_collate;";

    // Payroll entries.
    $sql_payroll_entries = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}eggplant_payroll_entries (
      id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      payroll_period_id  BIGINT UNSIGNED NOT NULL,
      staff_id           BIGINT UNSIGNED NOT NULL,
      regular_hours      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      overtime_hours     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      hourly_wage        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      gross_pay          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      vacation_pay       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      income_tax         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      cpp_employee       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      ei_employee        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      other_deductions   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      net_pay            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      deduction_summary  LONGTEXT NULL,
      created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY payroll_period_staff (payroll_period_id, staff_id),
      KEY staff_id (staff_id)
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
    dbDelta( $sql_tasks );
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $wpdb->last_error ) ) {
      error_log( 'Eggplant dbDelta error (eggplant_tasks): ' . $wpdb->last_error );
    }
    dbDelta( $sql_task_completions );
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $wpdb->last_error ) ) {
      error_log( 'Eggplant dbDelta error (eggplant_task_completions): ' . $wpdb->last_error );
    }
    dbDelta( $sql_staff );
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $wpdb->last_error ) ) {
      error_log( 'Eggplant dbDelta error (eggplant_staff): ' . $wpdb->last_error );
    }
    dbDelta( $sql_staff_checkins );
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $wpdb->last_error ) ) {
      error_log( 'Eggplant dbDelta error (eggplant_staff_checkins): ' . $wpdb->last_error );
    }
    dbDelta( $sql_payroll_periods );
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $wpdb->last_error ) ) {
      error_log( 'Eggplant dbDelta error (eggplant_payroll_periods): ' . $wpdb->last_error );
    }
    dbDelta( $sql_payroll_entries );
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $wpdb->last_error ) ) {
      error_log( 'Eggplant dbDelta error (eggplant_payroll_entries): ' . $wpdb->last_error );
    }

    update_option( 'eggplant_db_version', EGGPLANT_DB_VERSION );
  }

  private static function set_default_settings(): void {
    require_once EGGPLANT_PLUGIN_DIR . 'includes/class-eggplant-payroll.php';

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
      'front_page_info'      => '',
      'show_booking_form'    => 1,
    );
    if ( ! get_option( 'eggplant_settings' ) ) {
      add_option( 'eggplant_settings', $defaults );
    }

    if ( ! get_option( 'eggplant_payroll_settings' ) ) {
      add_option( 'eggplant_payroll_settings', Eggplant_Payroll::get_settings() );
    }
  }

}
