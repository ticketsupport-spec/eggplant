<?php

/**
 * Staff directory, payroll, and year-end summary tools.
 *
 * @since 1.2.0
 * @package Eggplant
 */

class Eggplant_Payroll {

  /**
   * Register payroll handlers.
   */
  public static function init(): void {
    add_action( 'admin_post_eggplant_save_staff', array( __CLASS__, 'handle_save_staff' ) );
    add_action( 'admin_post_eggplant_save_payroll_settings', array( __CLASS__, 'handle_save_payroll_settings' ) );
    add_action( 'admin_post_eggplant_save_payroll_period', array( __CLASS__, 'handle_save_payroll_period' ) );
    add_action( 'admin_post_eggplant_run_payroll', array( __CLASS__, 'handle_run_payroll' ) );
    add_action( 'admin_post_eggplant_update_staff_entry', array( __CLASS__, 'handle_update_staff_entry' ) );
  }

  /**
   * Get payroll settings.
   *
   * @return array<string,mixed>
   */
  public static function get_settings(): array {
    $defaults = array(
      'company_name'                   => get_bloginfo( 'name' ),
      'business_number'                => '',
      'currency_symbol'                => '$',
      'pay_periods_per_year'           => 26,
      'payroll_week_starts'            => 'monday',
      'ontario_overtime_threshold'     => 44,
      'ontario_overtime_multiplier'    => 1.5,
      'vacation_pay_rate'              => 0.04,
      'federal_tax_rate'               => 0.15,
      'ontario_tax_rate'               => 0.0505,
      'cpp_rate'                       => 0.0595,
      'cpp_basic_exemption_per_period' => 134.62,
      'cpp_annual_max'                 => 3867.50,
      'ei_rate'                        => 0.0166,
      'ei_annual_max'                  => 1077.48,
      'other_deduction_rate'           => 0,
      'other_deduction_flat'           => 0,
    );

    $saved = get_option( 'eggplant_payroll_settings', array() );
    return wp_parse_args( (array) $saved, $defaults );
  }

  /**
   * Update payroll settings.
   *
   * @param array<string,mixed> $data
   */
  public static function update_settings( array $data ): void {
    $current = get_option( 'eggplant_payroll_settings', array() );
    update_option( 'eggplant_payroll_settings', array_merge( (array) $current, $data ) );
  }

  /**
   * Get supported staff statuses.
   *
   * @return array<string,string>
   */
  public static function get_staff_status_options(): array {
    return array(
      'active'   => __( 'Active', 'eggplant' ),
      'inactive' => __( 'Inactive', 'eggplant' ),
    );
  }

  /**
   * Get active staff records.
   *
   * @return array<int,array<string,mixed>>
   */
  public static function get_active_staff(): array {
    global $wpdb;

    $results = $wpdb->get_results(
      // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- internal table name, no user input.
      "SELECT * FROM {$wpdb->prefix}eggplant_staff ORDER BY full_name ASC, staff_identifier ASC",
      ARRAY_A
    );

    if ( empty( $results ) ) {
      return array();
    }

    return array_values(
      array_filter(
        $results,
        static function ( $staff ) {
          return 'active' === ( $staff['status'] ?? 'active' );
        }
      )
    );
  }

  /**
   * Get a single staff record.
   *
   * @return array<string,mixed>|null
   */
  public static function get_staff( int $staff_id ): ?array {
    global $wpdb;

    $row = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eggplant_staff WHERE id = %d",
        $staff_id
      ),
      ARRAY_A
    );

    return $row ?: null;
  }

  /**
   * Get all staff records.
   *
   * @return array<int,array<string,mixed>>
   */
  public static function get_all_staff(): array {
    global $wpdb;

    $results = $wpdb->get_results(
      // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- internal table name, no user input.
      "SELECT * FROM {$wpdb->prefix}eggplant_staff ORDER BY status ASC, full_name ASC, staff_identifier ASC",
      ARRAY_A
    );

    return $results ?: array();
  }

  /**
   * Find a staff record from identifier or full name.
   *
   * @return array<string,mixed>|null
   */
  public static function find_staff_by_identifier( string $identifier ): ?array {
    global $wpdb;

    $identifier = sanitize_text_field( $identifier );
    if ( '' === $identifier ) {
      return null;
    }

    $row = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eggplant_staff
          WHERE staff_identifier = %s OR full_name = %s
          ORDER BY status ASC, id ASC
          LIMIT 1",
        $identifier,
        $identifier
      ),
      ARRAY_A
    );

    return $row ?: null;
  }

  /**
   * Insert or update a staff record.
   *
   * @param array<string,mixed> $data
   * @return int|false
   */
  public static function save_staff_record( array $data ) {
    global $wpdb;

    $staff_id = intval( $data['id'] ?? 0 );
    $record   = array(
      'staff_identifier'     => sanitize_text_field( $data['staff_identifier'] ?? '' ),
      'full_name'            => sanitize_text_field( $data['full_name'] ?? '' ),
      'email'                => sanitize_email( $data['email'] ?? '' ),
      'hire_date'            => sanitize_text_field( $data['hire_date'] ?? '' ) ?: null,
      'hourly_wage'          => self::sanitize_decimal( $data['hourly_wage'] ?? 0 ),
      'overtime_multiplier'  => self::sanitize_decimal( $data['overtime_multiplier'] ?? self::get_settings()['ontario_overtime_multiplier'] ),
      'status'               => array_key_exists( $data['status'] ?? 'active', self::get_staff_status_options() ) ? $data['status'] : 'active',
    );

    if ( empty( $record['staff_identifier'] ) || empty( $record['full_name'] ) ) {
      return false;
    }

    if ( $staff_id > 0 ) {
      $result = $wpdb->update(
        $wpdb->prefix . 'eggplant_staff',
        $record,
        array( 'id' => $staff_id ),
        array( '%s', '%s', '%s', '%s', '%f', '%f', '%s' ),
        array( '%d' )
      );

      return false !== $result ? $staff_id : false;
    }

    $result = $wpdb->insert(
      $wpdb->prefix . 'eggplant_staff',
      $record,
      array( '%s', '%s', '%s', '%s', '%f', '%f', '%s' )
    );

    return $result ? $wpdb->insert_id : false;
  }

  /**
   * Get recent time entries with optional staff metadata.
   *
   * @return array<int,array<string,mixed>>
   */
  public static function get_recent_staff_entries( int $limit = 50 ): array {
    global $wpdb;

    $results = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT c.*, s.full_name, s.hourly_wage
          FROM {$wpdb->prefix}eggplant_staff_checkins c
          LEFT JOIN {$wpdb->prefix}eggplant_staff s ON s.id = c.staff_id
          ORDER BY c.clock_in_at DESC, c.id DESC
          LIMIT %d",
        max( 1, $limit )
      ),
      ARRAY_A
    );

    return $results ?: array();
  }

  /**
   * Update a staff clock entry from admin.
   *
   * @param array<string,mixed> $data
   */
  public static function update_staff_entry( array $data ): bool {
    global $wpdb;

    $entry_id          = intval( $data['entry_id'] ?? 0 );
    $staff_identifier  = sanitize_text_field( $data['staff_identifier'] ?? '' );
    $staff             = self::find_staff_by_identifier( $staff_identifier );
    $clock_in_at       = self::normalize_datetime_input( $data['clock_in_at'] ?? '' );
    $clock_out_at      = self::normalize_datetime_input( $data['clock_out_at'] ?? '' );
    $is_approved       = ! empty( $data['approved'] );
    $approved_at       = $is_approved ? current_time( 'mysql' ) : null;
    $approved_by       = $is_approved ? get_current_user_id() : null;

    if ( $entry_id <= 0 || empty( $staff_identifier ) || empty( $clock_in_at ) ) {
      return false;
    }

    $result = $wpdb->update(
      $wpdb->prefix . 'eggplant_staff_checkins',
      array(
        'staff_id'         => $staff ? intval( $staff['id'] ) : null,
        'staff_identifier' => $staff_identifier,
        'clock_in_at'      => $clock_in_at,
        'clock_out_at'     => $clock_out_at,
        'notes'            => sanitize_text_field( $data['notes'] ?? '' ),
        'approved_at'      => $approved_at,
        'approved_by'      => $approved_by,
      ),
      array( 'id' => $entry_id ),
      array( '%d', '%s', '%s', '%s', '%s', '%s', '%d' ),
      array( '%d' )
    );

    return false !== $result;
  }

  /**
   * Get all payroll periods.
   *
   * @return array<int,array<string,mixed>>
   */
  public static function get_payroll_periods(): array {
    global $wpdb;

    $results = $wpdb->get_results(
      // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- internal table name, no user input.
      "SELECT * FROM {$wpdb->prefix}eggplant_payroll_periods ORDER BY period_start DESC, id DESC",
      ARRAY_A
    );

    return $results ?: array();
  }

  /**
   * Get a single payroll period.
   *
   * @return array<string,mixed>|null
   */
  public static function get_payroll_period( int $period_id ): ?array {
    global $wpdb;

    $row = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eggplant_payroll_periods WHERE id = %d",
        $period_id
      ),
      ARRAY_A
    );

    return $row ?: null;
  }

  /**
   * Insert or update a payroll period.
   *
   * @param array<string,mixed> $data
   * @return int|false
   */
  public static function save_payroll_period_record( array $data ) {
    global $wpdb;

    $period_id = intval( $data['id'] ?? 0 );
    $record    = array(
      'period_start' => sanitize_text_field( $data['period_start'] ?? '' ),
      'period_end'   => sanitize_text_field( $data['period_end'] ?? '' ),
      'pay_date'     => sanitize_text_field( $data['pay_date'] ?? '' ),
      'status'       => in_array( $data['status'] ?? 'draft', array( 'draft', 'processed' ), true ) ? $data['status'] : 'draft',
      'notes'        => sanitize_text_field( $data['notes'] ?? '' ),
    );

    if ( empty( $record['period_start'] ) || empty( $record['period_end'] ) || empty( $record['pay_date'] ) ) {
      return false;
    }

    if ( $period_id > 0 ) {
      $result = $wpdb->update(
        $wpdb->prefix . 'eggplant_payroll_periods',
        $record,
        array( 'id' => $period_id ),
        array( '%s', '%s', '%s', '%s', '%s' ),
        array( '%d' )
      );

      return false !== $result ? $period_id : false;
    }

    $result = $wpdb->insert(
      $wpdb->prefix . 'eggplant_payroll_periods',
      $record,
      array( '%s', '%s', '%s', '%s', '%s' )
    );

    return $result ? $wpdb->insert_id : false;
  }

  /**
   * Get payroll entries for a period.
   *
   * @return array<int,array<string,mixed>>
   */
  public static function get_payroll_entries( int $period_id ): array {
    global $wpdb;

    $results = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT e.*, s.full_name, s.staff_identifier
          FROM {$wpdb->prefix}eggplant_payroll_entries e
          INNER JOIN {$wpdb->prefix}eggplant_staff s ON s.id = e.staff_id
          WHERE e.payroll_period_id = %d
          ORDER BY s.full_name ASC, s.staff_identifier ASC",
        $period_id
      ),
      ARRAY_A
    );

    return $results ?: array();
  }

  /**
   * Generate payroll entries for a period.
   *
   * @return array<string,int>
   */
  public static function run_payroll_for_period( int $period_id ): array {
    global $wpdb;

    $period = self::get_payroll_period( $period_id );
    if ( ! $period ) {
      return array(
        'staff_count'      => 0,
        'entry_count'      => 0,
        'ignored_entries'  => 0,
      );
    }

    $settings    = self::get_settings();
    $staff_rows  = self::get_all_staff();
    $staff_index = array();

    foreach ( $staff_rows as $staff ) {
      $staff_index[ intval( $staff['id'] ) ] = $staff;
    }

    $hours_summary = self::get_staff_hours_for_period( $period, $settings['payroll_week_starts'] ?? 'monday', floatval( $settings['ontario_overtime_threshold'] ?? 44 ) );

    $wpdb->delete(
      $wpdb->prefix . 'eggplant_payroll_entries',
      array( 'payroll_period_id' => $period_id ),
      array( '%d' )
    );

    $entry_count     = 0;
    $staff_count     = 0;
    $ignored_entries = intval( $hours_summary['ignored_entries'] ?? 0 );
    unset( $hours_summary['ignored_entries'] );

    foreach ( $hours_summary as $staff_id => $summary ) {
      $staff_id = intval( $staff_id );
      if ( empty( $staff_index[ $staff_id ] ) ) {
        continue;
      }

      $staff  = $staff_index[ $staff_id ];
      $totals = self::calculate_payroll_totals( $staff, $summary, $settings, $period );

      $inserted = $wpdb->insert(
        $wpdb->prefix . 'eggplant_payroll_entries',
        array(
          'payroll_period_id'  => $period_id,
          'staff_id'           => $staff_id,
          'regular_hours'      => $totals['regular_hours'],
          'overtime_hours'     => $totals['overtime_hours'],
          'hourly_wage'        => $totals['hourly_wage'],
          'gross_pay'          => $totals['gross_pay'],
          'vacation_pay'       => $totals['vacation_pay'],
          'income_tax'         => $totals['income_tax'],
          'cpp_employee'       => $totals['cpp_employee'],
          'ei_employee'        => $totals['ei_employee'],
          'other_deductions'   => $totals['other_deductions'],
          'net_pay'            => $totals['net_pay'],
          'deduction_summary'  => wp_json_encode( $totals['deduction_summary'] ),
        ),
        array( '%d', '%d', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%s' )
      );

      if ( $inserted ) {
        ++$entry_count;
        ++$staff_count;
      }
    }

    $wpdb->update(
      $wpdb->prefix . 'eggplant_payroll_periods',
      array( 'status' => 'processed' ),
      array( 'id' => $period_id ),
      array( '%s' ),
      array( '%d' )
    );

    return array(
      'staff_count'     => $staff_count,
      'entry_count'     => $entry_count,
      'ignored_entries' => $ignored_entries,
    );
  }

  /**
   * Build year-end T4-style summaries for a tax year.
   *
   * @return array<int,array<string,mixed>>
   */
  public static function get_t4_summary( int $year ): array {
    global $wpdb;

    $results = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT e.staff_id, s.full_name, s.staff_identifier,
            SUM(e.gross_pay) AS employment_income,
            SUM(e.cpp_employee) AS cpp_employee,
            SUM(e.ei_employee) AS ei_employee,
            SUM(e.income_tax) AS income_tax
          FROM {$wpdb->prefix}eggplant_payroll_entries e
          INNER JOIN {$wpdb->prefix}eggplant_payroll_periods p ON p.id = e.payroll_period_id
          INNER JOIN {$wpdb->prefix}eggplant_staff s ON s.id = e.staff_id
          WHERE YEAR(p.pay_date) = %d
          GROUP BY e.staff_id, s.full_name, s.staff_identifier
          ORDER BY s.full_name ASC, s.staff_identifier ASC",
        $year
      ),
      ARRAY_A
    );

    return $results ?: array();
  }

  /**
   * Render the staff directory page.
   */
  public static function render_staff_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html__( 'You are not allowed to manage staff.', 'eggplant' ) );
    }

    $staff_id    = intval( $_GET['staff_id'] ?? 0 );
    $staff       = $staff_id ? self::get_staff( $staff_id ) : null;
    $all_staff   = self::get_all_staff();
    $status_list = self::get_staff_status_options();
    ?>
    <div class="wrap eg-admin">
      <h1><?php esc_html_e( 'Staff Directory', 'eggplant' ); ?></h1>

      <?php if ( self::get_message() ) : ?>
        <div class="notice notice-info"><p><?php echo esc_html( self::get_message() ); ?></p></div>
      <?php endif; ?>

      <div class="eg-card">
        <h2><?php echo esc_html( $staff ? __( 'Edit Staff Member', 'eggplant' ) : __( 'Add Staff Member', 'eggplant' ) ); ?></h2>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
          <input type="hidden" name="action" value="eggplant_save_staff">
          <input type="hidden" name="staff_id" value="<?php echo esc_attr( $staff['id'] ?? 0 ); ?>">
          <?php wp_nonce_field( 'eggplant_save_staff', 'eggplant_save_staff_nonce' ); ?>

          <div class="eg-form-row">
            <label for="eg-staff-code"><?php esc_html_e( 'Staff ID', 'eggplant' ); ?></label>
            <input type="text" id="eg-staff-code" name="staff_identifier" required value="<?php echo esc_attr( $staff['staff_identifier'] ?? '' ); ?>">
          </div>
          <div class="eg-form-row">
            <label for="eg-staff-name"><?php esc_html_e( 'Full Name', 'eggplant' ); ?></label>
            <input type="text" id="eg-staff-name" name="full_name" required value="<?php echo esc_attr( $staff['full_name'] ?? '' ); ?>">
          </div>
          <div class="eg-form-row">
            <label for="eg-staff-email"><?php esc_html_e( 'Email', 'eggplant' ); ?></label>
            <input type="text" id="eg-staff-email" name="email" value="<?php echo esc_attr( $staff['email'] ?? '' ); ?>">
          </div>
          <div class="eg-form-row">
            <label for="eg-staff-hire-date"><?php esc_html_e( 'Hire Date', 'eggplant' ); ?></label>
            <input type="date" id="eg-staff-hire-date" name="hire_date" value="<?php echo esc_attr( $staff['hire_date'] ?? '' ); ?>">
          </div>
          <div class="eg-form-row">
            <label for="eg-staff-hourly-wage"><?php esc_html_e( 'Hourly Wage', 'eggplant' ); ?></label>
            <input type="number" id="eg-staff-hourly-wage" name="hourly_wage" min="0" step="0.01" required value="<?php echo esc_attr( $staff['hourly_wage'] ?? '' ); ?>">
          </div>
          <div class="eg-form-row">
            <label for="eg-staff-overtime-multiplier"><?php esc_html_e( 'Overtime Multiplier', 'eggplant' ); ?></label>
            <input type="number" id="eg-staff-overtime-multiplier" name="overtime_multiplier" min="1" step="0.01" value="<?php echo esc_attr( $staff['overtime_multiplier'] ?? self::get_settings()['ontario_overtime_multiplier'] ); ?>">
          </div>
          <div class="eg-form-row">
            <label for="eg-staff-status"><?php esc_html_e( 'Status', 'eggplant' ); ?></label>
            <select id="eg-staff-status" name="status">
              <?php foreach ( $status_list as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $staff['status'] ?? 'active', $value ); ?>><?php echo esc_html( $label ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Staff Member', 'eggplant' ); ?></button>
          <?php if ( $staff ) : ?>
            <a class="button" href="<?php echo esc_url( self::admin_page_url( 'eggplant-staff' ) ); ?>"><?php esc_html_e( 'Add Another', 'eggplant' ); ?></a>
          <?php endif; ?>
        </form>
      </div>

      <div class="eg-card">
        <h2><?php esc_html_e( 'All Staff', 'eggplant' ); ?></h2>
        <?php if ( empty( $all_staff ) ) : ?>
          <p><?php esc_html_e( 'No staff members have been added yet.', 'eggplant' ); ?></p>
        <?php else : ?>
          <div class="eg-table-wrap">
            <table class="widefat striped">
              <thead>
                <tr>
                  <th><?php esc_html_e( 'Staff ID', 'eggplant' ); ?></th>
                  <th><?php esc_html_e( 'Name', 'eggplant' ); ?></th>
                  <th><?php esc_html_e( 'Hourly Wage', 'eggplant' ); ?></th>
                  <th><?php esc_html_e( 'Overtime', 'eggplant' ); ?></th>
                  <th><?php esc_html_e( 'Status', 'eggplant' ); ?></th>
                  <th><?php esc_html_e( 'Actions', 'eggplant' ); ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ( $all_staff as $row ) : ?>
                  <tr>
                    <td><?php echo esc_html( $row['staff_identifier'] ); ?></td>
                    <td>
                      <strong><?php echo esc_html( $row['full_name'] ); ?></strong><br>
                      <span><?php echo esc_html( $row['email'] ); ?></span>
                    </td>
                    <td><?php echo esc_html( self::format_money( $row['hourly_wage'] ) ); ?></td>
                    <td><?php echo esc_html( number_format_i18n( (float) $row['overtime_multiplier'], 2 ) . 'x' ); ?></td>
                    <td><?php echo esc_html( $status_list[ $row['status'] ] ?? $row['status'] ); ?></td>
                    <td><a class="button" href="<?php echo esc_url( self::admin_page_url( 'eggplant-staff', array( 'staff_id' => intval( $row['id'] ) ) ) ); ?>"><?php esc_html_e( 'Edit', 'eggplant' ); ?></a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php
  }

  /**
   * Render the payroll page.
   */
  public static function render_payroll_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html__( 'You are not allowed to manage payroll.', 'eggplant' ) );
    }

    $settings          = self::get_settings();
    $period_id         = intval( $_GET['period_id'] ?? 0 );
    $selected_period   = $period_id ? self::get_payroll_period( $period_id ) : null;
    $periods           = self::get_payroll_periods();
    $entries           = $selected_period ? self::get_payroll_entries( intval( $selected_period['id'] ) ) : array();
    $selected_year     = intval( $_GET['tax_year'] ?? gmdate( 'Y' ) );
    $t4_rows           = self::get_t4_summary( $selected_year );
    $period_totals     = self::sum_payroll_entries( $entries );
    ?>
    <div class="wrap eg-admin">
      <h1><?php esc_html_e( 'Payroll', 'eggplant' ); ?></h1>

      <?php if ( self::get_message() ) : ?>
        <div class="notice notice-info"><p><?php echo esc_html( self::get_message() ); ?></p></div>
      <?php endif; ?>

      <div class="eg-card">
        <h2><?php esc_html_e( 'Payroll Settings', 'eggplant' ); ?></h2>
        <p><?php esc_html_e( 'Ontario deduction defaults are editable. Review and update these rates each tax year before processing payroll.', 'eggplant' ); ?></p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
          <input type="hidden" name="action" value="eggplant_save_payroll_settings">
          <?php wp_nonce_field( 'eggplant_save_payroll_settings', 'eggplant_save_payroll_settings_nonce' ); ?>

          <div class="eg-form-row">
            <label for="eg-payroll-company-name"><?php esc_html_e( 'Company Name', 'eggplant' ); ?></label>
            <input type="text" id="eg-payroll-company-name" name="company_name" value="<?php echo esc_attr( $settings['company_name'] ); ?>">
          </div>
          <div class="eg-form-row">
            <label for="eg-payroll-business-number"><?php esc_html_e( 'Business Number', 'eggplant' ); ?></label>
            <input type="text" id="eg-payroll-business-number" name="business_number" value="<?php echo esc_attr( $settings['business_number'] ); ?>">
          </div>
          <div class="eg-form-row">
            <label for="eg-payroll-pay-periods-per-year"><?php esc_html_e( 'Pay Periods / Year', 'eggplant' ); ?></label>
            <input type="number" id="eg-payroll-pay-periods-per-year" name="pay_periods_per_year" min="1" step="1" value="<?php echo esc_attr( $settings['pay_periods_per_year'] ); ?>">
          </div>
          <div class="eg-form-row">
            <label for="eg-payroll-week-start"><?php esc_html_e( 'Payroll Week Starts', 'eggplant' ); ?></label>
            <select id="eg-payroll-week-start" name="payroll_week_starts">
              <option value="monday" <?php selected( $settings['payroll_week_starts'], 'monday' ); ?>><?php esc_html_e( 'Monday', 'eggplant' ); ?></option>
              <option value="sunday" <?php selected( $settings['payroll_week_starts'], 'sunday' ); ?>><?php esc_html_e( 'Sunday', 'eggplant' ); ?></option>
            </select>
          </div>
          <div class="eg-form-row">
            <label for="eg-payroll-overtime-threshold"><?php esc_html_e( 'Ontario Overtime Threshold (hours/week)', 'eggplant' ); ?></label>
            <input type="number" id="eg-payroll-overtime-threshold" name="ontario_overtime_threshold" min="1" step="0.01" value="<?php echo esc_attr( $settings['ontario_overtime_threshold'] ); ?>">
          </div>
          <div class="eg-form-row">
            <label for="eg-payroll-overtime-multiplier"><?php esc_html_e( 'Default Overtime Multiplier', 'eggplant' ); ?></label>
            <input type="number" id="eg-payroll-overtime-multiplier" name="ontario_overtime_multiplier" min="1" step="0.01" value="<?php echo esc_attr( $settings['ontario_overtime_multiplier'] ); ?>">
          </div>
          <div class="eg-form-row">
            <label for="eg-payroll-vacation-rate"><?php esc_html_e( 'Vacation Pay Rate', 'eggplant' ); ?></label>
            <input type="number" id="eg-payroll-vacation-rate" name="vacation_pay_rate" min="0" step="0.0001" value="<?php echo esc_attr( $settings['vacation_pay_rate'] ); ?>">
          </div>
          <div class="eg-form-row">
            <label for="eg-payroll-federal-tax-rate"><?php esc_html_e( 'Federal Tax Rate', 'eggplant' ); ?></label>
            <input type="number" id="eg-payroll-federal-tax-rate" name="federal_tax_rate" min="0" step="0.0001" value="<?php echo esc_attr( $settings['federal_tax_rate'] ); ?>">
          </div>
          <div class="eg-form-row">
            <label for="eg-payroll-ontario-tax-rate"><?php esc_html_e( 'Ontario Tax Rate', 'eggplant' ); ?></label>
            <input type="number" id="eg-payroll-ontario-tax-rate" name="ontario_tax_rate" min="0" step="0.0001" value="<?php echo esc_attr( $settings['ontario_tax_rate'] ); ?>">
          </div>
          <div class="eg-form-row">
            <label for="eg-payroll-cpp-rate"><?php esc_html_e( 'CPP Rate', 'eggplant' ); ?></label>
            <input type="number" id="eg-payroll-cpp-rate" name="cpp_rate" min="0" step="0.0001" value="<?php echo esc_attr( $settings['cpp_rate'] ); ?>">
          </div>
          <div class="eg-form-row">
            <label for="eg-payroll-cpp-exemption"><?php esc_html_e( 'CPP Basic Exemption / Period', 'eggplant' ); ?></label>
            <input type="number" id="eg-payroll-cpp-exemption" name="cpp_basic_exemption_per_period" min="0" step="0.01" value="<?php echo esc_attr( $settings['cpp_basic_exemption_per_period'] ); ?>">
          </div>
          <div class="eg-form-row">
            <label for="eg-payroll-cpp-max"><?php esc_html_e( 'CPP Annual Max', 'eggplant' ); ?></label>
            <input type="number" id="eg-payroll-cpp-max" name="cpp_annual_max" min="0" step="0.01" value="<?php echo esc_attr( $settings['cpp_annual_max'] ); ?>">
          </div>
          <div class="eg-form-row">
            <label for="eg-payroll-ei-rate"><?php esc_html_e( 'EI Rate', 'eggplant' ); ?></label>
            <input type="number" id="eg-payroll-ei-rate" name="ei_rate" min="0" step="0.0001" value="<?php echo esc_attr( $settings['ei_rate'] ); ?>">
          </div>
          <div class="eg-form-row">
            <label for="eg-payroll-ei-max"><?php esc_html_e( 'EI Annual Max', 'eggplant' ); ?></label>
            <input type="number" id="eg-payroll-ei-max" name="ei_annual_max" min="0" step="0.01" value="<?php echo esc_attr( $settings['ei_annual_max'] ); ?>">
          </div>
          <div class="eg-form-row">
            <label for="eg-payroll-other-rate"><?php esc_html_e( 'Other Deduction Rate', 'eggplant' ); ?></label>
            <input type="number" id="eg-payroll-other-rate" name="other_deduction_rate" min="0" step="0.0001" value="<?php echo esc_attr( $settings['other_deduction_rate'] ); ?>">
          </div>
          <div class="eg-form-row">
            <label for="eg-payroll-other-flat"><?php esc_html_e( 'Other Deduction Flat Amount', 'eggplant' ); ?></label>
            <input type="number" id="eg-payroll-other-flat" name="other_deduction_flat" min="0" step="0.01" value="<?php echo esc_attr( $settings['other_deduction_flat'] ); ?>">
          </div>

          <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Payroll Settings', 'eggplant' ); ?></button>
        </form>
      </div>

      <div class="eg-card">
        <h2><?php echo esc_html( $selected_period ? __( 'Edit Pay Period', 'eggplant' ) : __( 'Add Pay Period', 'eggplant' ) ); ?></h2>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
          <input type="hidden" name="action" value="eggplant_save_payroll_period">
          <input type="hidden" name="period_id" value="<?php echo esc_attr( $selected_period['id'] ?? 0 ); ?>">
          <?php wp_nonce_field( 'eggplant_save_payroll_period', 'eggplant_save_payroll_period_nonce' ); ?>
          <div class="eg-form-row">
            <label for="eg-period-start"><?php esc_html_e( 'Period Start', 'eggplant' ); ?></label>
            <input type="date" id="eg-period-start" name="period_start" required value="<?php echo esc_attr( $selected_period['period_start'] ?? '' ); ?>">
          </div>
          <div class="eg-form-row">
            <label for="eg-period-end"><?php esc_html_e( 'Period End', 'eggplant' ); ?></label>
            <input type="date" id="eg-period-end" name="period_end" required value="<?php echo esc_attr( $selected_period['period_end'] ?? '' ); ?>">
          </div>
          <div class="eg-form-row">
            <label for="eg-period-pay-date"><?php esc_html_e( 'Pay Date', 'eggplant' ); ?></label>
            <input type="date" id="eg-period-pay-date" name="pay_date" required value="<?php echo esc_attr( $selected_period['pay_date'] ?? '' ); ?>">
          </div>
          <div class="eg-form-row">
            <label for="eg-period-notes"><?php esc_html_e( 'Notes', 'eggplant' ); ?></label>
            <input type="text" id="eg-period-notes" name="notes" value="<?php echo esc_attr( $selected_period['notes'] ?? '' ); ?>">
          </div>
          <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Pay Period', 'eggplant' ); ?></button>
        </form>
      </div>

      <div class="eg-card">
        <h2><?php esc_html_e( 'Pay Periods', 'eggplant' ); ?></h2>
        <?php if ( empty( $periods ) ) : ?>
          <p><?php esc_html_e( 'No pay periods have been added yet.', 'eggplant' ); ?></p>
        <?php else : ?>
          <div class="eg-table-wrap">
            <table class="widefat striped">
              <thead>
                <tr>
                  <th><?php esc_html_e( 'Period', 'eggplant' ); ?></th>
                  <th><?php esc_html_e( 'Pay Date', 'eggplant' ); ?></th>
                  <th><?php esc_html_e( 'Status', 'eggplant' ); ?></th>
                  <th><?php esc_html_e( 'Actions', 'eggplant' ); ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ( $periods as $period ) : ?>
                  <tr>
                    <td><?php echo esc_html( $period['period_start'] . ' → ' . $period['period_end'] ); ?></td>
                    <td><?php echo esc_html( $period['pay_date'] ); ?></td>
                    <td><?php echo esc_html( ucfirst( $period['status'] ) ); ?></td>
                    <td>
                      <a class="button" href="<?php echo esc_url( self::admin_page_url( 'eggplant-payroll', array( 'period_id' => intval( $period['id'] ), 'tax_year' => $selected_year ) ) ); ?>"><?php esc_html_e( 'View', 'eggplant' ); ?></a>
                      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="eg-inline-form">
                        <input type="hidden" name="action" value="eggplant_run_payroll">
                        <input type="hidden" name="period_id" value="<?php echo esc_attr( $period['id'] ); ?>">
                        <?php wp_nonce_field( 'eggplant_run_payroll_' . $period['id'], 'eggplant_run_payroll_nonce' ); ?>
                        <button type="submit" class="button button-primary"><?php echo esc_html( 'processed' === $period['status'] ? __( 'Recalculate', 'eggplant' ) : __( 'Run Payroll', 'eggplant' ) ); ?></button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <?php if ( $selected_period ) : ?>
        <div class="eg-card">
          <h2><?php esc_html_e( 'Payroll Summary', 'eggplant' ); ?> — <?php echo esc_html( $selected_period['period_start'] . ' → ' . $selected_period['period_end'] ); ?></h2>
          <?php if ( empty( $entries ) ) : ?>
            <p><?php esc_html_e( 'Run payroll for this period to generate cheque totals and deductions.', 'eggplant' ); ?></p>
          <?php else : ?>
            <div class="eg-payroll-summary">
              <div class="eg-dash-card">
                <span class="eg-dash-number"><?php echo esc_html( number_format_i18n( count( $entries ) ) ); ?></span>
                <span class="eg-dash-label"><?php esc_html_e( 'Employees', 'eggplant' ); ?></span>
              </div>
              <div class="eg-dash-card">
                <span class="eg-dash-number"><?php echo esc_html( self::format_money( $period_totals['gross_pay'] ) ); ?></span>
                <span class="eg-dash-label"><?php esc_html_e( 'Gross Pay', 'eggplant' ); ?></span>
              </div>
              <div class="eg-dash-card">
                <span class="eg-dash-number"><?php echo esc_html( self::format_money( $period_totals['income_tax'] + $period_totals['cpp_employee'] + $period_totals['ei_employee'] + $period_totals['other_deductions'] ) ); ?></span>
                <span class="eg-dash-label"><?php esc_html_e( 'Total Deductions', 'eggplant' ); ?></span>
              </div>
              <div class="eg-dash-card">
                <span class="eg-dash-number"><?php echo esc_html( self::format_money( $period_totals['net_pay'] ) ); ?></span>
                <span class="eg-dash-label"><?php esc_html_e( 'Net Pay', 'eggplant' ); ?></span>
              </div>
            </div>
            <div class="eg-table-wrap">
              <table class="widefat striped">
                <thead>
                  <tr>
                    <th><?php esc_html_e( 'Employee', 'eggplant' ); ?></th>
                    <th><?php esc_html_e( 'Regular Hours', 'eggplant' ); ?></th>
                    <th><?php esc_html_e( 'OT Hours', 'eggplant' ); ?></th>
                    <th><?php esc_html_e( 'Gross', 'eggplant' ); ?></th>
                    <th><?php esc_html_e( 'Income Tax', 'eggplant' ); ?></th>
                    <th><?php esc_html_e( 'CPP', 'eggplant' ); ?></th>
                    <th><?php esc_html_e( 'EI', 'eggplant' ); ?></th>
                    <th><?php esc_html_e( 'Other', 'eggplant' ); ?></th>
                    <th><?php esc_html_e( 'Net Pay', 'eggplant' ); ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ( $entries as $entry ) : ?>
                    <tr>
                      <td>
                        <strong><?php echo esc_html( $entry['full_name'] ); ?></strong><br>
                        <span><?php echo esc_html( $entry['staff_identifier'] ); ?></span>
                      </td>
                      <td><?php echo esc_html( number_format_i18n( (float) $entry['regular_hours'], 2 ) ); ?></td>
                      <td><?php echo esc_html( number_format_i18n( (float) $entry['overtime_hours'], 2 ) ); ?></td>
                      <td><?php echo esc_html( self::format_money( $entry['gross_pay'] ) ); ?></td>
                      <td><?php echo esc_html( self::format_money( $entry['income_tax'] ) ); ?></td>
                      <td><?php echo esc_html( self::format_money( $entry['cpp_employee'] ) ); ?></td>
                      <td><?php echo esc_html( self::format_money( $entry['ei_employee'] ) ); ?></td>
                      <td><?php echo esc_html( self::format_money( $entry['other_deductions'] ) ); ?></td>
                      <td><?php echo esc_html( self::format_money( $entry['net_pay'] ) ); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="eg-card">
        <h2><?php esc_html_e( 'T4 Summary', 'eggplant' ); ?></h2>
        <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="eg-inline-form">
          <input type="hidden" name="page" value="eggplant-payroll">
          <?php if ( $selected_period ) : ?>
            <input type="hidden" name="period_id" value="<?php echo esc_attr( $selected_period['id'] ); ?>">
          <?php endif; ?>
          <label for="eg-tax-year"><?php esc_html_e( 'Tax Year', 'eggplant' ); ?></label>
          <input type="number" id="eg-tax-year" name="tax_year" min="2000" step="1" value="<?php echo esc_attr( $selected_year ); ?>">
          <button type="submit" class="button"><?php esc_html_e( 'View Year', 'eggplant' ); ?></button>
        </form>
        <?php if ( empty( $t4_rows ) ) : ?>
          <p><?php esc_html_e( 'No processed payroll entries were found for that year.', 'eggplant' ); ?></p>
        <?php else : ?>
          <div class="eg-table-wrap">
            <table class="widefat striped">
              <thead>
                <tr>
                  <th><?php esc_html_e( 'Employee', 'eggplant' ); ?></th>
                  <th><?php esc_html_e( 'Box 14 Employment Income', 'eggplant' ); ?></th>
                  <th><?php esc_html_e( 'Box 16 CPP', 'eggplant' ); ?></th>
                  <th><?php esc_html_e( 'Box 18 EI', 'eggplant' ); ?></th>
                  <th><?php esc_html_e( 'Box 22 Income Tax', 'eggplant' ); ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ( $t4_rows as $row ) : ?>
                  <tr>
                    <td>
                      <strong><?php echo esc_html( $row['full_name'] ); ?></strong><br>
                      <span><?php echo esc_html( $row['staff_identifier'] ); ?></span>
                    </td>
                    <td><?php echo esc_html( self::format_money( $row['employment_income'] ) ); ?></td>
                    <td><?php echo esc_html( self::format_money( $row['cpp_employee'] ) ); ?></td>
                    <td><?php echo esc_html( self::format_money( $row['ei_employee'] ) ); ?></td>
                    <td><?php echo esc_html( self::format_money( $row['income_tax'] ) ); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php
  }

  /**
   * Render the recent staff entries table.
   */
  public static function render_staff_entry_table( bool $show_actions = true, int $limit = 50 ): void {
    $entries = self::get_recent_staff_entries( $limit );

    if ( empty( $entries ) ) {
      echo '<p>' . esc_html__( 'No staff clock entries yet.', 'eggplant' ) . '</p>';
      return;
    }
    ?>
    <div class="eg-table-wrap">
      <table class="widefat striped">
        <thead>
          <tr>
            <th><?php esc_html_e( 'Staff Member', 'eggplant' ); ?></th>
            <th><?php esc_html_e( 'Clock In', 'eggplant' ); ?></th>
            <th><?php esc_html_e( 'Clock Out', 'eggplant' ); ?></th>
            <th><?php esc_html_e( 'Notes', 'eggplant' ); ?></th>
            <th><?php esc_html_e( 'Status', 'eggplant' ); ?></th>
            <?php if ( $show_actions ) : ?>
              <th><?php esc_html_e( 'Actions', 'eggplant' ); ?></th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ( $entries as $entry ) : ?>
            <?php $is_open = empty( $entry['clock_out_at'] ) || '0000-00-00 00:00:00' === $entry['clock_out_at']; ?>
            <tr class="<?php echo esc_attr( $is_open ? 'eg-row--active' : '' ); ?>">
              <?php if ( $show_actions ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                  <input type="hidden" name="action" value="eggplant_update_staff_entry">
                  <input type="hidden" name="entry_id" value="<?php echo esc_attr( $entry['id'] ); ?>">
                  <?php wp_nonce_field( 'eggplant_update_staff_entry_' . $entry['id'], 'eggplant_update_staff_entry_nonce' ); ?>
                  <td>
                    <input type="text" name="staff_identifier" value="<?php echo esc_attr( $entry['staff_identifier'] ); ?>">
                    <?php if ( ! empty( $entry['full_name'] ) ) : ?>
                      <p class="description"><?php echo esc_html( $entry['full_name'] ); ?></p>
                    <?php endif; ?>
                  </td>
                  <td><input type="datetime-local" name="clock_in_at" value="<?php echo esc_attr( self::format_datetime_input( $entry['clock_in_at'] ) ); ?>"></td>
                  <td><input type="datetime-local" name="clock_out_at" value="<?php echo esc_attr( self::format_datetime_input( $entry['clock_out_at'] ) ); ?>"></td>
                  <td><input type="text" name="notes" value="<?php echo esc_attr( $entry['notes'] ); ?>"></td>
                  <td>
                    <label><input type="checkbox" name="approved" value="1" <?php checked( ! empty( $entry['approved_at'] ) ); ?>> <?php echo esc_html( $is_open ? __( 'Open', 'eggplant' ) : __( 'Closed', 'eggplant' ) ); ?></label>
                  </td>
                  <td><button type="submit" class="button"><?php esc_html_e( 'Save', 'eggplant' ); ?></button></td>
                </form>
              <?php else : ?>
                <td><?php echo esc_html( $entry['staff_identifier'] ); ?></td>
                <td><?php echo esc_html( Eggplant_Operations::format_datetime( $entry['clock_in_at'] ) ); ?></td>
                <td><?php echo esc_html( $is_open ? __( 'Still checked in', 'eggplant' ) : Eggplant_Operations::format_datetime( $entry['clock_out_at'] ) ); ?></td>
                <td><?php echo esc_html( $entry['notes'] ); ?></td>
                <td><span class="eg-task-status <?php echo esc_attr( $is_open ? '' : 'eg-task-status--muted' ); ?>"><?php echo esc_html( $is_open ? __( 'Checked In', 'eggplant' ) : __( 'Checked Out', 'eggplant' ) ); ?></span></td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
  }

  /**
   * Handle staff saves.
   */
  public static function handle_save_staff(): void {
    self::assert_manage_options();
    check_admin_referer( 'eggplant_save_staff', 'eggplant_save_staff_nonce' );

    $staff_id = self::save_staff_record(
      array(
        'id'                  => intval( $_POST['staff_id'] ?? 0 ),
        'staff_identifier'    => wp_unslash( $_POST['staff_identifier'] ?? '' ),
        'full_name'           => wp_unslash( $_POST['full_name'] ?? '' ),
        'email'               => wp_unslash( $_POST['email'] ?? '' ),
        'hire_date'           => wp_unslash( $_POST['hire_date'] ?? '' ),
        'hourly_wage'         => wp_unslash( $_POST['hourly_wage'] ?? '' ),
        'overtime_multiplier' => wp_unslash( $_POST['overtime_multiplier'] ?? '' ),
        'status'              => wp_unslash( $_POST['status'] ?? 'active' ),
      )
    );

    $message = $staff_id ? __( 'Staff member saved.', 'eggplant' ) : __( 'The staff member could not be saved. Staff ID and full name are required.', 'eggplant' );
    self::redirect( self::admin_page_url( 'eggplant-staff' ), array( 'eggplant_message' => $message ) );
  }

  /**
   * Handle payroll settings saves.
   */
  public static function handle_save_payroll_settings(): void {
    self::assert_manage_options();
    check_admin_referer( 'eggplant_save_payroll_settings', 'eggplant_save_payroll_settings_nonce' );

    self::update_settings(
      array(
        'company_name'                   => sanitize_text_field( wp_unslash( $_POST['company_name'] ?? '' ) ),
        'business_number'                => sanitize_text_field( wp_unslash( $_POST['business_number'] ?? '' ) ),
        'pay_periods_per_year'           => max( 1, intval( $_POST['pay_periods_per_year'] ?? 26 ) ),
        'payroll_week_starts'            => 'sunday' === sanitize_key( wp_unslash( $_POST['payroll_week_starts'] ?? 'monday' ) ) ? 'sunday' : 'monday',
        'ontario_overtime_threshold'     => self::sanitize_decimal( wp_unslash( $_POST['ontario_overtime_threshold'] ?? 44 ) ),
        'ontario_overtime_multiplier'    => self::sanitize_decimal( wp_unslash( $_POST['ontario_overtime_multiplier'] ?? 1.5 ) ),
        'vacation_pay_rate'              => self::sanitize_decimal( wp_unslash( $_POST['vacation_pay_rate'] ?? 0.04 ) ),
        'federal_tax_rate'               => self::sanitize_decimal( wp_unslash( $_POST['federal_tax_rate'] ?? 0.15 ) ),
        'ontario_tax_rate'               => self::sanitize_decimal( wp_unslash( $_POST['ontario_tax_rate'] ?? 0.0505 ) ),
        'cpp_rate'                       => self::sanitize_decimal( wp_unslash( $_POST['cpp_rate'] ?? 0.0595 ) ),
        'cpp_basic_exemption_per_period' => self::sanitize_decimal( wp_unslash( $_POST['cpp_basic_exemption_per_period'] ?? 134.62 ) ),
        'cpp_annual_max'                 => self::sanitize_decimal( wp_unslash( $_POST['cpp_annual_max'] ?? 3867.50 ) ),
        'ei_rate'                        => self::sanitize_decimal( wp_unslash( $_POST['ei_rate'] ?? 0.0166 ) ),
        'ei_annual_max'                  => self::sanitize_decimal( wp_unslash( $_POST['ei_annual_max'] ?? 1077.48 ) ),
        'other_deduction_rate'           => self::sanitize_decimal( wp_unslash( $_POST['other_deduction_rate'] ?? 0 ) ),
        'other_deduction_flat'           => self::sanitize_decimal( wp_unslash( $_POST['other_deduction_flat'] ?? 0 ) ),
      )
    );

    self::redirect( self::admin_page_url( 'eggplant-payroll' ), array( 'eggplant_message' => __( 'Payroll settings saved.', 'eggplant' ) ) );
  }

  /**
   * Handle payroll period saves.
   */
  public static function handle_save_payroll_period(): void {
    self::assert_manage_options();
    check_admin_referer( 'eggplant_save_payroll_period', 'eggplant_save_payroll_period_nonce' );

    $period_id = self::save_payroll_period_record(
      array(
        'id'           => intval( $_POST['period_id'] ?? 0 ),
        'period_start' => wp_unslash( $_POST['period_start'] ?? '' ),
        'period_end'   => wp_unslash( $_POST['period_end'] ?? '' ),
        'pay_date'     => wp_unslash( $_POST['pay_date'] ?? '' ),
        'notes'        => wp_unslash( $_POST['notes'] ?? '' ),
        'status'       => 'draft',
      )
    );

    $message = $period_id ? __( 'Pay period saved.', 'eggplant' ) : __( 'The pay period could not be saved.', 'eggplant' );
    $args    = array( 'eggplant_message' => $message );
    if ( $period_id ) {
      $args['period_id'] = $period_id;
    }

    self::redirect( self::admin_page_url( 'eggplant-payroll' ), $args );
  }

  /**
   * Handle payroll processing.
   */
  public static function handle_run_payroll(): void {
    self::assert_manage_options();

    $period_id = intval( $_POST['period_id'] ?? 0 );
    check_admin_referer( 'eggplant_run_payroll_' . $period_id, 'eggplant_run_payroll_nonce' );

    $result = self::run_payroll_for_period( $period_id );
    $parts  = array(
      sprintf(
        /* translators: %d: number of payroll rows */
        __( 'Generated %d payroll row(s).', 'eggplant' ),
        intval( $result['entry_count'] )
      ),
    );

    if ( ! empty( $result['ignored_entries'] ) ) {
      $parts[] = sprintf(
        /* translators: %d: ignored time entries */
        __( '%d time entry(s) were skipped because no matching staff record was found.', 'eggplant' ),
        intval( $result['ignored_entries'] )
      );
    }

    self::redirect(
      self::admin_page_url(
        'eggplant-payroll',
        array(
          'period_id' => $period_id,
        )
      ),
      array(
        'eggplant_message' => implode( ' ', $parts ),
      )
    );
  }

  /**
   * Handle time entry updates.
   */
  public static function handle_update_staff_entry(): void {
    self::assert_manage_options();

    $entry_id = intval( $_POST['entry_id'] ?? 0 );
    check_admin_referer( 'eggplant_update_staff_entry_' . $entry_id, 'eggplant_update_staff_entry_nonce' );

    $updated = self::update_staff_entry(
      array(
        'entry_id'          => $entry_id,
        'staff_identifier'  => wp_unslash( $_POST['staff_identifier'] ?? '' ),
        'clock_in_at'       => wp_unslash( $_POST['clock_in_at'] ?? '' ),
        'clock_out_at'      => wp_unslash( $_POST['clock_out_at'] ?? '' ),
        'notes'             => wp_unslash( $_POST['notes'] ?? '' ),
        'approved'          => ! empty( $_POST['approved'] ),
      )
    );

    self::redirect(
      self::admin_page_url( 'eggplant-staff-clock' ),
      array(
        'eggplant_message' => $updated ? __( 'Time entry saved.', 'eggplant' ) : __( 'The time entry could not be saved.', 'eggplant' ),
      )
    );
  }

  /**
   * Calculate payroll totals for a staff member.
   *
   * @param array<string,mixed> $staff
   * @param array<string,mixed> $summary
   * @param array<string,mixed> $settings
   * @param array<string,mixed> $period
   * @return array<string,mixed>
   */
  private static function calculate_payroll_totals( array $staff, array $summary, array $settings, array $period ): array {
    $year               = intval( substr( (string) $period['pay_date'], 0, 4 ) );
    $year_to_date       = self::get_year_to_date_contributions( intval( $staff['id'] ), $year, intval( $period['id'] ) );
    $hourly_wage        = floatval( $staff['hourly_wage'] ?? 0 );
    $regular_hours      = floatval( $summary['regular_hours'] ?? 0 );
    $overtime_hours     = floatval( $summary['overtime_hours'] ?? 0 );
    $overtime_rate      = floatval( $staff['overtime_multiplier'] ?? $settings['ontario_overtime_multiplier'] );
    $base_pay           = ( $regular_hours * $hourly_wage ) + ( $overtime_hours * $hourly_wage * $overtime_rate );
    $vacation_pay       = round( $base_pay * floatval( $settings['vacation_pay_rate'] ?? 0 ), 2 );
    $gross_pay          = round( $base_pay + $vacation_pay, 2 );
    $federal_tax        = round( $gross_pay * floatval( $settings['federal_tax_rate'] ?? 0 ), 2 );
    $ontario_tax        = round( $gross_pay * floatval( $settings['ontario_tax_rate'] ?? 0 ), 2 );
    $income_tax         = round( $federal_tax + $ontario_tax, 2 );
    $cpp_contribution   = max( 0, round( max( 0, $gross_pay - floatval( $settings['cpp_basic_exemption_per_period'] ?? 0 ) ) * floatval( $settings['cpp_rate'] ?? 0 ), 2 ) );
    $cpp_remaining_room = max( 0, floatval( $settings['cpp_annual_max'] ?? 0 ) - floatval( $year_to_date['cpp_employee'] ?? 0 ) );
    $cpp_employee       = round( min( $cpp_contribution, $cpp_remaining_room ), 2 );
    $ei_contribution    = round( $gross_pay * floatval( $settings['ei_rate'] ?? 0 ), 2 );
    $ei_remaining_room  = max( 0, floatval( $settings['ei_annual_max'] ?? 0 ) - floatval( $year_to_date['ei_employee'] ?? 0 ) );
    $ei_employee        = round( min( $ei_contribution, $ei_remaining_room ), 2 );
    $other_deductions   = round( ( $gross_pay * floatval( $settings['other_deduction_rate'] ?? 0 ) ) + floatval( $settings['other_deduction_flat'] ?? 0 ), 2 );
    $net_pay            = round( $gross_pay - $income_tax - $cpp_employee - $ei_employee - $other_deductions, 2 );

    return array(
      'regular_hours'     => round( $regular_hours, 2 ),
      'overtime_hours'    => round( $overtime_hours, 2 ),
      'hourly_wage'       => round( $hourly_wage, 2 ),
      'gross_pay'         => $gross_pay,
      'vacation_pay'      => $vacation_pay,
      'income_tax'        => $income_tax,
      'cpp_employee'      => $cpp_employee,
      'ei_employee'       => $ei_employee,
      'other_deductions'  => $other_deductions,
      'net_pay'           => $net_pay,
      'deduction_summary' => array(
        'federal_tax'      => $federal_tax,
        'ontario_tax'      => $ontario_tax,
        'cpp_employee'     => $cpp_employee,
        'ei_employee'      => $ei_employee,
        'other_deductions' => $other_deductions,
      ),
    );
  }

  /**
   * Get year-to-date contribution totals before the current period.
   *
   * @return array<string,float>
   */
  private static function get_year_to_date_contributions( int $staff_id, int $year, int $period_id ): array {
    global $wpdb;

    $row = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT
            COALESCE(SUM(e.cpp_employee), 0) AS cpp_employee,
            COALESCE(SUM(e.ei_employee), 0) AS ei_employee
          FROM {$wpdb->prefix}eggplant_payroll_entries e
          INNER JOIN {$wpdb->prefix}eggplant_payroll_periods p ON p.id = e.payroll_period_id
          WHERE e.staff_id = %d
            AND YEAR(p.pay_date) = %d
            AND p.id <> %d",
        $staff_id,
        $year,
        $period_id
      ),
      ARRAY_A
    );

    return array(
      'cpp_employee' => floatval( $row['cpp_employee'] ?? 0 ),
      'ei_employee'  => floatval( $row['ei_employee'] ?? 0 ),
    );
  }

  /**
   * Get hours totals for each staff member in a period.
   *
   * @param array<string,mixed> $period
   * @return array<string,mixed>
   */
  private static function get_staff_hours_for_period( array $period, string $week_starts, float $overtime_threshold ): array {
    global $wpdb;

    $period_start = sanitize_text_field( $period['period_start'] ?? '' ) . ' 00:00:00';
    $period_end   = sanitize_text_field( $period['period_end'] ?? '' ) . ' 23:59:59';
    $results      = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT id, staff_id, staff_identifier, clock_in_at, clock_out_at
          FROM {$wpdb->prefix}eggplant_staff_checkins
          WHERE clock_in_at >= %s
            AND clock_in_at <= %s
            AND clock_out_at IS NOT NULL
            AND clock_out_at <> '0000-00-00 00:00:00'
          ORDER BY clock_in_at ASC, id ASC",
        $period_start,
        $period_end
      ),
      ARRAY_A
    );

    $totals          = array();
    $ignored_entries = 0;

    foreach ( $results as $entry ) {
      $staff_id = intval( $entry['staff_id'] ?? 0 );

      if ( $staff_id <= 0 && ! empty( $entry['staff_identifier'] ) ) {
        $staff = self::find_staff_by_identifier( (string) $entry['staff_identifier'] );
        if ( $staff ) {
          $staff_id = intval( $staff['id'] );
        }
      }

      if ( $staff_id <= 0 ) {
        ++$ignored_entries;
        continue;
      }

      $clock_in  = strtotime( (string) $entry['clock_in_at'] );
      $clock_out = strtotime( (string) $entry['clock_out_at'] );
      if ( false === $clock_in || false === $clock_out || $clock_out <= $clock_in ) {
        continue;
      }

      $hours      = round( ( $clock_out - $clock_in ) / HOUR_IN_SECONDS, 2 );
      $week_bucket = self::get_week_bucket( (string) $entry['clock_in_at'], $week_starts );

      if ( empty( $totals[ $staff_id ] ) ) {
        $totals[ $staff_id ] = array(
          'total_hours'   => 0,
          'weekly_totals' => array(),
        );
      }

      $totals[ $staff_id ]['total_hours'] += $hours;
      if ( empty( $totals[ $staff_id ]['weekly_totals'][ $week_bucket ] ) ) {
        $totals[ $staff_id ]['weekly_totals'][ $week_bucket ] = 0;
      }
      $totals[ $staff_id ]['weekly_totals'][ $week_bucket ] += $hours;
    }

    foreach ( $totals as $staff_id => $summary ) {
      $regular_hours  = 0;
      $overtime_hours = 0;

      foreach ( (array) $summary['weekly_totals'] as $week_hours ) {
        $week_hours      = floatval( $week_hours );
        $regular_hours  += min( $week_hours, $overtime_threshold );
        $overtime_hours += max( 0, $week_hours - $overtime_threshold );
      }

      $totals[ $staff_id ]['regular_hours']  = round( $regular_hours, 2 );
      $totals[ $staff_id ]['overtime_hours'] = round( $overtime_hours, 2 );
    }

    $totals['ignored_entries'] = $ignored_entries;

    return $totals;
  }

  /**
   * Convert a datetime to its payroll week bucket.
   */
  private static function get_week_bucket( string $datetime, string $week_starts ): string {
    try {
      $date = new DateTimeImmutable( $datetime, function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' ) );
    } catch ( Exception $e ) {
      return gmdate( 'Y-m-d' );
    }

    $start_index = 'sunday' === $week_starts ? 0 : 1;
    $day_index   = intval( $date->format( 'w' ) );
    $offset      = ( $day_index - $start_index + 7 ) % 7;

    return $date->modify( '-' . $offset . ' days' )->format( 'Y-m-d' );
  }

  /**
   * Sum payroll entry columns for totals.
   *
   * @param array<int,array<string,mixed>> $entries
   * @return array<string,float>
   */
  private static function sum_payroll_entries( array $entries ): array {
    $totals = array(
      'gross_pay'        => 0,
      'income_tax'       => 0,
      'cpp_employee'     => 0,
      'ei_employee'      => 0,
      'other_deductions' => 0,
      'net_pay'          => 0,
    );

    foreach ( $entries as $entry ) {
      $totals['gross_pay']        += floatval( $entry['gross_pay'] ?? 0 );
      $totals['income_tax']       += floatval( $entry['income_tax'] ?? 0 );
      $totals['cpp_employee']     += floatval( $entry['cpp_employee'] ?? 0 );
      $totals['ei_employee']      += floatval( $entry['ei_employee'] ?? 0 );
      $totals['other_deductions'] += floatval( $entry['other_deductions'] ?? 0 );
      $totals['net_pay']          += floatval( $entry['net_pay'] ?? 0 );
    }

    return $totals;
  }

  /**
   * Ensure an admin-capable user is acting.
   */
  private static function assert_manage_options(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html__( 'You are not allowed to access payroll tools.', 'eggplant' ) );
    }
  }

  /**
   * Build an admin page URL.
   *
   * @param array<string,mixed> $args
   */
  private static function admin_page_url( string $page, array $args = array() ): string {
    return add_query_arg( $args, admin_url( 'admin.php?page=' . $page ) );
  }

  /**
   * Redirect and stop execution.
   *
   * @param array<string,mixed> $args
   */
  private static function redirect( string $url, array $args = array() ): void {
    wp_safe_redirect( add_query_arg( $args, $url ) );
    exit;
  }

  /**
   * Get the current request message.
   */
  private static function get_message(): string {
    return isset( $_GET['eggplant_message'] ) ? sanitize_text_field( wp_unslash( $_GET['eggplant_message'] ) ) : '';
  }

  /**
   * Normalize decimal input.
   *
   * @param mixed $value
   */
  private static function sanitize_decimal( $value ): float {
    return round( floatval( is_string( $value ) ? str_replace( ',', '', $value ) : $value ), 4 );
  }

  /**
   * Normalize datetime-local input.
   *
   * @param mixed $value
   */
  private static function normalize_datetime_input( $value ): ?string {
    $value = sanitize_text_field( (string) $value );
    if ( '' === $value ) {
      return null;
    }

    $timestamp = strtotime( str_replace( 'T', ' ', $value ) );
    if ( false === $timestamp ) {
      return null;
    }

    return gmdate( 'Y-m-d H:i:s', $timestamp );
  }

  /**
   * Format a database datetime for datetime-local inputs.
   */
  private static function format_datetime_input( ?string $datetime ): string {
    if ( empty( $datetime ) || '0000-00-00 00:00:00' === $datetime ) {
      return '';
    }

    $timestamp = strtotime( $datetime );
    return false === $timestamp ? '' : gmdate( 'Y-m-d\TH:i', $timestamp );
  }

  /**
   * Format money.
   *
   * @param mixed $amount
   */
  public static function format_money( $amount ): string {
    $settings = self::get_settings();
    return ( $settings['currency_symbol'] ?? '$' ) . number_format_i18n( floatval( $amount ), 2 );
  }
}
