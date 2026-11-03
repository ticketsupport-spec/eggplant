# Eggplant Event Portal

A WordPress plugin that transforms your site into a full-screen event-center portal with a carousel, availability calendar, booking-request form, and a complete admin panel.

## Floor Calendar (Two Floors, One Booking Per Day)

- Name your two floors in `/wp-admin/admin.php?page=eggplant-settings` (Floor 1 / Floor 2 Name) and choose the Private, Public and Unavailable calendar colors.
- Manage bookings in `/wp-admin/admin.php?page=eggplant-floor-calendar`. Click a floor on any day to mark it **Booked** (as a **PRIVATE EVENT** or **PUBLIC EVENT**), **Unavailable**, or **Available** again.
- Public events can have a title, times, description, image and tickets/info link. Visitors click a PUBLIC EVENT on the front-page calendar to see these details in a pop-up. Private event details and internal notes are never sent to the public site.
- Use **Block Out Recurring Days** to block every selected weekday (e.g. every Tuesday, or Tue/Wed/Thu) on one or both floors for this week, this month, the month shown, the rest of the year, next year, or any custom range. Days that are already booked are skipped. **Unblock** removes only blocked days.
- Visitors choose the main floor, upstairs, or both floors when requesting a full-day booking; no preferred time is needed. Both floors can only be requested when both are available. Approving a request in Booking Requests books the requested floor(s) as a PRIVATE EVENT, and changing it from Approved frees them again.

## Time Slots

- On `/wp-admin/admin.php?page=eggplant-slots`, keep staff-facing start/end times and select the main floor, upper/top floor, or both (using your configured floor names).
- Use **Edit** to change the date, times, floor, label, and status of an existing slot, then **Save Changes**. **Cancel** returns to adding slots. Floor and status can also be changed directly in the list.
- A read-only floor calendar on this page refreshes after adding, editing, or deleting a slot. Booked slots color the selected floor(s) as **Private Event** (the default purple blackout) on both calendars; held slots show as **Unavailable**. Available slots do not reserve a floor. Public time-slot details also show the selected floor.
- Set **Event Type** to **PUBLIC EVENT** to show the slot's label and times publicly (click for the pop-up). Use **Upload / Choose Image** (WordPress Media Library) or paste an image URL; when booked, the image covers that day on the public and admin calendars (or just that floor if the other floor differs) and scales with the calendar on desktop and mobile. Event Type can also be changed directly in the list; existing slots stay private on upgrade.
- Availability remains full-day per floor, even when a slot has start/end times. Multiple slots on the same floor/date keep it occupied until all booked/held slots are released or deleted. Existing slots are assigned to **Both Floors** on upgrade; edit their floor if needed.
- Independently managed floor-calendar events and blocks are preserved and take display priority. Releasing a time slot never deletes those entries. To release a time-slot reservation, edit it on **Time Slots**, not **Floor Calendar**.

Run the standalone regressions with `php tests/time-slots.php` (requires `pdo_sqlite`) and `node tests/time-slots-admin.js`.

## Staff Clock and Payroll

- Use the `[eggplant_staff_clock]` shortcode on any page to show the front-end time clock for staff tablets or kiosks.
- Add employees in `/wp-admin/admin.php?page=eggplant-staff` with their staff ID, name, hourly wage, and overtime multiplier.
- Review and edit recent clock entries in `/wp-admin/admin.php?page=eggplant-staff-clock`.
- Configure payroll settings and process biweekly pay periods in `/wp-admin/admin.php?page=eggplant-payroll`.
- The payroll screen calculates regular hours, weekly Ontario overtime, gross pay, deductions, net pay, and yearly T4-style totals from processed pay periods.
- Ontario deduction defaults are editable in the payroll settings screen and should be reviewed each tax year before use.


## Ticketing System

- Use the `[eggplant_box_office]` shortcode on a public page to sell tickets with optional promo codes.
- Use the `[eggplant_ticket_scanner]` shortcode on a staff-only page to validate ticket barcodes at entry.
- Configure the Box Office and Ticket Scanner page URLs in `/wp-admin/admin.php?page=eggplant-settings`.
- Manage pricing, discounts, scans, orders, and settlements from the new Ticketing admin pages under Event Portal.

## Automatic Database Upgrades

Eggplant manages its own database schema versioning via the `EGGPLANT_DB_VERSION` constant defined in `eggplant.php`.

### How it works

1. **On activation** – `Eggplant_Activator::activate()` creates the three plugin tables (`{prefix}eggplant_time_slots`, `{prefix}eggplant_events`, `{prefix}eggplant_bookings`) using WordPress's `dbDelta()` function and stores the current schema version in the `eggplant_db_version` option.

2. **On every page load** – `Eggplant_DB_Migrator::maybe_upgrade()` runs on the `plugins_loaded` hook and checks two things:
   - Whether the stored `eggplant_db_version` option is older than `EGGPLANT_DB_VERSION`.
   - Whether any of the three tables are physically missing from the database.
   If either condition is true, `dbDelta()` is called automatically to create or upgrade the tables and the stored version is updated.

This means tables are recreated automatically even if the activation hook never fired (e.g. the plugin files were deployed manually, or the site was added to a multisite network after activation).

### Multisite / network activation

When the plugin is network-activated, the activation hook creates the tables for **every site** in the network. The runtime upgrade routine also iterates all sites when running in the network-admin context.

### Forcing a full rebuild

To force the plugin to drop and recreate all tables from scratch:

1. **Deactivate** the plugin in WP Admin → Plugins.
2. **Activate** it again.

> **Warning:** deactivating the plugin does *not* delete existing data. If you need a clean slate, remove the plugin tables manually from the database before reactivating.

### Debugging DB errors

When `WP_DEBUG` is enabled, any error returned by `dbDelta()` is written to the PHP error log via `error_log()` so you can diagnose schema issues without checking the database directly.
