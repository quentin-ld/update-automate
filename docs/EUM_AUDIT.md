# EUM (stops-core-theme-and-plugin-updates) Audit

Audit of how EUM logs updates/downgrades for parity in updates-control.

---

## 1. EUM Schema (`eum_logs`)

| Column       | Type         | Purpose                                      |
|-------------|--------------|----------------------------------------------|
| log_id      | bigint       | Primary key                                  |
| user_id     | bigint       | User who performed the update                |
| name        | varchar(255) | Display name (e.g. "WordPress", "My Plugin") |
| type        | varchar(255) | core, plugin, theme, translation             |
| version_from| varchar(255) | Version before update                        |
| version     | varchar(255) | Version after update                         |
| action      | varchar(255) | **manual** or **automatic**                  |
| status      | varchar(255) | 0=failure, 1=success, 2=not compatible       |
| notes       | text         | Process feedback from upgrader skin          |
| stacktrace  | text         | Serialized `debug_backtrace()` (normalised)  |
| date        | datetime     | When the update ran                          |

---

## 2. EUM Flow

1. **`pre_auto_update`** – Sets `$auto_update = true` (manual vs automatic).
2. **`upgrader_package_options`** – Initializes `$log_messages` with name, version_from, version_to *before* update (so fatals can still be logged).
3. **`upgrader_pre_download`** – For core: initializes core log, hooks `update_feedback` to collect notes.
4. **`upgrader_process_complete`** – Main logging: captures notes from `skin->get_upgrade_messages()`, builds stacktrace, inserts log.
5. **`automatic_updates_complete`** – Logs remaining auto-update results (in case `upgrader_process_complete` didn’t run).
6. **`register_shutdown_function`** – `maybe_log_updates()` flushes any pending `$log_messages` (e.g. on fatal error).

---

## 3. EUM vs updates-control

| Aspect               | EUM                                          | updates-control                           | Parity |
|----------------------|-----------------------------------------------|-------------------------------------------|--------|
| **logged**           | name, type, from, to, action, status, notes, stacktrace, user_id, date | item_name, log_type, action_type, version_before, version_after, status, message, trace, user_id, created_at | ✓ |
| **notes**            | `notes` (skin feedback)                       | `message` (title + steps + skin feedback) | ✓ |
| **stack trace**      | Serialized backtrace, `normalise_call_stack_args` | `capture_trace()` string                   | ✓ |
| **status**           | 0=failure, 1=success, 2=not compatible        | success, error, cancelled                 | Different but equivalent |
| **manual/automatic** | `action` = manual \| automatic                 | **Missing** – only `performed_by` (user/system) | ❌ |
| **version_from / to**| version_from, version                          | version_before, version_after              | ✓ |
| **type**             | core, plugin, theme, **translation**           | core, plugin, theme                       | ❌ (no translation) |
| **user**             | user_id                                       | user_id, performed_by_display             | ✓ |
| **name**             | name                                          | item_name                                 | ✓ |
| **date**             | date                                          | created_at                                | ✓ |
| **Shutdown fallback**| `maybe_log_updates()` on shutdown              | None                                       | ❌ |
| **automatic_updates_complete** | Yes                                    | None                                       | ❌ |
| **upgrader_package_options** | Yes (init before update)              | Uses options/transient for version_before  | Different approach, both capture version |

---

## 4. Gaps to Implement

### Required

1. **manual vs automatic**
   - Add `performed_as` column: `manual` | `automatic`.
   - Hook `pre_auto_update` to set flag.
   - Pass `performed_as` into `Logger::log()` and store it.

2. **Shutdown fallback**
   - `register_shutdown_function` to log any pending updates on fatal error.
   - Requires EUM-style pre-initialisation via `upgrader_package_options` (or equivalent).

3. **automatic_updates_complete**
   - Hook to log automatic updates when `upgrader_process_complete` might not run.

### Optional (for full EUM parity)

4. **Translation updates**
   - Add `translation` to allowed log types and handle in UpdateManager.
   - EUM logs plugin/theme/core language pack updates.

5. **upgrader_package_options**
   - Initialize version_from/to *before* update so shutdown can still log.
   - Our current approach (options/transient) may miss fatals that occur before `upgrader_process_complete`.

---

## 5. UI Mapping (EUM list table columns)

| EUM Column     | updates-control equivalent                 |
|----------------|--------------------------------------------|
| User           | performed_by_display (user link)           |
| Name           | item_name                                  |
| Type           | log_type (core, plugin, theme)             |
| From           | version_before                             |
| To             | version_after                              |
| Action         | **performed_as** (manual/automatic) – to add |
| Status         | status                                     |
| Date           | created_at                                 |
| Notes          | message (Show notes modal)                 |
| Stack Trace    | trace (Show trace modal)                   |

Our UI already exposes message and trace via a “View logs” modal; EUM uses separate “Show notes” and “Show Trace” links.
