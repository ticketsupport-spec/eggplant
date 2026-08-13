<?php

/**
 * Point of Sale (POS) system – DB helpers, AJAX handlers, and public shortcode.
 *
 * Shortcode: [eggplant_pos]
 *
 * @since 1.4.0
 * @package Eggplant
 */

class Eggplant_POS {

  // ------------------------------------------------------------------ init

  public static function init(): void {
    add_shortcode( 'eggplant_pos', array( __CLASS__, 'render_pos_shortcode' ) );

    // AJAX for public POS terminal.
    // Item listing is open to any logged-in user; sale processing requires the
    // user to be logged in (any subscriber-level capability) to prevent
    // unauthenticated visitors from writing sales or decrementing stock.
    add_action( 'wp_ajax_eggplant_pos_get_items',    array( __CLASS__, 'ajax_get_items' ) );
    add_action( 'wp_ajax_eggplant_pos_process_sale', array( __CLASS__, 'ajax_process_sale' ) );
    add_action( 'wp_ajax_nopriv_eggplant_pos_get_items', array( __CLASS__, 'ajax_get_items' ) );

    // AJAX for admin POS pages.
    add_action( 'wp_ajax_eggplant_pos_save_item',   array( __CLASS__, 'ajax_save_item' ) );
    add_action( 'wp_ajax_eggplant_pos_delete_item', array( __CLASS__, 'ajax_delete_item' ) );
    add_action( 'wp_ajax_eggplant_pos_get_item',    array( __CLASS__, 'ajax_get_item' ) );
    add_action( 'wp_ajax_eggplant_pos_adjust_stock', array( __CLASS__, 'ajax_adjust_stock' ) );
    add_action( 'wp_ajax_eggplant_pos_get_sales_report', array( __CLASS__, 'ajax_get_sales_report' ) );
  }

  // ------------------------------------------------------------------ DB: items

  /**
   * Insert a new POS item.
   *
   * @param array<string,mixed> $data  Keys: name, sku, price, cost_price, tax_rate, category,
   *                                         stock_quantity, reorder_level, active
   * @return int|false  Inserted row ID or false on failure.
   */
  public static function insert_item( array $data ) {
    global $wpdb;
    $result = $wpdb->insert(
      $wpdb->prefix . 'eggplant_pos_items',
      array(
        'name'           => sanitize_text_field( $data['name']           ?? '' ),
        'sku'            => sanitize_text_field( $data['sku']            ?? '' ),
        'price'          => round( (float) ( $data['price']              ?? 0 ), 2 ),
        'cost_price'     => round( (float) ( $data['cost_price']         ?? 0 ), 2 ),
        'tax_rate'       => round( (float) ( $data['tax_rate']           ?? 13 ), 4 ),
        'category'       => sanitize_text_field( $data['category']       ?? '' ),
        'stock_quantity' => max( 0, intval( $data['stock_quantity']      ?? 0 ) ),
        'reorder_level'  => max( 0, intval( $data['reorder_level']       ?? 5 ) ),
        'active'         => isset( $data['active'] ) ? (int) (bool) $data['active'] : 1,
      ),
      array( '%s', '%s', '%f', '%f', '%f', '%s', '%d', '%d', '%d' )
    );
    return $result ? $wpdb->insert_id : false;
  }

  /**
   * Update a POS item.
   *
   * @param int                 $id
   * @param array<string,mixed> $data
   */
  public static function update_item( int $id, array $data ): bool {
    global $wpdb;
    $result = $wpdb->update(
      $wpdb->prefix . 'eggplant_pos_items',
      array(
        'name'           => sanitize_text_field( $data['name']           ?? '' ),
        'sku'            => sanitize_text_field( $data['sku']            ?? '' ),
        'price'          => round( (float) ( $data['price']              ?? 0 ), 2 ),
        'cost_price'     => round( (float) ( $data['cost_price']         ?? 0 ), 2 ),
        'tax_rate'       => round( (float) ( $data['tax_rate']           ?? 13 ), 4 ),
        'category'       => sanitize_text_field( $data['category']       ?? '' ),
        'stock_quantity' => max( 0, intval( $data['stock_quantity']      ?? 0 ) ),
        'reorder_level'  => max( 0, intval( $data['reorder_level']       ?? 5 ) ),
        'active'         => isset( $data['active'] ) ? (int) (bool) $data['active'] : 1,
      ),
      array( 'id' => $id ),
      array( '%s', '%s', '%f', '%f', '%f', '%s', '%d', '%d', '%d' ),
      array( '%d' )
    );
    return $result !== false;
  }

  /**
   * Delete a POS item (soft-delete: set active = 0).
   */
  public static function delete_item( int $id ): bool {
    global $wpdb;
    $result = $wpdb->update(
      $wpdb->prefix . 'eggplant_pos_items',
      array( 'active' => 0 ),
      array( 'id'     => $id ),
      array( '%d' ),
      array( '%d' )
    );
    return $result !== false;
  }

  /**
   * Get all active items for the POS terminal.
   *
   * @return array<int,array<string,mixed>>
   */
  public static function get_active_items(): array {
    global $wpdb;
    $results = $wpdb->get_results(
      // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
      "SELECT * FROM {$wpdb->prefix}eggplant_pos_items WHERE active = 1 ORDER BY category ASC, name ASC",
      ARRAY_A
    );
    return $results ?: array();
  }

  /**
   * Get all items (admin list, including inactive).
   *
   * @return array<int,array<string,mixed>>
   */
  public static function get_all_items(): array {
    global $wpdb;
    $results = $wpdb->get_results(
      // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
      "SELECT * FROM {$wpdb->prefix}eggplant_pos_items ORDER BY category ASC, name ASC",
      ARRAY_A
    );
    return $results ?: array();
  }

  /**
   * Get a single item by ID.
   *
   * @param int $id
   * @return array<string,mixed>|null
   */
  public static function get_item( int $id ): ?array {
    global $wpdb;
    $row = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}eggplant_pos_items WHERE id = %d",
        $id
      ),
      ARRAY_A
    );
    return $row ?: null;
  }

  /**
   * Adjust stock quantity (positive = restock, negative = manual deduction).
   */
  public static function adjust_stock( int $item_id, int $delta ): bool {
    global $wpdb;
    $result = $wpdb->query(
      $wpdb->prepare(
        "UPDATE {$wpdb->prefix}eggplant_pos_items
           SET stock_quantity = GREATEST(0, stock_quantity + %d),
               updated_at = %s
         WHERE id = %d",
        $delta,
        current_time( 'mysql' ),
        $item_id
      )
    );
    return $result !== false;
  }

  /**
   * Get items whose stock_quantity is at or below their reorder_level.
   *
   * @return array<int,array<string,mixed>>
   */
  public static function get_low_stock_items(): array {
    global $wpdb;
    $results = $wpdb->get_results(
      // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
      "SELECT * FROM {$wpdb->prefix}eggplant_pos_items
        WHERE active = 1
          AND stock_quantity <= reorder_level
        ORDER BY stock_quantity ASC, name ASC",
      ARRAY_A
    );
    return $results ?: array();
  }

  // ------------------------------------------------------------------ DB: sales

  /**
   * Record a completed sale.
   *
   * @param array<string,mixed>              $sale_data   Keys: subtotal, tax_total, total, tender_amount, change_amount, payment_method, cashier
   * @param array<int,array<string,mixed>>   $line_items  Each: item_id, qty, unit_price, tax_rate, line_tax, line_total
   * @return int|false  Sale ID or false on failure.
   */
  public static function record_sale( array $sale_data, array $line_items ) {
    global $wpdb;

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $wpdb->query( 'START TRANSACTION' );

    $result = $wpdb->insert(
      $wpdb->prefix . 'eggplant_pos_sales',
      array(
        'subtotal'       => round( (float) ( $sale_data['subtotal']       ?? 0 ), 2 ),
        'tax_total'      => round( (float) ( $sale_data['tax_total']      ?? 0 ), 2 ),
        'total'          => round( (float) ( $sale_data['total']          ?? 0 ), 2 ),
        'tender_amount'  => round( (float) ( $sale_data['tender_amount']  ?? 0 ), 2 ),
        'change_amount'  => round( (float) ( $sale_data['change_amount']  ?? 0 ), 2 ),
        'payment_method' => sanitize_text_field( $sale_data['payment_method'] ?? 'cash' ),
        'cashier'        => sanitize_text_field( $sale_data['cashier']         ?? '' ),
        'sold_at'        => current_time( 'mysql' ),
      ),
      array( '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%s' )
    );

    if ( ! $result ) {
      // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
      $wpdb->query( 'ROLLBACK' );
      return false;
    }

    $sale_id = $wpdb->insert_id;

    foreach ( $line_items as $line ) {
      $item_id = intval( $line['item_id'] );
      $qty     = max( 1, intval( $line['qty'] ) );

      $line_result = $wpdb->insert(
        $wpdb->prefix . 'eggplant_pos_sale_items',
        array(
          'sale_id'    => $sale_id,
          'item_id'    => $item_id,
          'item_name'  => sanitize_text_field( $line['item_name']  ?? '' ),
          'qty'        => $qty,
          'unit_price' => round( (float) ( $line['unit_price'] ?? 0 ), 2 ),
          'cost_price' => round( (float) ( $line['cost_price'] ?? 0 ), 2 ),
          'tax_rate'   => round( (float) ( $line['tax_rate']   ?? 0 ), 4 ),
          'line_tax'   => round( (float) ( $line['line_tax']   ?? 0 ), 2 ),
          'line_total' => round( (float) ( $line['line_total'] ?? 0 ), 2 ),
        ),
        array( '%d', '%d', '%s', '%d', '%f', '%f', '%f', '%f', '%f' )
      );

      if ( ! $line_result ) {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query( 'ROLLBACK' );
        return false;
      }

      // Reduce stock.
      $stock_result = $wpdb->query(
        $wpdb->prepare(
          "UPDATE {$wpdb->prefix}eggplant_pos_items
             SET stock_quantity = GREATEST(0, stock_quantity - %d),
                 updated_at = %s
           WHERE id = %d",
          $qty,
          current_time( 'mysql' ),
          $item_id
        )
      );

      if ( $stock_result === false ) {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query( 'ROLLBACK' );
        return false;
      }
    }

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $wpdb->query( 'COMMIT' );
    return $sale_id;
  }

  /**
   * Sales report data grouped by period.
   *
   * @param string $period  'day' | 'week' | 'month' | 'year'
   * @param string $from    Y-m-d
   * @param string $to      Y-m-d
   * @return array<string,mixed>  Keys: rows (grouped by period), totals (aggregate sums)
   */
  public static function get_sales_report( string $period, string $from, string $to ): array {
    global $wpdb;

    $from = sanitize_text_field( $from );
    $to   = sanitize_text_field( $to );

    switch ( $period ) {
      case 'year':
        $date_expr = "DATE_FORMAT(s.sold_at, '%Y')";
        break;
      case 'month':
        $date_expr = "DATE_FORMAT(s.sold_at, '%Y-%m')";
        break;
      case 'week':
        $date_expr = "DATE_FORMAT(s.sold_at, '%Y-W%u')";
        break;
      default: // day
        $date_expr = "DATE_FORMAT(s.sold_at, '%Y-%m-%d')";
    }

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT
           {$date_expr} AS period_label,
           COUNT(DISTINCT s.id)           AS sale_count,
           SUM(si.qty)                    AS units_sold,
           SUM(si.line_total - si.line_tax) AS subtotal,
           SUM(si.line_tax)               AS tax_total,
           SUM(si.line_total)             AS revenue,
           SUM(si.cost_price * si.qty)    AS cogs,
           SUM(si.line_total - si.line_tax - (si.cost_price * si.qty)) AS gross_profit
         FROM {$wpdb->prefix}eggplant_pos_sales s
         JOIN {$wpdb->prefix}eggplant_pos_sale_items si ON si.sale_id = s.id
         WHERE DATE(s.sold_at) BETWEEN %s AND %s
         GROUP BY period_label
         ORDER BY period_label ASC",
        $from,
        $to
      ),
      ARRAY_A
    );

    $totals = array(
      'sale_count'   => 0,
      'units_sold'   => 0,
      'subtotal'     => 0.0,
      'tax_total'    => 0.0,
      'revenue'      => 0.0,
      'cogs'         => 0.0,
      'gross_profit' => 0.0,
    );

    foreach ( $rows ?: array() as $row ) {
      foreach ( array_keys( $totals ) as $k ) {
        $totals[ $k ] += (float) ( $row[ $k ] ?? 0 );
      }
    }

    return array(
      'rows'   => $rows ?: array(),
      'totals' => $totals,
    );
  }

  /**
   * Sales broken down by item for a date range (for profit accounting).
   *
   * @param string $from  Y-m-d
   * @param string $to    Y-m-d
   * @return array<int,array<string,mixed>>
   */
  public static function get_item_sales_report( string $from, string $to ): array {
    global $wpdb;
    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT
           si.item_id,
           si.item_name,
           SUM(si.qty)                                    AS units_sold,
           AVG(si.unit_price)                             AS avg_price,
           AVG(si.cost_price)                             AS avg_cost,
           SUM(si.line_total - si.line_tax)               AS subtotal,
           SUM(si.line_tax)                               AS tax_total,
           SUM(si.line_total)                             AS revenue,
           SUM(si.cost_price * si.qty)                    AS cogs,
           SUM(si.line_total - si.line_tax - (si.cost_price * si.qty)) AS gross_profit
         FROM {$wpdb->prefix}eggplant_pos_sales s
         JOIN {$wpdb->prefix}eggplant_pos_sale_items si ON si.sale_id = s.id
         WHERE DATE(s.sold_at) BETWEEN %s AND %s
         GROUP BY si.item_id, si.item_name
         ORDER BY revenue DESC",
        sanitize_text_field( $from ),
        sanitize_text_field( $to )
      ),
      ARRAY_A
    );
    return $rows ?: array();
  }

  // ------------------------------------------------------------------ AJAX: public terminal

  public static function ajax_get_items(): void {
    check_ajax_referer( 'eggplant_pos_nonce', 'nonce' );
    wp_send_json_success( self::get_active_items() );
  }

  public static function ajax_process_sale(): void {
    check_ajax_referer( 'eggplant_pos_nonce', 'nonce' );

    if ( ! is_user_logged_in() ) {
      wp_send_json_error( __( 'You must be logged in to process a sale.', 'eggplant' ) );
    }

    $raw_items = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array();
    if ( ! is_array( $raw_items ) || empty( $raw_items ) ) {
      wp_send_json_error( __( 'No items in cart.', 'eggplant' ) );
    }

    $line_items    = array();
    $subtotal      = 0.0;
    $tax_total     = 0.0;

    foreach ( $raw_items as $entry ) {
      $item_id = intval( $entry['item_id'] ?? 0 );
      $qty     = max( 1, intval( $entry['qty'] ?? 1 ) );

      if ( $item_id <= 0 ) {
        continue;
      }

      $item = self::get_item( $item_id );
      if ( ! $item || ! $item['active'] ) {
        wp_send_json_error( __( 'Invalid item in cart.', 'eggplant' ) );
      }

      if ( (int) $item['stock_quantity'] < $qty ) {
        /* translators: %s: item name */
        wp_send_json_error( sprintf( __( '"%s" does not have enough stock to complete this sale.', 'eggplant' ), $item['name'] ) );
      }

      $unit_price = (float) $item['price'];
      $tax_rate   = (float) $item['tax_rate'];      // e.g. 13 means 13%
      $line_net   = round( $unit_price * $qty, 2 );
      $line_tax   = round( $line_net * ( $tax_rate / 100 ), 2 );
      $line_total = $line_net + $line_tax;

      $subtotal  += $line_net;
      $tax_total += $line_tax;

      $line_items[] = array(
        'item_id'    => $item_id,
        'item_name'  => $item['name'],
        'qty'        => $qty,
        'unit_price' => $unit_price,
        'cost_price' => (float) $item['cost_price'],
        'tax_rate'   => $tax_rate,
        'line_tax'   => $line_tax,
        'line_total' => $line_total,
      );
    }

    if ( empty( $line_items ) ) {
      wp_send_json_error( __( 'No valid items in cart.', 'eggplant' ) );
    }

    $total          = round( $subtotal + $tax_total, 2 );
    $tender         = round( (float) ( $_POST['tender_amount'] ?? $total ), 2 );
    $change         = round( max( 0.0, $tender - $total ), 2 );
    $payment_method = sanitize_text_field( wp_unslash( $_POST['payment_method'] ?? 'cash' ) );
    $cashier        = is_user_logged_in() ? wp_get_current_user()->display_name : sanitize_text_field( wp_unslash( $_POST['cashier'] ?? '' ) );

    $sale_id = self::record_sale(
      array(
        'subtotal'       => $subtotal,
        'tax_total'      => $tax_total,
        'total'          => $total,
        'tender_amount'  => $tender,
        'change_amount'  => $change,
        'payment_method' => $payment_method,
        'cashier'        => $cashier,
      ),
      $line_items
    );

    if ( ! $sale_id ) {
      wp_send_json_error( __( 'Failed to save sale. Please try again.', 'eggplant' ) );
    }

    wp_send_json_success( array(
      'sale_id'  => $sale_id,
      'subtotal' => $subtotal,
      'tax'      => $tax_total,
      'total'    => $total,
      'change'   => $change,
    ) );
  }

  // ------------------------------------------------------------------ AJAX: admin

  public static function ajax_save_item(): void {
    check_ajax_referer( 'eggplant_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_send_json_error( 'Unauthorized' );
    }

    $id   = intval( $_POST['id'] ?? 0 );
    $data = array(
      'name'           => sanitize_text_field( wp_unslash( $_POST['name']           ?? '' ) ),
      'sku'            => sanitize_text_field( wp_unslash( $_POST['sku']            ?? '' ) ),
      'price'          => (float) ( $_POST['price']                                 ?? 0 ),
      'cost_price'     => (float) ( $_POST['cost_price']                            ?? 0 ),
      'tax_rate'       => (float) ( $_POST['tax_rate']                              ?? 13 ),
      'category'       => sanitize_text_field( wp_unslash( $_POST['category']       ?? '' ) ),
      'stock_quantity' => intval( $_POST['stock_quantity']                           ?? 0 ),
      'reorder_level'  => intval( $_POST['reorder_level']                            ?? 5 ),
      'active'         => isset( $_POST['active'] ) ? (int) $_POST['active'] : 1,
    );

    if ( empty( $data['name'] ) ) {
      wp_send_json_error( __( 'Item name is required.', 'eggplant' ) );
    }

    if ( $id ) {
      $ok = self::update_item( $id, $data );
      $ok ? wp_send_json_success( array( 'updated' => true ) ) : wp_send_json_error( 'Update failed.' );
    } else {
      $new_id = self::insert_item( $data );
      $new_id ? wp_send_json_success( array( 'id' => $new_id ) ) : wp_send_json_error( 'Insert failed.' );
    }
  }

  public static function ajax_delete_item(): void {
    check_ajax_referer( 'eggplant_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_send_json_error( 'Unauthorized' );
    }
    $id = intval( $_POST['id'] ?? 0 );
    $ok = self::delete_item( $id );
    $ok ? wp_send_json_success() : wp_send_json_error( 'Delete failed.' );
  }

  public static function ajax_get_item(): void {
    check_ajax_referer( 'eggplant_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_send_json_error( 'Unauthorized' );
    }
    $id   = intval( $_POST['id'] ?? 0 );
    $item = self::get_item( $id );
    $item ? wp_send_json_success( $item ) : wp_send_json_error( 'Not found.' );
  }

  public static function ajax_adjust_stock(): void {
    check_ajax_referer( 'eggplant_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_send_json_error( 'Unauthorized' );
    }
    $id    = intval( $_POST['id']    ?? 0 );
    $delta = intval( $_POST['delta'] ?? 0 );
    if ( $id <= 0 ) {
      wp_send_json_error( 'Invalid item.' );
    }
    $ok = self::adjust_stock( $id, $delta );
    if ( $ok ) {
      $item = self::get_item( $id );
      wp_send_json_success( array( 'stock_quantity' => $item ? $item['stock_quantity'] : 0 ) );
    } else {
      wp_send_json_error( 'Adjustment failed.' );
    }
  }

  public static function ajax_get_sales_report(): void {
    check_ajax_referer( 'eggplant_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_send_json_error( 'Unauthorized' );
    }
    $period = sanitize_text_field( wp_unslash( $_POST['period'] ?? 'day' ) );
    $from   = sanitize_text_field( wp_unslash( $_POST['from']   ?? current_time( 'Y-m-01' ) ) );
    $to     = sanitize_text_field( wp_unslash( $_POST['to']     ?? current_time( 'Y-m-d' ) ) );

    if ( ! in_array( $period, array( 'day', 'week', 'month', 'year' ), true ) ) {
      $period = 'day';
    }
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ) {
      $from = current_time( 'Y-m-01' );
    }
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ) {
      $to = current_time( 'Y-m-d' );
    }

    $report      = self::get_sales_report( $period, $from, $to );
    $item_report = self::get_item_sales_report( $from, $to );

    wp_send_json_success( array(
      'summary'     => $report,
      'by_item'     => $item_report,
    ) );
  }

  // ------------------------------------------------------------------ shortcode

  /**
   * Render the public POS terminal.
   *
   * @return string  HTML output.
   */
  public static function render_pos_shortcode(): string {
    ob_start();
    $items = self::get_active_items();
    $categories = array_unique( array_column( $items, 'category' ) );
    sort( $categories );
    ?>
    <div class="eg-pos" id="eg-pos-terminal">

      <div class="eg-pos__layout">

        <!-- Item grid -->
        <div class="eg-pos__items-panel">
          <?php if ( ! empty( $categories ) && count( $categories ) > 1 ) : ?>
          <div class="eg-pos__category-tabs">
            <button class="eg-pos__cat-tab eg-pos__cat-tab--active" data-cat=""><?php esc_html_e( 'All', 'eggplant' ); ?></button>
            <?php foreach ( $categories as $cat ) : ?>
              <?php if ( $cat !== '' ) : ?>
              <button class="eg-pos__cat-tab" data-cat="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></button>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <div class="eg-pos__items-grid" id="eg-pos-items-grid">
            <?php if ( empty( $items ) ) : ?>
              <p><?php esc_html_e( 'No items available.', 'eggplant' ); ?></p>
            <?php else : ?>
              <?php foreach ( $items as $item ) : ?>
              <button class="eg-pos__item-btn"
                      data-id="<?php echo esc_attr( $item['id'] ); ?>"
                      data-name="<?php echo esc_attr( $item['name'] ); ?>"
                      data-price="<?php echo esc_attr( $item['price'] ); ?>"
                      data-tax="<?php echo esc_attr( $item['tax_rate'] ); ?>"
                      data-stock="<?php echo esc_attr( $item['stock_quantity'] ); ?>"
                      data-cat="<?php echo esc_attr( $item['category'] ); ?>">
                <span class="eg-pos__item-name"><?php echo esc_html( $item['name'] ); ?></span>
                <span class="eg-pos__item-price">
                  $<?php echo number_format( (float) $item['price'], 2 ); ?>
                  <?php if ( (float) $item['tax_rate'] > 0 ) : ?>
                    <small>(+<?php echo esc_html( $item['tax_rate'] ); ?>% <?php esc_html_e( 'tax', 'eggplant' ); ?>)</small>
                  <?php endif; ?>
                </span>
                <?php if ( (int) $item['stock_quantity'] <= 0 ) : ?>
                  <span class="eg-pos__item-oos"><?php esc_html_e( 'Out of stock', 'eggplant' ); ?></span>
                <?php endif; ?>
              </button>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Cart / checkout -->
        <div class="eg-pos__cart-panel">
          <h2><?php esc_html_e( 'Current Order', 'eggplant' ); ?></h2>
          <div class="eg-pos__cart-items" id="eg-pos-cart-items">
            <p class="eg-pos__cart-empty"><?php esc_html_e( 'Cart is empty. Tap an item to add it.', 'eggplant' ); ?></p>
          </div>

          <div class="eg-pos__totals" id="eg-pos-totals" style="display:none;">
            <div class="eg-pos__totals-row">
              <span><?php esc_html_e( 'Subtotal', 'eggplant' ); ?></span>
              <span id="eg-pos-subtotal">$0.00</span>
            </div>
            <div class="eg-pos__totals-row">
              <span><?php esc_html_e( 'Tax', 'eggplant' ); ?></span>
              <span id="eg-pos-tax">$0.00</span>
            </div>
            <div class="eg-pos__totals-row eg-pos__totals-row--total">
              <span><?php esc_html_e( 'Total', 'eggplant' ); ?></span>
              <span id="eg-pos-total">$0.00</span>
            </div>
          </div>

          <div class="eg-pos__payment" id="eg-pos-payment" style="display:none;">
            <div class="eg-form-row">
              <label for="eg-pos-payment-method"><?php esc_html_e( 'Payment Method', 'eggplant' ); ?></label>
              <select id="eg-pos-payment-method">
                <option value="cash"><?php esc_html_e( 'Cash', 'eggplant' ); ?></option>
                <option value="card"><?php esc_html_e( 'Card', 'eggplant' ); ?></option>
                <option value="other"><?php esc_html_e( 'Other', 'eggplant' ); ?></option>
              </select>
            </div>
            <div class="eg-form-row" id="eg-pos-tender-row">
              <label for="eg-pos-tender"><?php esc_html_e( 'Tendered Amount ($)', 'eggplant' ); ?></label>
              <input type="number" id="eg-pos-tender" min="0" step="0.01" placeholder="0.00">
            </div>
          </div>

          <div class="eg-pos__actions" id="eg-pos-actions" style="display:none;">
            <button class="button button-primary eg-pos__btn-charge" id="eg-pos-btn-charge">
              <?php esc_html_e( 'Complete Sale', 'eggplant' ); ?>
            </button>
            <button class="button eg-pos__btn-clear" id="eg-pos-btn-clear">
              <?php esc_html_e( 'Clear', 'eggplant' ); ?>
            </button>
          </div>

          <div class="eg-pos__receipt" id="eg-pos-receipt" style="display:none;">
            <h3><?php esc_html_e( 'Sale Complete', 'eggplant' ); ?></h3>
            <div id="eg-pos-receipt-details"></div>
            <div class="eg-pos__receipt-actions">
              <button class="button" id="eg-pos-btn-print-receipt" onclick="document.body.classList.add('eg-pos-printing');window.addEventListener('afterprint',function(){document.body.classList.remove('eg-pos-printing');},{once:true});window.print();">
                <?php esc_html_e( 'Print Receipt', 'eggplant' ); ?>
              </button>
              <button class="button button-primary" id="eg-pos-btn-new-sale">
                <?php esc_html_e( 'New Sale', 'eggplant' ); ?>
              </button>
            </div>
          </div>

          <div id="eg-pos-msg" class="eg-msg" style="display:none;"></div>
        </div>

      </div><!-- .eg-pos__layout -->
    </div><!-- .eg-pos -->

    <script>
    (function($){
      var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
      var nonce   = <?php echo wp_json_encode( wp_create_nonce( 'eggplant_pos_nonce' ) ); ?>;
      var cart    = {};

      function fmt(n){ return '$' + parseFloat(n).toFixed(2); }

      function updateTotals(){
        var subtotal=0, tax=0;
        $.each(cart, function(id, row){
          var net = row.price * row.qty;
          var t   = net * (row.tax/100);
          subtotal += net;
          tax      += t;
        });
        var total = subtotal + tax;
        $('#eg-pos-subtotal').text(fmt(subtotal));
        $('#eg-pos-tax').text(fmt(tax));
        $('#eg-pos-total').text(fmt(total));
        $('#eg-pos-tender').attr('placeholder', total.toFixed(2));
        if(Object.keys(cart).length > 0){
          $('#eg-pos-totals,#eg-pos-payment,#eg-pos-actions').show();
          $('.eg-pos__cart-empty').hide();
        } else {
          $('#eg-pos-totals,#eg-pos-payment,#eg-pos-actions').hide();
          $('.eg-pos__cart-empty').show();
        }
      }

      function renderCart(){
        var html='';
        $.each(cart, function(id, row){
          html += '<div class="eg-pos__cart-row" data-id="'+id+'">';
          html += '<span class="eg-pos__cart-name">'+$('<span>').text(row.name).html()+'</span>';
          html += '<span class="eg-pos__cart-controls">';
          html += '<button class="eg-pos__qty-btn" data-action="dec" data-id="'+id+'">−</button>';
          html += '<span class="eg-pos__cart-qty">'+row.qty+'</span>';
          html += '<button class="eg-pos__qty-btn" data-action="inc" data-id="'+id+'">+</button>';
          html += '</span>';
          html += '<span class="eg-pos__cart-line">'+fmt(row.price*row.qty)+'</span>';
          html += '<button class="eg-pos__remove-btn" data-id="'+id+'">×</button>';
          html += '</div>';
        });
        $('#eg-pos-cart-items').html(html || '<p class="eg-pos__cart-empty"><?php echo esc_js( __( 'Cart is empty. Tap an item to add it.', 'eggplant' ) ); ?></p>');
        updateTotals();
      }

      // Category filter.
      $(document).on('click', '.eg-pos__cat-tab', function(){
        var cat = $(this).data('cat');
        $('.eg-pos__cat-tab').removeClass('eg-pos__cat-tab--active');
        $(this).addClass('eg-pos__cat-tab--active');
        if(cat===''){
          $('.eg-pos__item-btn').show();
        } else {
          $('.eg-pos__item-btn').hide().filter('[data-cat="'+cat+'"]').show();
        }
      });

      // Add item to cart.
      $(document).on('click', '.eg-pos__item-btn', function(){
        var id    = $(this).data('id');
        var name  = $(this).data('name');
        var price = parseFloat($(this).data('price'));
        var tax   = parseFloat($(this).data('tax'));
        var stock = parseInt($(this).data('stock'), 10);
        if(stock <= 0){ return; }
        if(cart[id]){
          cart[id].qty++;
        } else {
          cart[id] = {name:name, price:price, tax:tax, qty:1};
        }
        renderCart();
      });

      // Qty buttons.
      $(document).on('click', '.eg-pos__qty-btn', function(){
        var id     = $(this).data('id');
        var action = $(this).data('action');
        if(!cart[id]){ return; }
        if(action==='inc'){
          cart[id].qty++;
        } else {
          cart[id].qty--;
          if(cart[id].qty <= 0){ delete cart[id]; }
        }
        renderCart();
      });

      // Remove item.
      $(document).on('click', '.eg-pos__remove-btn', function(){
        delete cart[$(this).data('id')];
        renderCart();
      });

      // Payment method: show/hide tender field for cash.
      $(document).on('change','#eg-pos-payment-method', function(){
        if($(this).val()==='cash'){
          $('#eg-pos-tender-row').show();
        } else {
          $('#eg-pos-tender-row').hide();
        }
      });

      // Clear cart.
      $('#eg-pos-btn-clear').on('click', function(){
        cart={};
        renderCart();
      });

      // New sale.
      $('#eg-pos-btn-new-sale').on('click', function(){
        cart={};
        renderCart();
        $('#eg-pos-receipt').hide();
        $('#eg-pos-actions').show();
      });

      // Complete sale.
      $('#eg-pos-btn-charge').on('click', function(){
        var items=[];
        $.each(cart, function(id, row){ items.push({item_id:id, qty:row.qty}); });
        if(!items.length){ return; }
        var tender = parseFloat($('#eg-pos-tender').val()) || 0;
        var method = $('#eg-pos-payment-method').val();
        $('#eg-pos-msg').hide();
        $.post(ajaxUrl, {
          action:         'eggplant_pos_process_sale',
          nonce:          nonce,
          items:          items,
          tender_amount:  tender,
          payment_method: method
        }, function(res){
          if(res.success){
            var d = res.data;
            var html = '<table class="eg-pos__receipt-table">';
            html += '<thead><tr><th><?php echo esc_js( __('Item','eggplant') ); ?></th><th><?php echo esc_js( __('Qty','eggplant') ); ?></th><th><?php echo esc_js( __('Unit','eggplant') ); ?></th><th><?php echo esc_js( __('Line','eggplant') ); ?></th></tr></thead>';
            html += '<tbody>';
            $.each(cart, function(id, row){
              var qty = parseInt(row.qty, 10);
              var net = row.price * qty;
              html += '<tr><td>'+$('<span>').text(row.name).html()+'</td><td>'+qty+'</td><td>'+fmt(row.price)+'</td><td>'+fmt(net)+'</td></tr>';
            });
            html += '</tbody>';
            html += '<tfoot>';
            html += '<tr><th colspan="3"><?php echo esc_js( __('Subtotal','eggplant') ); ?></th><td>'+fmt(d.subtotal)+'</td></tr>';
            html += '<tr><th colspan="3"><?php echo esc_js( __('Tax','eggplant') ); ?></th><td>'+fmt(d.tax)+'</td></tr>';
            html += '<tr class="eg-pos__receipt-total"><th colspan="3"><?php echo esc_js( __('Total','eggplant') ); ?></th><td>'+fmt(d.total)+'</td></tr>';
            if(method==='cash' && d.change>0){
              html += '<tr><th colspan="3"><?php echo esc_js( __('Change','eggplant') ); ?></th><td>'+fmt(d.change)+'</td></tr>';
            }
            html += '</tfoot>';
            html += '</table>';
            $('#eg-pos-receipt-details').html(html);
            $('#eg-pos-actions').hide();
            $('#eg-pos-receipt').show();
          } else {
            $('#eg-pos-msg').text(res.data||'<?php echo esc_js( __('Error processing sale.','eggplant') ); ?>').show();
          }
        }, 'json');
      });
    })(jQuery);
    </script>
    <?php
    return ob_get_clean();
  }
}
