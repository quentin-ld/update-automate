# Plugin Check (PCP) – Error list and analysis

This document lists all current Plugin Check findings, whether they can be fixed by refactoring, and which are false positives.

---

## 1. List of errors

### inc/classes/Updater.php

| Line | Type   | Code                      | Message |
|------|--------|---------------------------|---------|
| 0    | ERROR  | `plugin_updater_detected` | Plugin Updater detected. These are not permitted in WordPress.org hosted plugins. Detected: site_transient_update_plugins |
| 0    | WARNING| `update_modification_detected` | Plugin Updater detected. Detected code which may be altering WordPress update routines. Detected: _site_transient_update_plugins |

### inc/classes/Logger.php

| Line | Type   | Code | Message |
|------|--------|------|---------|
| 160  | WARNING| `WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber` | Incorrect number of replacements passed to $wpdb->prepare(). Found 1 replacement parameters, expected 4. |
| 161  | WARNING| `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | Interpolated variable {$where_sql} in query string. |
| 161  | WARNING| `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | Interpolated variable {$order} in query string. |
| 164  | ERROR  | `WordPress.DB.PreparedSQL.NotPrepared` | Use placeholders and $wpdb->prepare(); found $prepared. |
| 164  | ERROR  | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | Unescaped parameter $prepared used in $wpdb->get_results($prepared) (trace: $where_sql / $args['status']). |
| 164  | WARNING| `WordPress.DB.DirectDatabaseQuery.DirectQuery` | Use of a direct database call is discouraged. |
| 164  | WARNING| `WordPress.DB.DirectDatabaseQuery.NoCaching` | Direct database call without caching detected. |
| 204  | WARNING| `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | Interpolated variable {$where_sql} in query string. |
| 207  | ERROR  | `WordPress.DB.PreparedSQL.NotPrepared` | Use placeholders and $wpdb->prepare(); found $prepared. |
| 207  | ERROR  | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | Unescaped parameter $prepared used in $wpdb->get_var($prepared). |
| 207  | WARNING| `WordPress.DB.DirectDatabaseQuery.DirectQuery` | Use of a direct database call is discouraged. |
| 207  | WARNING| `WordPress.DB.DirectDatabaseQuery.NoCaching` | Direct database call without caching detected. |
| 67   | WARNING| `WordPress.DB.DirectDatabaseQuery.DirectQuery` | Use of a direct database call is discouraged. ($wpdb->insert) |
| 224  | WARNING| `WordPress.DB.DirectDatabaseQuery.DirectQuery` | Use of a direct database call is discouraged. ($wpdb->delete) |
| 224  | WARNING| `WordPress.DB.DirectDatabaseQuery.NoCaching` | Direct database call without caching detected. |
| 244  | WARNING| `WordPress.DB.DirectDatabaseQuery.DirectQuery` | Use of a direct database call is discouraged. ($wpdb->query) |
| 244  | WARNING| `WordPress.DB.DirectDatabaseQuery.NoCaching` | Direct database call without caching detected. |

### inc/classes/Database.php

| Line | Type   | Code | Message |
|------|--------|------|---------|
| 100  | WARNING| `WordPress.DB.DirectDatabaseQuery.DirectQuery` | Use of a direct database call is discouraged. |
| 100  | WARNING| `WordPress.DB.DirectDatabaseQuery.NoCaching` | Direct database call without caching detected. |
| 100  | WARNING| `WordPress.DB.DirectDatabaseQuery.SchemaChange` | Attempting a database schema change is discouraged. |

---

## 2. What can be fixed by refactoring (while preserving behaviour)

- **None of these are safely “fixable” in a way that would satisfy the sniffs without changing how the plugin works or dropping features.**

  - **Updater.php**: The only way to “fix” the updater detection would be to stop using `set_site_transient_update_plugins` / `set_site_transient_update_themes` filters. That would mean dropping the “version before” logging that depends on those transients. So not fixable without losing functionality.

  - **Logger.php**:  
    - The sniffs complain about interpolated `{$where_sql}` and `{$order}` and about passing `$prepared` into `get_results` / `get_var`.  
    - In MySQL we cannot pass table name or ORDER BY column/direction as bound parameters (they would be quoted as strings). So some form of “identifier” in the query is required.  
    - We could refactor to a small set of **fully literal** query strings (e.g. one query per combination of filters) with no interpolation, and only pass value placeholders to `prepare()`. That would be a lot of branches and duplication, and would only satisfy the sniffs if the tool recognizes that the query string is literal.  
    - So: **theoretically** fixable with a heavy, brittle refactor; **in practice** not recommended and not guaranteed to clear the sniffs.

  - **Database.php**: There is no WordPress API for “drop custom table”. The only way to “fix” would be to not drop the table on uninstall, which would leave data behind. So not fixable without changing behaviour.

So: **no straightforward refactor** that keeps current behaviour and makes Plugin Check pass. The realistic approach is to treat the reported items as false positives or acceptable for your use case and use WP-CLI ignore/exclude when running Plugin Check.

---

## 3. False positives (and why)

| Code / Area | Verdict | Reason |
|-------------|--------|--------|
| **Updater.php** – `plugin_updater_detected` / `update_modification_detected` | **False positive** | The plugin does **not** implement a custom plugin updater or block/alter updates. It only hooks into `set_site_transient_update_plugins` / `set_site_transient_update_themes` as a **filter** to add `version_before` to the transient for **logging**. It does not change what gets updated or how. The checker flags any use of these transients. |
| **Logger.php** – `PreparedSQLPlaceholders.ReplacementsWrongNumber` | **False positive** | We pass an **array** as the second argument to `$wpdb->prepare()` (e.g. `$values`). PHPCS counts “1” (one argument) instead of the number of placeholders that the array is meant to fill. The number of placeholders and the number of values are correct. |
| **Logger.php** – `PreparedSQL.InterpolatedNotPrepared` | **Sniff limitation** | `$where_sql` and `$order` are built from **whitelisted** values only (e.g. `'site_id = %d'`, `'log_type = %s'`, and `'ASC'`/`'DESC'`). All user input is in `$values` and passed as bindings. The sniff does not allow any variable interpolation in the query string. |
| **Logger.php** – `PreparedSQL.NotPrepared` (“found $prepared”) | **Cascade of above** | The sniff does not treat `$prepared` as “prepared” because it thinks the earlier `prepare()` call was unsafe (due to interpolation). So it flags `get_results($prepared)` / `get_var($prepared)`. |
| **Logger.php** – `PluginCheck.Security.DirectDB.UnescapedDBParameter` | **False positive** | It traces `$prepared` back and blames `$where_sql` / `$args['status']`. In our code, `$args['status']` is sanitized (e.g. via `UpdatesControl_Security::sanitize_status()`) and only used in the **values** array passed to `prepare()`, not interpolated into the query. So the parameter is not “unescaped” in the way the message suggests. |
| **Logger.php** – `DirectDatabaseQuery.DirectQuery` / `NoCaching` | **False positive** | For a **custom table**, the intended WordPress API is exactly `$wpdb->insert`, `$wpdb->delete`, `$wpdb->get_results`, `$wpdb->get_var`, `$wpdb->query`. There is no higher-level API. Caching is not applicable for this kind of audit log table. |
| **Database.php** – `DirectDatabaseQuery` / `SchemaChange` | **False positive** | Dropping a custom table on uninstall is required; WordPress has no API for it. The table name comes from `get_table_name()` (prefix + constant). This is the standard pattern for uninstall. |

---

## 4. Summary

- **List**: All current Plugin Check errors and warnings are listed in section 1.  
- **Refactoring**: There is no clean refactor that keeps all current behaviour and makes every finding go away; the only “fixes” would be to remove or significantly change features (e.g. no transient hooks, no custom table drop) or to do a heavy, brittle query refactor that may still not satisfy the sniffs.  
- **False positives**: All of the reported items are either false positives (plugin updater detection, replacement count, “unescaped” parameter, direct DB use for custom table and uninstall) or due to sniff limitations (interpolation of whitelisted fragments, “found $prepared” as a consequence).  

**Recommendation**: Use Plugin Check via WP-CLI with `--ignore-codes` and/or `--exclude-checks` for these codes when you need a clean run (see `workflow.md`). Functionality and security are preserved; the sniffs are not tuned for this use case.
