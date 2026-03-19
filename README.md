# Eggplant Event Portal

A WordPress plugin that transforms your site into a full-screen event-center portal with a carousel, availability calendar, booking-request form, and a complete admin panel.

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
