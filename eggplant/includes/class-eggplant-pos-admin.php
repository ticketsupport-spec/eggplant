<?php

/**
 * POS admin pages: Items, Sales Reports, Inventory / Reorder, Profit Accounting.
 *
 * @since 1.4.0
 * @package Eggplant
 */

class Eggplant_POS_Admin {

  // ------------------------------------------------------------------ menus

  /**
   * Register the four POS sub-pages under the Event Portal menu.
   * Called by Eggplant_Admin via add_action( 'admin_menu', ..., 12 ).
   */
  public static function add_menus(): void {
    add_submenu_page(
      'eggplant',
      __( 'POS – Items', 'eggplant' ),
      __( 'POS – Items', 'eggplant' ),
      'manage_options',
      'eggplant-pos-items',
      array( __CLASS__, 'page_items' )
    );

    add_submenu_page(
      'eggplant',
      __( 'POS – Sales', 'eggplant' ),
      __( 'POS – Sales', 'eggplant' ),
      'manage_options',
      'eggplant-pos-sales',
      array( __CLASS__, 'page_sales' )
    );

    add_submenu_page(
      'eggplant',
      __( 'POS – Inventory', 'eggplant' ),
      __( 'POS – Inventory', 'eggplant' ),
      'manage_options',
      'eggplant-pos-inventory',
      array( __CLASS__, 'page_inventory' )
    );

    add_submenu_page(
      'eggplant',
      __( 'POS – Profit', 'eggplant' ),
      __( 'POS – Profit', 'eggplant' ),
      'manage_options',
      'eggplant-pos-profit',
      array( __CLASS__, 'page_profit' )
    );
  }

  // ------------------------------------------------------------------ page: items

  public static function page_items(): void {
    ?>
    <div class="wrap eg-admin">
      <h1><?php esc_html_e( 'POS – Items &amp; Prices', 'eggplant' ); ?></h1>

      <!-- Add / edit item form -->
      <div class="eg-card" id="eg-pos-item-form-card">
        <h2 id="eg-pos-item-form-title"><?php esc_html_e( 'Add Item', 'eggplant' ); ?></h2>
        <form id="eg-pos-item-form">
          <input type="hidden" id="eg-pos-item-id" value="">
          <div class="eg-form-row">
            <label><?php esc_html_e( 'Name *', 'eggplant' ); ?></label>
            <input type="text" id="eg-pos-item-name" required>
          </div>
          <div class="eg-form-row">
            <label><?php esc_html_e( 'SKU / Code', 'eggplant' ); ?></label>
            <input type="text" id="eg-pos-item-sku">
          </div>
          <div class="eg-form-row">
            <label><?php esc_html_e( 'Category', 'eggplant' ); ?></label>
            <input type="text" id="eg-pos-item-category" placeholder="<?php esc_attr_e( 'e.g. Drinks, Food', 'eggplant' ); ?>">
          </div>
          <div class="eg-form-row">
            <label><?php esc_html_e( 'Sale Price ($) *', 'eggplant' ); ?></label>
            <input type="number" id="eg-pos-item-price" min="0" step="0.01" required>
          </div>
          <div class="eg-form-row">
            <label><?php esc_html_e( 'Cost Price ($)', 'eggplant' ); ?></label>
            <input type="number" id="eg-pos-item-cost" min="0" step="0.01" value="0">
            <p class="description"><?php esc_html_e( 'Your cost / wholesale price. Used for profit calculations.', 'eggplant' ); ?></p>
          </div>
          <div class="eg-form-row">
            <label><?php esc_html_e( 'Sales Tax Rate (%)', 'eggplant' ); ?></label>
            <input type="number" id="eg-pos-item-tax" min="0" max="100" step="0.01" value="13">
            <p class="description"><?php esc_html_e( 'Default 13%. Enter 0 for tax-exempt items.', 'eggplant' ); ?></p>
          </div>
          <div class="eg-form-row">
            <label><?php esc_html_e( 'Starting Stock Qty', 'eggplant' ); ?></label>
            <input type="number" id="eg-pos-item-stock" min="0" step="1" value="0">
          </div>
          <div class="eg-form-row">
            <label><?php esc_html_e( 'Reorder Level', 'eggplant' ); ?></label>
            <input type="number" id="eg-pos-item-reorder" min="0" step="1" value="5">
            <p class="description"><?php esc_html_e( 'Alert when stock falls to this number or below.', 'eggplant' ); ?></p>
          </div>
          <div class="eg-form-row">
            <label><?php esc_html_e( 'Active', 'eggplant' ); ?></label>
            <input type="checkbox" id="eg-pos-item-active" value="1" checked>
          </div>
          <button type="submit" class="button button-primary" id="eg-pos-item-save-btn"><?php esc_html_e( 'Save Item', 'eggplant' ); ?></button>
          <button type="button" class="button" id="eg-pos-item-cancel-btn" style="display:none;"><?php esc_html_e( 'Cancel', 'eggplant' ); ?></button>
          <span class="eg-msg" id="eg-pos-item-msg"></span>
        </form>
      </div>

      <!-- Items table -->
      <div class="eg-card">
        <h2><?php esc_html_e( 'All Items', 'eggplant' ); ?></h2>
        <div id="eg-pos-items-table-wrap">
          <?php self::render_items_table(); ?>
        </div>
      </div>
    </div>

    <script>
    (function($){
      // Save item.
      $('#eg-pos-item-form').on('submit', function(e){
        e.preventDefault();
        var id      = $('#eg-pos-item-id').val();
        var payload = {
          action:         'eggplant_pos_save_item',
          nonce:          EggplantAdmin.nonce,
          id:             id,
          name:           $('#eg-pos-item-name').val(),
          sku:            $('#eg-pos-item-sku').val(),
          category:       $('#eg-pos-item-category').val(),
          price:          $('#eg-pos-item-price').val(),
          cost_price:     $('#eg-pos-item-cost').val(),
          tax_rate:       $('#eg-pos-item-tax').val(),
          stock_quantity: $('#eg-pos-item-stock').val(),
          reorder_level:  $('#eg-pos-item-reorder').val(),
          active:         $('#eg-pos-item-active').is(':checked') ? 1 : 0
        };
        $.post(EggplantAdmin.ajaxUrl, payload, function(res){
          if(res.success){
            $('#eg-pos-item-msg').text('<?php echo esc_js( __( 'Saved!', 'eggplant' ) ); ?>').show();
            setTimeout(function(){ location.reload(); }, 800);
          } else {
            $('#eg-pos-item-msg').text(res.data||'<?php echo esc_js( __( 'Error.', 'eggplant' ) ); ?>').show();
          }
        }, 'json');
      });

      // Edit item.
      $(document).on('click', '.eg-pos-edit-item', function(){
        var id = $(this).data('id');
        $.post(EggplantAdmin.ajaxUrl, {action:'eggplant_pos_get_item', nonce:EggplantAdmin.nonce, id:id}, function(res){
          if(!res.success){ return; }
          var d = res.data;
          $('#eg-pos-item-id').val(d.id);
          $('#eg-pos-item-name').val(d.name);
          $('#eg-pos-item-sku').val(d.sku);
          $('#eg-pos-item-category').val(d.category);
          $('#eg-pos-item-price').val(d.price);
          $('#eg-pos-item-cost').val(d.cost_price);
          $('#eg-pos-item-tax').val(d.tax_rate);
          $('#eg-pos-item-stock').val(d.stock_quantity);
          $('#eg-pos-item-reorder').val(d.reorder_level);
          $('#eg-pos-item-active').prop('checked', d.active == 1);
          $('#eg-pos-item-form-title').text('<?php echo esc_js( __( 'Edit Item', 'eggplant' ) ); ?>');
          $('#eg-pos-item-cancel-btn').show();
          $('html,body').animate({scrollTop:$('#eg-pos-item-form-card').offset().top - 32}, 300);
        }, 'json');
      });

      // Cancel edit.
      $('#eg-pos-item-cancel-btn').on('click', function(){
        $('#eg-pos-item-form')[0].reset();
        $('#eg-pos-item-id').val('');
        $('#eg-pos-item-active').prop('checked', true);
        $('#eg-pos-item-tax').val(13);
        $('#eg-pos-item-form-title').text('<?php echo esc_js( __( 'Add Item', 'eggplant' ) ); ?>');
        $(this).hide();
      });

      // Delete item.
      $(document).on('click', '.eg-pos-delete-item', function(){
        if(!confirm('<?php echo esc_js( __( 'Delete this item?', 'eggplant' ) ); ?>')){ return; }
        var id = $(this).data('id');
        $.post(EggplantAdmin.ajaxUrl, {action:'eggplant_pos_delete_item', nonce:EggplantAdmin.nonce, id:id}, function(res){
          if(res.success){ location.reload(); }
        }, 'json');
      });
    })(jQuery);
    </script>
    <?php
  }

  private static function render_items_table(): void {
    $items = Eggplant_POS::get_all_items();
    if ( empty( $items ) ) {
      echo '<p>' . esc_html__( 'No items yet. Add your first item above.', 'eggplant' ) . '</p>';
      return;
    }
    ?>
    <table class="widefat striped">
      <thead>
        <tr>
          <th><?php esc_html_e( 'Name', 'eggplant' ); ?></th>
          <th><?php esc_html_e( 'SKU', 'eggplant' ); ?></th>
          <th><?php esc_html_e( 'Category', 'eggplant' ); ?></th>
          <th><?php esc_html_e( 'Sale Price', 'eggplant' ); ?></th>
          <th><?php esc_html_e( 'Cost Price', 'eggplant' ); ?></th>
          <th><?php esc_html_e( 'Tax %', 'eggplant' ); ?></th>
          <th><?php esc_html_e( 'Stock', 'eggplant' ); ?></th>
          <th><?php esc_html_e( 'Reorder At', 'eggplant' ); ?></th>
          <th><?php esc_html_e( 'Active', 'eggplant' ); ?></th>
          <th><?php esc_html_e( 'Actions', 'eggplant' ); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $items as $item ) : ?>
        <tr>
          <td><?php echo esc_html( $item['name'] ); ?></td>
          <td><?php echo esc_html( $item['sku'] ); ?></td>
          <td><?php echo esc_html( $item['category'] ); ?></td>
          <td>$<?php echo number_format( (float) $item['price'], 2 ); ?></td>
          <td>$<?php echo number_format( (float) $item['cost_price'], 2 ); ?></td>
          <td><?php echo esc_html( $item['tax_rate'] ); ?>%</td>
          <td class="<?php echo ( (int) $item['stock_quantity'] <= (int) $item['reorder_level'] ) ? 'eg-low-stock' : ''; ?>">
            <?php echo esc_html( $item['stock_quantity'] ); ?>
          </td>
          <td><?php echo esc_html( $item['reorder_level'] ); ?></td>
          <td><?php echo $item['active'] ? '<span style="color:#2a9d8f;">&#10003;</span>' : '<span style="color:#e63946;">&#10005;</span>'; ?></td>
          <td>
            <button class="button button-small eg-pos-edit-item" data-id="<?php echo esc_attr( $item['id'] ); ?>"><?php esc_html_e( 'Edit', 'eggplant' ); ?></button>
            <button class="button button-small eg-pos-delete-item" data-id="<?php echo esc_attr( $item['id'] ); ?>"><?php esc_html_e( 'Delete', 'eggplant' ); ?></button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php
  }

  // ------------------------------------------------------------------ page: sales

  public static function page_sales(): void {
    $today      = current_time( 'Y-m-d' );
    $month_from = current_time( 'Y-m-01' );
    ?>
    <div class="wrap eg-admin">
      <h1><?php esc_html_e( 'POS – Sales Report', 'eggplant' ); ?></h1>

      <div class="eg-card">
        <h2><?php esc_html_e( 'Filter', 'eggplant' ); ?></h2>
        <div class="eg-form-row">
          <label><?php esc_html_e( 'Group By', 'eggplant' ); ?></label>
          <select id="eg-pos-period">
            <option value="day"><?php esc_html_e( 'Day', 'eggplant' ); ?></option>
            <option value="week"><?php esc_html_e( 'Week', 'eggplant' ); ?></option>
            <option value="month" selected><?php esc_html_e( 'Month', 'eggplant' ); ?></option>
            <option value="year"><?php esc_html_e( 'Year', 'eggplant' ); ?></option>
          </select>
        </div>
        <div class="eg-form-row">
          <label><?php esc_html_e( 'From', 'eggplant' ); ?></label>
          <input type="date" id="eg-pos-from" value="<?php echo esc_attr( $month_from ); ?>">
        </div>
        <div class="eg-form-row">
          <label><?php esc_html_e( 'To', 'eggplant' ); ?></label>
          <input type="date" id="eg-pos-to" value="<?php echo esc_attr( $today ); ?>">
        </div>
        <button class="button button-primary" id="eg-pos-run-report"><?php esc_html_e( 'Run Report', 'eggplant' ); ?></button>
        <span class="eg-msg" id="eg-pos-report-msg"></span>
      </div>

      <div class="eg-card" id="eg-pos-report-wrap" style="display:none;">
        <h2><?php esc_html_e( 'Summary', 'eggplant' ); ?></h2>
        <div id="eg-pos-totals-summary"></div>
        <h2 style="margin-top:24px;"><?php esc_html_e( 'Breakdown by Period', 'eggplant' ); ?></h2>
        <div class="eg-table-wrap" id="eg-pos-report-table"></div>
      </div>
    </div>

    <script>
    (function($){
      function fmt(n){ return '$'+parseFloat(n||0).toFixed(2); }

      $('#eg-pos-run-report').on('click', function(){
        $('#eg-pos-report-msg').text('<?php echo esc_js( __( 'Loading…', 'eggplant' ) ); ?>').show();
        $.post(EggplantAdmin.ajaxUrl, {
          action: 'eggplant_pos_get_sales_report',
          nonce:  EggplantAdmin.nonce,
          period: $('#eg-pos-period').val(),
          from:   $('#eg-pos-from').val(),
          to:     $('#eg-pos-to').val()
        }, function(res){
          $('#eg-pos-report-msg').hide();
          if(!res.success){ $('#eg-pos-report-msg').text(res.data||'Error').show(); return; }

          var s = res.data.summary;
          var t = s.totals;

          // Totals summary cards.
          var cards = '<div class="eg-dash-cards">';
          cards += '<div class="eg-dash-card"><span class="eg-dash-number">'+parseInt(t.sale_count||0)+'</span><span class="eg-dash-label"><?php echo esc_js( __('Sales','eggplant') ); ?></span></div>';
          cards += '<div class="eg-dash-card"><span class="eg-dash-number">'+parseInt(t.units_sold||0)+'</span><span class="eg-dash-label"><?php echo esc_js( __('Units Sold','eggplant') ); ?></span></div>';
          cards += '<div class="eg-dash-card"><span class="eg-dash-number">'+fmt(t.subtotal)+'</span><span class="eg-dash-label"><?php echo esc_js( __('Subtotal','eggplant') ); ?></span></div>';
          cards += '<div class="eg-dash-card"><span class="eg-dash-number">'+fmt(t.tax_total)+'</span><span class="eg-dash-label"><?php echo esc_js( __('Tax Collected','eggplant') ); ?></span></div>';
          cards += '<div class="eg-dash-card"><span class="eg-dash-number">'+fmt(t.revenue)+'</span><span class="eg-dash-label"><?php echo esc_js( __('Total Revenue','eggplant') ); ?></span></div>';
          cards += '</div>';
          $('#eg-pos-totals-summary').html(cards);

          // Period table.
          var html = '<table class="widefat striped"><thead><tr>';
          html += '<th><?php echo esc_js( __('Period','eggplant') ); ?></th>';
          html += '<th><?php echo esc_js( __('Sales','eggplant') ); ?></th>';
          html += '<th><?php echo esc_js( __('Units','eggplant') ); ?></th>';
          html += '<th><?php echo esc_js( __('Subtotal','eggplant') ); ?></th>';
          html += '<th><?php echo esc_js( __('Tax','eggplant') ); ?></th>';
          html += '<th><?php echo esc_js( __('Revenue','eggplant') ); ?></th>';
          html += '</tr></thead><tbody>';
          if(s.rows.length){
            $.each(s.rows, function(i,r){
              html += '<tr><td>'+$('<span>').text(r.period_label).html()+'</td>';
              html += '<td>'+parseInt(r.sale_count)+'</td>';
              html += '<td>'+parseInt(r.units_sold)+'</td>';
              html += '<td>'+fmt(r.subtotal)+'</td>';
              html += '<td>'+fmt(r.tax_total)+'</td>';
              html += '<td>'+fmt(r.revenue)+'</td></tr>';
            });
          } else {
            html += '<tr><td colspan="6"><?php echo esc_js( __('No sales in this range.','eggplant') ); ?></td></tr>';
          }
          html += '</tbody></table>';
          $('#eg-pos-report-table').html(html);
          $('#eg-pos-report-wrap').show();
        }, 'json');
      });
    })(jQuery);
    </script>
    <?php
  }

  // ------------------------------------------------------------------ page: inventory

  public static function page_inventory(): void {
    $low_stock_items = Eggplant_POS::get_low_stock_items();
    $all_items       = Eggplant_POS::get_all_items();
    ?>
    <div class="wrap eg-admin">
      <h1><?php esc_html_e( 'POS – Inventory', 'eggplant' ); ?></h1>

      <?php if ( ! empty( $low_stock_items ) ) : ?>
      <div class="notice notice-warning is-dismissible">
        <p><strong><?php
          /* translators: %d: number of items */
          printf( esc_html__( '%d item(s) are at or below their reorder level and need to be restocked.', 'eggplant' ), count( $low_stock_items ) );
        ?></strong></p>
      </div>

      <div class="eg-card">
        <h2 style="color:#b45309;"><?php esc_html_e( '⚠ Low Stock – Reorder Required', 'eggplant' ); ?></h2>
        <table class="widefat striped">
          <thead>
            <tr>
              <th><?php esc_html_e( 'Name', 'eggplant' ); ?></th>
              <th><?php esc_html_e( 'Category', 'eggplant' ); ?></th>
              <th><?php esc_html_e( 'Current Stock', 'eggplant' ); ?></th>
              <th><?php esc_html_e( 'Reorder Level', 'eggplant' ); ?></th>
              <th><?php esc_html_e( 'Restock', 'eggplant' ); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ( $low_stock_items as $item ) : ?>
            <tr>
              <td><strong><?php echo esc_html( $item['name'] ); ?></strong></td>
              <td><?php echo esc_html( $item['category'] ); ?></td>
              <td class="eg-low-stock"><strong><?php echo esc_html( $item['stock_quantity'] ); ?></strong></td>
              <td><?php echo esc_html( $item['reorder_level'] ); ?></td>
              <td>
                <span class="eg-stock-adjust-wrap" data-id="<?php echo esc_attr( $item['id'] ); ?>">
                  <input type="number" class="eg-stock-delta small-text" min="1" step="1" value="1" style="width:60px;">
                  <button class="button button-primary button-small eg-pos-restock" data-id="<?php echo esc_attr( $item['id'] ); ?>">
                    <?php esc_html_e( '+ Add Stock', 'eggplant' ); ?>
                  </button>
                  <span class="eg-stock-result" id="eg-stock-result-<?php echo esc_attr( $item['id'] ); ?>"></span>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else : ?>
      <div class="notice notice-success is-dismissible">
        <p><?php esc_html_e( 'All items are well stocked. No reorders needed at this time.', 'eggplant' ); ?></p>
      </div>
      <?php endif; ?>

      <div class="eg-card">
        <h2><?php esc_html_e( 'All Inventory', 'eggplant' ); ?></h2>
        <table class="widefat striped">
          <thead>
            <tr>
              <th><?php esc_html_e( 'Name', 'eggplant' ); ?></th>
              <th><?php esc_html_e( 'Category', 'eggplant' ); ?></th>
              <th><?php esc_html_e( 'In Stock', 'eggplant' ); ?></th>
              <th><?php esc_html_e( 'Reorder At', 'eggplant' ); ?></th>
              <th><?php esc_html_e( 'Status', 'eggplant' ); ?></th>
              <th><?php esc_html_e( 'Adjust Stock', 'eggplant' ); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ( $all_items as $item ) :
              $low = ( (int) $item['stock_quantity'] <= (int) $item['reorder_level'] );
            ?>
            <tr class="<?php echo $low ? 'eg-row-low-stock' : ''; ?>">
              <td><?php echo esc_html( $item['name'] ); ?></td>
              <td><?php echo esc_html( $item['category'] ); ?></td>
              <td class="<?php echo $low ? 'eg-low-stock' : ''; ?>">
                <strong id="eg-stock-qty-<?php echo esc_attr( $item['id'] ); ?>"><?php echo esc_html( $item['stock_quantity'] ); ?></strong>
              </td>
              <td><?php echo esc_html( $item['reorder_level'] ); ?></td>
              <td>
                <?php if ( ! $item['active'] ) : ?>
                  <span style="color:#777;"><?php esc_html_e( 'Inactive', 'eggplant' ); ?></span>
                <?php elseif ( $low ) : ?>
                  <span style="color:#b45309;font-weight:bold;"><?php esc_html_e( 'Low / Reorder', 'eggplant' ); ?></span>
                <?php else : ?>
                  <span style="color:#2a9d8f;"><?php esc_html_e( 'OK', 'eggplant' ); ?></span>
                <?php endif; ?>
              </td>
              <td>
                <span class="eg-stock-adjust-wrap" data-id="<?php echo esc_attr( $item['id'] ); ?>">
                  <input type="number" class="eg-stock-delta small-text" step="1" value="1" style="width:60px;" title="<?php esc_attr_e( 'Positive to add, negative to subtract', 'eggplant' ); ?>">
                  <button class="button button-small eg-pos-restock" data-id="<?php echo esc_attr( $item['id'] ); ?>">
                    <?php esc_html_e( 'Apply', 'eggplant' ); ?>
                  </button>
                  <span class="eg-stock-result" id="eg-stock-result-<?php echo esc_attr( $item['id'] ); ?>"></span>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <script>
    (function($){
      $(document).on('click', '.eg-pos-restock', function(){
        var id    = $(this).data('id');
        var delta = parseInt($(this).closest('.eg-stock-adjust-wrap').find('.eg-stock-delta').val(), 10);
        if(isNaN(delta) || delta === 0){ return; }
        $.post(EggplantAdmin.ajaxUrl, {
          action: 'eggplant_pos_adjust_stock',
          nonce:  EggplantAdmin.nonce,
          id:     id,
          delta:  delta
        }, function(res){
          if(res.success){
            $('#eg-stock-qty-'+id).text(res.data.stock_quantity);
            $('#eg-stock-result-'+id).text('<?php echo esc_js( __('Updated!','eggplant') ); ?>').show();
            setTimeout(function(){ location.reload(); }, 1000);
          }
        }, 'json');
      });
    })(jQuery);
    </script>
    <?php
  }

  // ------------------------------------------------------------------ page: profit

  public static function page_profit(): void {
    $today      = current_time( 'Y-m-d' );
    $month_from = current_time( 'Y-m-01' );
    ?>
    <div class="wrap eg-admin">
      <h1><?php esc_html_e( 'POS – Profit Accounting', 'eggplant' ); ?></h1>

      <div class="eg-card">
        <h2><?php esc_html_e( 'Date Range', 'eggplant' ); ?></h2>
        <div class="eg-form-row">
          <label><?php esc_html_e( 'From', 'eggplant' ); ?></label>
          <input type="date" id="eg-pos-profit-from" value="<?php echo esc_attr( $month_from ); ?>">
        </div>
        <div class="eg-form-row">
          <label><?php esc_html_e( 'To', 'eggplant' ); ?></label>
          <input type="date" id="eg-pos-profit-to" value="<?php echo esc_attr( $today ); ?>">
        </div>
        <button class="button button-primary" id="eg-pos-run-profit"><?php esc_html_e( 'Run Report', 'eggplant' ); ?></button>
        <span class="eg-msg" id="eg-pos-profit-msg"></span>
      </div>

      <div id="eg-pos-profit-wrap" style="display:none;">
        <div class="eg-card">
          <h2><?php esc_html_e( 'Profit Summary', 'eggplant' ); ?></h2>
          <div id="eg-pos-profit-summary"></div>
        </div>
        <div class="eg-card">
          <h2><?php esc_html_e( 'Profit by Item', 'eggplant' ); ?></h2>
          <div class="eg-table-wrap" id="eg-pos-profit-table"></div>
        </div>
      </div>
    </div>

    <script>
    (function($){
      function fmt(n){ return '$'+parseFloat(n||0).toFixed(2); }

      $('#eg-pos-run-profit').on('click', function(){
        $('#eg-pos-profit-msg').text('<?php echo esc_js( __( 'Loading…', 'eggplant' ) ); ?>').show();
        $.post(EggplantAdmin.ajaxUrl, {
          action: 'eggplant_pos_get_sales_report',
          nonce:  EggplantAdmin.nonce,
          period: 'day',
          from:   $('#eg-pos-profit-from').val(),
          to:     $('#eg-pos-profit-to').val()
        }, function(res){
          $('#eg-pos-profit-msg').hide();
          if(!res.success){ $('#eg-pos-profit-msg').text(res.data||'Error').show(); return; }

          var t  = res.data.summary.totals;
          var bi = res.data.by_item;

          // Summary cards.
          var margin = (t.revenue > 0) ? ((t.gross_profit / t.revenue) * 100).toFixed(1) : '0.0';
          var cards  = '<div class="eg-dash-cards">';
          cards += '<div class="eg-dash-card"><span class="eg-dash-number">'+fmt(t.revenue)+'</span><span class="eg-dash-label"><?php echo esc_js( __('Total Revenue','eggplant') ); ?></span></div>';
          cards += '<div class="eg-dash-card"><span class="eg-dash-number">'+fmt(t.cogs)+'</span><span class="eg-dash-label"><?php echo esc_js( __('Cost of Goods (COGS)','eggplant') ); ?></span></div>';
          cards += '<div class="eg-dash-card"><span class="eg-dash-number">'+fmt(t.tax_total)+'</span><span class="eg-dash-label"><?php echo esc_js( __('Tax Collected','eggplant') ); ?></span></div>';
          cards += '<div class="eg-dash-card"><span class="eg-dash-number">'+fmt(t.gross_profit)+'</span><span class="eg-dash-label"><?php echo esc_js( __('Gross Profit','eggplant') ); ?></span></div>';
          cards += '<div class="eg-dash-card"><span class="eg-dash-number">'+margin+'%</span><span class="eg-dash-label"><?php echo esc_js( __('Gross Margin','eggplant') ); ?></span></div>';
          cards += '</div>';
          cards += '<p class="description"><?php echo esc_js( __( 'Gross Profit = Revenue (excl. tax) − Cost of Goods. Enter each item\'s cost price on the Items page to see accurate figures.', 'eggplant' ) ); ?></p>';
          $('#eg-pos-profit-summary').html(cards);

          // Per-item table.
          var html = '<table class="widefat striped"><thead><tr>';
          html += '<th><?php echo esc_js( __('Item','eggplant') ); ?></th>';
          html += '<th><?php echo esc_js( __('Units Sold','eggplant') ); ?></th>';
          html += '<th><?php echo esc_js( __('Avg Sale Price','eggplant') ); ?></th>';
          html += '<th><?php echo esc_js( __('Avg Cost','eggplant') ); ?></th>';
          html += '<th><?php echo esc_js( __('Revenue','eggplant') ); ?></th>';
          html += '<th><?php echo esc_js( __('COGS','eggplant') ); ?></th>';
          html += '<th><?php echo esc_js( __('Tax','eggplant') ); ?></th>';
          html += '<th><?php echo esc_js( __('Gross Profit','eggplant') ); ?></th>';
          html += '<th><?php echo esc_js( __('Margin %','eggplant') ); ?></th>';
          html += '</tr></thead><tbody>';
          if(bi && bi.length){
            $.each(bi, function(i,r){
              var m = (r.revenue > 0) ? ((r.gross_profit/r.revenue)*100).toFixed(1) : '0.0';
              html += '<tr>';
              html += '<td>'+$('<span>').text(r.item_name).html()+'</td>';
              html += '<td>'+parseInt(r.units_sold)+'</td>';
              html += '<td>'+fmt(r.avg_price)+'</td>';
              html += '<td>'+fmt(r.avg_cost)+'</td>';
              html += '<td>'+fmt(r.revenue)+'</td>';
              html += '<td>'+fmt(r.cogs)+'</td>';
              html += '<td>'+fmt(r.tax_total)+'</td>';
              html += '<td>'+fmt(r.gross_profit)+'</td>';
              html += '<td>'+m+'%</td>';
              html += '</tr>';
            });
          } else {
            html += '<tr><td colspan="9"><?php echo esc_js( __('No sales data in this range.','eggplant') ); ?></td></tr>';
          }
          html += '</tbody></table>';
          $('#eg-pos-profit-table').html(html);
          $('#eg-pos-profit-wrap').show();
        }, 'json');
      });
    })(jQuery);
    </script>
    <?php
  }
}
