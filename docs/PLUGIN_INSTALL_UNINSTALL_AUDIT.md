# Plugin installation and uninstall – audit and logging

## 1. Plugin installation – current behaviour

**Already logged.** Plugin installation is covered by the existing upgrader flow.

- **Flow:** User installs a plugin via **Plugins → Add New** (from repo) or **Upload** → `Plugin_Upgrader::install()` → `run()` with `hook_extra['action'] = 'install'` → on completion `upgrader_process_complete` fires with `$options['type'] = 'plugin'`, `$options['action'] = 'install'`.
- **In our code:** `UpdateManager::on_upgrader_process_complete()` (around 743–762) handles `type === 'plugin'`. When `$action === 'install'` and `$plugins` is empty (single install), we resolve the plugin via `$upgrader->plugin_info()`, then call `log_plugin_update(..., $log_action, ...)` where `$log_action` is `'install'` when there was no `version_before` (fresh install) or `'update'` when there was (e.g. overwrite from upload).
- **Result:** Installations from Add New or upload are logged with `action_type` **install** (or **update** when replacing). No change needed for installation logging.

---

## 2. Plugin uninstall / deletion – current behaviour

**Not logged.** Deleting a plugin is not hooked anywhere in the plugin.

- **WordPress flow:** User deletes a plugin from **Plugins** (single delete or bulk) → `delete_plugins( $plugins )` in `wp-admin/includes/plugin.php`. For each plugin:
  1. If uninstallable: `uninstall_plugin( $plugin_file )` runs (runs uninstall callbacks / uninstall.php).
  2. `do_action( 'delete_plugin', $plugin_file );` runs.
  3. Plugin directory is removed from the filesystem.
- **Hooks available:**
  - **`pre_uninstall_plugin`** (`$plugin`, `$uninstallable_plugins`) – runs only when the plugin is uninstallable, before uninstall callbacks. Plugin still on disk.
  - **`delete_plugin`** (`$plugin_file`) – runs for **every** plugin deletion, immediately before the directory is deleted. Plugin file path is still valid; we can still read name/version from `get_plugins()`.

**Recommendation:** Use **`delete_plugin`** so that every deletion is logged (whether or not the plugin had an uninstall callback). When the action runs, the plugin is still on disk, so we can resolve name and version from `get_plugins()` (or `get_plugin_data()`).

---

## 3. Implementation plan for uninstall logging

1. **Action type**
   - Add **`uninstall`** to `Security::ALLOWED_ACTION_TYPES` (and to `Logger` / DB usage; `action_type` already supports arbitrary allowed values).
   - Optionally use a dedicated “Uninstall” label in the logs table (e.g. in LogsTable.js `getActionTypeLabel`).

2. **Hook**
   - In `UpdateManager::register()` (or a dedicated registration method), add:
     - `add_action( 'delete_plugin', [ self::class, 'log_plugin_uninstall' ], 10, 1 );`
   - Implement **`UpdateManager::log_plugin_uninstall( string $plugin_file ): void`** that:
     - Returns early if logging is disabled.
     - Gets plugin name and version from `get_plugins()` (or `get_plugin_data()`) for `$plugin_file` (still valid when the action runs).
     - Calls `UpdatesControl_Logger::log()` with:
       - `log_type` = `'plugin'`
       - `action_type` = `'uninstall'`
       - `item_name`, `item_slug` (e.g. from dirname of `$plugin_file`), `version_before` = current version, `version_after` = `''`
       - `status` = `'success'`
       - `message` = optional (e.g. “Plugin deleted.” or empty)
       - `trace` = optional (e.g. `ErrorHandler::capture_trace()`)
       - `performed_as` = `'manual'` (or resolve from context if needed later)
       - `update_context` = `''`

3. **UI**
   - In **LogsTable.js**, add a label for `action_type === 'uninstall'` in `getActionTypeLabel` (e.g. “Uninstall” / “Deleted”) so the new entries display correctly.

4. **Edge cases**
   - If `get_plugins()` or `get_plugin_data()` is not available or the plugin is already missing from the list, log with a fallback name (e.g. plugin basename or “Unknown”) and empty version.
   - No need to hook `pre_uninstall_plugin` unless we want a separate “uninstall callbacks ran” event; for an audit of “plugin removed”, `delete_plugin` is enough.

---

## 4. Summary

| Event              | Logged today? | How / where                                      | Action        |
|--------------------|---------------|--------------------------------------------------|---------------|
| Plugin install     | Yes           | `upgrader_process_complete` → `log_plugin_update` with action `install` | None          |
| Plugin uninstall  | No            | —                                                | Add `delete_plugin` → `log_plugin_uninstall` and `action_type` **uninstall** |

Base the implementation on the existing `log_plugin_update` / `Logger::log` pattern and the above hook and parameters.
