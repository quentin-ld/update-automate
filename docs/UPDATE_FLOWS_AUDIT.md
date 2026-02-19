# WordPress update flows – audit (updates-control)

This document maps each WordPress update flow to our hooks and logging. We use **native WordPress hooks only**; we do not block or alter updates, only observe and log.

---

## Native WordPress entry points (reference)

| Flow | Entry point | Hooks that fire |
|------|-------------|-----------------|
| Plugin install (repo) | `update.php?action=install-plugin` | `Plugin_Upgrader::install()` → `run()` with `hook_extra`: type=plugin, action=install |
| Plugin install (upload) | `update.php?action=upload-plugin` | Same; overwrite via `overwrite_package` |
| Plugin update (manual) | `update.php?action=upgrade-plugin` or bulk | `Plugin_Upgrader::upgrade()` → `run()` with plugin, type=plugin, action=update |
| Theme install (repo) | `update.php?action=install-theme` | `Theme_Upgrader::install()` → `run()` with type=theme, action=install |
| Theme install (upload) | `update.php?action=upload-theme` | Same; overwrite via `overwrite_package` |
| Theme update (manual) | `update.php?action=upgrade-theme` or bulk | `Theme_Upgrader::upgrade()` → theme, type=theme, action=update |
| Core update | `update-core.php` (minor/major/reinstall) | `Core_Upgrader::upgrade()` → `run()`, `update_feedback` filter |
| Automatic updates | WP-Cron / `WP_Automatic_Updater` | `pre_auto_update`, then multiple `run()` calls, then `automatic_updates_complete` |
| Translation | Dashboard → Updates or auto | `Language_Pack_Upgrader` → `run()` with `language_update`, `language_update_type` |

---

## Our hooks (UpdateManager + ErrorHandler)

| Hook | Purpose |
|------|--------|
| `pre_auto_update` | Set `$auto_update` so logs get performed_as=automatic |
| `upgrader_process_complete` (10) | Main logging: plugin, theme, core, translation success |
| `automatic_updates_complete` (10) | Fallback log when automatic run completes (in case upgrader_process_complete did not run) |
| `upgrader_pre_install` (5) | Store version_before for core/plugin/theme (from hook_extra) |
| `upgrader_package_options` (10) | Start OB for plugin/theme feedback; init pending_logs for update + translation |
| `upgrader_source_selection` (20) | Store plugin version_before for upload overwrite (and theme – see below) |
| `upgrader_pre_download` (5) | Core: init version_before + pending_logs['core'], hook update_feedback |
| `set_site_transient_update_plugins` | Capture version_before into transient for manual plugin updates |
| `set_site_transient_update_themes` | Capture version_before for manual theme updates |
| `register_shutdown_function` | maybe_flush_pending_logs() – log pending on fatal |
| **ErrorHandler:** `wp_redirect` | Log core redirect errors (4xx to update-core.php) |
| **ErrorHandler:** `upgrader_pre_download` | Log download WP_Error (plugin/theme) |
| **ErrorHandler:** `upgrader_process_complete` (20) | Log when skin->result is WP_Error (failed update) |

---

## Flow-by-flow coverage

### Plugins

| # | Flow | Handled | How |
|---|------|--------|-----|
| 1 | Install from repo (Add New) | Yes | `on_upgrader_process_complete`: type=plugin, action=install, empty options['plugins'] → `plugin_info()` → log_plugin_update(..., 'install', ..., 'manual') |
| 2 | Install via upload (ZIP) | Yes | Same as 1; no overwrite, so action=install |
| 3 | Same version again via upload | Yes | `store_plugin_version_before_upload_overwrite` stores version_before; we log with action_type same_version |
| 4 | Downgrade via upload | Yes | version_before stored (slug or fallback by name); resolve_action_type → downgrade |
| 5 | Update via upload | Yes | version_before stored; resolve_action_type → update |
| 6 | Update automatically | Yes | pre_auto_update; upgrader_process_complete with plugins array; or log_automatic_updates |
| 7 | Bulk update (multiple plugins) | Yes | options['plugins'] array; we loop and log each |
| 8 | Update blocked (incompatible WP/PHP) | Partial | If WP blocks before run(), no upgrader runs → no log. If run() starts and fails, log_upgrader_failure logs it. |
| 9 | Update fails (permissions, timeout, space) | Yes | ErrorHandler::log_upgrader_failure when skin->result is WP_Error |
| 10 | Update with rollback prompt | Yes | We log the update when it runs; rollback is a separate flow (we don’t log rollback unless it triggers an upgrader) |

### Themes

| # | Flow | Handled | How |
|---|------|--------|-----|
| 1 | Install from repo (Add New) | Yes | type=theme, action=install, empty options['themes'] → theme_info() → log_theme_update(..., 'install') |
| 2 | Install via upload (ZIP) | Yes | Same; theme identity from theme_info() after install |
| 3 | Same version again via upload | Yes | store_theme_version_before_upload_overwrite stores version_before; resolve_action_type → same_version |
| 4 | Downgrade via upload | Yes | version_before stored (slug or fallback by Name); resolve_action_type → downgrade |
| 5 | Update via upload | Yes | version_before stored; resolve_action_type → update; performed_as=upload |
| 6 | Update automatically | Yes | pre_auto_update; upgrader_process_complete with themes; or log_automatic_updates |
| 7 | Bulk update | Yes | options['themes'] array; we loop and log each |
| 8 | Blocked (incompatible) | Partial | Same as plugin |
| 9 | Update fails | Yes | log_upgrader_failure |
| 10 | Rollback prompt | Yes | We log the update |

### Core (WordPress)

| # | Flow | Handled | How |
|---|------|--------|-----|
| 1 | Minor update via admin (e.g. 6.5 → 6.5.1) | Yes | store_core_version_before; init_core_feedback_on_download; log_core_update |
| 2 | Major update via admin (e.g. 6.5 → 6.6) | Yes | Same |
| 3 | Reinstall current version | Yes | Same; we fixed packages array/object for reinstall |
| 4 | Update automatically | Yes | pre_auto_update; log_core_update or log_automatic_updates |
| 5 | DB upgrade prompt after core update | N/A | We log the core update; the prompt is post-update, no separate log needed |
| 6 | Core update fails | Yes | capture_redirect_error; capture_download_error; log_upgrader_failure |
| 7 | Maintenance mode | N/A | No separate log; update is logged |

### Translations

| # | Flow | Handled | How |
|---|------|--------|-----|
| 1 | Update manually (Dashboard → Updates) | Yes | initialize_pending_logs (action=update, language_update); on_upgrader_process_complete type=translation |
| 2 | Update automatically | Yes | log_automatic_updates with translation results |
| 3 | Tied to core/plugin/theme update | Yes | Same upgrader_process_complete / automatic_updates_complete |

---

## Reference: EUM (stops-core-theme-and-plugin-updates-premium)

- **MPSUM_Logs**: `pre_auto_update`, `upgrader_package_options` (initialize_log_messages), `upgrader_pre_download` (initialize_core_log_messages), `automatic_updates_complete`, `upgrader_process_complete` (priority 1). Shutdown: maybe_log_updates().
- EUM only initializes log data when `action === 'update'` in upgrader_package_options (no install/upload overwrite in that filter). We additionally handle plugin/theme install and upload overwrite (version_before and identity from plugin_info() / theme_info() and source_selection).
- We do not block or disable updates; we only observe and log (native methods, add checks and logs only).

---

## Gaps addressed in code

1. **Theme install (repo or upload)** – When `type=theme`, `action=install`, and `options['themes']` / `options['theme']` are empty, resolve theme slug from `Theme_Upgrader::theme_info()` and call `log_theme_update(..., 'install' or 'update', ..., performed_as 'upload' or 'manual')`.
2. **Theme upload overwrite (same version / downgrade / update)** – `store_theme_version_before_upload_overwrite` on `upgrader_source_selection` (theme, install); stores version_before by slug or fallback by theme Name from `new_theme_data`; `log_theme_update` uses `resolve_action_type` and performed_as=upload when version was stored.
