<?php

/**
 * Database helper – thin wrapper around $wpdb for plugin tables.
 *
 * @since 1.0.0
 * @package Eggplant
 */

class Eggplant_DB {

  // ------------------------------------------------------------------ time slots

  /**
   * Insert a time slot.
   *
   * @param array<string,mixed> $data  Keys: slot_date, start_time, end_time, label, status
   * @return int|false  Inserted row ID or false on failure.
   */
  public static function insert_slot( array $data ) {
    global $wpdb;
    $result = $wpdb->insert(
      $wpdb->prefix . 'eggplant_time_slots',
      array(
        'slot_date'  => sanitize_text_field( $data['slot_date']  ?? '' ),
        'start_time' => sanitize_text_field( $data['start_time'] ?? '' ),
        'end_time'   => sanitize_text_field( $data['end_time']   ?? '' ),
        'label'      => sanitize_text_field( $data['label']      ?? '' ),
        'status'     => in_array( $data['status'] ?? 'available', array( 'available', 'booked', 'held' ), true )
                        ? $data['status'] : 'available',
      ),
      array( '%s', '%s', '%s', '%s', '%s' )
    );
    return $result ? $wpdb->insert_id : false;
  }

  /**
   * Update the status of a slot.
   *
   * @param int    $id
   * @param string $status  available|booked|held
   */
  public static function update_slot_status( int $id, string $status ): bool {
    global $wpdb;
    if ( ! in_array( $status, array( 'available', 'booked', 'held' ), true ) ) {
      return false;
    }
    $result = $wpdb->update(
      $wpdb->prefix . 'eggplant_time_slots',
      array( 'status' => $status ),
      array( 'id'     => $id ),
      array( '%s' ),
      array( '%d' )
    );
    return $result !== false;
  }

  /**
   * Delete a slot.
   *
   * @param int $id
   */
  public static function delete_slot( int $id ): bool {
    global $wpdb;
    $result = $wpdb->delete(
      $wpdb->prefix . 'eggplant_time_slots',
      array( 'id' => $id ),
      array( '%d' )
    );
    return $result !== false;
  }

  /**
   * Get all slots for a given month (YYYY-MM).
   *
   * @param string $year_month  e.g. "2024-07"
   * @return array<int,array<string,mixed>>
   */
  public static function get_slots_for_month( string $year_month ): array {
    global $wpdb;
    $like = $wpdb->esc_like( $year_month ) . '%';
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $results = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eggplant_time_slots WHERE slot_date LIKE %s ORDER BY slot_date, start_time",
        $like
      ),
      ARRAY_A
    );
    return $results ?: array();
  }

  /**
   * Get all slots (admin list).
   *
   * @return array<int,array<string,mixed>>
   */
  public static function get_all_slots(): array {
    global $wpdb;
    $results = $wpdb->get_results(
      // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name uses $wpdb->prefix (safe internal value), no user input.
      "SELECT * FROM {$wpdb->prefix}eggplant_time_slots ORDER BY slot_date DESC, start_time",
      ARRAY_A
    );
    return $results ?: array();
  }

  // ------------------------------------------------------------------ events

  /**
   * Get all active events sorted by sort_order.
   *
   * @return array<int,array<string,mixed>>
   */
  public static function get_active_events(): array {
    global $wpdb;
    $today   = current_time( 'Y-m-d' );
    $results = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eggplant_events
          WHERE active = 1
            AND (end_date IS NULL OR end_date = '' OR end_date >= %s)
          ORDER BY sort_order ASC, id ASC",
        $today
      ),
      ARRAY_A
    );
    return $results ?: array();
  }

  /**
   * Get all events for admin management.
   *
   * @return array<int,array<string,mixed>>
   */
  public static function get_all_events(): array {
    global $wpdb;
    $results = $wpdb->get_results(
      // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name uses $wpdb->prefix (safe internal value), no user input.
      "SELECT * FROM {$wpdb->prefix}eggplant_events ORDER BY sort_order ASC, id ASC",
      ARRAY_A
    );
    return $results ?: array();
  }

  /**
   * Insert an event.
   *
   * @param array<string,mixed> $data
   * @return int|false
   */
  public static function insert_event( array $data ) {
    global $wpdb;
    $result = $wpdb->insert(
      $wpdb->prefix . 'eggplant_events',
      array(
        'title'       => sanitize_text_field( $data['title']       ?? '' ),
        'description' => wp_kses_post( $data['description']        ?? '' ),
        'image_url'   => esc_url_raw( $data['image_url']           ?? '' ),
        'link_url'    => esc_url_raw( $data['link_url']            ?? '' ),
        'start_date'  => sanitize_text_field( $data['start_date']  ?? '' ) ?: null,
        'end_date'    => sanitize_text_field( $data['end_date']    ?? '' ) ?: null,
        'sort_order'  => intval( $data['sort_order']               ?? 0 ),
        'active'      => isset( $data['active'] ) ? (int) $data['active'] : 1,
      ),
      array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
    );
    return $result ? $wpdb->insert_id : false;
  }

  /**
   * Update an event.
   *
   * @param int                 $id
   * @param array<string,mixed> $data
   */
  public static function update_event( int $id, array $data ): bool {
    global $wpdb;
    $result = $wpdb->update(
      $wpdb->prefix . 'eggplant_events',
      array(
        'title'       => sanitize_text_field( $data['title']       ?? '' ),
        'description' => wp_kses_post( $data['description']        ?? '' ),
        'image_url'   => esc_url_raw( $data['image_url']           ?? '' ),
        'link_url'    => esc_url_raw( $data['link_url']            ?? '' ),
        'start_date'  => sanitize_text_field( $data['start_date']  ?? '' ) ?: null,
        'end_date'    => sanitize_text_field( $data['end_date']    ?? '' ) ?: null,
        'sort_order'  => intval( $data['sort_order']               ?? 0 ),
        'active'      => isset( $data['active'] ) ? (int) $data['active'] : 1,
      ),
      array( 'id' => $id ),
      array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' ),
      array( '%d' )
    );
    return $result !== false;
  }

  /**
   * Delete an event.
   *
   * @param int $id
   */
  public static function delete_event( int $id ): bool {
    global $wpdb;
    $result = $wpdb->delete(
      $wpdb->prefix . 'eggplant_events',
      array( 'id' => $id ),
      array( '%d' )
    );
    return $result !== false;
  }

  // ------------------------------------------------------------------ bookings

  /**
   * Insert a booking request.
   *
   * @param array<string,mixed> $data
   * @return int|false
   */
  public static function insert_booking( array $data ) {
    global $wpdb;
    $result = $wpdb->insert(
      $wpdb->prefix . 'eggplant_bookings',
      array(
        'name'         => sanitize_text_field( $data['name']         ?? '' ),
        'email'        => sanitize_email( $data['email']             ?? '' ),
        'phone'        => sanitize_text_field( $data['phone']        ?? '' ),
        'event_type'   => sanitize_text_field( $data['event_type']   ?? '' ),
        'event_date'   => sanitize_text_field( $data['event_date']   ?? '' ) ?: null,
        'time_slot_id' => ! empty( $data['time_slot_id'] ) ? intval( $data['time_slot_id'] ) : null,
        'message'      => sanitize_textarea_field( $data['message']  ?? '' ),
        'status'       => 'new',
      ),
      array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
    );
    return $result ? $wpdb->insert_id : false;
  }

  /**
   * Get all bookings for admin.
   *
   * @return array<int,array<string,mixed>>
   */
  public static function get_all_bookings(): array {
    global $wpdb;
    $results = $wpdb->get_results(
      // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table names use $wpdb->prefix (safe internal value), no user input.
      "SELECT b.*, s.start_time, s.end_time, s.label AS slot_label
        FROM {$wpdb->prefix}eggplant_bookings b
        LEFT JOIN {$wpdb->prefix}eggplant_time_slots s ON s.id = b.time_slot_id
        ORDER BY b.created_at DESC",
      ARRAY_A
    );
    return $results ?: array();
  }

  /**
   * Update a booking status.
   *
   * @param int    $id
   * @param string $status  new|reviewed|approved|declined
   */
  public static function update_booking_status( int $id, string $status ): bool {
    global $wpdb;
    if ( ! in_array( $status, array( 'new', 'reviewed', 'approved', 'declined' ), true ) ) {
      return false;
    }
    $result = $wpdb->update(
      $wpdb->prefix . 'eggplant_bookings',
      array( 'status' => $status ),
      array( 'id'     => $id ),
      array( '%s' ),
      array( '%d' )
    );
    return $result !== false;
  }

  // ------------------------------------------------------------------ recurring tasks

  /**
   * Insert a recurring task.
   *
   * @param array<string,mixed> $data
   * @return int|false
   */
  public static function insert_task( array $data ) {
    global $wpdb;
    $result = $wpdb->insert(
      $wpdb->prefix . 'eggplant_tasks',
      array(
        'task_name'      => sanitize_text_field( $data['task_name'] ?? '' ),
        'interval_type'  => sanitize_key( $data['interval_type'] ?? 'hours' ),
        'interval_value' => max( 1, intval( $data['interval_value'] ?? 1 ) ),
        'active'         => isset( $data['active'] ) ? (int) $data['active'] : 1,
      ),
      array( '%s', '%s', '%d', '%d' )
    );

    return $result ? $wpdb->insert_id : false;
  }

  /**
   * Update a recurring task.
   *
   * @param int                 $id
   * @param array<string,mixed> $data
   */
  public static function update_task( int $id, array $data ): bool {
    global $wpdb;
    $result = $wpdb->update(
      $wpdb->prefix . 'eggplant_tasks',
      array(
        'task_name'      => sanitize_text_field( $data['task_name'] ?? '' ),
        'interval_type'  => sanitize_key( $data['interval_type'] ?? 'hours' ),
        'interval_value' => max( 1, intval( $data['interval_value'] ?? 1 ) ),
        'active'         => isset( $data['active'] ) ? (int) $data['active'] : 1,
      ),
      array( 'id' => $id ),
      array( '%s', '%s', '%d', '%d' ),
      array( '%d' )
    );

    return $result !== false;
  }

  /**
   * Update derived schedule fields.
   */
  public static function update_task_schedule( int $id, ?string $last_completed_at, ?string $next_due_at ): bool {
    global $wpdb;
    $result = $wpdb->update(
      $wpdb->prefix . 'eggplant_tasks',
      array(
        'last_completed_at' => $last_completed_at,
        'next_due_at'       => $next_due_at,
      ),
      array( 'id' => $id ),
      array( '%s', '%s' ),
      array( '%d' )
    );

    return $result !== false;
  }

  /**
   * Get a single recurring task.
   *
   * @return array<string,mixed>|null
   */
  public static function get_task( int $id ): ?array {
    global $wpdb;
    $row = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eggplant_tasks WHERE id = %d",
        $id
      ),
      ARRAY_A
    );

    return $row ?: null;
  }

  /**
   * Get all recurring tasks for admins.
   *
   * @return array<int,array<string,mixed>>
   */
  public static function get_all_tasks(): array {
    global $wpdb;
    $results = $wpdb->get_results(
      // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name uses $wpdb->prefix (safe internal value), no user input.
      "SELECT * FROM {$wpdb->prefix}eggplant_tasks ORDER BY active DESC, next_due_at ASC, task_name ASC",
      ARRAY_A
    );

    return $results ?: array();
  }

  /**
   * Get active recurring tasks for display.
   *
   * @return array<int,array<string,mixed>>
   */
  public static function get_active_tasks(): array {
    global $wpdb;
    $results = $wpdb->get_results(
      // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name uses $wpdb->prefix (safe internal value), no user input.
      "SELECT * FROM {$wpdb->prefix}eggplant_tasks WHERE active = 1 ORDER BY next_due_at ASC, task_name ASC",
      ARRAY_A
    );

    return $results ?: array();
  }

  /**
   * Insert a task completion record.
   *
   * @param array<string,mixed> $data
   * @return int|false
   */
  public static function insert_task_completion( array $data ) {
    global $wpdb;
    $result = $wpdb->insert(
      $wpdb->prefix . 'eggplant_task_completions',
      array(
        'task_id'          => intval( $data['task_id'] ?? 0 ),
        'staff_identifier' => sanitize_text_field( $data['staff_identifier'] ?? '' ),
      ),
      array( '%d', '%s' )
    );

    return $result ? $wpdb->insert_id : false;
  }

  /**
   * Get the latest completion for a task.
   *
   * @return array<string,mixed>|null
   */
  public static function get_latest_task_completion( int $task_id ): ?array {
    global $wpdb;
    $row = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eggplant_task_completions WHERE task_id = %d ORDER BY completed_at DESC, id DESC LIMIT 1",
        $task_id
      ),
      ARRAY_A
    );

    return $row ?: null;
  }

  /**
   * Get recent completion history.
   *
   * @return array<int,array<string,mixed>>
   */
  public static function get_recent_task_completions( int $limit = 20 ): array {
    global $wpdb;
    $results = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT c.*, t.task_name
          FROM {$wpdb->prefix}eggplant_task_completions c
          INNER JOIN {$wpdb->prefix}eggplant_tasks t ON t.id = c.task_id
          ORDER BY c.completed_at DESC, c.id DESC
          LIMIT %d",
        max( 1, $limit )
      ),
      ARRAY_A
    );

    return $results ?: array();
  }

  // ------------------------------------------------------------------ staff clock

  /**
   * Create a new staff clock-in entry when no open entry exists.
   *
   * @param array<string,mixed> $data
   * @return int|false
   */
  public static function check_in_staff( array $data ) {
    global $wpdb;

    $staff_identifier = sanitize_text_field( $data['staff_identifier'] ?? '' );
    if ( empty( $staff_identifier ) || self::get_open_checkin_for_staff( $staff_identifier ) ) {
      return false;
    }

    $result = $wpdb->insert(
      $wpdb->prefix . 'eggplant_staff_checkins',
      array(
        'staff_identifier' => $staff_identifier,
        'notes'            => sanitize_text_field( $data['notes'] ?? '' ),
      ),
      array( '%s', '%s' )
    );

    return $result ? $wpdb->insert_id : false;
  }

  /**
   * Close the latest open entry for a staff member.
   *
   * @return int|false
   */
  public static function check_out_staff( string $staff_identifier ) {
    global $wpdb;

    $open_entry = self::get_open_checkin_for_staff( $staff_identifier );
    if ( ! $open_entry ) {
      return false;
    }

    $result = $wpdb->update(
      $wpdb->prefix . 'eggplant_staff_checkins',
      array(
        'clock_out_at' => current_time( 'mysql' ),
      ),
      array( 'id' => intval( $open_entry['id'] ) ),
      array( '%s' ),
      array( '%d' )
    );

    return false !== $result ? intval( $open_entry['id'] ) : false;
  }

  /**
   * Get the current open entry for a staff member.
   *
   * @return array<string,mixed>|null
   */
  public static function get_open_checkin_for_staff( string $staff_identifier ): ?array {
    global $wpdb;
    $row = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eggplant_staff_checkins
          WHERE staff_identifier = %s AND (clock_out_at IS NULL OR clock_out_at = '0000-00-00 00:00:00')
          ORDER BY clock_in_at DESC, id DESC
          LIMIT 1",
        sanitize_text_field( $staff_identifier )
      ),
      ARRAY_A
    );

    return $row ?: null;
  }

  /**
   * Get recent staff check-in entries.
   *
   * @return array<int,array<string,mixed>>
   */
  public static function get_recent_staff_checkins( int $limit = 50 ): array {
    global $wpdb;
    $results = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eggplant_staff_checkins ORDER BY clock_in_at DESC, id DESC LIMIT %d",
        max( 1, $limit )
      ),
      ARRAY_A
    );

    return $results ?: array();
  }

  /**
   * Get a single event row.
   *
   * @param int $id
   * @return array<string,mixed>|null
   */
  public static function get_event( int $id ): ?array {
    global $wpdb;
    $row = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eggplant_events WHERE id = %d",
        $id
      ),
      ARRAY_A
    );
    return $row ?: null;
  }
}
