<?php

/**
 * Operational features: recurring tasks, task status, and staff time clock.
 *
 * @since 1.1.0
 * @package Eggplant
 */

class Eggplant_Operations {

  /**
   * Bootstraps shortcodes and form handlers.
   */
  public static function init(): void {
    add_shortcode( 'eggplant_tasks', array( __CLASS__, 'render_tasks_shortcode' ) );
    add_shortcode( 'eggplant_staff_clock', array( __CLASS__, 'render_staff_clock_shortcode' ) );

    add_action( 'admin_post_eggplant_save_task', array( __CLASS__, 'handle_save_task' ) );
    add_action( 'admin_post_eggplant_complete_task', array( __CLASS__, 'handle_complete_task' ) );
    add_action( 'admin_post_eggplant_staff_clock', array( __CLASS__, 'handle_staff_clock' ) );
    add_action( 'admin_post_nopriv_eggplant_complete_task', array( __CLASS__, 'handle_complete_task' ) );
    add_action( 'admin_post_nopriv_eggplant_staff_clock', array( __CLASS__, 'handle_staff_clock' ) );

    add_action( 'wp_ajax_eggplant_refresh_tasks', array( __CLASS__, 'ajax_refresh_tasks' ) );
    add_action( 'wp_ajax_nopriv_eggplant_refresh_tasks', array( __CLASS__, 'ajax_refresh_tasks' ) );
  }

  /**
   * Supported interval labels.
   *
   * @return array<string,string>
   */
  public static function get_interval_options(): array {
    return array(
      'hours'  => __( 'Hours', 'eggplant' ),
      'days'   => __( 'Days', 'eggplant' ),
      'weeks'  => __( 'Weeks', 'eggplant' ),
      'months' => __( 'Months', 'eggplant' ),
    );
  }

  /**
   * Return human label for an interval.
   *
   * @param array<string,mixed> $task
   */
  public static function get_interval_label( array $task ): string {
    $type  = sanitize_key( $task['interval_type'] ?? 'hours' );
    $value = max( 1, intval( $task['interval_value'] ?? 1 ) );

    if ( 1 === $value ) {
      $labels = array(
        'hours'  => __( 'Hourly', 'eggplant' ),
        'days'   => __( 'Daily', 'eggplant' ),
        'weeks'  => __( 'Weekly', 'eggplant' ),
        'months' => __( 'Monthly', 'eggplant' ),
      );

      return $labels[ $type ] ?? __( 'Custom', 'eggplant' );
    }

    return sprintf(
      /* translators: 1: interval number, 2: interval unit */
      __( 'Every %1$d %2$s', 'eggplant' ),
      $value,
      $type
    );
  }

  /**
   * Calculate the next due datetime for a task.
   */
  public static function calculate_next_due( string $interval_type, int $interval_value, ?string $base_time ): ?string {
    $value = max( 1, $interval_value );
    $type  = sanitize_key( $interval_type );
    $tz    = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );

    try {
      $datetime = $base_time ? new DateTimeImmutable( $base_time, $tz ) : new DateTimeImmutable( 'now', $tz );
    } catch ( Exception $e ) {
      $datetime = new DateTimeImmutable( 'now', $tz );
    }

    switch ( $type ) {
      case 'days':
        $datetime = $datetime->modify( '+' . $value . ' days' );
        break;
      case 'weeks':
        $datetime = $datetime->modify( '+' . $value . ' weeks' );
        break;
      case 'months':
        $datetime = $datetime->modify( '+' . $value . ' months' );
        break;
      case 'hours':
      default:
        $datetime = $datetime->modify( '+' . $value . ' hours' );
        break;
    }

    return $datetime->format( 'Y-m-d H:i:s' );
  }

  /**
   * Return task status metadata.
   *
   * @param array<string,mixed> $task
   * @return array<string,mixed>
   */
  public static function get_task_status( array $task ): array {
    $next_due = $task['next_due_at'] ?? '';
    if ( empty( $next_due ) ) {
      $next_due = self::calculate_next_due(
        (string) ( $task['interval_type'] ?? 'hours' ),
        max( 1, intval( $task['interval_value'] ?? 1 ) ),
        ! empty( $task['last_completed_at'] ) ? (string) $task['last_completed_at'] : (string) ( $task['created_at'] ?? '' )
      );
    }

    $now        = current_time( 'timestamp' );
    $due_stamp  = $next_due ? strtotime( $next_due ) : false;
    $is_overdue = $due_stamp && $due_stamp <= $now;

    return array(
      'last_completed_at' => $task['last_completed_at'] ?? '',
      'next_due_at'       => $next_due,
      'is_overdue'        => (bool) $is_overdue,
      'status_label'      => $is_overdue ? __( 'Overdue', 'eggplant' ) : __( 'On Schedule', 'eggplant' ),
    );
  }

  /**
   * Update last/next due fields after task changes or completions.
   */
  public static function refresh_task_schedule( int $task_id ): void {
    $task = Eggplant_DB::get_task( $task_id );
    if ( ! $task ) {
      return;
    }

    $latest_completion = Eggplant_DB::get_latest_task_completion( $task_id );
    $last_completed_at = $latest_completion['completed_at'] ?? null;
    $base_time         = $last_completed_at ?: ( $task['created_at'] ?? current_time( 'mysql' ) );
    $next_due_at       = self::calculate_next_due(
      (string) $task['interval_type'],
      max( 1, intval( $task['interval_value'] ) ),
      $base_time
    );

    Eggplant_DB::update_task_schedule( $task_id, $last_completed_at, $next_due_at );
  }

  /**
   * Format a database datetime for display.
   */
  public static function format_datetime( ?string $datetime ): string {
    if ( empty( $datetime ) ) {
      return __( 'Not yet', 'eggplant' );
    }

    $timestamp = strtotime( $datetime );
    if ( false === $timestamp ) {
      return $datetime;
    }

    return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
  }

  /**
   * Render the recurring task list shortcode.
   */
  public static function render_tasks_shortcode(): string {
    $tasks   = Eggplant_DB::get_active_tasks();
    $message = self::get_request_message();

    ob_start();
    ?>
    <div class="eg-ops eg-ops-tasks">
      <?php if ( $message ) : ?>
        <div class="eg-ops-message"><?php echo esc_html( $message ); ?></div>
      <?php endif; ?>

      <div class="eg-ops-header">
        <h2><?php esc_html_e( 'Operational Tasks', 'eggplant' ); ?></h2>
      </div>

      <?php if ( empty( $tasks ) ) : ?>
        <p><?php esc_html_e( 'No recurring tasks have been configured yet.', 'eggplant' ); ?></p>
      <?php else : ?>
        <div class="eg-ops-staff">
          <label for="eg-ops-staff-identifier"><?php esc_html_e( 'Staff member', 'eggplant' ); ?></label>
          <input type="text" id="eg-ops-staff-identifier" class="eg-ops-staff-identifier" placeholder="<?php esc_attr_e( 'Enter your name or staff ID', 'eggplant' ); ?>">
        </div>
        <div id="eg-ops-tasks-list" class="eg-ops-list" data-nonce="<?php echo esc_attr( wp_create_nonce( 'eggplant_refresh_tasks' ) ); ?>">
          <?php echo self::render_task_items( $tasks ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped inside ?>
        </div>
      <?php endif; ?>
    </div>
    <?php

    return (string) ob_get_clean();
  }

  /**
   * Render task item HTML sorted with overdue tasks first.
   *
   * @param array<int,array<string,mixed>> $tasks
   */
  private static function render_task_items( array $tasks ): string {
    $button_class = is_admin() ? 'button button-primary' : 'eg-btn eg-btn--primary';

    // Attach status and sort: overdue first, then by next_due_at ascending.
    $decorated = array();
    foreach ( $tasks as $task ) {
      $status        = self::get_task_status( $task );
      $decorated[]   = array( 'task' => $task, 'status' => $status );
    }

    usort( $decorated, function ( $a, $b ) {
      if ( $a['status']['is_overdue'] !== $b['status']['is_overdue'] ) {
        return $a['status']['is_overdue'] ? -1 : 1;
      }
      $at = $a['status']['next_due_at'] ? strtotime( (string) $a['status']['next_due_at'] ) : PHP_INT_MAX;
      $bt = $b['status']['next_due_at'] ? strtotime( (string) $b['status']['next_due_at'] ) : PHP_INT_MAX;
      return $at <=> $bt;
    } );

    ob_start();
    foreach ( $decorated as $item ) :
      $task   = $item['task'];
      $status = $item['status'];
      ?>
      <article class="eg-ops-task <?php echo $status['is_overdue'] ? 'eg-ops-task--overdue' : ''; ?>">
        <div class="eg-ops-task__content">
          <h3><?php echo esc_html( $task['task_name'] ); ?></h3>
          <p class="eg-ops-task__meta">
            <span><?php echo esc_html( self::get_interval_label( $task ) ); ?></span>
            <span><?php echo esc_html( sprintf( __( 'Last completed: %s', 'eggplant' ), self::format_datetime( $status['last_completed_at'] ) ) ); ?></span>
            <span><?php echo esc_html( sprintf( __( 'Due: %s', 'eggplant' ), self::format_datetime( $status['next_due_at'] ) ) ); ?></span>
          </p>
        </div>
        <div class="eg-ops-task__actions">
          <span class="eg-ops-status"><?php echo esc_html( $status['status_label'] ); ?></span>
          <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="eg-ops-complete-form">
            <input type="hidden" name="action" value="eggplant_complete_task">
            <input type="hidden" name="task_id" value="<?php echo esc_attr( $task['id'] ); ?>">
            <input type="hidden" name="redirect_to" value="<?php echo esc_url( self::get_current_url() ); ?>">
            <?php wp_nonce_field( 'eggplant_complete_task_' . $task['id'], 'eggplant_complete_task_nonce' ); ?>
            <input type="hidden" name="staff_identifier" class="eg-ops-staff-mirror" value="">
            <button type="submit" class="<?php echo esc_attr( $button_class ); ?>"><?php esc_html_e( 'Mark Complete', 'eggplant' ); ?></button>
          </form>
        </div>
      </article>
      <?php
    endforeach;
    return (string) ob_get_clean();
  }

  /**
   * AJAX handler: return refreshed task list HTML.
   */
  public static function ajax_refresh_tasks(): void {
    check_ajax_referer( 'eggplant_refresh_tasks', 'nonce' );
    $tasks = Eggplant_DB::get_active_tasks();
    wp_send_json_success( self::render_task_items( $tasks ) );
  }

  /**
   * Render the staff clock shortcode.
   */
  public static function render_staff_clock_shortcode(): string {
    $message                  = self::get_request_message();
    $primary_button_class   = is_admin() ? 'button button-primary' : 'eg-btn eg-btn--primary';
    $secondary_button_class = is_admin() ? 'button' : 'eg-btn';
    $staff_members            = class_exists( 'Eggplant_Payroll' ) ? Eggplant_Payroll::get_active_staff() : array();

    ob_start();
    ?>
    <div class="eg-ops eg-ops-clock">
      <?php if ( $message ) : ?>
        <div class="eg-ops-message"><?php echo esc_html( $message ); ?></div>
      <?php endif; ?>

      <h2><?php esc_html_e( 'Staff Time Clock', 'eggplant' ); ?></h2>
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="eg-ops-clock-form">
        <input type="hidden" name="action" value="eggplant_staff_clock">
        <input type="hidden" name="redirect_to" value="<?php echo esc_url( self::get_current_url() ); ?>">
        <?php wp_nonce_field( 'eggplant_staff_clock', 'eggplant_staff_clock_nonce' ); ?>

        <div class="eg-form-row">
          <label for="eg-staff-identifier"><?php esc_html_e( 'Staff member', 'eggplant' ); ?></label>
          <input type="text" id="eg-staff-identifier" name="staff_identifier" list="eg-staff-directory" required placeholder="<?php esc_attr_e( 'Enter your name or staff ID', 'eggplant' ); ?>">
          <?php if ( ! empty( $staff_members ) ) : ?>
            <datalist id="eg-staff-directory">
              <?php foreach ( $staff_members as $staff_member ) : ?>
                <option value="<?php echo esc_attr( $staff_member['staff_identifier'] ); ?>"><?php echo esc_html( $staff_member['full_name'] ); ?></option>
              <?php endforeach; ?>
            </datalist>
          <?php endif; ?>
        </div>

        <div class="eg-form-row">
          <label for="eg-clock-note"><?php esc_html_e( 'Notes', 'eggplant' ); ?></label>
          <input type="text" id="eg-clock-note" name="notes" placeholder="<?php esc_attr_e( 'Optional shift note', 'eggplant' ); ?>">
        </div>

        <div class="eg-ops-clock-actions">
          <button type="submit" name="clock_action" value="check_in" class="<?php echo esc_attr( $primary_button_class ); ?>"><?php esc_html_e( 'Check In', 'eggplant' ); ?></button>
          <button type="submit" name="clock_action" value="check_out" class="<?php echo esc_attr( $secondary_button_class ); ?>"><?php esc_html_e( 'Check Out', 'eggplant' ); ?></button>
        </div>
      </form>
    </div>
    <?php

    return (string) ob_get_clean();
  }

  /**
   * Handle admin task saves.
   */
  public static function handle_save_task(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html__( 'You are not allowed to manage tasks.', 'eggplant' ) );
    }

    check_admin_referer( 'eggplant_save_task', 'eggplant_save_task_nonce' );

    $task_id         = intval( $_POST['task_id'] ?? 0 );
    $interval_type   = sanitize_key( wp_unslash( $_POST['interval_type'] ?? 'hours' ) );
    $interval_value  = max( 1, intval( $_POST['interval_value'] ?? 1 ) );
    $allowed_types   = array_keys( self::get_interval_options() );
    $data            = array(
      'task_name'       => sanitize_text_field( wp_unslash( $_POST['task_name'] ?? '' ) ),
      'interval_type'   => in_array( $interval_type, $allowed_types, true ) ? $interval_type : 'hours',
      'interval_value'  => $interval_value,
      'active'          => ! empty( $_POST['active'] ) ? 1 : 0,
    );

    if ( empty( $data['task_name'] ) ) {
      self::safe_redirect( self::get_admin_tasks_url( $task_id ), array( 'eggplant_message' => __( 'Task name is required.', 'eggplant' ) ) );
    }

    if ( $task_id ) {
      Eggplant_DB::update_task( $task_id, $data );
    } else {
      $task_id = (int) Eggplant_DB::insert_task( $data );
    }

    if ( ! $task_id ) {
      self::safe_redirect( self::get_admin_tasks_url(), array( 'eggplant_message' => __( 'The task could not be saved.', 'eggplant' ) ) );
    }

    self::refresh_task_schedule( $task_id );

    self::safe_redirect(
      self::get_admin_tasks_url(),
      array( 'eggplant_message' => __( 'Task saved.', 'eggplant' ) )
    );
  }

  /**
   * Handle task completion posts.
   */
  public static function handle_complete_task(): void {
    $task_id = intval( $_POST['task_id'] ?? 0 );
    check_admin_referer( 'eggplant_complete_task_' . $task_id, 'eggplant_complete_task_nonce' );

    $task = Eggplant_DB::get_task( $task_id );
    if ( ! $task ) {
      self::safe_redirect( self::get_redirect_target(), array( 'eggplant_message' => __( 'Task not found.', 'eggplant' ) ) );
    }

    $staff_identifier = sanitize_text_field( wp_unslash( $_POST['staff_identifier'] ?? '' ) );
    Eggplant_DB::insert_task_completion(
      array(
        'task_id'           => $task_id,
        'staff_identifier'  => $staff_identifier,
      )
    );

    self::refresh_task_schedule( $task_id );

    self::safe_redirect(
      self::get_redirect_target(),
      array( 'eggplant_message' => __( 'Task marked complete.', 'eggplant' ) )
    );
  }

  /**
   * Handle staff clock check-ins/check-outs.
   */
  public static function handle_staff_clock(): void {
    check_admin_referer( 'eggplant_staff_clock', 'eggplant_staff_clock_nonce' );

    $staff_identifier = sanitize_text_field( wp_unslash( $_POST['staff_identifier'] ?? '' ) );
    $clock_action     = sanitize_key( wp_unslash( $_POST['clock_action'] ?? 'check_in' ) );
    $notes            = sanitize_text_field( wp_unslash( $_POST['notes'] ?? '' ) );

    if ( empty( $staff_identifier ) ) {
      self::safe_redirect( self::get_redirect_target(), array( 'eggplant_message' => __( 'Staff member is required.', 'eggplant' ) ) );
    }

    if ( 'check_out' === $clock_action ) {
      $entry_id = Eggplant_DB::check_out_staff( $staff_identifier );
      $message  = $entry_id ? __( 'Staff member checked out.', 'eggplant' ) : __( 'No open clock entry was found for that staff member.', 'eggplant' );
    } else {
      $staff = class_exists( 'Eggplant_Payroll' ) ? Eggplant_Payroll::find_staff_by_identifier( $staff_identifier ) : null;
      $entry_id = Eggplant_DB::check_in_staff(
        array(
          'staff_id'         => $staff['id'] ?? 0,
          'staff_identifier' => $staff_identifier,
          'notes'            => $notes,
        )
      );
      $message = $entry_id ? __( 'Staff member checked in.', 'eggplant' ) : __( 'That staff member is already checked in.', 'eggplant' );
    }

    self::safe_redirect( self::get_redirect_target(), array( 'eggplant_message' => $message ) );
  }

  /**
   * Return current-page message from redirects.
   */
  private static function get_request_message(): string {
    return isset( $_GET['eggplant_message'] ) ? sanitize_text_field( wp_unslash( $_GET['eggplant_message'] ) ) : '';
  }

  /**
   * Redirect safely and terminate.
   *
   * @param string               $url
   * @param array<string,string> $args
   */
  private static function safe_redirect( string $url, array $args = array() ): void {
    wp_safe_redirect( add_query_arg( $args, $url ) );
    exit;
  }

  /**
   * Return current front-end/admin URL.
   */
  private static function get_current_url(): string {
    $scheme      = is_ssl() ? 'https://' : 'http://';
    $host        = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) );
    $request_uri = wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' );
    return esc_url_raw( $scheme . $host . $request_uri );
  }

  /**
   * Return admin task page URL.
   */
  public static function get_admin_tasks_url( int $task_id = 0 ): string {
    $url = admin_url( 'admin.php?page=eggplant-operations' );
    if ( $task_id ) {
      $url = add_query_arg( 'task_id', $task_id, $url );
    }
    return $url;
  }

  /**
   * Determine post-redirect URL.
   */
  private static function get_redirect_target(): string {
    $redirect_to = esc_url_raw( wp_unslash( $_POST['redirect_to'] ?? '' ) );
    return $redirect_to ?: self::get_current_url();
  }
}
