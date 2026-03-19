<?php

/**
 * Registers and renders all admin pages for the Eggplant plugin.
 *
 * @since 1.0.0
 * @package Eggplant
 */

class Eggplant_Admin {

  public function __construct() {
    add_action( 'admin_menu',            array( $this, 'add_menus' ) );
    add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

    // AJAX handlers for admin actions.
    add_action( 'wp_ajax_eggplant_admin_add_slot',    array( $this, 'ajax_add_slot' ) );
    add_action( 'wp_ajax_eggplant_admin_update_slot', array( $this, 'ajax_update_slot' ) );
    add_action( 'wp_ajax_eggplant_admin_delete_slot', array( $this, 'ajax_delete_slot' ) );
    add_action( 'wp_ajax_eggplant_admin_get_slots',   array( $this, 'ajax_get_slots' ) );

    add_action( 'wp_ajax_eggplant_admin_save_event',   array( $this, 'ajax_save_event' ) );
    add_action( 'wp_ajax_eggplant_admin_delete_event', array( $this, 'ajax_delete_event' ) );
    add_action( 'wp_ajax_eggplant_admin_get_event',    array( $this, 'ajax_get_event' ) );

    add_action( 'wp_ajax_eggplant_admin_update_booking', array( $this, 'ajax_update_booking' ) );
  }

  // ------------------------------------------------------------------ menus

  public function add_menus(): void {
    add_menu_page(
      __( 'Event Portal', 'eggplant' ),
      __( 'Event Portal', 'eggplant' ),
      'manage_options',
      'eggplant',
      array( $this, 'page_dashboard' ),
      'dashicons-calendar-alt',
      6
    );

    add_submenu_page(
      'eggplant',
      __( 'Time Slots', 'eggplant' ),
      __( 'Time Slots', 'eggplant' ),
      'manage_options',
      'eggplant-slots',
      array( $this, 'page_slots' )
    );

    add_submenu_page(
      'eggplant',
      __( 'Events / Ads', 'eggplant' ),
      __( 'Events / Ads', 'eggplant' ),
      'manage_options',
      'eggplant-events',
      array( $this, 'page_events' )
    );

    add_submenu_page(
      'eggplant',
      __( 'Booking Requests', 'eggplant' ),
      __( 'Booking Requests', 'eggplant' ),
      'manage_options',
      'eggplant-bookings',
      array( $this, 'page_bookings' )
    );

    add_submenu_page(
      'eggplant',
      __( 'Settings', 'eggplant' ),
      __( 'Settings', 'eggplant' ),
      'manage_options',
      'eggplant-settings',
      array( $this, 'page_settings' )
    );
  }

  // ------------------------------------------------------------------ assets

  public function enqueue_assets( string $hook ): void {
    $pages = array(
      'toplevel_page_eggplant',
      'event-portal_page_eggplant-slots',
      'event-portal_page_eggplant-events',
      'event-portal_page_eggplant-bookings',
      'event-portal_page_eggplant-settings',
    );
    if ( ! in_array( $hook, $pages, true ) ) {
      return;
    }

    wp_enqueue_style(
      'eggplant-admin',
      EGGPLANT_PLUGIN_URL . 'admin/css/admin.css',
      array(),
      EGGPLANT_VERSION
    );

    wp_enqueue_script(
      'eggplant-admin',
      EGGPLANT_PLUGIN_URL . 'admin/js/admin.js',
      array( 'jquery' ),
      EGGPLANT_VERSION,
      true
    );

    wp_localize_script( 'eggplant-admin', 'EggplantAdmin', array(
      'ajaxUrl' => admin_url( 'admin-ajax.php' ),
      'nonce'   => wp_create_nonce( 'eggplant_admin_nonce' ),
    ) );
  }

  // ------------------------------------------------------------------ page: dashboard

  public function page_dashboard(): void {
    $bookings = Eggplant_DB::get_all_bookings();
    $new_count = count( array_filter( $bookings, fn( $b ) => $b['status'] === 'new' ) );
    $slots     = Eggplant_DB::get_all_slots();
    $events    = Eggplant_DB::get_all_events();
    ?>
    <div class="wrap eg-admin">
      <h1><?php esc_html_e( 'Event Portal – Dashboard', 'eggplant' ); ?></h1>
      <div class="eg-dash-cards">
        <div class="eg-dash-card">
          <span class="eg-dash-number"><?php echo count( $slots ); ?></span>
          <span class="eg-dash-label"><?php esc_html_e( 'Time Slots', 'eggplant' ); ?></span>
          <a href="<?php echo esc_url( admin_url( 'admin.php?page=eggplant-slots' ) ); ?>"><?php esc_html_e( 'Manage', 'eggplant' ); ?></a>
        </div>
        <div class="eg-dash-card">
          <span class="eg-dash-number"><?php echo count( $events ); ?></span>
          <span class="eg-dash-label"><?php esc_html_e( 'Events / Ads', 'eggplant' ); ?></span>
          <a href="<?php echo esc_url( admin_url( 'admin.php?page=eggplant-events' ) ); ?>"><?php esc_html_e( 'Manage', 'eggplant' ); ?></a>
        </div>
        <div class="eg-dash-card <?php echo $new_count ? 'eg-dash-card--alert' : ''; ?>">
          <span class="eg-dash-number"><?php echo $new_count; ?></span>
          <span class="eg-dash-label"><?php esc_html_e( 'New Booking Requests', 'eggplant' ); ?></span>
          <a href="<?php echo esc_url( admin_url( 'admin.php?page=eggplant-bookings' ) ); ?>"><?php esc_html_e( 'View All', 'eggplant' ); ?></a>
        </div>
      </div>
    </div>
    <?php
  }

  // ------------------------------------------------------------------ page: time slots

  public function page_slots(): void {
    ?>
    <div class="wrap eg-admin">
      <h1><?php esc_html_e( 'Time Slots', 'eggplant' ); ?></h1>

      <!-- Add slot form -->
      <div class="eg-card" id="eg-add-slot-card">
        <h2><?php esc_html_e( 'Add Time Slot', 'eggplant' ); ?></h2>
        <form id="eg-add-slot-form">
          <div class="eg-form-row">
            <label><?php esc_html_e( 'Date', 'eggplant' ); ?></label>
            <input type="date" id="eg-slot-date" required>
          </div>
          <div class="eg-form-row">
            <label><?php esc_html_e( 'Start Time', 'eggplant' ); ?></label>
            <input type="time" id="eg-slot-start" required>
          </div>
          <div class="eg-form-row">
            <label><?php esc_html_e( 'End Time', 'eggplant' ); ?></label>
            <input type="time" id="eg-slot-end" required>
          </div>
          <div class="eg-form-row">
            <label><?php esc_html_e( 'Label (optional)', 'eggplant' ); ?></label>
            <input type="text" id="eg-slot-label" placeholder="<?php esc_attr_e( 'e.g. Evening Show', 'eggplant' ); ?>">
          </div>
          <div class="eg-form-row">
            <label><?php esc_html_e( 'Status', 'eggplant' ); ?></label>
            <select id="eg-slot-status">
              <option value="available"><?php esc_html_e( 'Available', 'eggplant' ); ?></option>
              <option value="held"><?php esc_html_e( 'Hold', 'eggplant' ); ?></option>
              <option value="booked"><?php esc_html_e( 'Booked', 'eggplant' ); ?></option>
            </select>
          </div>
          <button type="submit" class="button button-primary"><?php esc_html_e( 'Add Slot', 'eggplant' ); ?></button>
          <span class="eg-msg" id="eg-add-slot-msg"></span>
        </form>
      </div>

      <!-- Slots table -->
      <div class="eg-card">
        <h2><?php esc_html_e( 'All Time Slots', 'eggplant' ); ?></h2>
        <div class="eg-table-wrap" id="eg-slots-table-wrap">
          <?php $this->render_slots_table(); ?>
        </div>
      </div>
    </div>
    <?php
  }

  private function render_slots_table(): void {
    $slots = Eggplant_DB::get_all_slots();
    if ( empty( $slots ) ) {
      echo '<p>' . esc_html__( 'No time slots yet.', 'eggplant' ) . '</p>';
      return;
    }
    ?>
    <table class="widefat striped eg-slots-table">
      <thead>
        <tr>
          <th><?php esc_html_e( 'Date', 'eggplant' ); ?></th>
          <th><?php esc_html_e( 'Start', 'eggplant' ); ?></th>
          <th><?php esc_html_e( 'End', 'eggplant' ); ?></th>
          <th><?php esc_html_e( 'Label', 'eggplant' ); ?></th>
          <th><?php esc_html_e( 'Status', 'eggplant' ); ?></th>
          <th><?php esc_html_e( 'Actions', 'eggplant' ); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $slots as $slot ) : ?>
        <tr id="eg-slot-row-<?php echo esc_attr( $slot['id'] ); ?>">
          <td><?php echo esc_html( $slot['slot_date'] ); ?></td>
          <td><?php echo esc_html( substr( $slot['start_time'], 0, 5 ) ); ?></td>
          <td><?php echo esc_html( substr( $slot['end_time'],   0, 5 ) ); ?></td>
          <td><?php echo esc_html( $slot['label'] ); ?></td>
          <td>
            <select class="eg-slot-status-select" data-id="<?php echo esc_attr( $slot['id'] ); ?>">
              <option value="available" <?php selected( $slot['status'], 'available' ); ?>><?php esc_html_e( 'Available', 'eggplant' ); ?></option>
              <option value="held"      <?php selected( $slot['status'], 'held' );      ?>><?php esc_html_e( 'Hold',      'eggplant' ); ?></option>
              <option value="booked"    <?php selected( $slot['status'], 'booked' );    ?>><?php esc_html_e( 'Booked',    'eggplant' ); ?></option>
            </select>
          </td>
          <td>
            <button class="button eg-delete-slot" data-id="<?php echo esc_attr( $slot['id'] ); ?>"><?php esc_html_e( 'Delete', 'eggplant' ); ?></button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php
  }

  // ------------------------------------------------------------------ page: events

  public function page_events(): void {
    ?>
    <div class="wrap eg-admin">
      <h1><?php esc_html_e( 'Events / Advertisements', 'eggplant' ); ?></h1>

      <div class="eg-card" id="eg-event-form-card">
        <h2 id="eg-event-form-title"><?php esc_html_e( 'Add Event / Advertisement', 'eggplant' ); ?></h2>
        <form id="eg-event-form">
          <input type="hidden" id="eg-event-id" value="">
          <div class="eg-form-row">
            <label><?php esc_html_e( 'Title *', 'eggplant' ); ?></label>
            <input type="text" id="eg-event-title" required>
          </div>
          <div class="eg-form-row">
            <label><?php esc_html_e( 'Description', 'eggplant' ); ?></label>
            <textarea id="eg-event-description" rows="3"></textarea>
          </div>
          <div class="eg-form-row">
            <label><?php esc_html_e( 'Image URL', 'eggplant' ); ?></label>
            <input type="url" id="eg-event-image-url">
          </div>
          <div class="eg-form-row">
            <label><?php esc_html_e( 'Link URL', 'eggplant' ); ?></label>
            <input type="url" id="eg-event-link-url">
          </div>
          <div class="eg-form-row">
            <label><?php esc_html_e( 'Start Date', 'eggplant' ); ?></label>
            <input type="date" id="eg-event-start-date">
          </div>
          <div class="eg-form-row">
            <label><?php esc_html_e( 'End Date', 'eggplant' ); ?></label>
            <input type="date" id="eg-event-end-date">
          </div>
          <div class="eg-form-row">
            <label><?php esc_html_e( 'Sort Order', 'eggplant' ); ?></label>
            <input type="number" id="eg-event-sort" value="0" min="0">
          </div>
          <div class="eg-form-row">
            <label><?php esc_html_e( 'Active', 'eggplant' ); ?></label>
            <input type="checkbox" id="eg-event-active" checked>
          </div>
          <button type="submit" class="button button-primary" id="eg-event-submit"><?php esc_html_e( 'Add Event', 'eggplant' ); ?></button>
          <button type="button" class="button" id="eg-event-cancel" style="display:none"><?php esc_html_e( 'Cancel', 'eggplant' ); ?></button>
          <span class="eg-msg" id="eg-event-msg"></span>
        </form>
      </div>

      <div class="eg-card">
        <h2><?php esc_html_e( 'All Events / Ads', 'eggplant' ); ?></h2>
        <div id="eg-events-table-wrap">
          <?php $this->render_events_table(); ?>
        </div>
      </div>
    </div>
    <?php
  }

  private function render_events_table(): void {
    $events = Eggplant_DB::get_all_events();
    if ( empty( $events ) ) {
      echo '<p>' . esc_html__( 'No events yet.', 'eggplant' ) . '</p>';
      return;
    }
    ?>
    <table class="widefat striped">
      <thead>
        <tr>
          <th><?php esc_html_e( 'Image', 'eggplant' ); ?></th>
          <th><?php esc_html_e( 'Title', 'eggplant' ); ?></th>
          <th><?php esc_html_e( 'Dates', 'eggplant' ); ?></th>
          <th><?php esc_html_e( 'Order', 'eggplant' ); ?></th>
          <th><?php esc_html_e( 'Active', 'eggplant' ); ?></th>
          <th><?php esc_html_e( 'Actions', 'eggplant' ); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $events as $event ) : ?>
        <tr id="eg-event-row-<?php echo esc_attr( $event['id'] ); ?>">
          <td>
            <?php if ( $event['image_url'] ) : ?>
            <img src="<?php echo esc_url( $event['image_url'] ); ?>" style="max-width:80px;max-height:50px;object-fit:cover;" alt="">
            <?php endif; ?>
          </td>
          <td><?php echo esc_html( $event['title'] ); ?></td>
          <td><?php echo esc_html( ( $event['start_date'] ?: '' ) . ( $event['end_date'] ? ' – ' . $event['end_date'] : '' ) ); ?></td>
          <td><?php echo esc_html( $event['sort_order'] ); ?></td>
          <td><?php echo $event['active'] ? '✅' : '❌'; ?></td>
          <td>
            <button class="button eg-edit-event" data-id="<?php echo esc_attr( $event['id'] ); ?>"><?php esc_html_e( 'Edit', 'eggplant' ); ?></button>
            <button class="button eg-delete-event" data-id="<?php echo esc_attr( $event['id'] ); ?>"><?php esc_html_e( 'Delete', 'eggplant' ); ?></button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php
  }

  // ------------------------------------------------------------------ page: bookings

  public function page_bookings(): void {
    $bookings = Eggplant_DB::get_all_bookings();
    ?>
    <div class="wrap eg-admin">
      <h1><?php esc_html_e( 'Booking Requests', 'eggplant' ); ?></h1>
      <?php if ( empty( $bookings ) ) : ?>
        <p><?php esc_html_e( 'No booking requests yet.', 'eggplant' ); ?></p>
      <?php else : ?>
      <table class="widefat striped eg-bookings-table">
        <thead>
          <tr>
            <th><?php esc_html_e( 'Date Submitted', 'eggplant' ); ?></th>
            <th><?php esc_html_e( 'Name', 'eggplant' ); ?></th>
            <th><?php esc_html_e( 'Email', 'eggplant' ); ?></th>
            <th><?php esc_html_e( 'Phone', 'eggplant' ); ?></th>
            <th><?php esc_html_e( 'Event Type', 'eggplant' ); ?></th>
            <th><?php esc_html_e( 'Requested Date', 'eggplant' ); ?></th>
            <th><?php esc_html_e( 'Time Slot', 'eggplant' ); ?></th>
            <th><?php esc_html_e( 'Message', 'eggplant' ); ?></th>
            <th><?php esc_html_e( 'Status', 'eggplant' ); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ( $bookings as $booking ) : ?>
          <tr id="eg-booking-row-<?php echo esc_attr( $booking['id'] ); ?>">
            <td><?php echo esc_html( $booking['created_at'] ); ?></td>
            <td><?php echo esc_html( $booking['name'] ); ?></td>
            <td><a href="mailto:<?php echo esc_attr( $booking['email'] ); ?>"><?php echo esc_html( $booking['email'] ); ?></a></td>
            <td><?php echo esc_html( $booking['phone'] ); ?></td>
            <td><?php echo esc_html( $booking['event_type'] ); ?></td>
            <td><?php echo esc_html( $booking['event_date'] ); ?></td>
            <td><?php
              if ( $booking['start_time'] ) {
                echo esc_html( substr( $booking['start_time'], 0, 5 ) . ' – ' . substr( $booking['end_time'], 0, 5 ) );
                if ( $booking['slot_label'] ) {
                  echo ' (' . esc_html( $booking['slot_label'] ) . ')';
                }
              }
            ?></td>
            <td><?php echo esc_html( $booking['message'] ); ?></td>
            <td>
              <select class="eg-booking-status-select" data-id="<?php echo esc_attr( $booking['id'] ); ?>">
                <option value="new"      <?php selected( $booking['status'], 'new' );      ?>><?php esc_html_e( 'New',      'eggplant' ); ?></option>
                <option value="reviewed" <?php selected( $booking['status'], 'reviewed' ); ?>><?php esc_html_e( 'Reviewed', 'eggplant' ); ?></option>
                <option value="approved" <?php selected( $booking['status'], 'approved' ); ?>><?php esc_html_e( 'Approved', 'eggplant' ); ?></option>
                <option value="declined" <?php selected( $booking['status'], 'declined' ); ?>><?php esc_html_e( 'Declined', 'eggplant' ); ?></option>
              </select>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
    <?php
  }

  // ------------------------------------------------------------------ page: settings

  public function page_settings(): void {
    // Handle form save.
    if ( isset( $_POST['eggplant_settings_nonce'] ) &&
         wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eggplant_settings_nonce'] ) ), 'eggplant_save_settings' ) ) {
      $this->save_settings();
      echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'eggplant' ) . '</p></div>';
    }

    $settings = Eggplant_Settings::get_all();
    ?>
    <div class="wrap eg-admin">
      <h1><?php esc_html_e( 'Event Portal Settings', 'eggplant' ); ?></h1>
      <form method="post" action="">
        <?php wp_nonce_field( 'eggplant_save_settings', 'eggplant_settings_nonce' ); ?>

        <div class="eg-card">
          <h2><?php esc_html_e( 'General', 'eggplant' ); ?></h2>
          <table class="form-table">
            <tr>
              <th><?php esc_html_e( 'Portal Title', 'eggplant' ); ?></th>
              <td><input type="text" name="portal_title" value="<?php echo esc_attr( $settings['portal_title'] ); ?>" class="regular-text"></td>
            </tr>
            <tr>
              <th><?php esc_html_e( 'Contact / Notification Email', 'eggplant' ); ?></th>
              <td><input type="email" name="contact_email" value="<?php echo esc_attr( $settings['contact_email'] ); ?>" class="regular-text"></td>
            </tr>
            <tr>
              <th><?php esc_html_e( 'Show Booking Form', 'eggplant' ); ?></th>
              <td><input type="checkbox" name="show_booking_form" value="1" <?php checked( $settings['show_booking_form'], 1 ); ?>></td>
            </tr>
          </table>
        </div>

        <div class="eg-card">
          <h2><?php esc_html_e( 'Colors', 'eggplant' ); ?></h2>
          <table class="form-table">
            <?php
            $color_fields = array(
              'bg_color'        => __( 'Background Color',        'eggplant' ),
              'primary_color'   => __( 'Primary / Accent Color',  'eggplant' ),
              'secondary_color' => __( 'Secondary Color',         'eggplant' ),
              'text_color'      => __( 'Text Color',              'eggplant' ),
              'available_color' => __( 'Calendar: Available Day', 'eggplant' ),
              'booked_color'    => __( 'Calendar: Booked Day',    'eggplant' ),
              'held_color'      => __( 'Calendar: Held Day',      'eggplant' ),
            );
            foreach ( $color_fields as $key => $label ) :
            ?>
            <tr>
              <th><?php echo esc_html( $label ); ?></th>
              <td>
                <input type="color" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $settings[ $key ] ); ?>" aria-label="<?php echo esc_attr( $label ); ?>">
                <input type="text"  name="<?php echo esc_attr( $key ); ?>_text" value="<?php echo esc_attr( $settings[ $key ] ); ?>" class="eg-color-text" data-for="<?php echo esc_attr( $key ); ?>" aria-label="<?php /* translators: %s: color field name */ echo esc_attr( sprintf( __( '%s hex value', 'eggplant' ), $label ) ); ?>">
              </td>
            </tr>
            <?php endforeach; ?>
          </table>
        </div>

        <div class="eg-card">
          <h2><?php esc_html_e( 'Carousel', 'eggplant' ); ?></h2>
          <table class="form-table">
            <tr>
              <th><?php esc_html_e( 'Auto-play', 'eggplant' ); ?></th>
              <td><input type="checkbox" name="carousel_autoplay" value="1" <?php checked( $settings['carousel_autoplay'], 1 ); ?>></td>
            </tr>
            <tr>
              <th><?php esc_html_e( 'Slide Speed (ms)', 'eggplant' ); ?></th>
              <td><input type="number" name="carousel_speed" value="<?php echo esc_attr( $settings['carousel_speed'] ); ?>" min="500" step="500" class="small-text"></td>
            </tr>
          </table>
        </div>

        <div class="eg-card">
          <h2><?php esc_html_e( 'Front Page Info', 'eggplant' ); ?></h2>
          <p class="description"><?php esc_html_e( 'Content displayed above the availability calendar on the front page. HTML is allowed.', 'eggplant' ); ?></p>
          <?php
          wp_editor(
            $settings['front_page_info'],
            'front_page_info',
            array(
              'textarea_name' => 'front_page_info',
              'textarea_rows' => 8,
              'media_buttons' => false,
            )
          );
          ?>
        </div>

        <div class="eg-card">
          <h2><?php esc_html_e( 'Custom CSS', 'eggplant' ); ?></h2>
          <p class="description"><?php esc_html_e( 'Extra CSS appended to the front-end page.', 'eggplant' ); ?></p>
          <textarea name="custom_css" rows="10" class="large-text code"><?php echo esc_textarea( $settings['custom_css'] ); ?></textarea>
        </div>

        <?php submit_button( __( 'Save Settings', 'eggplant' ) ); ?>
      </form>
    </div>
    <?php
  }

  private function save_settings(): void {
    $color_keys = array( 'bg_color', 'primary_color', 'secondary_color', 'text_color', 'available_color', 'booked_color', 'held_color' );
    $data = array();

    $data['portal_title']      = sanitize_text_field( wp_unslash( $_POST['portal_title']      ?? '' ) );
    $data['contact_email']     = sanitize_email( wp_unslash( $_POST['contact_email']          ?? '' ) );
    $data['show_booking_form'] = ! empty( $_POST['show_booking_form'] ) ? 1 : 0;
    $data['carousel_autoplay'] = ! empty( $_POST['carousel_autoplay'] ) ? 1 : 0;
    $data['carousel_speed']    = max( 500, intval( $_POST['carousel_speed'] ?? 5000 ) );
    $data['custom_css']        = wp_strip_all_tags( wp_unslash( $_POST['custom_css'] ?? '' ) ); // Admin-only; HTML tags removed; CSS is trusted.
    $data['front_page_info']   = wp_kses_post( wp_unslash( $_POST['front_page_info'] ?? '' ) );

    foreach ( $color_keys as $key ) {
      // Color picker sends hex value; text field is the editable version.
      $raw = sanitize_text_field( wp_unslash( $_POST[ $key . '_text' ] ?? $_POST[ $key ] ?? '' ) );
      if ( preg_match( '/^#[0-9a-fA-F]{3,6}$/', $raw ) ) {
        $data[ $key ] = $raw;
      }
    }

    Eggplant_Settings::update( $data );
  }

  // ------------------------------------------------------------------ AJAX handlers

  public function ajax_add_slot(): void {
    check_ajax_referer( 'eggplant_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_send_json_error( 'Unauthorized' );
    }

    $id = Eggplant_DB::insert_slot( array(
      'slot_date'  => sanitize_text_field( wp_unslash( $_POST['slot_date']  ?? '' ) ),
      'start_time' => sanitize_text_field( wp_unslash( $_POST['start_time'] ?? '' ) ),
      'end_time'   => sanitize_text_field( wp_unslash( $_POST['end_time']   ?? '' ) ),
      'label'      => sanitize_text_field( wp_unslash( $_POST['label']      ?? '' ) ),
      'status'     => sanitize_text_field( wp_unslash( $_POST['status']     ?? 'available' ) ),
    ) );

    if ( $id ) {
      wp_send_json_success( array( 'id' => $id ) );
    } else {
      wp_send_json_error( 'Could not insert slot.' );
    }
  }

  public function ajax_update_slot(): void {
    check_ajax_referer( 'eggplant_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_send_json_error( 'Unauthorized' );
    }
    $id     = intval( $_POST['id']     ?? 0 );
    $status = sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) );
    $ok     = Eggplant_DB::update_slot_status( $id, $status );
    $ok ? wp_send_json_success() : wp_send_json_error( 'Update failed.' );
  }

  public function ajax_delete_slot(): void {
    check_ajax_referer( 'eggplant_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_send_json_error( 'Unauthorized' );
    }
    $id = intval( $_POST['id'] ?? 0 );
    $ok = Eggplant_DB::delete_slot( $id );
    $ok ? wp_send_json_success() : wp_send_json_error( 'Delete failed.' );
  }

  public function ajax_get_slots(): void {
    check_ajax_referer( 'eggplant_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_send_json_error( 'Unauthorized' );
    }
    wp_send_json_success( Eggplant_DB::get_all_slots() );
  }

  public function ajax_save_event(): void {
    check_ajax_referer( 'eggplant_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_send_json_error( 'Unauthorized' );
    }

    $id   = intval( $_POST['id'] ?? 0 );
    $data = array(
      'title'       => sanitize_text_field( wp_unslash( $_POST['title']       ?? '' ) ),
      'description' => wp_kses_post( wp_unslash( $_POST['description']        ?? '' ) ),
      'image_url'   => esc_url_raw( wp_unslash( $_POST['image_url']           ?? '' ) ),
      'link_url'    => esc_url_raw( wp_unslash( $_POST['link_url']            ?? '' ) ),
      'start_date'  => sanitize_text_field( wp_unslash( $_POST['start_date']  ?? '' ) ),
      'end_date'    => sanitize_text_field( wp_unslash( $_POST['end_date']    ?? '' ) ),
      'sort_order'  => intval( $_POST['sort_order'] ?? 0 ),
      'active'      => isset( $_POST['active'] ) ? (int) $_POST['active'] : 1,
    );

    if ( $id ) {
      $ok = Eggplant_DB::update_event( $id, $data );
      $ok ? wp_send_json_success( array( 'updated' => true ) ) : wp_send_json_error( 'Update failed.' );
    } else {
      $new_id = Eggplant_DB::insert_event( $data );
      $new_id ? wp_send_json_success( array( 'id' => $new_id ) ) : wp_send_json_error( 'Insert failed.' );
    }
  }

  public function ajax_delete_event(): void {
    check_ajax_referer( 'eggplant_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_send_json_error( 'Unauthorized' );
    }
    $id = intval( $_POST['id'] ?? 0 );
    $ok = Eggplant_DB::delete_event( $id );
    $ok ? wp_send_json_success() : wp_send_json_error( 'Delete failed.' );
  }

  public function ajax_get_event(): void {
    check_ajax_referer( 'eggplant_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_send_json_error( 'Unauthorized' );
    }
    $id    = intval( $_POST['id'] ?? 0 );
    $event = Eggplant_DB::get_event( $id );
    $event ? wp_send_json_success( $event ) : wp_send_json_error( 'Not found.' );
  }

  public function ajax_update_booking(): void {
    check_ajax_referer( 'eggplant_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_send_json_error( 'Unauthorized' );
    }
    $id     = intval( $_POST['id']     ?? 0 );
    $status = sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) );
    $ok     = Eggplant_DB::update_booking_status( $id, $status );
    $ok ? wp_send_json_success() : wp_send_json_error( 'Update failed.' );
  }
}
