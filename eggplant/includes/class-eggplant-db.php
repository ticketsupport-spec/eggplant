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
        'staff_id'         => ! empty( $data['staff_id'] ) ? intval( $data['staff_id'] ) : null,
        'staff_identifier' => $staff_identifier,
        'notes'            => sanitize_text_field( $data['notes'] ?? '' ),
      ),
      array( '%d', '%s', '%s' )
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


  // ------------------------------------------------------------------ ticketing

  /**
   * Return all events with ticketing-relevant fields.
   *
   * @return array<int,array<string,mixed>>
   */
  public static function get_ticketing_events(): array {
    global $wpdb;
    $results = $wpdb->get_results(
      // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name uses internal prefix only.
      "SELECT * FROM {$wpdb->prefix}eggplant_events WHERE active = 1 ORDER BY sort_order ASC, id ASC",
      ARRAY_A
    );

    return $results ?: array();
  }

  /**
   * Update per-event ticketing settings.
   */
  public static function update_event_ticketing_settings( int $event_id, array $data ): bool {
    global $wpdb;
    $result = $wpdb->update(
      $wpdb->prefix . 'eggplant_events',
      array(
        'organizer_split_percent' => max( 0, min( 100, floatval( $data['organizer_split_percent'] ?? 0 ) ) ),
        'box_office_slug'         => sanitize_title( $data['box_office_slug'] ?? '' ),
        'scanner_slug'            => sanitize_title( $data['scanner_slug'] ?? '' ),
      ),
      array( 'id' => $event_id ),
      array( '%f', '%s', '%s' ),
      array( '%d' )
    );

    return false !== $result;
  }

  /**
   * Insert a ticket type.
   *
   * @return int|false
   */
  public static function insert_ticket_type( array $data ) {
    global $wpdb;
    $result = $wpdb->insert(
      $wpdb->prefix . 'eggplant_ticket_types',
      array(
        'event_id'       => intval( $data['event_id'] ?? 0 ),
        'ticket_name'    => sanitize_text_field( $data['ticket_name'] ?? '' ),
        'ticket_price'   => max( 0, floatval( $data['ticket_price'] ?? 0 ) ),
        'quantity_total' => max( 1, intval( $data['quantity_total'] ?? 1 ) ),
        'quantity_sold'  => 0,
        'active'         => ! empty( $data['active'] ) ? 1 : 0,
      ),
      array( '%d', '%s', '%f', '%d', '%d', '%d' )
    );

    return $result ? $wpdb->insert_id : false;
  }

  /**
   * @return array<int,array<string,mixed>>
   */
  public static function get_all_ticket_types(): array {
    global $wpdb;
    $results = $wpdb->get_results(
      // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table names use internal prefix only.
      "SELECT t.*, e.title AS event_title
        FROM {$wpdb->prefix}eggplant_ticket_types t
        INNER JOIN {$wpdb->prefix}eggplant_events e ON e.id = t.event_id
        ORDER BY e.sort_order ASC, e.id ASC, t.id ASC",
      ARRAY_A
    );

    return $results ?: array();
  }

  /**
   * Insert a discount code.
   *
   * @return int|false
   */
  public static function insert_discount_code( array $data ) {
    global $wpdb;

    $code = strtoupper( sanitize_text_field( $data['code'] ?? '' ) );
    if ( '' === $code ) {
      return false;
    }

    $base_data = array(
      'code'           => $code,
      'discount_type'  => in_array( $data['discount_type'] ?? 'percent', array( 'percent', 'fixed' ), true ) ? $data['discount_type'] : 'percent',
      'discount_value' => max( 0, floatval( $data['discount_value'] ?? 0 ) ),
      'max_uses'       => max( 0, intval( $data['max_uses'] ?? 0 ) ),
      'used_count'     => 0,
      'active'         => ! empty( $data['active'] ) ? 1 : 0,
      'start_date'     => sanitize_text_field( $data['start_date'] ?? '' ) ?: null,
      'end_date'       => sanitize_text_field( $data['end_date'] ?? '' ) ?: null,
    );
    $base_formats = array( '%s', '%s', '%f', '%d', '%d', '%d', '%s', '%s' );

    $event_id = intval( $data['event_id'] ?? 0 );
    if ( $event_id > 0 ) {
      $base_data['event_id'] = $event_id;
      $base_formats[] = '%d';
    }

    $result = $wpdb->insert(
      $wpdb->prefix . 'eggplant_discount_codes',
      $base_data,
      $base_formats
    );

    return $result ? $wpdb->insert_id : false;
  }

  /**
   * Check whether a discount code already exists.
   */
  public static function discount_code_exists( string $code ): bool {
    global $wpdb;

    $existing = $wpdb->get_var(
      $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}eggplant_discount_codes WHERE code = %s LIMIT 1",
        strtoupper( sanitize_text_field( $code ) )
      )
    );

    return ! empty( $existing );
  }

  /**
   * @return array<int,array<string,mixed>>
   */
  public static function get_discount_codes(): array {
    global $wpdb;
    $results = $wpdb->get_results(
      // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table names use internal prefix only.
      "SELECT d.*, e.title AS event_title
        FROM {$wpdb->prefix}eggplant_discount_codes d
        LEFT JOIN {$wpdb->prefix}eggplant_events e ON e.id = d.event_id
        ORDER BY d.created_at DESC, d.id DESC",
      ARRAY_A
    );

    return $results ?: array();
  }

  /**
   * Create an order and issue tickets in one transaction-safe operation.
   *
   * @return array<string,mixed>
   */
  public static function create_ticket_order( int $event_id, int $ticket_type_id, int $quantity, string $buyer_name, string $buyer_email, string $promo_code = '' ): array {
    global $wpdb;

    $event_id      = max( 1, $event_id );
    $ticket_type_id= max( 1, $ticket_type_id );
    $quantity      = max( 1, min( 20, $quantity ) );

    $ticket_type = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eggplant_ticket_types WHERE id = %d AND active = 1 LIMIT 1",
        $ticket_type_id
      ),
      ARRAY_A
    );

    if ( ! $ticket_type || intval( $ticket_type['event_id'] ) !== $event_id ) {
      return array( 'success' => false, 'message' => __( 'Invalid ticket type selection.', 'eggplant' ) );
    }

    $available = intval( $ticket_type['quantity_total'] ) - intval( $ticket_type['quantity_sold'] );
    if ( $available < $quantity ) {
      return array( 'success' => false, 'message' => __( 'Not enough tickets available for this ticket type.', 'eggplant' ) );
    }

    $gross = round( floatval( $ticket_type['ticket_price'] ) * $quantity, 2 );
    $discount_amount = 0.00;
    $discount_row = null;

    if ( '' !== $promo_code ) {
      $discount_row = self::validate_discount_code( $promo_code, $event_id, $gross );
      if ( ! empty( $discount_row['error'] ) ) {
        return array( 'success' => false, 'message' => $discount_row['error'] );
      }
      $discount_amount = floatval( $discount_row['discount_amount'] ?? 0 );
    }

    $net = max( 0, round( $gross - $discount_amount, 2 ) );

    $wpdb->query( 'START TRANSACTION' );

    try {
      $lock = $wpdb->get_row(
        $wpdb->prepare(
          // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- FOR UPDATE requires literal SQL.
          "SELECT id, quantity_total, quantity_sold FROM {$wpdb->prefix}eggplant_ticket_types WHERE id = %d FOR UPDATE",
          $ticket_type_id
        ),
        ARRAY_A
      );

      if ( ! $lock || ( intval( $lock['quantity_total'] ) - intval( $lock['quantity_sold'] ) ) < $quantity ) {
        $wpdb->query( 'ROLLBACK' );
        return array( 'success' => false, 'message' => __( 'Tickets sold out while processing. Please retry.', 'eggplant' ) );
      }

      if ( $discount_row && ! empty( $discount_row['id'] ) ) {
        $discount_lock = $wpdb->get_row(
          $wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- FOR UPDATE requires literal SQL.
            "SELECT * FROM {$wpdb->prefix}eggplant_discount_codes WHERE id = %d FOR UPDATE",
            intval( $discount_row['id'] )
          ),
          ARRAY_A
        );

        if ( ! $discount_lock ) {
          $wpdb->query( 'ROLLBACK' );
          return array( 'success' => false, 'message' => __( 'Discount code is no longer available.', 'eggplant' ) );
        }

        $recheck = self::validate_discount_code( $promo_code, $event_id, $gross, $discount_lock );
        if ( ! empty( $recheck['error'] ) ) {
          $wpdb->query( 'ROLLBACK' );
          return array( 'success' => false, 'message' => $recheck['error'] );
        }
        $discount_amount = floatval( $recheck['discount_amount'] ?? 0 );
        $net = max( 0, round( $gross - $discount_amount, 2 ) );
      }

      $order_number = strtoupper( wp_generate_password( 12, false, false ) );
      $order_access_key = wp_generate_password( 32, false, false );
      $insert_order = $wpdb->insert(
        $wpdb->prefix . 'eggplant_ticket_orders',
        array(
          'event_id'         => $event_id,
          'order_number'     => $order_number,
          'order_access_key' => $order_access_key,
          'buyer_name'       => sanitize_text_field( $buyer_name ),
          'buyer_email'      => sanitize_email( $buyer_email ),
          'gross_amount'     => $gross,
          'discount_amount'  => $discount_amount,
          'net_amount'       => $net,
          'discount_code'    => strtoupper( sanitize_text_field( $promo_code ) ),
          'payment_status'   => 'paid',
        ),
        array( '%d', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%s' )
      );

      if ( ! $insert_order ) {
        $wpdb->query( 'ROLLBACK' );
        return array( 'success' => false, 'message' => __( 'Could not create ticket order.', 'eggplant' ) );
      }

      $order_id = intval( $wpdb->insert_id );
      for ( $i = 0; $i < $quantity; $i++ ) {
        $ticket_code = 'TKT-' . strtoupper( wp_generate_password( 10, false, false ) );
        $barcode = 'EGP-' . $event_id . '-' . strtoupper( wp_generate_password( 16, false, false ) );

        $ok = $wpdb->insert(
          $wpdb->prefix . 'eggplant_tickets',
          array(
            'order_id'       => $order_id,
            'event_id'       => $event_id,
            'ticket_type_id' => $ticket_type_id,
            'ticket_code'    => $ticket_code,
            'barcode_value'  => $barcode,
            'ticket_status'  => 'valid',
          ),
          array( '%d', '%d', '%d', '%s', '%s', '%s' )
        );

        if ( ! $ok ) {
          $wpdb->query( 'ROLLBACK' );
          return array( 'success' => false, 'message' => __( 'Could not issue tickets.', 'eggplant' ) );
        }
      }

      $update_stock = $wpdb->query(
        $wpdb->prepare(
          "UPDATE {$wpdb->prefix}eggplant_ticket_types SET quantity_sold = quantity_sold + %d WHERE id = %d",
          $quantity,
          $ticket_type_id
        )
      );

      if ( false === $update_stock ) {
        $wpdb->query( 'ROLLBACK' );
        return array( 'success' => false, 'message' => __( 'Could not update ticket inventory.', 'eggplant' ) );
      }

      if ( $discount_row && ! empty( $discount_row['id'] ) ) {
        $wpdb->query(
          $wpdb->prepare(
            "UPDATE {$wpdb->prefix}eggplant_discount_codes SET used_count = used_count + 1 WHERE id = %d",
            intval( $discount_row['id'] )
          )
        );
      }

      $wpdb->query( 'COMMIT' );
      return array(
        'success'          => true,
        'order_number'     => $order_number,
        'order_access_key' => $order_access_key,
      );
    } catch ( Exception $e ) {
      $wpdb->query( 'ROLLBACK' );
      return array( 'success' => false, 'message' => __( 'Unexpected purchase error.', 'eggplant' ) );
    }
  }

  /**
   * Validate and evaluate a discount code.
   *
   * @return array<string,mixed>
   */
  private static function validate_discount_code( string $code, int $event_id, float $gross, ?array $existing_row = null ): array {
    global $wpdb;

    $row = $existing_row;
    if ( ! $row ) {
      $row = $wpdb->get_row(
        $wpdb->prepare(
          "SELECT * FROM {$wpdb->prefix}eggplant_discount_codes WHERE code = %s LIMIT 1",
          strtoupper( sanitize_text_field( $code ) )
        ),
        ARRAY_A
      );
    }

    if ( ! $row ) {
      return array( 'error' => __( 'Promo code was not found.', 'eggplant' ) );
    }

    if ( ! intval( $row['active'] ) ) {
      return array( 'error' => __( 'Promo code is inactive.', 'eggplant' ) );
    }

    if ( ! empty( $row['event_id'] ) && intval( $row['event_id'] ) !== $event_id ) {
      return array( 'error' => __( 'Promo code is not valid for this event.', 'eggplant' ) );
    }

    $today = current_time( 'Y-m-d' );
    if ( ! empty( $row['start_date'] ) && $row['start_date'] > $today ) {
      return array( 'error' => __( 'Promo code is not active yet.', 'eggplant' ) );
    }

    if ( ! empty( $row['end_date'] ) && $row['end_date'] < $today ) {
      return array( 'error' => __( 'Promo code has expired.', 'eggplant' ) );
    }

    if ( intval( $row['max_uses'] ) > 0 && intval( $row['used_count'] ) >= intval( $row['max_uses'] ) ) {
      return array( 'error' => __( 'Promo code usage limit has been reached.', 'eggplant' ) );
    }

    $discount = 0.00;
    if ( 'fixed' === $row['discount_type'] ) {
      $discount = min( $gross, max( 0, floatval( $row['discount_value'] ) ) );
    } else {
      $percent = max( 0, min( 100, floatval( $row['discount_value'] ) ) );
      $discount = round( $gross * ( $percent / 100 ), 2 );
    }

    return array(
      'id'              => intval( $row['id'] ),
      'discount_amount' => $discount,
      'row'             => $row,
    );
  }

  /**
   * Scan a ticket barcode and enforce one-time use.
   *
   * @return array<string,mixed>
   */
  public static function scan_ticket_barcode( string $barcode_value, int $user_id = 0 ): array {
    global $wpdb;

    $barcode = sanitize_text_field( $barcode_value );
    if ( '' === $barcode ) {
      return array( 'success' => false, 'message' => __( 'Barcode is required.', 'eggplant' ) );
    }

    $wpdb->query( 'START TRANSACTION' );

    try {
      $ticket = $wpdb->get_row(
        $wpdb->prepare(
          // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- FOR UPDATE requires literal SQL.
          "SELECT * FROM {$wpdb->prefix}eggplant_tickets WHERE barcode_value = %s LIMIT 1 FOR UPDATE",
          $barcode
        ),
        ARRAY_A
      );

      if ( ! $ticket ) {
        self::insert_scan_log( null, null, $barcode, 'invalid', __( 'Ticket was not found.', 'eggplant' ), $user_id );
        $wpdb->query( 'COMMIT' );
        return array( 'success' => false, 'message' => __( 'Ticket not found.', 'eggplant' ) );
      }

      if ( 'used' === $ticket['ticket_status'] ) {
        self::insert_scan_log( intval( $ticket['id'] ), intval( $ticket['event_id'] ), $barcode, 'duplicate', __( 'Duplicate scan blocked: ticket already used.', 'eggplant' ), $user_id );
        $wpdb->query( 'COMMIT' );
        return array( 'success' => false, 'message' => __( 'Duplicate scan blocked: ticket already used.', 'eggplant' ) );
      }

      $updated = $wpdb->update(
        $wpdb->prefix . 'eggplant_tickets',
        array(
          'ticket_status' => 'used',
          'scanned_at'    => current_time( 'mysql' ),
          'scanned_by'    => $user_id ?: null,
        ),
        array(
          'id'            => intval( $ticket['id'] ),
          'ticket_status' => 'valid',
        ),
        array( '%s', '%s', '%d' ),
        array( '%d', '%s' )
      );

      if ( ! $updated ) {
        self::insert_scan_log( intval( $ticket['id'] ), intval( $ticket['event_id'] ), $barcode, 'duplicate', __( 'Duplicate scan blocked: ticket already used.', 'eggplant' ), $user_id );
        $wpdb->query( 'COMMIT' );
        return array( 'success' => false, 'message' => __( 'Duplicate scan blocked: ticket already used.', 'eggplant' ) );
      }

      self::insert_scan_log( intval( $ticket['id'] ), intval( $ticket['event_id'] ), $barcode, 'accepted', __( 'Ticket accepted.', 'eggplant' ), $user_id );
      $wpdb->query( 'COMMIT' );
      return array( 'success' => true, 'message' => __( 'Ticket accepted. Entry granted.', 'eggplant' ) );
    } catch ( Exception $e ) {
      $wpdb->query( 'ROLLBACK' );
      return array( 'success' => false, 'message' => __( 'Scan error. Please retry.', 'eggplant' ) );
    }
  }

  private static function insert_scan_log( ?int $ticket_id, ?int $event_id, string $barcode, string $status, string $message, int $user_id = 0 ): void {
    global $wpdb;
    $wpdb->insert(
      $wpdb->prefix . 'eggplant_ticket_scans',
      array(
        'ticket_id'     => $ticket_id,
        'event_id'      => $event_id,
        'barcode_value' => sanitize_text_field( $barcode ),
        'scan_status'   => sanitize_key( $status ),
        'scan_message'  => sanitize_text_field( $message ),
        'scanned_by'    => $user_id ?: null,
      ),
      array( '%d', '%d', '%s', '%s', '%s', '%d' )
    );
  }

  /**
   * @return array<int,array<string,mixed>>
   */
  public static function get_ticket_orders(): array {
    global $wpdb;
    $rows = $wpdb->get_results(
      // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table names use internal prefix only.
      "SELECT o.*, e.title AS event_title
        FROM {$wpdb->prefix}eggplant_ticket_orders o
        INNER JOIN {$wpdb->prefix}eggplant_events e ON e.id = o.event_id
        ORDER BY o.created_at DESC, o.id DESC",
      ARRAY_A
    );

    return $rows ?: array();
  }

  /**
   * @return array<int,array<string,mixed>>
   */
  public static function get_tickets_for_order_number( string $order_number ): array {
    global $wpdb;
    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT t.*, o.order_number, e.title AS event_title, tt.ticket_name
          FROM {$wpdb->prefix}eggplant_tickets t
          INNER JOIN {$wpdb->prefix}eggplant_ticket_orders o ON o.id = t.order_id
          INNER JOIN {$wpdb->prefix}eggplant_events e ON e.id = t.event_id
          INNER JOIN {$wpdb->prefix}eggplant_ticket_types tt ON tt.id = t.ticket_type_id
          WHERE o.order_number = %s
          ORDER BY t.id ASC",
        sanitize_text_field( $order_number )
      ),
      ARRAY_A
    );

    return $rows ?: array();
  }

  /**
   * @return array<string,mixed>|null
   */
  public static function get_ticket_order_by_public_key( string $order_number, string $order_key ): ?array {
    global $wpdb;

    $row = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eggplant_ticket_orders WHERE order_number = %s AND order_access_key = %s LIMIT 1",
        sanitize_text_field( $order_number ),
        sanitize_text_field( $order_key )
      ),
      ARRAY_A
    );

    return $row ?: null;
  }

  /**
   * @return array<int,array<string,mixed>>
   */
  public static function get_ticket_scan_logs( int $limit = 200 ): array {
    global $wpdb;
    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT s.*, e.title AS event_title, u.display_name AS scanned_by_name
          FROM {$wpdb->prefix}eggplant_ticket_scans s
          LEFT JOIN {$wpdb->prefix}eggplant_events e ON e.id = s.event_id
          LEFT JOIN {$wpdb->users} u ON u.ID = s.scanned_by
          ORDER BY s.created_at DESC, s.id DESC
          LIMIT %d",
        max( 1, $limit )
      ),
      ARRAY_A
    );

    return $rows ?: array();
  }

  /**
   * @return int|false
   */
  public static function insert_event_settlement( array $data ) {
    global $wpdb;

    $payload = array(
      'event_id'          => intval( $data['event_id'] ?? 0 ),
      'adjustment_amount' => floatval( $data['adjustment_amount'] ?? 0 ),
      'adjustment_note'   => sanitize_text_field( $data['adjustment_note'] ?? '' ),
      'created_by'        => ! empty( $data['created_by'] ) ? intval( $data['created_by'] ) : null,
    );
    $formats = array( '%d', '%f', '%s', '%d' );

    if ( isset( $data['organizer_split_override'] ) && null !== $data['organizer_split_override'] && '' !== (string) $data['organizer_split_override'] ) {
      $payload['organizer_split_override'] = floatval( $data['organizer_split_override'] );
      $formats[] = '%f';
    }

    $result = $wpdb->insert(
      $wpdb->prefix . 'eggplant_event_settlements',
      $payload,
      $formats
    );

    return $result ? $wpdb->insert_id : false;
  }


  /**
   * @return array<int,array<string,mixed>>
   */
  public static function get_event_settlements( int $limit = 200 ): array {
    global $wpdb;

    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT s.*, e.title AS event_title, u.display_name AS created_by_name
          FROM {$wpdb->prefix}eggplant_event_settlements s
          INNER JOIN {$wpdb->prefix}eggplant_events e ON e.id = s.event_id
          LEFT JOIN {$wpdb->users} u ON u.ID = s.created_by
          ORDER BY s.created_at DESC, s.id DESC
          LIMIT %d",
        max( 1, $limit )
      ),
      ARRAY_A
    );

    return $rows ?: array();
  }

  /**
   * Aggregate top-level ticketing totals.
   *
   * @return array<string,mixed>
   */
  public static function get_ticketing_totals(): array {
    global $wpdb;

    $order_totals = $wpdb->get_row(
      // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- internal table name only.
      "SELECT COUNT(*) AS orders_count, COALESCE(SUM(net_amount),0) AS net_total FROM {$wpdb->prefix}eggplant_ticket_orders",
      ARRAY_A
    );

    $ticket_totals = $wpdb->get_row(
      // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- internal table name only.
      "SELECT COUNT(*) AS tickets_count, COALESCE(SUM(CASE WHEN ticket_status = 'used' THEN 1 ELSE 0 END),0) AS used_tickets FROM {$wpdb->prefix}eggplant_tickets",
      ARRAY_A
    );

    return array(
      'orders_count' => intval( $order_totals['orders_count'] ?? 0 ),
      'net_total'    => floatval( $order_totals['net_total'] ?? 0 ),
      'tickets_count'=> intval( $ticket_totals['tickets_count'] ?? 0 ),
      'used_tickets' => intval( $ticket_totals['used_tickets'] ?? 0 ),
    );
  }

  /**
   * @return array<int,array<string,mixed>>
   */
  public static function get_ticketing_event_accounting_rows(): array {
    global $wpdb;

    $rows = $wpdb->get_results(
      // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- internal table names only.
      "SELECT
         e.id AS event_id,
         e.title AS event_title,
         e.organizer_split_percent,
         COALESCE(SUM(o.gross_amount),0) AS gross_amount,
         COALESCE(SUM(o.discount_amount),0) AS discount_amount,
         COALESCE(SUM(o.net_amount),0) AS net_amount
       FROM {$wpdb->prefix}eggplant_events e
       LEFT JOIN {$wpdb->prefix}eggplant_ticket_orders o ON o.event_id = e.id
       GROUP BY e.id
       ORDER BY e.sort_order ASC, e.id ASC",
      ARRAY_A
    );

    if ( ! $rows ) {
      return array();
    }

    $settlements = self::get_event_settlement_rollups();

    foreach ( $rows as &$row ) {
      $event_id = intval( $row['event_id'] );
      $settlement = $settlements[ $event_id ] ?? array(
        'adjustment_amount' => 0,
        'organizer_override' => null,
      );
      $net = floatval( $row['net_amount'] );
      $percent = null !== $settlement['organizer_override'] ? floatval( $settlement['organizer_override'] ) : floatval( $row['organizer_split_percent'] );
      $base_share = round( $net * ( max( 0, min( 100, $percent ) ) / 100 ), 2 );
      $organizer_share = round( $base_share + floatval( $settlement['adjustment_amount'] ), 2 );
      $venue_share = round( $net - $organizer_share, 2 );

      $row['organizer_percent'] = $percent;
      $row['organizer_share'] = $organizer_share;
      $row['venue_share'] = $venue_share;
    }

    return $rows;
  }

  /**
   * @return array<int,array<string,mixed>>
   */
  private static function get_event_settlement_rollups(): array {
    global $wpdb;

    $rows = $wpdb->get_results(
      // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- internal table names only.
      "SELECT totals.event_id, totals.adjustment_amount, latest.organizer_split_override AS organizer_override
        FROM (
          SELECT event_id, COALESCE(SUM(adjustment_amount),0) AS adjustment_amount
          FROM {$wpdb->prefix}eggplant_event_settlements
          GROUP BY event_id
        ) totals
        LEFT JOIN (
          SELECT s1.event_id, s1.organizer_split_override
          FROM {$wpdb->prefix}eggplant_event_settlements s1
          INNER JOIN (
            SELECT event_id, MAX(id) AS max_id
            FROM {$wpdb->prefix}eggplant_event_settlements
            GROUP BY event_id
          ) latest_ids ON latest_ids.max_id = s1.id
        ) latest ON latest.event_id = totals.event_id",
      ARRAY_A
    );

    $result = array();
    foreach ( $rows ?: array() as $row ) {
      $event_id = intval( $row['event_id'] );
      $result[ $event_id ] = array(
        'adjustment_amount' => floatval( $row['adjustment_amount'] ),
        'organizer_override'=> ( null === $row['organizer_override'] || '' === $row['organizer_override'] ) ? null : floatval( $row['organizer_override'] ),
      );
    }

    return $result;
  }

}
