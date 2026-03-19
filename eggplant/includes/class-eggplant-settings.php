<?php

/**
 * Settings helper – single place for reading/writing plugin options.
 *
 * @since 1.0.0
 * @package Eggplant
 */

class Eggplant_Settings {

  /**
   * Return all settings, merged with defaults so keys are always present.
   *
   * @return array<string,mixed>
   */
  public static function get_all(): array {
    $defaults = array(
      'portal_title'      => 'Event Center',
      'bg_color'          => '#000000',
      'primary_color'     => '#e63946',
      'secondary_color'   => '#457b9d',
      'text_color'        => '#f1faee',
      'available_color'   => '#2a9d8f',
      'booked_color'      => '#e63946',
      'held_color'        => '#f4a261',
      'carousel_speed'    => 5000,
      'carousel_autoplay' => 1,
      'custom_css'        => '',
      'contact_email'     => get_option( 'admin_email' ),
      'show_booking_form' => 1,
    );
    $saved = get_option( 'eggplant_settings', array() );
    return wp_parse_args( (array) $saved, $defaults );
  }

  /**
   * Return a single setting value.
   *
   * @param string $key
   * @param mixed  $default
   * @return mixed
   */
  public static function get( string $key, $default = null ) {
    $all = self::get_all();
    return isset( $all[ $key ] ) ? $all[ $key ] : $default;
  }

  /**
   * Update settings (merges supplied array over existing values).
   *
   * @param array<string,mixed> $data
   */
  public static function update( array $data ): void {
    $current = get_option( 'eggplant_settings', array() );
    update_option( 'eggplant_settings', array_merge( (array) $current, $data ) );
  }
}
