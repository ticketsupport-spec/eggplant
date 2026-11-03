<?php

/**
 * Floor calendar – one booking per day for each of the two floors.
 *
 * Each row in {prefix}eggplant_floor_days represents a single floor on a
 * single date and is either:
 *  - booked  : an event (PRIVATE or PUBLIC) occupies the floor that day.
 *  - blocked : the floor is not available that day (closed, maintenance…).
 * A date/floor with no row is available.
 *
 * @since 1.5.0
 * @package Eggplant
 */

class Eggplant_Floor_Calendar {

  public const FLOORS       = array( 'floor_1', 'floor_2' );
  public const STATUSES     = array( 'booked', 'blocked' );
  public const VISIBILITIES = array( 'private', 'public' );

  /** Maximum number of days a single bulk action may span. */
  private const MAX_BULK_DAYS = 1100;

  public static function init(): void {
    add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ), 11 );
    add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );

    add_action( 'wp_ajax_eggplant_fc_get_month',  array( __CLASS__, 'ajax_get_month' ) );
    add_action( 'wp_ajax_eggplant_fc_save_day',   array( __CLASS__, 'ajax_save_day' ) );
    add_action( 'wp_ajax_eggplant_fc_clear_day',  array( __CLASS__, 'ajax_clear_day' ) );
    add_action( 'wp_ajax_eggplant_fc_bulk',       array( __CLASS__, 'ajax_bulk' ) );
  }

  // ------------------------------------------------------------------ helpers

  private static function table(): string {
    global $wpdb;
    return $wpdb->prefix . 'eggplant_floor_days';
  }

  /**
   * Display names of the floors, keyed by floor key.
   *
   * @return array<string,string>
   */
  public static function floor_names(): array {
    $settings = Eggplant_Settings::get_all();
    return array(
      'floor_1' => $settings['floor_1_name'] !== '' ? $settings['floor_1_name'] : __( 'Main Floor', 'eggplant' ),
      'floor_2' => $settings['floor_2_name'] !== '' ? $settings['floor_2_name'] : __( 'Upper Floor', 'eggplant' ),
    );
  }

  /** @return array<string,string> */
  public static function booking_floor_names(): array {
    $names = self::floor_names();
    return array(
      'floor_1' => $names['floor_1'],
      'both'    => __( 'Both Floors', 'eggplant' ),
      'floor_2' => $names['floor_2'],
    );
  }

  /** @return string[] */
  public static function booking_floors( string $choice ): array {
    if ( 'both' === $choice ) {
      return self::FLOORS;
    }
    return in_array( $choice, self::FLOORS, true ) ? array( $choice ) : array();
  }

  public static function is_valid_date( string $date ): bool {
    if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) ) {
      return false;
    }
    return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
  }

  private static function sanitize_time( string $time ): ?string {
    $time = trim( $time );
    if ( preg_match( '/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $time ) ) {
      return strlen( $time ) === 5 ? $time . ':00' : $time;
    }
    return null;
  }

  /**
   * CSS custom properties for the floor-calendar status colors.
   */
  public static function color_vars_css(): string {
    $settings = Eggplant_Settings::get_all();
    $vars     = array(
      '--eg-available' => $settings['available_color'],
      '--eg-private'   => $settings['private_color'],
      '--eg-public'    => $settings['public_color'],
      '--eg-blocked'   => $settings['blocked_color'],
    );
    $css = '';
    foreach ( $vars as $name => $value ) {
      if ( preg_match( '/^#[0-9a-fA-F]{3,6}$/', (string) $value ) ) {
        $css .= $name . ':' . $value . ';';
      }
    }
    return ':root{' . $css . '}';
  }

  // ------------------------------------------------------------------ DB

  /**
   * @return array<string,mixed>|null
   */
  public static function get_day( string $date, string $floor ): ?array {
    global $wpdb;
    $row = $wpdb->get_row(
      $wpdb->prepare(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
        'SELECT * FROM ' . self::table() . ' WHERE day_date = %s AND floor = %s',
        $date,
        $floor
      ),
      ARRAY_A
    );
    return $row ?: null;
  }

  /**
   * All rows between two dates inclusive.
   *
   * @return array<int,array<string,mixed>>
   */
  public static function get_range( string $start, string $end ): array {
    global $wpdb;
    $rows = $wpdb->get_results(
      $wpdb->prepare(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
        'SELECT * FROM ' . self::table() . ' WHERE day_date BETWEEN %s AND %s ORDER BY day_date, floor',
        $start,
        $end
      ),
      ARRAY_A
    );
    return $rows ?: array();
  }

  /**
   * @return array<int,array<string,mixed>>
   */
  public static function get_month( string $year_month ): array {
    $start = $year_month . '-01';
    $end   = gmdate( 'Y-m-t', strtotime( $start . ' 00:00:00 UTC' ) );
    return self::get_calendar_range( $start, $end );
  }

  /** Merge time-slot occupancy without writing over independently managed floor days. */
  private static function get_calendar_range( string $start, string $end ): array {
    $days = array();
    foreach ( self::get_range( $start, $end ) as $row ) {
      $days[ $row['day_date'] . '|' . $row['floor'] ] = $row;
    }
    foreach ( self::get_slot_days( $start, $end ) as $key => $row ) {
      if ( ! isset( $days[ $key ] ) ) {
        $days[ $key ] = $row;
      }
    }
    return array_values( $days );
  }

  /** @return array<string,array<string,mixed>> */
  private static function get_slot_days( string $start, string $end ): array {
    $days = array();
    foreach ( Eggplant_DB::get_occupied_slots( $start, $end ) as $slot ) {
      foreach ( self::booking_floors( $slot['floor'] ) as $floor ) {
        $key = $slot['slot_date'] . '|' . $floor;
        if ( ! isset( $days[ $key ] ) ) {
          $days[ $key ] = array(
            'day_date' => $slot['slot_date'], 'floor' => $floor,
            'status' => 'blocked', 'visibility' => 'private',
            'title' => __( 'Time slots', 'eggplant' ), 'description' => '',
            'start_time' => '', 'end_time' => '', 'image_url' => '', 'link_url' => '',
            'note' => '', 'booking_id' => null, 'time_slots' => true,
          );
        }
        if ( 'booked' === $slot['status'] ) {
          $days[ $key ]['status'] = 'booked';
        }
        $days[ $key ]['note'] .= ( $days[ $key ]['note'] ? '; ' : '' )
          . substr( $slot['start_time'], 0, 5 ) . ' - ' . substr( $slot['end_time'], 0, 5 )
          . ( $slot['label'] ? ' (' . $slot['label'] . ')' : '' );
      }
    }
    return $days;
  }

  /**
   * Whether a floor is free (no booking or block) on a date.
   */
  public static function is_available( string $date, string $floor ): bool {
    return null === self::get_day( $date, $floor )
      && ! isset( self::get_slot_days( $date, $date )[ $date . '|' . $floor ] );
  }

  /**
   * Insert or update the entry for one date + floor.
   *
   * @param array<string,mixed> $data
   * @param bool                $only_if_free When true, never overwrite: rely on the unique key so concurrent claims fail.
   * @return int|false Row ID or false.
   */
  public static function save_day( string $date, string $floor, array $data, bool $only_if_free = false ) {
    global $wpdb;

    if ( ! self::is_valid_date( $date ) || ! in_array( $floor, self::FLOORS, true ) ) {
      return false;
    }
    if ( $only_if_free && ! self::is_available( $date, $floor ) ) {
      return false;
    }

    $status     = in_array( $data['status'] ?? '', self::STATUSES, true ) ? $data['status'] : 'booked';
    $visibility = in_array( $data['visibility'] ?? '', self::VISIBILITIES, true ) ? $data['visibility'] : 'private';

    $row = array(
      'day_date'    => $date,
      'floor'       => $floor,
      'status'      => $status,
      'visibility'  => 'booked' === $status ? $visibility : 'private',
      'title'       => sanitize_text_field( $data['title'] ?? '' ),
      'description' => wp_kses_post( $data['description'] ?? '' ),
      'start_time'  => self::sanitize_time( (string) ( $data['start_time'] ?? '' ) ),
      'end_time'    => self::sanitize_time( (string) ( $data['end_time'] ?? '' ) ),
      'image_url'   => esc_url_raw( $data['image_url'] ?? '' ),
      'link_url'    => esc_url_raw( $data['link_url'] ?? '' ),
      'note'        => sanitize_text_field( $data['note'] ?? '' ),
    );
    $formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

    if ( array_key_exists( 'booking_id', $data ) ) {
      $row['booking_id'] = $data['booking_id'] ? (int) $data['booking_id'] : null;
      $formats[]         = '%d';
    }

    $existing = $only_if_free ? null : self::get_day( $date, $floor );
    if ( $existing ) {
      $ok = $wpdb->update( self::table(), $row, array( 'id' => (int) $existing['id'] ), $formats, array( '%d' ) );
      return false === $ok ? false : (int) $existing['id'];
    }

    $ok = $wpdb->insert( self::table(), $row, $formats );
    return $ok ? (int) $wpdb->insert_id : false;
  }

  public static function delete_day( string $date, string $floor ): bool {
    global $wpdb;
    return false !== $wpdb->delete( self::table(), array( 'day_date' => $date, 'floor' => $floor ), array( '%s', '%s' ) );
  }

  public static function delete_for_booking( int $booking_id ): void {
    global $wpdb;
    $wpdb->delete( self::table(), array( 'booking_id' => $booking_id, 'status' => 'booked' ), array( '%d', '%s' ) );
  }

  /**
   * Block or unblock every matching weekday for the given floors within a date range.
   * Existing bookings are never overwritten or removed.
   *
   * @param int[]    $weekdays 0 (Sun) … 6 (Sat).
   * @param string[] $floors
   * @return array{changed:int,skipped:int}|WP_Error
   */
  public static function bulk_apply( string $mode, string $start, string $end, array $weekdays, array $floors, string $note = '' ) {
    if ( ! self::is_valid_date( $start ) || ! self::is_valid_date( $end ) ) {
      return new WP_Error( 'invalid_date', __( 'Please choose a valid start and end date.', 'eggplant' ) );
    }
    if ( $start > $end ) {
      return new WP_Error( 'invalid_range', __( 'The start date must be on or before the end date.', 'eggplant' ) );
    }

    $weekdays = array_values( array_unique( array_filter( array_map( 'intval', $weekdays ), static function ( $d ) {
      return $d >= 0 && $d <= 6;
    } ) ) );
    if ( empty( $weekdays ) ) {
      return new WP_Error( 'no_weekdays', __( 'Please choose at least one day of the week.', 'eggplant' ) );
    }

    $floors = array_values( array_intersect( self::FLOORS, $floors ) );
    if ( empty( $floors ) ) {
      return new WP_Error( 'no_floors', __( 'Please choose a floor.', 'eggplant' ) );
    }

    $utc     = new DateTimeZone( 'UTC' );
    $cursor  = new DateTimeImmutable( $start, $utc );
    $last    = new DateTimeImmutable( $end, $utc );
    if ( (int) $cursor->diff( $last )->days > self::MAX_BULK_DAYS ) {
      return new WP_Error( 'range_too_long', __( 'Please choose a range of three years or less.', 'eggplant' ) );
    }

    $existing = array();
    foreach ( self::get_calendar_range( $start, $end ) as $row ) {
      $existing[ $row['day_date'] . '|' . $row['floor'] ] = ! empty( $row['time_slots'] ) ? 'booked' : $row['status'];
    }

    $changed = 0;
    $skipped = 0;
    while ( $cursor <= $last ) {
      if ( in_array( (int) $cursor->format( 'w' ), $weekdays, true ) ) {
        $date = $cursor->format( 'Y-m-d' );
        foreach ( $floors as $floor ) {
          $current = $existing[ $date . '|' . $floor ] ?? null;
          if ( 'block' === $mode ) {
            if ( null === $current ) {
              self::save_day( $date, $floor, array( 'status' => 'blocked', 'note' => $note ) ) ? $changed++ : $skipped++;
            } else {
              $skipped++;
            }
          } elseif ( 'blocked' === $current ) {
            self::delete_day( $date, $floor ) ? $changed++ : $skipped++;
          } elseif ( 'booked' === $current ) {
            $skipped++;
          }
        }
      }
      $cursor = $cursor->modify( '+1 day' );
    }

    return array( 'changed' => $changed, 'skipped' => $skipped );
  }

  /**
   * Public-safe view of a month: private event details and internal notes are never exposed.
   *
   * @return array<string,array<string,array<string,mixed>>> date => floor => info
   */
  public static function get_public_month( string $year_month ): array {
    $out = array();
    foreach ( self::get_month( $year_month ) as $row ) {
      $info = array( 'status' => $row['status'] );
      if ( 'booked' === $row['status'] ) {
        $info['visibility'] = $row['visibility'];
        if ( 'public' === $row['visibility'] ) {
          $info['title']       = $row['title'];
          $info['description'] = $row['description'];
          $info['start']       = $row['start_time'] ? substr( $row['start_time'], 0, 5 ) : '';
          $info['end']         = $row['end_time'] ? substr( $row['end_time'], 0, 5 ) : '';
          $info['image_url']   = $row['image_url'];
          $info['link_url']    = $row['link_url'];
        }
      }
      $out[ $row['day_date'] ][ $row['floor'] ] = $info;
    }
    return $out;
  }

  // ------------------------------------------------------------------ admin

  public static function add_admin_menu(): void {
    add_submenu_page(
      'eggplant',
      __( 'Floor Calendar', 'eggplant' ),
      __( 'Floor Calendar', 'eggplant' ),
      'manage_options',
      'eggplant-floor-calendar',
      array( __CLASS__, 'render_admin_page' )
    );
  }

  public static function enqueue_admin_assets( string $hook ): void {
    if ( ! in_array( $hook, array( 'event-portal_page_eggplant-floor-calendar', 'event-portal_page_eggplant-slots' ), true ) ) {
      return;
    }
    wp_enqueue_style( 'eggplant-admin', EGGPLANT_PLUGIN_URL . 'admin/css/admin.css', array(), EGGPLANT_VERSION );
    wp_add_inline_style( 'eggplant-admin', self::color_vars_css() );
    wp_enqueue_script(
      'eggplant-floor-calendar',
      EGGPLANT_PLUGIN_URL . 'admin/js/floor-calendar.js',
      array( 'jquery' ),
      EGGPLANT_VERSION,
      true
    );
    wp_localize_script( 'eggplant-floor-calendar', 'EggplantFloorCal', array(
      'ajaxUrl' => admin_url( 'admin-ajax.php' ),
      'nonce'   => wp_create_nonce( 'eggplant_admin_nonce' ),
      'floors'  => self::floor_names(),
      'today'   => current_time( 'Y-m-d' ),
      'readOnly' => 'event-portal_page_eggplant-slots' === $hook,
      'slotsUrl' => admin_url( 'admin.php?page=eggplant-slots' ),
    ) );
  }

  public static function render_admin_page( bool $read_only = false ): void {
    $floors   = self::floor_names();
    $weekdays = array(
      0 => __( 'Sun', 'eggplant' ),
      1 => __( 'Mon', 'eggplant' ),
      2 => __( 'Tue', 'eggplant' ),
      3 => __( 'Wed', 'eggplant' ),
      4 => __( 'Thu', 'eggplant' ),
      5 => __( 'Fri', 'eggplant' ),
      6 => __( 'Sat', 'eggplant' ),
    );
    ?>
    <div class="wrap eg-admin eg-fc-admin">
      <h1><?php esc_html_e( 'Floor Calendar', 'eggplant' ); ?></h1>
      <p class="description">
        <?php if ( $read_only ) : ?>
          <?php esc_html_e( 'Booked time slots show as private events; holds show as unavailable. Times remain in the list above. Availability is per floor for the whole day.', 'eggplant' ); ?>
        <?php else : ?>
          <?php esc_html_e( 'Each floor can hold one booking per day. Click a floor on any day to book it (private or public event), mark it unavailable, or make it available again. Manage time-slot bookings on the Time Slots page.', 'eggplant' ); ?>
        <?php endif; ?>
      </p>

      <ul class="eg-fc-legend">
        <li><span class="eg-fc-swatch eg-fc--available"></span><?php esc_html_e( 'Available', 'eggplant' ); ?></li>
        <li><span class="eg-fc-swatch eg-fc--private"></span><?php esc_html_e( 'Private Event', 'eggplant' ); ?></li>
        <li><span class="eg-fc-swatch eg-fc--public"></span><?php esc_html_e( 'Public Event', 'eggplant' ); ?></li>
        <li><span class="eg-fc-swatch eg-fc--blocked"></span><?php esc_html_e( 'Unavailable', 'eggplant' ); ?></li>
      </ul>

      <div class="eg-card">
        <div class="eg-fc-nav">
          <button type="button" class="button" id="eg-fc-prev" aria-label="<?php esc_attr_e( 'Previous month', 'eggplant' ); ?>">&#8592;</button>
          <strong id="eg-fc-heading"></strong>
          <button type="button" class="button" id="eg-fc-next" aria-label="<?php esc_attr_e( 'Next month', 'eggplant' ); ?>">&#8594;</button>
          <button type="button" class="button" id="eg-fc-today"><?php esc_html_e( 'Today', 'eggplant' ); ?></button>
        </div>
        <div class="eg-fc-weekdays">
          <?php foreach ( $weekdays as $label ) : ?>
            <div><?php echo esc_html( $label ); ?></div>
          <?php endforeach; ?>
        </div>
        <div class="eg-fc-grid" id="eg-fc-grid" aria-live="polite"></div>
      </div>

      <?php
      if ( $read_only ) {
        echo '</div>';
        return;
      }
      ?>

      <!-- Day editor -->
      <div class="eg-card" id="eg-fc-editor" hidden>
        <h2 id="eg-fc-editor-title"></h2>
        <form id="eg-fc-editor-form">
          <input type="hidden" id="eg-fc-date">
          <div class="eg-form-row">
            <label for="eg-fc-floor"><?php esc_html_e( 'Floor', 'eggplant' ); ?></label>
            <select id="eg-fc-floor">
              <?php foreach ( $floors as $key => $name ) : ?>
                <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $name ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="eg-form-row">
            <label for="eg-fc-status"><?php esc_html_e( 'Status', 'eggplant' ); ?></label>
            <select id="eg-fc-status">
              <option value="available"><?php esc_html_e( 'Available', 'eggplant' ); ?></option>
              <option value="booked"><?php esc_html_e( 'Booked (event)', 'eggplant' ); ?></option>
              <option value="blocked"><?php esc_html_e( 'Unavailable (blocked)', 'eggplant' ); ?></option>
            </select>
          </div>
          <div class="eg-fc-booked-fields">
            <div class="eg-form-row">
              <label for="eg-fc-visibility"><?php esc_html_e( 'Event Type', 'eggplant' ); ?></label>
              <select id="eg-fc-visibility">
                <option value="private"><?php esc_html_e( 'PRIVATE EVENT (details hidden from public)', 'eggplant' ); ?></option>
                <option value="public"><?php esc_html_e( 'PUBLIC EVENT (details shown in pop-up)', 'eggplant' ); ?></option>
              </select>
            </div>
            <div class="eg-form-row">
              <label for="eg-fc-title"><?php esc_html_e( 'Event Title', 'eggplant' ); ?></label>
              <input type="text" id="eg-fc-title">
            </div>
            <div class="eg-form-row">
              <label for="eg-fc-start"><?php esc_html_e( 'Start / End Time', 'eggplant' ); ?></label>
              <input type="time" id="eg-fc-start"> <input type="time" id="eg-fc-end">
            </div>
            <div class="eg-fc-public-fields">
              <div class="eg-form-row">
                <label for="eg-fc-description"><?php esc_html_e( 'Public Description', 'eggplant' ); ?></label>
                <textarea id="eg-fc-description" rows="4"></textarea>
              </div>
              <div class="eg-form-row">
                <label for="eg-fc-image"><?php esc_html_e( 'Image URL', 'eggplant' ); ?></label>
                <input type="url" id="eg-fc-image" placeholder="https://">
              </div>
              <div class="eg-form-row">
                <label for="eg-fc-link"><?php esc_html_e( 'Tickets / Info URL', 'eggplant' ); ?></label>
                <input type="url" id="eg-fc-link" placeholder="https://">
              </div>
            </div>
          </div>
          <div class="eg-form-row">
            <label for="eg-fc-note"><?php esc_html_e( 'Internal Note', 'eggplant' ); ?></label>
            <input type="text" id="eg-fc-note" placeholder="<?php esc_attr_e( 'Only visible to admins (client name, reason blocked…)', 'eggplant' ); ?>">
          </div>
          <button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'eggplant' ); ?></button>
          <button type="button" class="button" id="eg-fc-editor-close"><?php esc_html_e( 'Close', 'eggplant' ); ?></button>
          <span class="eg-msg" id="eg-fc-editor-msg"></span>
        </form>
      </div>

      <!-- Bulk block -->
      <div class="eg-card" id="eg-fc-bulk">
        <h2><?php esc_html_e( 'Block Out Recurring Days', 'eggplant' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Mark every selected weekday as unavailable (e.g. every Tuesday) for a week, month, year or custom range. Days that are already booked are never changed.', 'eggplant' ); ?></p>
        <form id="eg-fc-bulk-form">
          <div class="eg-form-row">
            <label><?php esc_html_e( 'Days of the week', 'eggplant' ); ?></label>
            <span class="eg-fc-weekday-picks">
              <?php foreach ( $weekdays as $num => $label ) : ?>
                <label class="eg-fc-check"><input type="checkbox" name="weekdays[]" value="<?php echo esc_attr( $num ); ?>"> <?php echo esc_html( $label ); ?></label>
              <?php endforeach; ?>
            </span>
          </div>
          <div class="eg-form-row">
            <label for="eg-fc-bulk-floor"><?php esc_html_e( 'Floor', 'eggplant' ); ?></label>
            <select id="eg-fc-bulk-floor">
              <option value="both"><?php esc_html_e( 'Both floors', 'eggplant' ); ?></option>
              <?php foreach ( $floors as $key => $name ) : ?>
                <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $name ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="eg-form-row">
            <label><?php esc_html_e( 'Period', 'eggplant' ); ?></label>
            <span class="eg-fc-presets">
              <button type="button" class="button" data-preset="week"><?php esc_html_e( 'This Week', 'eggplant' ); ?></button>
              <button type="button" class="button" data-preset="month"><?php esc_html_e( 'This Month', 'eggplant' ); ?></button>
              <button type="button" class="button" data-preset="shown"><?php esc_html_e( 'Month Shown Above', 'eggplant' ); ?></button>
              <button type="button" class="button" data-preset="year"><?php esc_html_e( 'Rest of This Year', 'eggplant' ); ?></button>
              <button type="button" class="button" data-preset="next-year"><?php esc_html_e( 'Next Year', 'eggplant' ); ?></button>
            </span>
          </div>
          <div class="eg-form-row">
            <label for="eg-fc-bulk-start"><?php esc_html_e( 'From / To', 'eggplant' ); ?></label>
            <input type="date" id="eg-fc-bulk-start" required> <input type="date" id="eg-fc-bulk-end" required>
          </div>
          <div class="eg-form-row">
            <label for="eg-fc-bulk-note"><?php esc_html_e( 'Internal Note', 'eggplant' ); ?></label>
            <input type="text" id="eg-fc-bulk-note" placeholder="<?php esc_attr_e( 'e.g. Closed Tuesdays', 'eggplant' ); ?>">
          </div>
          <button type="submit" class="button button-primary" data-mode="block"><?php esc_html_e( 'Block Selected Days', 'eggplant' ); ?></button>
          <button type="submit" class="button" data-mode="unblock"><?php esc_html_e( 'Unblock Selected Days', 'eggplant' ); ?></button>
          <span class="eg-msg" id="eg-fc-bulk-msg"></span>
        </form>
      </div>
    </div>
    <?php
  }

  // ------------------------------------------------------------------ AJAX (admin)

  private static function verify_admin_request(): void {
    check_ajax_referer( 'eggplant_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_send_json_error( 'Unauthorized', 403 );
    }
  }

  public static function ajax_get_month(): void {
    self::verify_admin_request();
    $month = sanitize_text_field( wp_unslash( $_POST['month'] ?? '' ) );
    if ( ! preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $month ) ) {
      wp_send_json_error( 'Invalid month.' );
    }
    $out = array();
    foreach ( self::get_month( $month ) as $row ) {
      $out[ $row['day_date'] ][ $row['floor'] ] = array(
        'status'      => $row['status'],
        'visibility'  => $row['visibility'],
        'title'       => $row['title'],
        'description' => $row['description'],
        'start'       => $row['start_time'] ? substr( $row['start_time'], 0, 5 ) : '',
        'end'         => $row['end_time'] ? substr( $row['end_time'], 0, 5 ) : '',
        'image_url'   => $row['image_url'],
        'link_url'    => $row['link_url'],
        'note'        => $row['note'],
        'booking_id'  => $row['booking_id'] ? (int) $row['booking_id'] : 0,
        'time_slots'  => ! empty( $row['time_slots'] ),
      );
    }
    wp_send_json_success( $out );
  }

  public static function ajax_save_day(): void {
    self::verify_admin_request();
    $date   = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
    $floor  = sanitize_key( wp_unslash( $_POST['floor'] ?? '' ) );
    $status = sanitize_key( wp_unslash( $_POST['status'] ?? '' ) );

    if ( ! self::is_valid_date( $date ) || ! in_array( $floor, self::FLOORS, true ) ) {
      wp_send_json_error( __( 'Invalid date or floor.', 'eggplant' ) );
    }
    if ( ! self::get_day( $date, $floor ) && ! self::is_available( $date, $floor ) ) {
      wp_send_json_error( __( 'Manage this reservation on the Time Slots page.', 'eggplant' ) );
    }

    if ( 'available' === $status ) {
      self::delete_day( $date, $floor ) ? wp_send_json_success() : wp_send_json_error( __( 'Could not clear this day.', 'eggplant' ) );
    }

    if ( ! in_array( $status, self::STATUSES, true ) ) {
      wp_send_json_error( __( 'Invalid status.', 'eggplant' ) );
    }

    $id = self::save_day( $date, $floor, array(
      'status'      => $status,
      'visibility'  => sanitize_key( wp_unslash( $_POST['visibility'] ?? 'private' ) ),
      'title'       => wp_unslash( $_POST['title'] ?? '' ),
      'description' => wp_unslash( $_POST['description'] ?? '' ),
      'start_time'  => sanitize_text_field( wp_unslash( $_POST['start_time'] ?? '' ) ),
      'end_time'    => sanitize_text_field( wp_unslash( $_POST['end_time'] ?? '' ) ),
      'image_url'   => wp_unslash( $_POST['image_url'] ?? '' ),
      'link_url'    => wp_unslash( $_POST['link_url'] ?? '' ),
      'note'        => wp_unslash( $_POST['note'] ?? '' ),
    ) );

    $id ? wp_send_json_success( array( 'id' => $id ) ) : wp_send_json_error( __( 'Could not save this day.', 'eggplant' ) );
  }

  public static function ajax_clear_day(): void {
    self::verify_admin_request();
    $date  = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
    $floor = sanitize_key( wp_unslash( $_POST['floor'] ?? '' ) );
    if ( ! self::is_valid_date( $date ) || ! in_array( $floor, self::FLOORS, true ) ) {
      wp_send_json_error( __( 'Invalid date or floor.', 'eggplant' ) );
    }
    if ( ! self::get_day( $date, $floor ) && ! self::is_available( $date, $floor ) ) {
      wp_send_json_error( __( 'Manage this reservation on the Time Slots page.', 'eggplant' ) );
    }
    self::delete_day( $date, $floor ) ? wp_send_json_success() : wp_send_json_error( __( 'Could not clear this day.', 'eggplant' ) );
  }

  public static function ajax_bulk(): void {
    self::verify_admin_request();
    $mode  = 'unblock' === sanitize_key( wp_unslash( $_POST['mode'] ?? '' ) ) ? 'unblock' : 'block';
    $floor = sanitize_key( wp_unslash( $_POST['floor'] ?? 'both' ) );
    $days  = isset( $_POST['weekdays'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['weekdays'] ) ) : array();

    $result = self::bulk_apply(
      $mode,
      sanitize_text_field( wp_unslash( $_POST['start'] ?? '' ) ),
      sanitize_text_field( wp_unslash( $_POST['end'] ?? '' ) ),
      $days,
      'both' === $floor ? self::FLOORS : array( $floor ),
      sanitize_text_field( wp_unslash( $_POST['note'] ?? '' ) )
    );

    if ( is_wp_error( $result ) ) {
      wp_send_json_error( $result->get_error_message() );
    }
    wp_send_json_success( $result );
  }
}
