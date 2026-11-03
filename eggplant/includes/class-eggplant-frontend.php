<?php

/**
 * Handles the front-end: takes over the WordPress front page and renders
 * the event-center portal (carousel + calendar + booking form).
 *
 * @since 1.0.0
 * @package Eggplant
 */

class Eggplant_Frontend {

  public function __construct() {
    // Take over the front page template.
    add_filter( 'template_include',    array( $this, 'override_template' ), 99 );

    // Remove the default <title> tag and replace with portal title.
    add_filter( 'wp_title', array( $this, 'filter_page_title' ), 99, 2 );

    // Enqueue frontend assets.
    add_action( 'wp_enqueue_scripts',  array( $this, 'enqueue_assets' ) );

    // AJAX: calendar data.
    add_action( 'wp_ajax_nopriv_eggplant_get_calendar', array( $this, 'ajax_get_calendar' ) );
    add_action( 'wp_ajax_eggplant_get_calendar',        array( $this, 'ajax_get_calendar' ) );

    // AJAX: booking form submission.
    add_action( 'wp_ajax_nopriv_eggplant_submit_booking', array( $this, 'ajax_submit_booking' ) );
    add_action( 'wp_ajax_eggplant_submit_booking',        array( $this, 'ajax_submit_booking' ) );
  }

  /**
   * Override the page title on the front page with the portal title.
   *
   * @param string $title
   * @param string $sep
   * @return string
   */
  public function filter_page_title( string $title, string $sep ): string {
    if ( is_front_page() || is_home() ) {
      return Eggplant_Settings::get( 'portal_title', 'Event Center' );
    }
    return $title;
  }

  /**
   * Replace the theme template on the front page (is_front_page() || is_home()).
   *
   * @param string $template
   * @return string
   */
  public function override_template( string $template ): string {
    if ( is_front_page() || is_home() ) {
      $custom = EGGPLANT_PLUGIN_DIR . 'public/templates/front-page.php';
      if ( file_exists( $custom ) ) {
        return $custom;
      }
    }
    return $template;
  }

  /**
   * Enqueue CSS & JS on plugin-controlled front-end views and shortcodes.
   */
  public function enqueue_assets(): void {
    if ( ! $this->should_enqueue_assets() ) {
      return;
    }

    $settings = Eggplant_Settings::get_all();

    wp_enqueue_style(
      'eggplant-frontend',
      EGGPLANT_PLUGIN_URL . 'public/css/frontend.css',
      array(),
      EGGPLANT_VERSION
    );

    // Inline dynamic CSS driven by settings.
    $dynamic_css = $this->build_dynamic_css( $settings );
    wp_add_inline_style( 'eggplant-frontend', $dynamic_css );

    wp_enqueue_script(
      'eggplant-frontend',
      EGGPLANT_PLUGIN_URL . 'public/js/frontend.js',
      array( 'jquery' ),
      EGGPLANT_VERSION,
      true
    );

    wp_localize_script( 'eggplant-frontend', 'EggplantData', array(
      'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
      'nonce'           => wp_create_nonce( 'eggplant_nonce' ),
      'carouselSpeed'   => intval( $settings['carousel_speed'] ),
      'carouselAuto'    => ! empty( $settings['carousel_autoplay'] ),
      'showBookingForm' => ! empty( $settings['show_booking_form'] ),
      'floors'          => Eggplant_Floor_Calendar::floor_names(),
      'bookingFloors'   => Eggplant_Floor_Calendar::booking_floor_names(),
      'today'           => current_time( 'Y-m-d' ),
    ) );
  }

  /**
   * Determine whether plugin front-end assets are needed on the current page.
   */
  private function should_enqueue_assets(): bool {
    if ( is_front_page() || is_home() ) {
      return true;
    }

    if ( ! is_singular() ) {
      return false;
    }

    $post = get_post();
    if ( ! $post || empty( $post->post_content ) ) {
      return false;
    }

    return has_shortcode( $post->post_content, 'eggplant_tasks' ) || has_shortcode( $post->post_content, 'eggplant_staff_clock' ) || has_shortcode( $post->post_content, 'eggplant_box_office' ) || has_shortcode( $post->post_content, 'eggplant_ticket_scanner' ) || has_shortcode( $post->post_content, 'eggplant_pos' );
  }

  /**
   * Build inline CSS from the color settings.
   *
   * @param array<string,mixed> $settings
   * @return string
   */
  private function build_dynamic_css( array $settings ): string {
    $bg        = esc_attr( $settings['bg_color']        ?? '#000000' );
    $primary   = esc_attr( $settings['primary_color']   ?? '#e63946' );
    $secondary = esc_attr( $settings['secondary_color'] ?? '#457b9d' );
    $text      = esc_attr( $settings['text_color']      ?? '#f1faee' );
    $avail     = esc_attr( $settings['available_color'] ?? '#2a9d8f' );
    $booked    = esc_attr( $settings['booked_color']    ?? '#e63946' );
    $held      = esc_attr( $settings['held_color']      ?? '#f4a261' );
    $private   = esc_attr( $settings['private_color']   ?? '#7b2cbf' );
    $public    = esc_attr( $settings['public_color']    ?? '#1d7ed8' );
    $blocked   = esc_attr( $settings['blocked_color']   ?? '#4a4a4a' );
    $custom    = wp_strip_all_tags( $settings['custom_css'] ?? '' );

    $css  = ":root{";
    $css .= "--eg-bg:{$bg};";
    $css .= "--eg-primary:{$primary};";
    $css .= "--eg-secondary:{$secondary};";
    $css .= "--eg-text:{$text};";
    $css .= "--eg-available:{$avail};";
    $css .= "--eg-booked:{$booked};";
    $css .= "--eg-held:{$held};";
    $css .= "--eg-private:{$private};";
    $css .= "--eg-public:{$public};";
    $css .= "--eg-blocked:{$blocked};";
    $css .= "}";
    $css .= $custom;
    return $css;
  }

  // ------------------------------------------------------------------ AJAX

  /**
   * Return calendar data for a given month (YYYY-MM) as JSON:
   *  - slots : legacy time-slot data keyed by date.
   *  - floors: floor-calendar data keyed by date then floor (public-safe).
   */
  public function ajax_get_calendar(): void {
    check_ajax_referer( 'eggplant_nonce', 'nonce' );

    $month = isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : current_time( 'Y-m' );

    if ( ! preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $month ) ) {
      wp_send_json_error( 'Invalid month format' );
    }

    $slots   = Eggplant_DB::get_slots_for_month( $month );
    $by_day  = array();

    foreach ( $slots as $slot ) {
      $day = $slot['slot_date'];
      if ( ! isset( $by_day[ $day ] ) ) {
        $by_day[ $day ] = array(
          'status' => 'available',
          'slots'  => array(),
        );
      }
      $by_day[ $day ]['slots'][] = array(
        'id'    => $slot['id'],
        'start' => $slot['start_time'],
        'end'   => $slot['end_time'],
        'label' => $slot['label'],
        'status'=> $slot['status'],
      );
      // Day status: if any slot is booked -> booked; elif held -> held; else available
      $existing = $by_day[ $day ]['status'];
      $new      = $slot['status'];
      if ( $new === 'booked' || $existing === 'booked' ) {
        $by_day[ $day ]['status'] = 'booked';
      } elseif ( $new === 'held' || $existing === 'held' ) {
        $by_day[ $day ]['status'] = 'held';
      }
    }

    wp_send_json_success( array(
      'slots'  => (object) $by_day,
      'floors' => (object) Eggplant_Floor_Calendar::get_public_month( $month ),
    ) );
  }

  /**
   * Handle front-end booking form submission.
   */
  public function ajax_submit_booking(): void {
    check_ajax_referer( 'eggplant_nonce', 'nonce' );

    $name       = sanitize_text_field( wp_unslash( $_POST['name']       ?? '' ) );
    $email      = sanitize_email( wp_unslash( $_POST['email']           ?? '' ) );
    $phone      = sanitize_text_field( wp_unslash( $_POST['phone']      ?? '' ) );
    $event_type = sanitize_text_field( wp_unslash( $_POST['event_type'] ?? '' ) );
    $event_date = sanitize_text_field( wp_unslash( $_POST['event_date'] ?? '' ) );
    $floor      = sanitize_key( wp_unslash( $_POST['floor'] ?? '' ) );
    $message    = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );

    if ( empty( $name ) || ! is_email( $email ) ) {
      wp_send_json_error( __( 'Please provide your name and a valid email address.', 'eggplant' ) );
    }

    if ( empty( $event_date ) || ! Eggplant_Floor_Calendar::is_valid_date( $event_date ) ) {
      wp_send_json_error( __( 'Please choose an event date.', 'eggplant' ) );
    }

    if ( $event_date < current_time( 'Y-m-d' ) ) {
      wp_send_json_error( __( 'Please choose a date in the future.', 'eggplant' ) );
    }

    $floor_names = Eggplant_Floor_Calendar::booking_floor_names();
    if ( ! isset( $floor_names[ $floor ] ) ) {
      wp_send_json_error( __( 'Please choose a floor.', 'eggplant' ) );
    }

    foreach ( Eggplant_Floor_Calendar::booking_floors( $floor ) as $requested_floor ) {
      if ( ! Eggplant_Floor_Calendar::is_available( $event_date, $requested_floor ) ) {
        wp_send_json_error( sprintf(
          /* translators: %s: floor name */
          __( 'Sorry, the %s is not available on that date. Please choose another date or floor.', 'eggplant' ),
          $floor_names[ $requested_floor ]
        ) );
      }
    }

    $id = Eggplant_DB::insert_booking( array(
      'name'         => $name,
      'email'        => $email,
      'phone'        => $phone,
      'event_type'   => $event_type,
      'event_date'   => $event_date,
      'floor'        => $floor,
      'message'      => $message,
    ) );

    if ( ! $id ) {
      wp_send_json_error( __( 'There was an error submitting your request. Please try again.', 'eggplant' ) );
    }

    // Notify admin.
    $admin_email = Eggplant_Settings::get( 'contact_email', get_option( 'admin_email' ) );
    $subject     = sprintf( __( 'New Booking Request – %s', 'eggplant' ), esc_html( $name ) );
    $body        = sprintf(
      /* translators: booking request notification email */
      __(
        "New booking request received:\n\nName: %s\nEmail: %s\nPhone: %s\nEvent Type: %s\nDate: %s\nFloor: %s\nMessage:\n%s",
        'eggplant'
      ),
      $name, $email, $phone, $event_type, $event_date, $floor_names[ $floor ], $message
    );
    wp_mail( $admin_email, $subject, $body );

    wp_send_json_success( __( 'Your request has been submitted! We will be in touch soon.', 'eggplant' ) );
  }
}
