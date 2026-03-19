<?php
/**
 * Front-page template – takes over the entire WordPress page.
 * Renders the event-center portal: carousel, calendar, booking form.
 *
 * @since 1.0.0
 * @package Eggplant
 */

if ( ! defined( 'WPINC' ) ) {
  die;
}

$settings = Eggplant_Settings::get_all();
$events   = Eggplant_DB::get_active_events();
$title    = $settings['portal_title'] ?? 'Event Center';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo esc_html( $title ); ?></title>
  <?php wp_head(); ?>
</head>
<body class="eg-portal-page">

<!-- ======================================================
     CAROUSEL
====================================================== -->
<?php if ( ! empty( $events ) ) : ?>
<section class="eg-carousel" aria-label="<?php esc_attr_e( 'Upcoming Events', 'eggplant' ); ?>">
  <div class="eg-carousel__track" id="eg-carousel-track">
    <?php foreach ( $events as $event ) : ?>
    <article class="eg-carousel__slide">
      <?php if ( $event['link_url'] ) : ?>
        <a href="<?php echo esc_url( $event['link_url'] ); ?>" class="eg-carousel__link" target="_blank" rel="noopener noreferrer">
      <?php endif; ?>

      <?php if ( $event['image_url'] ) : ?>
        <div class="eg-carousel__img-wrap">
          <img src="<?php echo esc_url( $event['image_url'] ); ?>" alt="<?php echo esc_attr( $event['title'] ); ?>" loading="lazy">
        </div>
      <?php endif; ?>

      <div class="eg-carousel__content">
        <h2 class="eg-carousel__title"><?php echo esc_html( $event['title'] ); ?></h2>
        <?php if ( $event['description'] ) : ?>
          <p class="eg-carousel__desc"><?php echo wp_kses_post( $event['description'] ); ?></p>
        <?php endif; ?>
        <?php if ( $event['start_date'] ) : ?>
          <p class="eg-carousel__date">
            <?php
            echo esc_html(
              date_i18n( get_option( 'date_format' ), strtotime( $event['start_date'] ) ) .
              ( $event['end_date'] ? ' – ' . date_i18n( get_option( 'date_format' ), strtotime( $event['end_date'] ) ) : '' )
            );
            ?>
          </p>
        <?php endif; ?>
      </div>

      <?php if ( $event['link_url'] ) : ?>
        </a>
      <?php endif; ?>
    </article>
    <?php endforeach; ?>
  </div>

  <?php if ( count( $events ) > 1 ) : ?>
  <button class="eg-carousel__btn eg-carousel__btn--prev" aria-label="<?php esc_attr_e( 'Previous', 'eggplant' ); ?>">&#10094;</button>
  <button class="eg-carousel__btn eg-carousel__btn--next" aria-label="<?php esc_attr_e( 'Next', 'eggplant' ); ?>">&#10095;</button>
  <div class="eg-carousel__dots" id="eg-carousel-dots">
    <?php foreach ( $events as $i => $_ ) : ?>
    <button class="eg-carousel__dot <?php echo $i === 0 ? 'eg-carousel__dot--active' : ''; ?>"
            aria-label="<?php /* translators: %d: slide number */ echo esc_attr( sprintf( __( 'Slide %d', 'eggplant' ), $i + 1 ) ); ?>"
            data-index="<?php echo esc_attr( $i ); ?>"></button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<!-- ======================================================
     MAIN CONTENT: CALENDAR + BOOKING FORM
====================================================== -->
<main class="eg-main">
  <div class="eg-container">

    <!-- FRONT PAGE INFO -->
    <?php if ( ! empty( $settings['front_page_info'] ) ) : ?>
    <div class="eg-front-info">
      <?php echo wp_kses_post( $settings['front_page_info'] ); ?>
    </div>
    <?php endif; ?>

    <!-- CALENDAR -->
    <section class="eg-calendar-section">
      <h2 class="eg-section-title"><?php esc_html_e( 'Availability Calendar', 'eggplant' ); ?></h2>

      <!-- Legend -->
      <ul class="eg-legend">
        <li><span class="eg-legend__dot eg-legend__dot--available"></span><?php esc_html_e( 'Available', 'eggplant' ); ?></li>
        <li><span class="eg-legend__dot eg-legend__dot--booked"></span><?php esc_html_e( 'Booked',    'eggplant' ); ?></li>
        <li><span class="eg-legend__dot eg-legend__dot--held"></span><?php esc_html_e( 'On Hold',   'eggplant' ); ?></li>
      </ul>

      <!-- Calendar nav -->
      <div class="eg-calendar__nav">
        <button id="eg-cal-prev" aria-label="<?php esc_attr_e( 'Previous month', 'eggplant' ); ?>">&#8592;</button>
        <span   id="eg-cal-heading"></span>
        <button id="eg-cal-next" aria-label="<?php esc_attr_e( 'Next month', 'eggplant' ); ?>">&#8594;</button>
      </div>

      <!-- Calendar grid -->
      <div class="eg-calendar__grid-wrap">
        <div class="eg-calendar__weekdays">
          <?php
          $days = array( 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' );
          foreach ( $days as $d ) {
            echo '<div class="eg-calendar__weekday">' . esc_html__( $d, 'eggplant' ) . '</div>';
          }
          ?>
        </div>
        <div class="eg-calendar__grid" id="eg-calendar-grid" aria-live="polite"></div>
      </div>

      <!-- Slot detail panel -->
      <div class="eg-slot-detail" id="eg-slot-detail" aria-live="polite" hidden>
        <h3 id="eg-slot-detail-date"></h3>
        <ul id="eg-slot-detail-list"></ul>
        <?php if ( ! empty( $settings['show_booking_form'] ) ) : ?>
        <button class="eg-btn eg-btn--primary" id="eg-slot-book-btn"><?php esc_html_e( 'Request to Book This Date', 'eggplant' ); ?></button>
        <?php endif; ?>
      </div>
    </section>

    <!-- BOOKING FORM -->
    <?php if ( ! empty( $settings['show_booking_form'] ) ) : ?>
    <section class="eg-booking-section" id="eg-booking-section">
      <h2 class="eg-section-title"><?php esc_html_e( 'Request a Booking', 'eggplant' ); ?></h2>
      <form class="eg-booking-form" id="eg-booking-form" novalidate>

        <div class="eg-form-row">
          <label for="eg-name"><?php esc_html_e( 'Full Name *', 'eggplant' ); ?></label>
          <input type="text" id="eg-name" name="name" required autocomplete="name">
        </div>

        <div class="eg-form-row">
          <label for="eg-email"><?php esc_html_e( 'Email Address *', 'eggplant' ); ?></label>
          <input type="email" id="eg-email" name="email" required autocomplete="email">
        </div>

        <div class="eg-form-row">
          <label for="eg-phone"><?php esc_html_e( 'Phone Number', 'eggplant' ); ?></label>
          <input type="tel" id="eg-phone" name="phone" autocomplete="tel">
        </div>

        <div class="eg-form-row">
          <label for="eg-event-type"><?php esc_html_e( 'Type of Event', 'eggplant' ); ?></label>
          <input type="text" id="eg-event-type" name="event_type" placeholder="<?php esc_attr_e( 'e.g. Concert, Comedy Show, Private Party…', 'eggplant' ); ?>">
        </div>

        <div class="eg-form-row">
          <label for="eg-event-date"><?php esc_html_e( 'Preferred Date', 'eggplant' ); ?></label>
          <input type="date" id="eg-event-date" name="event_date" required>
        </div>

        <div class="eg-form-row" id="eg-time-slot-row">
          <label for="eg-time-slot"><?php esc_html_e( 'Preferred Time Slot', 'eggplant' ); ?></label>
          <select id="eg-time-slot" name="time_slot_id">
            <option value=""><?php esc_html_e( '— Choose a date first —', 'eggplant' ); ?></option>
          </select>
        </div>

        <div class="eg-form-row">
          <label for="eg-message"><?php esc_html_e( 'Tell us about your event', 'eggplant' ); ?></label>
          <textarea id="eg-message" name="message" rows="5" placeholder="<?php esc_attr_e( 'Expected attendance, equipment needs, special requests…', 'eggplant' ); ?>"></textarea>
        </div>

        <div class="eg-form-row">
          <button type="submit" class="eg-btn eg-btn--primary" id="eg-booking-submit"><?php esc_html_e( 'Send Booking Request', 'eggplant' ); ?></button>
        </div>

        <div id="eg-booking-response" role="alert" aria-live="assertive"></div>
      </form>
    </section>
    <?php endif; ?>

  </div><!-- .eg-container -->
</main>

<?php wp_footer(); ?>
</body>
</html>
