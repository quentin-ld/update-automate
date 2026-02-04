=== Updates Control - Manage updates easily ===
Contributors: @quentinldd
Donate link: https://github.com/sponsors/quentin-ld/
Tags: updates, logging, security, multisite, notifications
Requires at least: 6.2
Tested up to: 6.9
Stable tag: 0.2.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html/

Log WordPress core, plugin, and theme updates with error handling, security, and optional email notifications. Supports multisite.

== Description ==

Updates Control is a WordPress plugin that records every core, plugin, and theme update (and install) in a custom log table. It uses a Zenpress-style admin interface with vertical tabs: **Update logs** and **Settings**. You get a clear history of what was updated, when, and by whom, plus optional email alerts for errors or important updates.

= Features =

* **Logging** – Records update type (core / plugin / theme), action (update / install / failed), item name and slug, version before/after, status, and optional message. Logs are stored in a dedicated table with proper indexes.
* **Error handling** – Captures update failures: redirect errors during core updates, download errors during package install/update, and upgrader completion with WP_Error result. Failed updates are logged with status "error".
* **Security** – All inputs are sanitized (log type, action type, status, versions, messages). Database access uses prepared statements. Only users with `manage_options` can view or manage logs and settings.
* **Multisite** – On multisite, each site has its own log table. When a new site is created, the table is created automatically; when a site is deleted, the table is dropped. Logs are keyed by `site_id`.
* **Cron** – Daily scheduled task deletes logs older than the configured retention period (default 90 days, configurable 1–365).
* **Notifications (optional)** – Email alerts when notifications are enabled: on errors (failed updates), on core updates, or on all updates. Recipients and triggers are configurable in Settings.
* **Admin UI** – Zenpress-style layout: header with version and docs links, main content with vertical tabs (Update logs, Settings), footer. Update logs tab shows a table with filters, refresh, cleanup, and per-row delete. Settings tab: enable/disable logging, retention days, email notifications (enable, recipients, notify on: errors / core / all).

= Why use Updates Control? =

* Keep an audit trail of what was updated and when.
* Spot failed updates quickly via the logs or email.
* Comply with change tracking or security reviews.
* Works on single sites and multisite.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/updates-control/` or install via the Plugins screen.
2. Activate the plugin through the **Plugins** screen.
3. Go to **Settings → Updates Control** to view logs and configure options (logging, retention, notifications).

On activation, the plugin creates the log table and schedules the daily cleanup cron. On deactivation, the cron is unscheduled; the table is kept.

== Frequently Asked Questions ==

= Does it work on multisite? =

Yes. Each site has its own log table. On network activate, each existing site gets the table on first load; new sites get it when they are created.

= Where are the logs stored? =

In a custom table: `{prefix}updatescontrol_logs` (e.g. `wp_updatescontrol_logs`). On multisite, each site uses its own prefix.

= Can I export or delete old logs? =

You can delete a single log from the table (Delete button per row) or run **Cleanup old logs** to remove all entries older than the retention period. Export is not included; you can query the table or use the REST API (`GET /wp-json/updatescontrol/v1/logs`) if you need to build an export.

= Does it send data outside my site? =

No. The plugin only stores data in your database and, if you enable it, sends notification emails to the addresses you configure. No analytics or external services.

== Hooks & filters ==

For developers:

= Actions =

* `updatescontrol_after_log` – Fired after a log entry is inserted. Parameters: `(int $log_id, array $data)`. Use to integrate with other systems or trigger custom notifications.

= REST API =

* `GET /wp-json/updatescontrol/v1/logs` – List logs (args: per_page, page, log_type, status, site_id). Requires `manage_options`.
* `DELETE /wp-json/updatescontrol/v1/logs/<id>` – Delete one log. Requires `manage_options`.
* `POST /wp-json/updatescontrol/v1/logs/cleanup` – Delete logs older than retention days. Requires `manage_options`.
* `GET /wp-json/updatescontrol/v1/settings` – Get plugin settings. Requires `manage_options`.
* `PUT /wp-json/updatescontrol/v1/settings` – Update settings (logging_enabled, retention_days, notify_enabled, notify_emails, notify_on). Requires `manage_options`.

== Changelog ==

= 0.2.0 =
* Plugin adapted from Zenpress-style interface.
* Class structure: MainPlugin, Updater, Logger, Database, Cron, Settings, ErrorHandler, Security, MultisiteSupport, Notifications.
* Logging for core/plugin/theme updates and installs; error handling (redirect, download, upgrader failure); security sanitization; multisite table per site; cron cleanup; optional email notifications; REST API for logs and settings; admin UI with Update logs and Settings tabs.

= 0.1.0 =
* Initial release.

== Upgrade Notice ==

= 0.2.0 =
* Zenpress-style admin interface, error handling for failed updates, REST GET settings, multisite and notifications support.
