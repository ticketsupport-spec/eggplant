<?php

/**
 * Ticketing features: sales, tickets, scanning, discounts, and settlements.
 *
 * @since 1.3.0
 * @package Eggplant
 */
class Eggplant_Ticketing {

  public static function init(): void {
    add_shortcode( 'eggplant_box_office', array( __CLASS__, 'render_box_office_shortcode' ) );
    add_shortcode( 'eggplant_ticket_scanner', array( __CLASS__, 'render_ticket_scanner_shortcode' ) );

    add_action( 'admin_menu', array( __CLASS__, 'add_admin_menus' ) );

    add_action( 'admin_post_eggplant_ticket_purchase', array( __CLASS__, 'handle_ticket_purchase' ) );
    add_action( 'admin_post_nopriv_eggplant_ticket_purchase', array( __CLASS__, 'handle_ticket_purchase' ) );
    add_action( 'admin_post_eggplant_ticket_scan', array( __CLASS__, 'handle_ticket_scan' ) );

    add_action( 'admin_post_eggplant_ticket_save_type', array( __CLASS__, 'handle_save_ticket_type' ) );
    add_action( 'admin_post_eggplant_ticket_save_discount', array( __CLASS__, 'handle_save_discount_code' ) );
    add_action( 'admin_post_eggplant_ticket_save_settlement', array( __CLASS__, 'handle_save_settlement' ) );
    add_action( 'admin_post_eggplant_ticket_save_event_settings', array( __CLASS__, 'handle_save_event_settings' ) );
  }

  public static function add_admin_menus(): void {
    add_submenu_page(
      'eggplant',
      __( 'Ticketing Dashboard', 'eggplant' ),
      __( 'Ticketing Dashboard', 'eggplant' ),
      'manage_options',
      'eggplant-ticketing-dashboard',
      array( __CLASS__, 'page_dashboard' )
    );

    add_submenu_page(
      'eggplant',
      __( 'Ticketing Events & Pricing', 'eggplant' ),
      __( 'Ticketing Events & Pricing', 'eggplant' ),
      'manage_options',
      'eggplant-ticketing-events',
      array( __CLASS__, 'page_events_pricing' )
    );

    add_submenu_page(
      'eggplant',
      __( 'Ticket Discount Codes', 'eggplant' ),
      __( 'Ticket Discount Codes', 'eggplant' ),
      'manage_options',
      'eggplant-ticketing-discounts',
      array( __CLASS__, 'page_discounts' )
    );

    add_submenu_page(
      'eggplant',
      __( 'Ticket Orders & Tickets', 'eggplant' ),
      __( 'Ticket Orders & Tickets', 'eggplant' ),
      'manage_options',
      'eggplant-ticketing-orders',
      array( __CLASS__, 'page_orders' )
    );

    add_submenu_page(
      'eggplant',
      __( 'Ticket Scans', 'eggplant' ),
      __( 'Ticket Scans', 'eggplant' ),
      'manage_options',
      'eggplant-ticketing-scans',
      array( __CLASS__, 'page_scans' )
    );

    add_submenu_page(
      'eggplant',
      __( 'Ticket Settlements', 'eggplant' ),
      __( 'Ticket Settlements', 'eggplant' ),
      'manage_options',
      'eggplant-ticketing-settlements',
      array( __CLASS__, 'page_settlements' )
    );
  }

  public static function render_box_office_shortcode(): string {
    $events = Eggplant_DB::get_ticketing_events();
    $selected_event_id = intval( $_GET['eggplant_event'] ?? 0 );
    $selected_type_id = intval( $_GET['ticket_type'] ?? 0 );
    $order_number = sanitize_text_field( wp_unslash( $_GET['eggplant_order'] ?? '' ) );
    $order_key = sanitize_text_field( wp_unslash( $_GET['eggplant_key'] ?? '' ) );

    ob_start();
    ?>
    <div class="eg-ticketing eg-box-office">
      <h2><?php esc_html_e( 'Box Office', 'eggplant' ); ?></h2>
      <?php self::render_request_message(); ?>

      <?php if ( $order_number && $order_key ) : ?>
        <?php $order = Eggplant_DB::get_ticket_order_by_public_key( $order_number, $order_key ); ?>
        <?php if ( $order ) : ?>
          <div class="eg-ticket-receipt">
            <h3><?php esc_html_e( 'Order Confirmation', 'eggplant' ); ?> #<?php echo esc_html( $order['order_number'] ); ?></h3>
            <p><?php echo esc_html( sprintf( __( 'Total Paid: %s', 'eggplant' ), self::format_money( $order['net_amount'] ) ) ); ?></p>
            <p>
              <a class="eg-btn eg-btn--primary" href="<?php echo esc_url( self::build_order_url( $order['order_number'], $order['order_access_key'], true ) ); ?>"><?php esc_html_e( 'Print Tickets', 'eggplant' ); ?></a>
            </p>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ( $order_number && $order_key && isset( $_GET['eggplant_print'] ) ) : ?>
        <?php self::render_printable_tickets( $order_number, $order_key ); ?>
      <?php endif; ?>

      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="eg-ticket-form">
        <input type="hidden" name="action" value="eggplant_ticket_purchase">
        <input type="hidden" name="redirect_to" value="<?php echo esc_url( self::get_current_url() ); ?>">
        <?php wp_nonce_field( 'eggplant_ticket_purchase', 'eggplant_ticket_purchase_nonce' ); ?>

        <div class="eg-form-row">
          <label for="eg-ticket-event"><?php esc_html_e( 'Event', 'eggplant' ); ?></label>
          <select id="eg-ticket-event" name="event_id" required>
            <option value=""><?php esc_html_e( 'Select event', 'eggplant' ); ?></option>
            <?php foreach ( $events as $event ) : ?>
              <option value="<?php echo esc_attr( $event['id'] ); ?>" <?php selected( $selected_event_id, intval( $event['id'] ) ); ?>>
                <?php echo esc_html( $event['title'] ); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="eg-form-row">
          <label for="eg-ticket-type"><?php esc_html_e( 'Ticket Type', 'eggplant' ); ?></label>
          <select id="eg-ticket-type" name="ticket_type_id" required>
            <option value=""><?php esc_html_e( 'Select ticket type', 'eggplant' ); ?></option>
            <?php foreach ( Eggplant_DB::get_all_ticket_types() as $type ) : ?>
              <option value="<?php echo esc_attr( $type['id'] ); ?>" data-event="<?php echo esc_attr( $type['event_id'] ); ?>" <?php selected( $selected_type_id, intval( $type['id'] ) ); ?>>
                <?php
                echo esc_html(
                  $type['event_title'] . ' — ' . $type['ticket_name'] . ' (' . self::format_money( $type['ticket_price'] ) . ')'
                );
                ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="eg-form-row">
          <label for="eg-ticket-qty"><?php esc_html_e( 'Quantity', 'eggplant' ); ?></label>
          <input id="eg-ticket-qty" type="number" name="quantity" min="1" max="20" value="1" required>
        </div>

        <div class="eg-form-row">
          <label for="eg-ticket-name"><?php esc_html_e( 'Buyer Name', 'eggplant' ); ?></label>
          <input id="eg-ticket-name" type="text" name="buyer_name" required>
        </div>

        <div class="eg-form-row">
          <label for="eg-ticket-email"><?php esc_html_e( 'Buyer Email', 'eggplant' ); ?></label>
          <input id="eg-ticket-email" type="email" name="buyer_email" required>
        </div>

        <div class="eg-form-row">
          <label for="eg-ticket-code"><?php esc_html_e( 'Promo Code', 'eggplant' ); ?></label>
          <input id="eg-ticket-code" type="text" name="promo_code" placeholder="PROMO10">
        </div>

        <button type="submit" class="eg-btn eg-btn--primary"><?php esc_html_e( 'Purchase Tickets', 'eggplant' ); ?></button>
      </form>
    </div>
    <script>
      (function(){
        const eventSelect = document.getElementById('eg-ticket-event');
        const typeSelect = document.getElementById('eg-ticket-type');
        if (!eventSelect || !typeSelect) return;
        function filterTypes(){
          const eventId = eventSelect.value;
          Array.from(typeSelect.options).forEach((opt, idx) => {
            if (idx === 0) return;
            const visible = !eventId || opt.getAttribute('data-event') === eventId;
            opt.hidden = !visible;
            if (!visible && opt.selected) typeSelect.value = '';
          });
        }
        eventSelect.addEventListener('change', filterTypes);
        filterTypes();
      })();
    </script>
    <?php
    return (string) ob_get_clean();
  }

  public static function render_ticket_scanner_shortcode(): string {
    if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
      return '<div class="eg-ticketing"><p>' . esc_html__( 'You must be an authorized staff member to use the scanner.', 'eggplant' ) . '</p></div>';
    }

    ob_start();
    ?>
    <div class="eg-ticketing eg-ticket-scanner">
      <h2><?php esc_html_e( 'Ticket Scanner', 'eggplant' ); ?></h2>
      <?php self::render_request_message(); ?>
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="eg-ticket-form">
        <input type="hidden" name="action" value="eggplant_ticket_scan">
        <input type="hidden" name="redirect_to" value="<?php echo esc_url( self::get_current_url() ); ?>">
        <?php wp_nonce_field( 'eggplant_ticket_scan', 'eggplant_ticket_scan_nonce' ); ?>

        <div class="eg-form-row">
          <label for="eg-scan-barcode"><?php esc_html_e( 'Barcode / Ticket Code', 'eggplant' ); ?></label>
          <input id="eg-scan-barcode" type="text" name="barcode_value" required autofocus>
        </div>

        <button type="submit" class="eg-btn eg-btn--primary"><?php esc_html_e( 'Validate Ticket', 'eggplant' ); ?></button>
      </form>
    </div>
    <?php
    return (string) ob_get_clean();
  }

  public static function handle_ticket_purchase(): void {
    if ( ! isset( $_POST['eggplant_ticket_purchase_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eggplant_ticket_purchase_nonce'] ) ), 'eggplant_ticket_purchase' ) ) {
      self::redirect_with_message( __( 'Invalid purchase request.', 'eggplant' ), 'error' );
      return;
    }

    $event_id      = intval( $_POST['event_id'] ?? 0 );
    $ticket_type_id= intval( $_POST['ticket_type_id'] ?? 0 );
    $quantity      = max( 1, min( 20, intval( $_POST['quantity'] ?? 1 ) ) );
    $buyer_name    = sanitize_text_field( wp_unslash( $_POST['buyer_name'] ?? '' ) );
    $buyer_email   = sanitize_email( wp_unslash( $_POST['buyer_email'] ?? '' ) );
    $promo_code    = strtoupper( sanitize_text_field( wp_unslash( $_POST['promo_code'] ?? '' ) ) );

    if ( empty( $event_id ) || empty( $ticket_type_id ) || empty( $buyer_name ) || ! is_email( $buyer_email ) ) {
      self::redirect_with_message( __( 'Please complete all required fields.', 'eggplant' ), 'error' );
      return;
    }

    $result = Eggplant_DB::create_ticket_order(
      $event_id,
      $ticket_type_id,
      $quantity,
      $buyer_name,
      $buyer_email,
      $promo_code
    );

    if ( empty( $result['success'] ) ) {
      self::redirect_with_message( (string) ( $result['message'] ?? __( 'Ticket purchase failed.', 'eggplant' ) ), 'error' );
      return;
    }

    $url = add_query_arg(
      array(
        'eggplant_msg'   => rawurlencode( __( 'Purchase completed. Your tickets are ready to print.', 'eggplant' ) ),
        'eggplant_state' => 'success',
        'eggplant_order' => rawurlencode( $result['order_number'] ),
        'eggplant_key'   => rawurlencode( $result['order_access_key'] ),
      ),
      self::get_redirect_url()
    );

    wp_safe_redirect( $url );
    exit;
  }

  public static function handle_ticket_scan(): void {
    if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
      self::redirect_with_message( __( 'Unauthorized scanner access.', 'eggplant' ), 'error' );
      return;
    }

    if ( ! isset( $_POST['eggplant_ticket_scan_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eggplant_ticket_scan_nonce'] ) ), 'eggplant_ticket_scan' ) ) {
      self::redirect_with_message( __( 'Invalid scan request.', 'eggplant' ), 'error' );
      return;
    }

    $barcode_value = sanitize_text_field( wp_unslash( $_POST['barcode_value'] ?? '' ) );
    if ( '' === $barcode_value ) {
      self::redirect_with_message( __( 'Please enter a barcode/ticket code.', 'eggplant' ), 'error' );
      return;
    }

    $result = Eggplant_DB::scan_ticket_barcode( $barcode_value, get_current_user_id() );
    $state = ! empty( $result['success'] ) ? 'success' : 'error';
    $message = (string) ( $result['message'] ?? __( 'Ticket scan failed.', 'eggplant' ) );
    self::redirect_with_message( $message, $state );
  }

  public static function handle_save_ticket_type(): void {
    self::assert_admin_post( 'eggplant_ticket_save_type_nonce', 'eggplant_ticket_save_type' );

    $data = array(
      'event_id'        => intval( $_POST['event_id'] ?? 0 ),
      'ticket_name'     => sanitize_text_field( wp_unslash( $_POST['ticket_name'] ?? '' ) ),
      'ticket_price'    => floatval( $_POST['ticket_price'] ?? 0 ),
      'quantity_total'  => max( 1, intval( $_POST['quantity_total'] ?? 1 ) ),
      'active'          => ! empty( $_POST['active'] ) ? 1 : 0,
    );

    if ( empty( $data['event_id'] ) || empty( $data['ticket_name'] ) || $data['ticket_price'] < 0 ) {
      self::redirect_with_message( __( 'Please provide valid ticket type data.', 'eggplant' ), 'error' );
      return;
    }

    $id = Eggplant_DB::insert_ticket_type( $data );
    $id ? self::redirect_with_message( __( 'Ticket type saved.', 'eggplant' ), 'success' ) : self::redirect_with_message( __( 'Could not save ticket type.', 'eggplant' ), 'error' );
  }

  public static function handle_save_discount_code(): void {
    self::assert_admin_post( 'eggplant_ticket_save_discount_nonce', 'eggplant_ticket_save_discount' );

    $data = array(
      'code'           => strtoupper( sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) ) ),
      'event_id'       => intval( $_POST['event_id'] ?? 0 ),
      'discount_type'  => sanitize_key( wp_unslash( $_POST['discount_type'] ?? 'percent' ) ),
      'discount_value' => floatval( $_POST['discount_value'] ?? 0 ),
      'max_uses'       => max( 0, intval( $_POST['max_uses'] ?? 0 ) ),
      'active'         => ! empty( $_POST['active'] ) ? 1 : 0,
      'start_date'     => sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) ),
      'end_date'       => sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) ),
    );

    if ( '' === $data['code'] || $data['discount_value'] <= 0 || ! in_array( $data['discount_type'], array( 'percent', 'fixed' ), true ) ) {
      self::redirect_with_message( __( 'Please provide valid discount code data.', 'eggplant' ), 'error' );
      return;
    }

    if ( Eggplant_DB::discount_code_exists( $data['code'] ) ) {
      self::redirect_with_message( __( 'This discount code already exists. Please choose a unique code.', 'eggplant' ), 'error' );
      return;
    }

    $id = Eggplant_DB::insert_discount_code( $data );
    $id ? self::redirect_with_message( __( 'Discount code saved.', 'eggplant' ), 'success' ) : self::redirect_with_message( __( 'Could not save discount code.', 'eggplant' ), 'error' );
  }

  public static function handle_save_settlement(): void {
    self::assert_admin_post( 'eggplant_ticket_save_settlement_nonce', 'eggplant_ticket_save_settlement' );

    $data = array(
      'event_id'                  => intval( $_POST['event_id'] ?? 0 ),
      'adjustment_amount'         => floatval( $_POST['adjustment_amount'] ?? 0 ),
      'adjustment_note'           => sanitize_text_field( wp_unslash( $_POST['adjustment_note'] ?? '' ) ),
      'organizer_split_override'  => '' !== (string) ( $_POST['organizer_split_override'] ?? '' ) ? floatval( $_POST['organizer_split_override'] ) : null,
      'created_by'                => get_current_user_id(),
    );

    if ( empty( $data['event_id'] ) ) {
      self::redirect_with_message( __( 'Please select an event for settlement.', 'eggplant' ), 'error' );
      return;
    }

    $id = Eggplant_DB::insert_event_settlement( $data );
    $id ? self::redirect_with_message( __( 'Settlement adjustment saved.', 'eggplant' ), 'success' ) : self::redirect_with_message( __( 'Could not save settlement adjustment.', 'eggplant' ), 'error' );
  }

  public static function handle_save_event_settings(): void {
    self::assert_admin_post( 'eggplant_ticket_save_event_settings_nonce', 'eggplant_ticket_save_event_settings' );

    $event_id = intval( $_POST['event_id'] ?? 0 );
    $data = array(
      'organizer_split_percent' => max( 0, min( 100, floatval( $_POST['organizer_split_percent'] ?? 0 ) ) ),
      'box_office_slug'         => sanitize_title( wp_unslash( $_POST['box_office_slug'] ?? '' ) ),
      'scanner_slug'            => sanitize_title( wp_unslash( $_POST['scanner_slug'] ?? '' ) ),
    );

    if ( ! $event_id ) {
      self::redirect_with_message( __( 'Invalid event.', 'eggplant' ), 'error' );
      return;
    }

    $ok = Eggplant_DB::update_event_ticketing_settings( $event_id, $data );
    $ok ? self::redirect_with_message( __( 'Event ticketing settings saved.', 'eggplant' ), 'success' ) : self::redirect_with_message( __( 'Could not save event ticketing settings.', 'eggplant' ), 'error' );
  }

  public static function page_dashboard(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
      return;
    }

    $summary = Eggplant_DB::get_ticketing_totals();
    $rows = Eggplant_DB::get_ticketing_event_accounting_rows();
    ?>
    <div class="wrap eg-admin">
      <h1><?php esc_html_e( 'Ticketing Dashboard', 'eggplant' ); ?></h1>
      <?php self::render_request_message(); ?>

      <div class="eg-dash-cards">
        <div class="eg-dash-card"><span class="eg-dash-number"><?php echo esc_html( intval( $summary['orders_count'] ) ); ?></span><span class="eg-dash-label"><?php esc_html_e( 'Orders', 'eggplant' ); ?></span></div>
        <div class="eg-dash-card"><span class="eg-dash-number"><?php echo esc_html( intval( $summary['tickets_count'] ) ); ?></span><span class="eg-dash-label"><?php esc_html_e( 'Tickets Issued', 'eggplant' ); ?></span></div>
        <div class="eg-dash-card"><span class="eg-dash-number"><?php echo esc_html( intval( $summary['used_tickets'] ) ); ?></span><span class="eg-dash-label"><?php esc_html_e( 'Tickets Scanned', 'eggplant' ); ?></span></div>
        <div class="eg-dash-card"><span class="eg-dash-number"><?php echo esc_html( self::format_money( $summary['net_total'] ) ); ?></span><span class="eg-dash-label"><?php esc_html_e( 'Net Sales', 'eggplant' ); ?></span></div>
      </div>

      <div class="eg-card">
        <h2><?php esc_html_e( 'Event Accounting', 'eggplant' ); ?></h2>
        <?php if ( empty( $rows ) ) : ?>
          <p><?php esc_html_e( 'No ticket sales yet.', 'eggplant' ); ?></p>
        <?php else : ?>
          <div class="eg-table-wrap">
            <table class="widefat striped">
              <thead>
                <tr>
                  <th><?php esc_html_e( 'Event', 'eggplant' ); ?></th>
                  <th><?php esc_html_e( 'Gross', 'eggplant' ); ?></th>
                  <th><?php esc_html_e( 'Discounts', 'eggplant' ); ?></th>
                  <th><?php esc_html_e( 'Net', 'eggplant' ); ?></th>
                  <th><?php esc_html_e( 'Organizer %', 'eggplant' ); ?></th>
                  <th><?php esc_html_e( 'Organizer Share', 'eggplant' ); ?></th>
                  <th><?php esc_html_e( 'Venue Share', 'eggplant' ); ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ( $rows as $row ) : ?>
                  <tr>
                    <td><?php echo esc_html( $row['event_title'] ); ?></td>
                    <td><?php echo esc_html( self::format_money( $row['gross_amount'] ) ); ?></td>
                    <td><?php echo esc_html( self::format_money( $row['discount_amount'] ) ); ?></td>
                    <td><?php echo esc_html( self::format_money( $row['net_amount'] ) ); ?></td>
                    <td><?php echo esc_html( number_format( (float) $row['organizer_percent'], 2 ) ); ?>%</td>
                    <td><?php echo esc_html( self::format_money( $row['organizer_share'] ) ); ?></td>
                    <td><?php echo esc_html( self::format_money( $row['venue_share'] ) ); ?></td>
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

  public static function page_events_pricing(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
      return;
    }

    $events = Eggplant_DB::get_all_events();
    $types = Eggplant_DB::get_all_ticket_types();
    ?>
    <div class="wrap eg-admin">
      <h1><?php esc_html_e( 'Ticketing Events & Pricing', 'eggplant' ); ?></h1>
      <?php self::render_request_message(); ?>

      <div class="eg-card">
        <h2><?php esc_html_e( 'Per-Event Ticketing Settings', 'eggplant' ); ?></h2>
        <div class="eg-table-wrap">
          <table class="widefat striped">
            <thead>
              <tr>
                <th><?php esc_html_e( 'Event', 'eggplant' ); ?></th>
                <th><?php esc_html_e( 'Organizer Split %', 'eggplant' ); ?></th>
                <th><?php esc_html_e( 'Box Office Slug', 'eggplant' ); ?></th>
                <th><?php esc_html_e( 'Scanner Slug', 'eggplant' ); ?></th>
                <th><?php esc_html_e( 'Action', 'eggplant' ); ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ( $events as $event ) : ?>
                <?php $form_id = 'eg-ticket-event-' . intval( $event['id'] ); ?>
                <tr>
                  <td>
                    <?php echo esc_html( $event['title'] ); ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="<?php echo esc_attr( $form_id ); ?>">
                      <input type="hidden" name="action" value="eggplant_ticket_save_event_settings">
                      <input type="hidden" name="event_id" value="<?php echo esc_attr( $event['id'] ); ?>">
                      <?php wp_nonce_field( 'eggplant_ticket_save_event_settings', 'eggplant_ticket_save_event_settings_nonce' ); ?>
                    </form>
                  </td>
                  <td><input type="number" name="organizer_split_percent" step="0.01" min="0" max="100" value="<?php echo esc_attr( $event['organizer_split_percent'] ?? 0 ); ?>" form="<?php echo esc_attr( $form_id ); ?>"></td>
                  <td><input type="text" name="box_office_slug" value="<?php echo esc_attr( $event['box_office_slug'] ?? '' ); ?>" form="<?php echo esc_attr( $form_id ); ?>"></td>
                  <td><input type="text" name="scanner_slug" value="<?php echo esc_attr( $event['scanner_slug'] ?? '' ); ?>" form="<?php echo esc_attr( $form_id ); ?>"></td>
                  <td><button type="submit" class="button button-primary" form="<?php echo esc_attr( $form_id ); ?>"><?php esc_html_e( 'Save', 'eggplant' ); ?></button></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="eg-card">
        <h2><?php esc_html_e( 'Add Ticket Type', 'eggplant' ); ?></h2>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
          <input type="hidden" name="action" value="eggplant_ticket_save_type">
          <?php wp_nonce_field( 'eggplant_ticket_save_type', 'eggplant_ticket_save_type_nonce' ); ?>
          <div class="eg-form-row">
            <label><?php esc_html_e( 'Event', 'eggplant' ); ?></label>
            <select name="event_id" required>
              <option value=""><?php esc_html_e( 'Select event', 'eggplant' ); ?></option>
              <?php foreach ( $events as $event ) : ?>
                <option value="<?php echo esc_attr( $event['id'] ); ?>"><?php echo esc_html( $event['title'] ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="eg-form-row"><label><?php esc_html_e( 'Ticket Name', 'eggplant' ); ?></label><input type="text" name="ticket_name" required></div>
          <div class="eg-form-row"><label><?php esc_html_e( 'Price', 'eggplant' ); ?></label><input type="number" name="ticket_price" step="0.01" min="0" required></div>
          <div class="eg-form-row"><label><?php esc_html_e( 'Quantity', 'eggplant' ); ?></label><input type="number" name="quantity_total" min="1" required></div>
          <div class="eg-form-row"><label><?php esc_html_e( 'Active', 'eggplant' ); ?></label><input type="checkbox" name="active" value="1" checked></div>
          <button class="button button-primary" type="submit"><?php esc_html_e( 'Save Ticket Type', 'eggplant' ); ?></button>
        </form>
      </div>

      <div class="eg-card">
        <h2><?php esc_html_e( 'Ticket Types', 'eggplant' ); ?></h2>
        <?php if ( empty( $types ) ) : ?>
          <p><?php esc_html_e( 'No ticket types yet.', 'eggplant' ); ?></p>
        <?php else : ?>
          <div class="eg-table-wrap">
            <table class="widefat striped">
              <thead><tr><th><?php esc_html_e( 'Event', 'eggplant' ); ?></th><th><?php esc_html_e( 'Type', 'eggplant' ); ?></th><th><?php esc_html_e( 'Price', 'eggplant' ); ?></th><th><?php esc_html_e( 'Sold / Total', 'eggplant' ); ?></th><th><?php esc_html_e( 'Active', 'eggplant' ); ?></th></tr></thead>
              <tbody>
                <?php foreach ( $types as $type ) : ?>
                  <tr>
                    <td><?php echo esc_html( $type['event_title'] ); ?></td>
                    <td><?php echo esc_html( $type['ticket_name'] ); ?></td>
                    <td><?php echo esc_html( self::format_money( $type['ticket_price'] ) ); ?></td>
                    <td><?php echo esc_html( intval( $type['quantity_sold'] ) . ' / ' . intval( $type['quantity_total'] ) ); ?></td>
                    <td><?php echo intval( $type['active'] ) ? '✅' : '❌'; ?></td>
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

  public static function page_discounts(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
      return;
    }

    $events = Eggplant_DB::get_all_events();
    $codes = Eggplant_DB::get_discount_codes();
    ?>
    <div class="wrap eg-admin">
      <h1><?php esc_html_e( 'Ticket Discount Codes', 'eggplant' ); ?></h1>
      <?php self::render_request_message(); ?>

      <div class="eg-card">
        <h2><?php esc_html_e( 'Add Discount Code', 'eggplant' ); ?></h2>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
          <input type="hidden" name="action" value="eggplant_ticket_save_discount">
          <?php wp_nonce_field( 'eggplant_ticket_save_discount', 'eggplant_ticket_save_discount_nonce' ); ?>
          <div class="eg-form-row"><label><?php esc_html_e( 'Code', 'eggplant' ); ?></label><input type="text" name="code" required placeholder="PROMO10"></div>
          <div class="eg-form-row"><label><?php esc_html_e( 'Event Scope', 'eggplant' ); ?></label>
            <select name="event_id"><option value="0"><?php esc_html_e( 'All events', 'eggplant' ); ?></option><?php foreach ( $events as $event ) : ?><option value="<?php echo esc_attr( $event['id'] ); ?>"><?php echo esc_html( $event['title'] ); ?></option><?php endforeach; ?></select>
          </div>
          <div class="eg-form-row"><label><?php esc_html_e( 'Type', 'eggplant' ); ?></label><select name="discount_type"><option value="percent">%</option><option value="fixed"><?php esc_html_e( 'Fixed', 'eggplant' ); ?></option></select></div>
          <div class="eg-form-row"><label><?php esc_html_e( 'Value', 'eggplant' ); ?></label><input type="number" step="0.01" min="0.01" name="discount_value" required></div>
          <div class="eg-form-row"><label><?php esc_html_e( 'Max Uses', 'eggplant' ); ?></label><input type="number" min="0" name="max_uses" value="0"><span class="description"><?php esc_html_e( '0 = unlimited', 'eggplant' ); ?></span></div>
          <div class="eg-form-row"><label><?php esc_html_e( 'Start Date', 'eggplant' ); ?></label><input type="date" name="start_date"></div>
          <div class="eg-form-row"><label><?php esc_html_e( 'End Date', 'eggplant' ); ?></label><input type="date" name="end_date"></div>
          <div class="eg-form-row"><label><?php esc_html_e( 'Active', 'eggplant' ); ?></label><input type="checkbox" name="active" value="1" checked></div>
          <button class="button button-primary" type="submit"><?php esc_html_e( 'Save Discount Code', 'eggplant' ); ?></button>
        </form>
      </div>

      <div class="eg-card">
        <h2><?php esc_html_e( 'Configured Codes', 'eggplant' ); ?></h2>
        <div class="eg-table-wrap">
          <table class="widefat striped">
            <thead><tr><th><?php esc_html_e( 'Code', 'eggplant' ); ?></th><th><?php esc_html_e( 'Scope', 'eggplant' ); ?></th><th><?php esc_html_e( 'Discount', 'eggplant' ); ?></th><th><?php esc_html_e( 'Uses', 'eggplant' ); ?></th><th><?php esc_html_e( 'Active Window', 'eggplant' ); ?></th><th><?php esc_html_e( 'Status', 'eggplant' ); ?></th></tr></thead>
            <tbody>
              <?php foreach ( $codes as $code ) : ?>
                <tr>
                  <td><?php echo esc_html( $code['code'] ); ?></td>
                  <td><?php echo esc_html( $code['event_title'] ?: __( 'All events', 'eggplant' ) ); ?></td>
                  <td><?php echo esc_html( 'percent' === $code['discount_type'] ? number_format( (float) $code['discount_value'], 2 ) . '%' : self::format_money( $code['discount_value'] ) ); ?></td>
                  <td><?php echo esc_html( intval( $code['used_count'] ) . ' / ' . ( intval( $code['max_uses'] ) ? intval( $code['max_uses'] ) : '∞' ) ); ?></td>
                  <td><?php echo esc_html( ( $code['start_date'] ?: '—' ) . ' → ' . ( $code['end_date'] ?: '—' ) ); ?></td>
                  <td><?php echo intval( $code['active'] ) ? '✅' : '❌'; ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php
  }

  public static function page_orders(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
      return;
    }

    $orders = Eggplant_DB::get_ticket_orders();
    $selected = sanitize_text_field( wp_unslash( $_GET['order_number'] ?? '' ) );
    $tickets = $selected ? Eggplant_DB::get_tickets_for_order_number( $selected ) : array();
    ?>
    <div class="wrap eg-admin">
      <h1><?php esc_html_e( 'Orders & Tickets', 'eggplant' ); ?></h1>
      <?php self::render_request_message(); ?>

      <div class="eg-card">
        <h2><?php esc_html_e( 'Orders', 'eggplant' ); ?></h2>
        <div class="eg-table-wrap">
          <table class="widefat striped">
            <thead><tr><th><?php esc_html_e( 'Order #', 'eggplant' ); ?></th><th><?php esc_html_e( 'Event', 'eggplant' ); ?></th><th><?php esc_html_e( 'Buyer', 'eggplant' ); ?></th><th><?php esc_html_e( 'Gross', 'eggplant' ); ?></th><th><?php esc_html_e( 'Discount', 'eggplant' ); ?></th><th><?php esc_html_e( 'Net', 'eggplant' ); ?></th><th><?php esc_html_e( 'Date', 'eggplant' ); ?></th><th><?php esc_html_e( 'Action', 'eggplant' ); ?></th></tr></thead>
            <tbody>
              <?php foreach ( $orders as $order ) : ?>
                <tr>
                  <td><?php echo esc_html( $order['order_number'] ); ?></td>
                  <td><?php echo esc_html( $order['event_title'] ); ?></td>
                  <td><?php echo esc_html( $order['buyer_name'] . ' (' . $order['buyer_email'] . ')' ); ?></td>
                  <td><?php echo esc_html( self::format_money( $order['gross_amount'] ) ); ?></td>
                  <td><?php echo esc_html( self::format_money( $order['discount_amount'] ) ); ?></td>
                  <td><?php echo esc_html( self::format_money( $order['net_amount'] ) ); ?></td>
                  <td><?php echo esc_html( $order['created_at'] ); ?></td>
                  <td><a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'eggplant-ticketing-orders', 'order_number' => $order['order_number'] ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'View Tickets', 'eggplant' ); ?></a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php if ( $selected ) : ?>
      <div class="eg-card">
        <h2><?php echo esc_html( sprintf( __( 'Tickets for order %s', 'eggplant' ), $selected ) ); ?></h2>
        <?php if ( empty( $tickets ) ) : ?>
          <p><?php esc_html_e( 'No tickets found.', 'eggplant' ); ?></p>
        <?php else : ?>
          <div class="eg-table-wrap">
            <table class="widefat striped">
              <thead><tr><th><?php esc_html_e( 'Ticket', 'eggplant' ); ?></th><th><?php esc_html_e( 'Barcode', 'eggplant' ); ?></th><th><?php esc_html_e( 'Status', 'eggplant' ); ?></th><th><?php esc_html_e( 'Scanned At', 'eggplant' ); ?></th></tr></thead>
              <tbody>
                <?php foreach ( $tickets as $ticket ) : ?>
                  <tr>
                    <td><?php echo esc_html( $ticket['ticket_code'] ); ?></td>
                    <td><code><?php echo esc_html( $ticket['barcode_value'] ); ?></code></td>
                    <td><?php echo esc_html( ucfirst( $ticket['ticket_status'] ) ); ?></td>
                    <td><?php echo esc_html( $ticket['scanned_at'] ?: '—' ); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php
  }

  public static function page_scans(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
      return;
    }
    $scans = Eggplant_DB::get_ticket_scan_logs();
    ?>
    <div class="wrap eg-admin">
      <h1><?php esc_html_e( 'Ticket Scans', 'eggplant' ); ?></h1>
      <div class="eg-card">
        <h2><?php esc_html_e( 'Recent Scan Activity', 'eggplant' ); ?></h2>
        <div class="eg-table-wrap">
          <table class="widefat striped">
            <thead><tr><th><?php esc_html_e( 'Date', 'eggplant' ); ?></th><th><?php esc_html_e( 'Event', 'eggplant' ); ?></th><th><?php esc_html_e( 'Barcode', 'eggplant' ); ?></th><th><?php esc_html_e( 'Status', 'eggplant' ); ?></th><th><?php esc_html_e( 'Message', 'eggplant' ); ?></th><th><?php esc_html_e( 'User', 'eggplant' ); ?></th></tr></thead>
            <tbody>
              <?php foreach ( $scans as $scan ) : ?>
                <tr>
                  <td><?php echo esc_html( $scan['created_at'] ); ?></td>
                  <td><?php echo esc_html( $scan['event_title'] ?: '—' ); ?></td>
                  <td><code><?php echo esc_html( $scan['barcode_value'] ); ?></code></td>
                  <td><?php echo esc_html( ucfirst( $scan['scan_status'] ) ); ?></td>
                  <td><?php echo esc_html( $scan['scan_message'] ); ?></td>
                  <td><?php echo esc_html( $scan['scanned_by_name'] ?: '—' ); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php
  }

  public static function page_settlements(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
      return;
    }

    $events = Eggplant_DB::get_all_events();
    $settlements = Eggplant_DB::get_event_settlements();
    ?>
    <div class="wrap eg-admin">
      <h1><?php esc_html_e( 'Ticket Settlements', 'eggplant' ); ?></h1>
      <?php self::render_request_message(); ?>

      <div class="eg-card">
        <h2><?php esc_html_e( 'Add Settlement Adjustment', 'eggplant' ); ?></h2>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
          <input type="hidden" name="action" value="eggplant_ticket_save_settlement">
          <?php wp_nonce_field( 'eggplant_ticket_save_settlement', 'eggplant_ticket_save_settlement_nonce' ); ?>

          <div class="eg-form-row"><label><?php esc_html_e( 'Event', 'eggplant' ); ?></label><select name="event_id" required><option value=""><?php esc_html_e( 'Select event', 'eggplant' ); ?></option><?php foreach ( $events as $event ) : ?><option value="<?php echo esc_attr( $event['id'] ); ?>"><?php echo esc_html( $event['title'] ); ?></option><?php endforeach; ?></select></div>
          <div class="eg-form-row"><label><?php esc_html_e( 'Adjustment Amount', 'eggplant' ); ?></label><input type="number" step="0.01" name="adjustment_amount" value="0"></div>
          <div class="eg-form-row"><label><?php esc_html_e( 'Organizer % Override', 'eggplant' ); ?></label><input type="number" step="0.01" min="0" max="100" name="organizer_split_override" placeholder="Optional"></div>
          <div class="eg-form-row"><label><?php esc_html_e( 'Note', 'eggplant' ); ?></label><input type="text" name="adjustment_note" placeholder="Optional note"></div>

          <button class="button button-primary" type="submit"><?php esc_html_e( 'Save Adjustment', 'eggplant' ); ?></button>
        </form>
      </div>

      <div class="eg-card">
        <h2><?php esc_html_e( 'Recorded Settlements', 'eggplant' ); ?></h2>
        <div class="eg-table-wrap">
          <table class="widefat striped">
            <thead><tr><th><?php esc_html_e( 'Date', 'eggplant' ); ?></th><th><?php esc_html_e( 'Event', 'eggplant' ); ?></th><th><?php esc_html_e( 'Adjustment', 'eggplant' ); ?></th><th><?php esc_html_e( 'Override %', 'eggplant' ); ?></th><th><?php esc_html_e( 'Note', 'eggplant' ); ?></th><th><?php esc_html_e( 'By', 'eggplant' ); ?></th></tr></thead>
            <tbody>
              <?php foreach ( $settlements as $row ) : ?>
                <tr>
                  <td><?php echo esc_html( $row['created_at'] ); ?></td>
                  <td><?php echo esc_html( $row['event_title'] ); ?></td>
                  <td><?php echo esc_html( self::format_money( $row['adjustment_amount'] ) ); ?></td>
                  <td><?php echo null !== $row['organizer_split_override'] ? esc_html( number_format( (float) $row['organizer_split_override'], 2 ) . '%' ) : '—'; ?></td>
                  <td><?php echo esc_html( $row['adjustment_note'] ); ?></td>
                  <td><?php echo esc_html( $row['created_by_name'] ?: '—' ); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php
  }

  private static function assert_admin_post( string $nonce_field, string $nonce_action ): void {
    if ( ! current_user_can( 'manage_options' ) ) {
      self::redirect_with_message( __( 'Unauthorized action.', 'eggplant' ), 'error' );
      return;
    }

    if ( ! isset( $_POST[ $nonce_field ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ), $nonce_action ) ) {
      self::redirect_with_message( __( 'Invalid request.', 'eggplant' ), 'error' );
      return;
    }
  }

  private static function render_request_message(): void {
    $msg = sanitize_text_field( wp_unslash( $_GET['eggplant_msg'] ?? '' ) );
    if ( '' === $msg ) {
      return;
    }
    $state = sanitize_key( wp_unslash( $_GET['eggplant_state'] ?? 'success' ) );
    $class = 'error' === $state ? 'notice notice-error' : 'notice notice-success';
    echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( rawurldecode( $msg ) ) . '</p></div>';
  }

  private static function get_current_url(): string {
    $uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
    return esc_url_raw( home_url( $uri ) );
  }

  private static function get_redirect_url(): string {
    $redirect_to = esc_url_raw( wp_unslash( $_POST['redirect_to'] ?? '' ) );
    if ( empty( $redirect_to ) ) {
      $redirect_to = wp_get_referer() ?: home_url( '/' );
    }
    return $redirect_to;
  }

  private static function redirect_with_message( string $message, string $state = 'success' ): void {
    $url = add_query_arg(
      array(
        'eggplant_msg'   => rawurlencode( $message ),
        'eggplant_state' => sanitize_key( $state ),
      ),
      self::get_redirect_url()
    );
    wp_safe_redirect( $url );
    exit;
  }

  private static function format_money( $amount ): string {
    return '$' . number_format( (float) $amount, 2 );
  }

  private static function build_order_url( string $order_number, string $order_key, bool $print ): string {
    $args = array(
      'eggplant_order' => rawurlencode( $order_number ),
      'eggplant_key'   => rawurlencode( $order_key ),
    );
    if ( $print ) {
      $args['eggplant_print'] = 1;
    }
    return add_query_arg( $args, self::get_current_url() );
  }

  private static function render_printable_tickets( string $order_number, string $order_key ): void {
    $order = Eggplant_DB::get_ticket_order_by_public_key( $order_number, $order_key );
    if ( ! $order ) {
      return;
    }

    $tickets = Eggplant_DB::get_tickets_for_order_number( $order_number );
    if ( empty( $tickets ) ) {
      return;
    }
    ?>
    <div class="eg-ticket-print-wrap">
      <h3><?php esc_html_e( 'Printable Tickets', 'eggplant' ); ?></h3>
      <?php foreach ( $tickets as $ticket ) : ?>
        <div class="eg-print-ticket">
          <h4><?php echo esc_html( $ticket['event_title'] ); ?></h4>
          <p><?php echo esc_html( $ticket['ticket_name'] ); ?> — <?php echo esc_html( $ticket['ticket_code'] ); ?></p>
          <p><strong><?php esc_html_e( 'Barcode:', 'eggplant' ); ?></strong> <code><?php echo esc_html( $ticket['barcode_value'] ); ?></code></p>
          <p><?php esc_html_e( 'Present this code at entry. Tickets are valid for one scan only.', 'eggplant' ); ?></p>
        </div>
      <?php endforeach; ?>
      <p><button type="button" class="eg-btn" onclick="window.print()"><?php esc_html_e( 'Print Now', 'eggplant' ); ?></button></p>
    </div>
    <?php
  }
}
