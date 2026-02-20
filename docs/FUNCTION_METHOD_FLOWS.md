# Function & method flows

Overview of the main function and method flows implemented in Updates Control.

---

## 1. Bootstrap & init

- **Entry:** `plugins_loaded` → `UpdatesControl_Bootstrap::init()`
- **Bootstrap::init()** loads classes, then:
  - **on_activation_create_table()** – ensures log table exists (DB version check → `Database::create_table()` if needed)
  - **Cron::register()** – cron hook + `schedule_if_needed()` on `init`
  - **Update_Manager::register()** – all update-related hooks
  - **ErrorHandler::register()** – error/failure hooks
  - **Settings::register()** – REST API routes
  - **Notifications::register()** – `updatescontrol_after_log` hook

---

## 2. Update logging flows (success paths)

### 2.1 Automatic-update flag

- **pre_auto_update** → `Update_Manager::pre_auto_update()`  
  Sets `$auto_update = true` so later logs use `performed_as = 'automatic'`.

### 2.2 Version capture (before updates run)

- **set_site_transient_update_plugins** → `Update_Manager::capture_plugin_versions_before()`  
  Adds `version_before` on each item in `$value->response` from `get_plugins()`.
- **set_site_transient_update_themes** → `Update_Manager::capture_theme_versions_before()`  
  Same for themes from `wp_get_themes()`.

### 2.3 Core update

- **upgrader_pre_install** (type core) → `Update_Manager::store_core_version_before()`  
  Saves current `get_bloginfo('version')` in option.
- **upgrader_pre_download** (Core_Upgrader) → `Update_Manager::init_core_feedback_on_download()`  
  Saves core version_before, sets pending core log, starts `update_feedback` collection.
- **update_feedback** → `Update_Manager::collect_core_feedback()`  
  Appends each message to `$core_feedback`.
- **upgrader_process_complete** (type=core, action=update) → `Update_Manager::on_upgrader_process_complete()`  
  → **log_core_update()** → **Logger::log()** (core, update/downgrade/same_version), then `do_action('updatescontrol_after_log', ...)`.

### 2.4 Plugin update / install

- **upgrader_package_options** → `Update_Manager::initialize_pending_logs()`  
  For plugin/theme/translation: may start output buffer to capture bulk skin feedback; fills `$pending_logs` for plugins/themes/translations from hook_extra and transients.
- **upgrader_process_complete** (type=plugin) → `Update_Manager::on_upgrader_process_complete()`  
  Collects feedback from OB/skin, then:
  - **Plugin_Upgrader, install:** if no `options['plugins']`, uses `$upgrader->plugin_info()` and calls **log_plugin_update($plugin_file, 'install'|'update', ...)**.
  - Else loops `options['plugins']` and calls **log_plugin_update($plugin_file, $action, ...)**.
- **log_plugin_update()** – reads version_before from options/transient, name/version_after from `get_plugins()`/`get_plugin_data()`, builds title/message via **format_plugin_log_title()** / **format_plugin_log_message()**, then **Logger::log()** and cleans up version options and `$pending_logs['plugin']`.

### 2.5 Plugin upload (overwrite)

- **upgrader_source_selection** (plugin) → `Update_Manager::store_plugin_version_before_upload_overwrite()`  
  When overwriting existing plugin, stores current version in option keyed by main file.
- Same completion path as 2.4; **log_plugin_update()** uses stored version_before and may set `performed_as = 'upload'`.

### 2.6 Theme update / install

- **upgrader_pre_install** (theme) → version stored in `store_core_version_before()` (theme branch) for theme slug.
- **upgrader_source_selection** (theme) → `Update_Manager::store_theme_version_before_upload_overwrite()`  
  For theme upload/overwrite, stores current theme version.
- **upgrader_process_complete** (type=theme) → **log_theme_update()** per theme (install vs update from options), then **Logger::log()** (theme, …).

### 2.7 Translation update

- **upgrader_process_complete** (type=translation) → for each item in `options['translations']`, resolves name/version from `$pending_logs['translation']` or fallback, then **Logger::log()** (translation, update, …).

### 2.8 Plugin uninstall / deletion

- **delete_plugin** → `Update_Manager::log_plugin_uninstall($plugin_file)`  
  Reads name/version from `get_plugins()` (fallback basename + empty version), slug from `dirname($plugin_file)`, builds title with **format_plugin_log_title('uninstall', ...)**, then **Logger::log()** (plugin, uninstall, version_after='', …).

### 2.9 Theme uninstall / deletion

- **delete_theme** → `Update_Manager::log_theme_uninstall($stylesheet)`  
  Reads name/version from `wp_get_themes()[$stylesheet]` (theme still on disk before deletion), builds title with **format_plugin_log_title('uninstall', ...)**, then **Logger::log()** (theme, uninstall, version_after='', …).

### 2.10 Automatic updates fallback

- **automatic_updates_complete** → `Update_Manager::log_automatic_updates($update_results)`  
  For each result (plugin/theme/translation), builds name/slug/version from `$pending_logs` or current state and calls **Logger::log()** so automatic runs are logged even if `upgrader_process_complete` didn’t run per item.

### 2.11 Shutdown fallback

- **register_shutdown_function** → `Update_Manager::maybe_flush_pending_logs()`  
  If `$pending_logs` not empty (e.g. fatal during update), logs each entry with status `error` and message “Update may have been interrupted (logged on shutdown).”

### 2.12 Bulk feedback capture (plugin/theme)

- **upgrader_pre_download** → `Update_Manager::start_bulk_post_flush_buffer()`  
  After bulk skin flush, starts a new OB to capture “Downloading…”, “Unpacking…”, etc., used in **on_upgrader_process_complete** as `$process_message`.

---

## 3. Error / failure flows

- **upgrader_pre_download** → `ErrorHandler::capture_download_error()`  
  Wraps download in try/catch; on failure logs via **Logger::log()** (status `error`) and returns WP_Error.
- **upgrader_process_complete** (priority 20) → `ErrorHandler::log_upgrader_failure()`  
  If skin result is WP_Error (and not `folder_exists`), resolves plugin/theme identity, gets process message via **ErrorHandler::get_skin_process_message()**, then **Logger::log()** (status `error`).
- **wp_redirect** → `ErrorHandler::capture_redirect_error()`  
  Detects redirect to update-core with error query; can log or handle redirect error case.
- **automatic_updates_is_vcs_checkout** → `ErrorHandler::vcs_checkout_check()`  
  Optional VCS checkout detection for automatic updates.

---

## 4. Logger & after-log

- **Logger::log(...)**  
  Sanitizes inputs via **Security::** sanitize_* methods, inserts row, then `do_action('updatescontrol_after_log', $log_id, $data)`.
- **updatescontrol_after_log** → `Notifications::maybe_send_notification($log_id, $data)`  
  If notifications enabled and recipients set, checks `updatescontrol_notify_on` (error / core / all) and sends email for matching logs.

---

## 5. REST API & admin UI

- **rest_api_init** → `Settings::register_rest_routes()`:
  - `GET .../logs` → **Settings::rest_get_logs()** → **Logger::get_logs($args)**
  - `DELETE .../logs/(?P<id>\d+)` → **Settings::rest_delete_log()** → **Logger::delete($id)**
  - `POST .../logs/cleanup` → **Settings::rest_cleanup_logs()** → **Logger::delete_older_than($days)**
  - `GET .../settings` → **Settings::rest_get_settings()**
  - `POST .../settings` → **Settings::rest_update_settings()**
- **admin_menu** → `updatescontrol_add_option_page()`  
  Adds “Updates Control” under Tools and under Dashboard; callback **updatescontrol_options_page()** (PHP shell + `#updatescontrol-settings` for React).
- **admin_enqueue_scripts** (tools/dashboard updates-control page) → **updatescontrol_admin_enqueue_scripts()** (script/style), **updatescontrol_localize_settings()** (REST URL, nonce, settings for React).
- React app (LogsTable, etc.) calls REST endpoints above to list logs, delete single log, run cleanup, and read/update settings.

---

## 6. Cron cleanup

- **init** → `Cron::schedule_if_needed()`  
  Schedules daily event `updatescontrol_cleanup_logs` if not already scheduled.
- **updatescontrol_cleanup_logs** → `Cron::run_cleanup()`  
  Reads `updatescontrol_retention_days`, then **Logger::delete_older_than($days)**.

---

## 7. Other flows

- **plugin_action_links_...** → `updatescontrol_add_settings_link()`  
  Adds “Settings” link on Plugins list.
- **plugin_row_meta** → `updatescontrol_plugin_row_meta()`  
  Adds meta links (e.g. docs) on Plugins list.
- **init** → `updatescontrol_register_settings()`  
  Registers `updatescontrol_*` options (e.g. logging_enabled, retention_days, notify_*).

---

## 8. Helper usage (no direct WordPress hooks)

- **ErrorHandler::capture_trace()** – used by Update_Manager and ErrorHandler to attach stack trace to log entries.
- **ErrorHandler::get_skin_process_message($upgrader)** – used by Update_Manager and ErrorHandler to get skin upgrade messages.
- **Update_Manager::resolve_action_type()** – version_compare → downgrade / same_version / update.
- **Update_Manager::format_plugin_log_title()** – title string by action (install, uninstall, downgrade, same_version, update).
- **Update_Manager::format_plugin_log_message()** – title + process message.
- **Logger::get_logs()** – used by REST and (indirectly) by admin UI.
- **Logger::delete()** / **Logger::delete_older_than()** – used by REST and Cron.
- **Security::** sanitize_* – used by Logger and REST for all log/request inputs.
- **Database::table_exists()** / **create_table()** / **get_table_name()** – used by Bootstrap, Logger, Settings.
