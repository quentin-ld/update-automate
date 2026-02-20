# PHP code audit – refactoring, dead code, comments, PHP 8.1

Audit date: 2026-02. Scope: all plugin PHP (excl. `.config` tooling). Preserve functionality; no version bump.

---

## 1. Dead code to remove

| # | Location | What | Action |
|---|----------|------|--------|
| 1.1 | `ErrorHandler.php` ~95–97 | Second `if ($result->get_error_code() === 'folder_exists')` block that appends to `$message`. Unreachable because we already `return` at ~47–49 for `folder_exists`. | Remove the block (lines 95–97). The “Replace current with uploaded” message never runs; if it should be shown elsewhere, document and implement separately. |
| 1.2 | `inc/core/constants.php` | Fallback definition of `updatescontrol_PLUGIN_FILE` / `updatescontrol_PLUGIN_DIR` when not defined. Main plugin file always defines them before requiring this file. | Optional: keep for safety if constants.php is ever loaded in isolation; or remove fallback and require loading from main file only. Prefer keeping unless you enforce a single entry point. |

---

## 2. Comments to remove (non-docblock, non–phpcs:ignore)

| # | Location | Comment | Action |
|---|----------|---------|--------|
| 2.1 | `Bootstrap.php` ~49 | `// Bootstrap is loaded by the main plugin file, not here.` | Remove or move to class/file docblock: “Class files are loaded here; bootstrap itself is loaded by the main plugin file.” |
| 2.2 | `UpdateManager.php` ~86–89 | Four inline `//` lines describing callback OB, bulk/translation, core, PHP ob_start. | Move into the docblock of `initialize_pending_logs()` (e.g. “Use callback OB when skin flushes… PHP forbids ob_start() inside an OB handler…”). Then delete the inline comments. |
| 2.3 | `.config/.php-cs-fixer.php` ~10 | `// Front usefull config` | Remove or fix typo and put in a short docblock if needed (config files often have no docblock; removing is fine). |

---

## 3. Comments to convert into docblocks

| # | Location | Current | Action |
|---|----------|---------|--------|
| 3.1 | `Security.php` ~41–42 | Standalone `/** Manual, automatic, or file upload (update.php upload flow). */` above `ALLOWED_PERFORMED_AS`. | Merge into the existing `@var` docblock for `ALLOWED_PERFORMED_AS` (single docblock: “Allowed performed_as values. Manual, automatic, or file upload (update.php upload flow).”). |
| 3.2 | `updates-control.php` ~40 | `/** Plugin version (must match Version header above; used for DB schema version). */` above `define('UPDATESCONTROL_VERSION'…)`. | Already a docblock; ensure it sits directly above the define. No change if already adjacent. |
| 3.3 | `updates-control.php` ~56–57, ~68–69 | Function descriptions above `updatescontrol_activate` and `updatescontrol_deactivate`. | Convert to proper docblocks: `@return void`, brief description, and “Create log table and schedule cron on activation” / “Unschedule cron on deactivation (table is kept).” |

---

## 4. Refactoring and optimization

| # | Location | Suggestion | Risk / note |
|---|----------|------------|-------------|
| 4.1 | `Logger.php` ~60–69 | `get_current_blog_id()` and `get_current_user_id()` wrapped in `function_exists()`. In WP 6.2+ and PHP 8.1 minimum these always exist. | Replace with direct calls; drop `function_exists`. Low risk. |
| 4.2 | `ErrorHandler.php` ~239–240 | `$file = isset($frame['file']) ? $frame['file'] : '';` and same for `line`. | Use null coalescing: `$frame['file'] ?? ''`, `(int) ($frame['line'] ?? 0)`. PHP 8.1+. |
| 4.3 | `UpdateManager.php` | Large class (~1120 lines). Many private static methods. | Consider extracting: (1) “Plugin log/version” helpers, (2) “Theme log/version” helpers, (3) “Core/feedback” helpers into dedicated classes (e.g. PluginUpdateLogger, ThemeUpdateLogger, CoreUpdateLogger) and call from UpdateManager. Improves readability and testability; do incrementally to avoid regressions. |
| 4.4 | `Logger::get_logs` and `Logger::get_logs_count` | Similar `$where` / `$values` building and sanitization. | Extract a private method e.g. `build_logs_where_clause(array $args): array{where: string, values: array}` and reuse in both to reduce duplication. |
| 4.5 | `Settings::rest_update_settings` | Loop over option_name => param with repeated `has_param` / `get_param` and type-specific logic. | Keep as is or extract small helpers (e.g. `apply_retention_days(int $v): int`, `apply_notify_on(array $v): array`) for clarity. Low priority. |
| 4.6 | `ErrorHandler.php` ~160, ~165 | `function_exists('wp_normalize_path')` before calling. | WordPress always provides `wp_normalize_path` in admin/update context. Can call directly for PHP 8.1 + WP 6.2+. Optional. |
| 4.7 | `UpdateManager.php` | Repeated `function_exists('get_plugins')` / `function_exists('get_plugin_data')` checks. | In plugin context these are always loaded. Can be replaced by direct calls; keep if you want to support edge cases where plugin.php is not loaded. Document if you keep. |

---

## 5. PHP 8.1+ alignment

| # | Item | Status / action |
|---|------|------------------|
| 5.1 | Typed properties, return types, union types | Already used (e.g. `bool\|WP_Error`, `array<string,mixed>`). Good. |
| 5.2 | `match` expressions | Optional: replace some long `if/elseif` chains (e.g. action_type or log_type handling) with `match` for readability. |
| 5.3 | First-class callable syntax | Optional: e.g. `array_map(self::sanitize_log_type(...), $arr)` where applicable. Not required. |
| 5.4 | `$frame['file'] ?? ''` | Use null coalescing instead of `isset($x) ? $x : default` where applicable (see 4.2). |
| 5.5 | No `create_function` / old-style callbacks | None found. OK. |

---

## 6. Docblock and consistency

| # | Location | Action |
|---|----------|--------|
| 6.1 | `UpdateManager.php` | Class has two docblocks (lines ~3 and ~16). Merge into one class docblock. |
| 6.2 | `Security.php` | `ALLOWED_UPDATE_CONTEXT` and `ALLOWED_PERFORMED_AS`: ensure each has a single, clear `@var` or description (merge duplicate docblock for performed_as). |
| 6.3 | `Logger::log` | Docblock lists “One of: core, plugin, theme” but log_type also allows `translation`. Update to “One of: core, plugin, theme, translation.” |
| 6.4 | `menu.php` | File-level docblock missing. Add `@package updatescontrol` and one-line description. |
| 6.5 | `links.php` | Filter is added before the function that’s hooked; consider adding a short file docblock. |

---

## 7. Other

| # | Item | Action |
|---|------|--------|
| 7.1 | `index.php` | Contains only `// Silence is golden`. Standard for directory listing protection. Keep. |
| 7.2 | `Settings::rest_cleanup_logs` | Calls `UpdatesControl_Logger::delete_older_than($days)` but does not pass request params; uses option. Correct. No change. |
| 7.3 | REST `site_id` | Still used in Settings/Logger. Kept for single-site with default 1. No change. |

---

## Todo list (for implementation)

- [x] **Dead code**: Remove unreachable `folder_exists` block in `ErrorHandler.php` (lines 95–97).
- [x] **Comments**: Remove or move Bootstrap “Bootstrap is loaded by the main plugin file” into docblock.
- [x] **Comments**: Move UpdateManager OB/bulk/PHP comments (lines 86–89) into `initialize_pending_logs` docblock; remove inline.
- [x] **Comments**: Fix or remove `.config/.php-cs-fixer.php` “Front usefull config”.
- [x] **Docblocks**: Merge Security.php duplicate docblock for `ALLOWED_PERFORMED_AS` into one.
- [x] **Docblocks**: Convert `updatescontrol_activate` / `updatescontrol_deactivate` comments to full docblocks.
- [x] **Docblocks**: Add file-level docblock to `menu.php`; optionally to `links.php`.
- [x] **Docblocks**: Merge UpdateManager double class docblock into one; fix Logger @param log_type to include “translation”.
- [x] **PHP 8.1**: Logger – use `get_current_blog_id()` and `get_current_user_id()` without `function_exists`.
- [x] **PHP 8.1**: ErrorHandler `capture_trace` – use `$frame['file'] ?? ''` and `(int) ($frame['line'] ?? 0)`.
- [x] **Refactor (optional)**: Extract Logger `build_logs_where_clause` (or similar) for get_logs and get_logs_count.
- [ ] **Refactor (optional)**: Consider splitting UpdateManager into smaller classes (plugin/theme/core logging) in a later pass.
- [x] **Constants**: Decide whether to keep or simplify `inc/core/constants.php` fallback; document loading order.

---

## Summary

- **Remove**: One unreachable block (ErrorHandler folder_exists message), and a few redundant inline comments after moving them to docblocks.
- **Improve**: Docblocks (merge duplicates, add missing file/function docblocks, fix Logger log_type description).
- **Modernize**: Use null coalescing and drop unnecessary `function_exists` where WP/PHP 8.1 guarantee presence.
- **Optional**: Extract shared logic in Logger; consider splitting UpdateManager for maintainability; use `match` / first-class callables where it helps readability.

All changes should be validated with existing tests or manual runs (logging, REST, activation/deactivation, and update flows).
