# Logs & UpdateManager audit – issues, risks, and use cases

This document lists **issues**, **potential problems**, and **crash risks** found in the Logger, UpdateManager, ErrorHandler, and related code, grouped **by use case** where useful.

---

## 1. Crashes / PHP errors

### 1.1 Theme updates – `capture_theme_versions_before` (theme version_before injection)

**Use case:** Any flow that sets the `update_themes` transient (e.g. “Check for theme updates”, bulk theme update).

**Issue:** WordPress stores **theme** update data as **arrays** in `update_themes` transient:  
`$new_update->response[ $theme_stylesheet ] = (array) $update;` (wp-includes/update.php).  
Our code treats each item as an **object** and sets `$value->response[$slug]->version_before`, which is invalid when `$value->response[$slug]` is an array.

**Risk:** PHP notice/fatal when the filter runs (e.g. “Trying to get property 'version_before' of array” or “Cannot create dynamic property on array” depending on PHP version).

**Fix:** Treat theme response entries as arrays:  
e.g. `$value->response[$slug]['version_before'] = $themes[$slug]->get('Version');` and use `isset($value->response[$slug]['version_before'])` for the guard.

**File:** `inc/classes/UpdateManager.php` – `capture_theme_versions_before()`.

---

### 1.2 Logger – `get_logs()` prepared statement argument order

**Use case:** Any request that fetches logs (REST “get logs”, admin list).

**Issue:** Placeholders in the SQL are: `%i` (table), then placeholders from `$where_sql`, then `%i` (orderby), `%d` (limit), `%d` (offset).  
Values are built as: `[$table, $orderby, ...$where_values..., $per_page, $offset]`.  
So the **second** placeholder (first “where” value) receives `$orderby`, and the order-by placeholder receives the first where value. That breaks the query and can cause SQL errors or wrong results.

**Risk:** Broken log list (wrong rows, empty list, or DB error depending on driver/version).

**Fix:** Build values in the same order as placeholders:  
`$values = array_merge([$table], $values, [$orderby, $per_page, $offset]);`  
(and do not merge `$orderby` before `$values`).

**File:** `inc/classes/Logger.php` – `get_logs()`.

---

### 1.3 ErrorHandler – undefined index `$options['themes']`

**Use case:** Theme update/install **failure** when `upgrader_process_complete` is called with `type => 'theme'` and only `theme` (singular) set, or `themes` not set.

**Issue:** Code uses `$options['themes'][0] ?? $options['theme'] ?? ''`. If `$options['themes']` is not set, PHP may still evaluate `$options['themes'][0]` (e.g. in older PHP) or the intent is fragile. Safer is to treat `themes` as array first.

**Risk:** Undefined index notice if `themes` is missing and `theme` is used.

**Fix:**  
`$theme_slug = (isset($options['themes']) && is_array($options['themes']) && isset($options['themes'][0])) ? $options['themes'][0] : ($options['theme'] ?? '');`

**File:** `inc/classes/ErrorHandler.php` – `log_upgrader_failure()`.

---

### 1.4 UpdateManager – `log_plugin_update()` undefined array key `Version`

**Use case:** Plugin update/install when the plugin file has no `Version` header (or `get_plugin_data` omits it).

**Issue:** `$version_after = $data['Version'];` – if `Version` is missing, this triggers an undefined array key notice (PHP 8+).

**Fix:** `$version_after = $data['Version'] ?? '';`

**File:** `inc/classes/UpdateManager.php` – `log_plugin_update()`.

---

### 1.5 UpdateManager – `maybe_flush_pending_logs()` undefined array keys

**Use case:** Shutdown fallback when logging “interrupted” updates; pending log structure is wrong or corrupted.

**Issue:** Code uses `$data['name']`, `$data['slug']`, `$data['version_before']`, `$data['version_after']` without checking. If a pending entry is malformed or a future change adds a different shape, this can cause undefined index notices.

**Fix:** Use defaults, e.g.  
`$name = $data['name'] ?? '';` (and same for slug, version_before, version_after), or merge with a default array before use.

**File:** `inc/classes/UpdateManager.php` – `maybe_flush_pending_logs()`.

---

### 1.6 UpdateManager – OB buffer and `feedback_html_to_plain(false)`

**Use case:** Plugin/theme update when our OB was started but another code path closed the buffer before `on_upgrader_process_complete`.

**Issue:** We call `$buffer = ob_get_clean();` then `self::$captured_feedback = self::feedback_html_to_plain($buffer);`. If the buffer was already ended, `$buffer` can be `false`. Passing `false` to `feedback_html_to_plain()` type-hints `string`; in practice PHP may coerce and we’d get `""`, but it’s cleaner and safer to only call `feedback_html_to_plain` when `$buffer` is a non-empty string (or at least a string).

**Risk:** Type violation / static analysis / future PHP strictness; no need to run HTML-to-plain on non-string.

**Fix:** Only set `self::$captured_feedback = self::feedback_html_to_plain($buffer)` when `$buffer !== false && $buffer !== ''` (and optionally when `is_string($buffer)`).

**File:** `inc/classes/UpdateManager.php` – `on_upgrader_process_complete()`.

---

## 2. Logic / correctness (no immediate crash)

### 2.1 Double logging and wrong status on failure

**Use case:** Plugin/theme/core update that **fails** (e.g. WP_Error from upgrader).

**Issue:** `on_upgrader_process_complete` runs at priority 10 and always logs a **success** entry. `log_upgrader_failure` runs at priority 20 and logs a **failure** entry when `$skin->result` is `WP_Error`. So a single failed update produces two log entries: one incorrect “success” and one correct “error”.

**Fix:** In `UpdateManager::on_upgrader_process_complete`, at the start (after basic checks), if `isset($upgrader->skin->result) && $upgrader->skin->result instanceof \WP_Error`, return early and do not log success. Let `ErrorHandler::log_upgrader_failure` be the only logger for that run.

**Files:** `inc/classes/UpdateManager.php`, `inc/classes/ErrorHandler.php`.

---

### 2.2 Theme log message title ignores downgrade / same_version

**Use case:** Theme **downgrade** or **reinstall same version** (e.g. upload replace).

**Issue:** `log_theme_update()` sets `$action_type` correctly via `resolve_action_type()` (e.g. `downgrade`, `same_version`) but the human-readable title is built only from `$action === 'install'` vs not:  
`$title = $action === 'install' ? sprintf(__('Installation of ...')) : sprintf(__('Update to ...'));`  
So downgrades and same-version reinstalls are shown as “Update to X Y” instead of “Downgrade to X Y” / “Reinstall X Y (same version)”.

**Fix:** Build title from `$action_type` (like `format_plugin_log_title()` for plugins), e.g. separate cases for install, downgrade, same_version, and update.

**File:** `inc/classes/UpdateManager.php` – `log_theme_update()`.

---

### 2.3 Automatic updates – `log_automatic_updates` iteration safety

**Use case:** Automatic updates when `automatic_updates_complete` fires with unexpected structure.

**Issue:** `foreach ($update_results as $type => $results)` then `foreach ($results as $result)`. If `$results` is not an array (e.g. null), the inner loop can error. If `$result` is not an object, `$result->item` etc. can error.

**Risk:** PHP warning/error in edge cases (e.g. filter altering the structure).

**Fix:** At start of inner loop: `if (!is_array($results)) continue;` and inside: `if (!is_object($result)) continue;`.

**File:** `inc/classes/UpdateManager.php` – `log_automatic_updates()`.

---

## 3. Use-case summary table

| Use case | Area | Issue (short) |
|----------|------|----------------|
| Theme updates (transient) | UpdateManager | Theme response is array; code uses object → crash risk (1.1) |
| Log list (REST / admin) | Logger | Wrong prepare() argument order → wrong SQL (1.2) |
| Theme failure logging | ErrorHandler | `$options['themes']` possibly undefined (1.3) |
| Plugin update (no Version header) | UpdateManager | `$data['Version']` undefined key (1.4) |
| Shutdown fallback logs | UpdateManager | Pending log keys possibly missing (1.5) |
| Plugin/theme OB feedback | UpdateManager | feedback_html_to_plain(false) edge case (1.6) |
| Failed update (any) | UpdateManager + ErrorHandler | Double log + one wrong “success” (2.1) |
| Theme downgrade/same version | UpdateManager | Title always “Update to…” (2.2) |
| Automatic updates (weird payload) | UpdateManager | $results / $result not validated (2.3) |

---

## 4. Optional / follow-up

- **Logger `%i` (identifier) placeholder:** Used in `get_logs()` and `get_logs_count()`. `%i` exists in WordPress 6.2+. If you need to support older WP, you need an alternative (e.g. validated table name, no placeholder for identifier).
- **ErrorHandler `capture_download_error`:** Logs with empty slug and version; that’s acceptable for “download failed” but could be enriched if upgrader/skin provides more context (e.g. plugin/theme slug from hook_extra or skin).
- **Security:** All log inputs go through Security sanitizers; allowed enums (log_type, action_type, status, performed_as) are aligned with usage. No additional crash or injection risks identified in the audited paths.

---

*Audit date: 2025-02-04. Files: UpdateManager.php, Logger.php, ErrorHandler.php, Security.php, Settings.php, Database.php.*
