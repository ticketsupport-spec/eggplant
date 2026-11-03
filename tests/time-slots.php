<?php
/**
 * Standalone regression tests: php tests/time-slots.php (requires pdo_sqlite).
 */

define( 'ARRAY_A', 'ARRAY_A' );

function __( $text, $domain = '' ) { return $text; }
function sanitize_text_field( $text ) { return trim( strip_tags( $text ) ); }
function sanitize_key( $text ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $text ) ); }
function wp_unslash( $value ) { return $value; }
function wp_kses_post( $value ) { return $value; }
function esc_url_raw( $value ) { return $value; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_html__( $text, $domain = '' ) { return esc_html( $text ); }
function esc_html_e( $text, $domain = '' ) { echo esc_html( $text ); }
function esc_attr_e( $text, $domain = '' ) { echo esc_attr( $text ); }
function selected( $value, $expected ) { if ( $value === $expected ) echo ' selected'; }
function add_action( ...$args ) {}
function check_ajax_referer( ...$args ) {}
function current_user_can( $capability ) { return true; }

class Eggplant_Settings {
  public static function get_all(): array {
    return array( 'floor_1_name' => 'Main Floor', 'floor_2_name' => 'Top Floor' );
  }
}

class Slot_Test_Response extends Exception {
  public $success;
  public $data;

  public function __construct( bool $success, $data = null ) {
    parent::__construct();
    $this->success = $success;
    $this->data = $data;
  }
}

function wp_send_json_success( $data = null ) { throw new Slot_Test_Response( true, $data ); }
function wp_send_json_error( $data = null ) { throw new Slot_Test_Response( false, $data ); }

class Slot_Test_DB {
  public $prefix = 'wp_';
  public $insert_id = 0;
  public $pdo;

  public function __construct() {
    $this->pdo = new PDO( 'sqlite::memory:' );
    $this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
  }

  public function prepare( string $sql, ...$args ): string {
    $index = 0;
    return preg_replace_callback( '/%[sd]/', function ( $match ) use ( $args, &$index ) {
      $value = $args[ $index++ ];
      return '%d' === $match[0] ? (string) (int) $value : $this->pdo->quote( $value );
    }, $sql );
  }

  public function get_results( string $sql, $format ): array {
    return $this->pdo->query( $sql )->fetchAll( PDO::FETCH_ASSOC );
  }

  public function get_row( string $sql, $format ) {
    return $this->pdo->query( $sql )->fetch( PDO::FETCH_ASSOC ) ?: null;
  }

  public function get_var( string $sql ) {
    if ( preg_match( "/SHOW COLUMNS FROM (\w+) LIKE '(\w+)'/", $sql, $matches ) ) {
      foreach ( $this->get_results( 'PRAGMA table_info(' . $matches[1] . ')', ARRAY_A ) as $column ) {
        if ( $matches[2] === $column['name'] ) return $column['name'];
      }
      return null;
    }
    return $this->pdo->query( $sql )->fetchColumn();
  }

  public function query( string $sql ) { return $this->pdo->exec( $sql ); }

  public function insert( string $table, array $data, array $formats ) {
    $sql = 'INSERT INTO ' . $table . ' (' . implode( ',', array_keys( $data ) ) . ') VALUES ('
      . implode( ',', array_fill( 0, count( $data ), '?' ) ) . ')';
    $ok = $this->pdo->prepare( $sql )->execute( array_values( $data ) );
    $this->insert_id = (int) $this->pdo->lastInsertId();
    return $ok;
  }

  public function update( string $table, array $data, array $where, array $formats, array $where_formats ) {
    $set = implode( ',', array_map( static function ( $key ) { return $key . ' = ?'; }, array_keys( $data ) ) );
    $condition = implode( ' AND ', array_map( static function ( $key ) { return $key . ' = ?'; }, array_keys( $where ) ) );
    $stmt = $this->pdo->prepare( "UPDATE $table SET $set WHERE $condition" );
    $stmt->execute( array_merge( array_values( $data ), array_values( $where ) ) );
    return $stmt->rowCount();
  }

  public function delete( string $table, array $where, array $formats ) {
    $condition = implode( ' AND ', array_map( static function ( $key ) { return $key . ' = ?'; }, array_keys( $where ) ) );
    $stmt = $this->pdo->prepare( "DELETE FROM $table WHERE $condition" );
    $stmt->execute( array_values( $where ) );
    return $stmt->rowCount();
  }
}

require_once __DIR__ . '/../eggplant/includes/class-eggplant-db.php';
require_once __DIR__ . '/../eggplant/includes/class-eggplant-admin.php';
require_once __DIR__ . '/../eggplant/includes/class-eggplant-floor-calendar.php';
require_once __DIR__ . '/../eggplant/includes/class-eggplant-activator.php';

function expect( bool $condition, string $message ): void {
  if ( ! $condition ) throw new RuntimeException( $message );
}

function request( $handler, string $method, array $post = array(), array $get = array() ): Slot_Test_Response {
  $_POST = $post;
  $_GET = $get;
  try {
    $handler->$method();
  } catch ( Slot_Test_Response $response ) {
    return $response;
  }
  throw new RuntimeException( 'Expected a JSON response.' );
}

$wpdb = new Slot_Test_DB();
$wpdb->query( "CREATE TABLE wp_eggplant_time_slots (
  id INTEGER PRIMARY KEY AUTOINCREMENT, slot_date TEXT, start_time TEXT, end_time TEXT,
  label TEXT DEFAULT '', status TEXT DEFAULT 'available'
)" );
$wpdb->query( "INSERT INTO wp_eggplant_time_slots (slot_date,start_time,end_time,status)
  VALUES ('2026-11-03','18:00','23:00','booked')" );
$migration = new ReflectionMethod( Eggplant_Activator::class, 'ensure_column' );
if ( PHP_VERSION_ID < 80100 ) $migration->setAccessible( true );
$migration->invoke( null, 'eggplant_time_slots', 'floor', "VARCHAR(20) NOT NULL DEFAULT 'both'" );
$migration->invoke( null, 'eggplant_time_slots', 'floor', "VARCHAR(20) NOT NULL DEFAULT 'both'" );
expect( 'both' === Eggplant_DB::get_slot( 1 )['floor'], 'Upgrade must preserve existing slots and default to both floors.' );
$wpdb->query( "CREATE TABLE wp_eggplant_floor_days (
  id INTEGER PRIMARY KEY AUTOINCREMENT, day_date TEXT, floor TEXT, status TEXT, visibility TEXT,
  title TEXT, description TEXT, start_time TEXT, end_time TEXT, image_url TEXT, link_url TEXT,
  note TEXT, booking_id INTEGER, UNIQUE(day_date,floor)
)" );

$admin = new Eggplant_Admin();
$slot = array(
  'slot_date' => '2026-11-04', 'start_time' => '18:30', 'end_time' => '23:30',
  'floor' => 'floor_1', 'status' => 'booked', 'label' => 'Client booking',
);
$result = request( $admin, 'ajax_add_slot', $slot );
expect( $result->success, 'Adding a slot should succeed.' );
$id = $result->data['id'];
expect( '18:30' === Eggplant_DB::get_slot( $id )['start_time'], 'Start time must be retained.' );
expect( ! Eggplant_Floor_Calendar::is_available( '2026-11-04', 'floor_1' ), 'Booked main floor must be unavailable.' );
expect( Eggplant_Floor_Calendar::is_available( '2026-11-04', 'floor_2' ), 'Other floor must stay available.' );
$public = Eggplant_Floor_Calendar::get_public_month( '2026-11' );
expect( array( 'status' => 'booked', 'visibility' => 'private' ) === $public['2026-11-04']['floor_1'], 'Public data must expose only private status, never slot notes/times/labels.' );
expect( isset( $public['2026-11-03']['floor_1'], $public['2026-11-03']['floor_2'] ), 'Legacy bookings must occupy both floors.' );

$edited = array_merge( $slot, array(
  'id' => $id, 'slot_date' => '2026-11-05', 'start_time' => '09:00', 'end_time' => '12:00',
  'floor' => 'floor_2', 'label' => 'Updated', 'status' => 'held',
) );
expect( request( $admin, 'ajax_update_slot', $edited )->success, 'Full editing should succeed.' );
$loaded = request( $admin, 'ajax_get_slots', array(), array( 'id' => $id ) );
expect( $loaded->success && 'Updated' === $loaded->data['label'] && '09:00' === $loaded->data['start_time']
  && '12:00' === $loaded->data['end_time'], 'Edit must persist all fields and be loadable.' );
$public = Eggplant_Floor_Calendar::get_public_month( '2026-11' );
expect( ! isset( $public['2026-11-04'] ), 'Moving a slot must free its old date/floor.' );
expect( array( 'status' => 'blocked' ) === $public['2026-11-05']['floor_2'], 'Holds must show as unavailable.' );
expect( false === Eggplant_Floor_Calendar::save_day( '2026-11-05', 'floor_2', array(), true ), 'Approvals must not claim a floor occupied by a slot.' );
expect( request( $admin, 'ajax_update_slot', array( 'id' => $id, 'floor' => 'both' ) )->success, 'Inline floor editing should succeed.' );
expect( request( $admin, 'ajax_update_slot', array( 'id' => $id, 'status' => 'booked' ) )->success, 'Inline status editing should succeed.' );
expect( ! Eggplant_Floor_Calendar::is_available( '2026-11-05', 'floor_1' ), 'Both floor choice must occupy the main floor too.' );
$calendar = new Eggplant_Floor_Calendar();
$month = request( $calendar, 'ajax_get_month', array( 'month' => '2026-11' ) );
expect( $month->success && $month->data['2026-11-05']['floor_2']['time_slots'], 'Admin calendar must identify slot-owned occupancy.' );
expect( false !== strpos( $month->data['2026-11-05']['floor_2']['note'], '09:00 - 12:00' ), 'Admin calendar must retain staff-facing times.' );
foreach ( array( 'ajax_save_day', 'ajax_clear_day' ) as $method ) {
  expect( ! request( $calendar, $method, array( 'date' => '2026-11-05', 'floor' => 'floor_2', 'status' => 'available' ) )->success,
    'Floor calendar must explicitly reject attempts to clear slot-owned reservations.' );
}
ob_start();
$admin->page_slots();
$html = ob_get_clean();
expect( false !== strpos( $html, 'eg-edit-slot' ) && false !== strpos( $html, 'eg-slot-cancel' ), 'Slots page must render editing controls.' );
expect( false !== strpos( $html, 'Top Floor' ) && false !== strpos( $html, 'Both Floors' ), 'Floor choices must use configured names.' );
expect( false !== strpos( $html, 'id="eg-fc-grid"' ) && false === strpos( $html, 'id="eg-fc-editor"' ), 'Slots page must include a read-only calendar without a duplicate editor.' );

$second = Eggplant_DB::insert_slot( array_merge( $slot, array( 'slot_date' => '2026-11-05' ) ) );
Eggplant_DB::delete_slot( $id );
expect( ! Eggplant_Floor_Calendar::is_available( '2026-11-05', 'floor_1' ), 'Other booked slots must keep the floor occupied after deletion.' );
Eggplant_DB::update_slot_status( $second, 'available' );
expect( Eggplant_Floor_Calendar::is_available( '2026-11-05', 'floor_1' ), 'Releasing the final slot should free the floor.' );
Eggplant_DB::update_slot_status( $second, 'held' );
$bulk = Eggplant_Floor_Calendar::bulk_apply( 'unblock', '2026-11-05', '2026-11-05', array( 4 ), array( 'floor_1' ) );
expect( 0 === $bulk['changed'] && 1 === $bulk['skipped'], 'Bulk unblocking must not claim to release a held slot.' );
$bulk = Eggplant_Floor_Calendar::bulk_apply( 'block', '2026-11-05', '2026-11-05', array( 4 ), array( 'floor_1' ) );
expect( 0 === $bulk['changed'] && 1 === $bulk['skipped'], 'Bulk blocking must skip occupied slots.' );

Eggplant_Floor_Calendar::save_day( '2026-11-05', 'floor_1', array(
  'status' => 'booked', 'visibility' => 'public', 'title' => 'Public concert', 'start_time' => '20:00',
) );
$public = Eggplant_Floor_Calendar::get_public_month( '2026-11' );
expect( 'Public concert' === $public['2026-11-05']['floor_1']['title'], 'Independent public events must retain their details and priority.' );
Eggplant_DB::delete_slot( $second );
expect( ! Eggplant_Floor_Calendar::is_available( '2026-11-05', 'floor_1' ), 'Deleting slots must never delete independent floor events.' );
foreach ( array(
  array( 'slot_date' => '2026-02-30' ), array( 'start_time' => '25:00' ),
  array( 'end_time' => '12:99' ), array( 'floor' => 'invalid' ), array( 'status' => 'invalid' ),
) as $invalid ) {
  expect( ! request( $admin, 'ajax_add_slot', array_merge( $slot, $invalid ) )->success, 'Invalid fields must fail explicitly.' );
}
expect( ! request( $admin, 'ajax_update_slot', array_merge( $edited, array( 'id' => 999 ) ) )->success, 'Editing a missing slot must fail.' );
expect( ! request( $admin, 'ajax_get_slots', array(), array( 'id' => 999 ) )->success, 'Loading a missing slot must fail.' );
echo "Time-slot regressions passed.\n";
