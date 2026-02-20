# Activity Logs Data View — Implementation Spec

This document is the single source of truth for building the WordPress Gutenberg Data Views component that displays activity logs using only official Gutenberg components.

**References (official only):**

- DataViews: https://wordpress.github.io/gutenberg/?path=/docs/dataviews-dataviews--docs
- Layout (activity-style): https://wordpress.github.io/gutenberg/?path=/story/dataviews-dataviews--layout-activity
- Icons: https://wordpress.github.io/gutenberg/?path=/story/icons-icon--library
- Badge: https://wordpress.github.io/gutenberg/?path=/docs/design-system-components-badge--docs

---

## 1. Component setup

- **Package:** Use the **wp** build of DataViews: `import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews/wp';` so the bundle is self-contained and avoids version conflicts (see [Using Data Views in plugins](https://developer.wordpress.org/news/2024/08/using-data-views-to-display-and-interact-with-data-in-plugins/)). Do not add `@wordpress/dataviews` or `@wordpress/icons` to `package.json` dependencies; use `@wordpress/dataviews` only as a devDependency for the build. Load DataViews styles via `@import '@wordpress/dataviews/build-style/style.css'` in your main SCSS (after all `@use` rules) and enqueue the built CSS with `wp-components` as a dependency.
- **Layout:** Use the **list** layout configured as an activity-style feed:
  - Set `view.type` to `'list'`.
  - Use `titleField` and `descriptionField` on the view to get the “title + description” activity row (no separate “layout-activity” type in the package; the Storybook “Layout Activity” is list with these fields).
- **Docs:** Follow the DataViews docs for props: `view`, `onChangeView`, `fields`, `data`, `actions`, `paginationInfo`, `defaultLayouts`, `getItemId`, etc.

---

## 2. Icon

- Use one icon from the official Gutenberg icon library (`@wordpress/icons`).
- **Preferred (in order):** `wordpress`, `plugin`, `brush` (theme), or `language` (translation).
- **Choice:** `plugins` (from `@wordpress/icons`) — fits “updates control” / plugin activity logs. Used for the “View logs” action.

---

## 3. Item display format

### Title (event title)

- Pattern: `[Name of item] + " updated" + " — " + [action]`
- Example: `Contact Form updated — Field added`
- Implement in a field’s `render` or `getValue` (e.g. computed `title` field) from `item_name` and `action_type` (or equivalent).

### Description

- If two versions exist: `[From version] → [To version]`
- If only one version (e.g. install/reset): `[Version]`
- Examples: `v1.2 → v1.3` or `v1.0 (installed)`
- Implement in another field’s `render` / `getValue` (e.g. computed `description` from `version_before`, `version_after`, and action type).

---

## 4. Item fields (per row)

Each log entry must show:

| Field      | Format / component |
|-----------|---------------------|
| **Date**  | Human-readable date/time (e.g. from `created_at`). |
| **Context** | Plain text (e.g. “Plugin settings”, “Theme update”). |
| **User**  | Clickable link: use a link with `href` (e.g. `user_edit_link`). Prefer DataViews patterns (e.g. `__experimentalItemLink` if documented) or a simple `<a>` in the field `render`. |
| **Status** | Badge-style UI: use design-system classes `components-badge is-{intent}` (success, warning, error, default). Note: `Badge` is not in the public `@wordpress/components` export; the implementation uses a span with the same classes for consistent styling. |

All of these can be implemented as DataViews **fields** with custom `render` / `getValue` so they appear in the list (and optionally table) layout.

---

## 5. Actions per item

Each row must include:

1. **“View logs”** — Opens a dialog/modal with full log details (message, trace, etc.). Use DataViews item actions (e.g. `__experimentalItemAction` or an action that opens a Modal).
2. **“Delete”** — Destructive; opens a confirmation dialog before calling delete. Use an action with `isDestructive: true` and a confirmation step (e.g. `ActionModal` with a confirm dialog) or a `Button` with `isDestructive` in a custom modal.

Use only official APIs: e.g. DataViews `actions` array with `ActionModal` / `ActionButton` and Gutenberg `Modal`, `Button`.

---

## 6. Toolbar (top of Data Views)

- **Search:** Use DataViews built-in search (e.g. `search={ true }` and optional `searchLabel`).
- **Filters:** Use Data Views built-in filters (dropdown/toggle) via `fields` with `filterBy` / `elements` where applicable.
- **Appearance:** Use DataViews view config (layout toggle: list/grid/table if provided in `defaultLayouts`). Compose with `Toolbar` / `ToolbarGroup` only if the docs show it and it’s needed.

All of this is provided by the default DataViews toolbar when using `search`, filters, and `defaultLayouts`.

---

## 7. Constraints

- Only official WordPress Gutenberg components and packages.
- No external libraries or custom CSS unless strictly necessary and documented in Gutenberg docs.
- UI must be accessible and follow Gutenberg design patterns.

---

## 8. Data and wiring

- **Data:** Use existing log API and `useLogs` (or equivalent). Each log item must have at least: id, date, context, user link, status, item name, action type, version_before, version_after, and full log content for “View logs”.
- **Badge mapping:** Map log status to Badge `intent`: e.g. success, warning, error, default (define exact mapping in code).
- **“View logs” dialog:** Use Gutenberg `Modal` to show full log details (message, trace, etc.); trigger from a DataViews item action.

---

## 9. Deliverable

- A single, reusable React component (e.g. `ActivityLogsDataView` or `LogsDataView`) that:
  - Uses `DataViews` from `@wordpress/dataviews`.
  - Uses list layout with `titleField` and `descriptionField` for activity-style rows.
  - Implements the fields and actions above with official components only.
- Component and key props documented in code (JSDoc) and aligned with WordPress/Gutenberg standards.

---

## 10. Audit: WordPress Developer Blog compliance

This implementation is aligned with [Using Data Views to display and interact with data in plugins](https://developer.wordpress.org/news/2024/08/using-data-views-to-display-and-interact-with-data-in-plugins/):

| Requirement | Status | Notes |
|-------------|--------|--------|
| Import from `@wordpress/dataviews/wp` | ✅ | `DataViews` and `filterSortAndPaginate` from `/wp` (bundled build). |
| No dataviews/icons in `dependencies` | ✅ | `@wordpress/dataviews` only in `devDependencies`; bundle is self-contained. |
| `@wordpress/scripts` ≥ 30.6.2 | ✅ | Project uses ^30.22.0. |
| DataViews styles from node_modules | ✅ | `@import '@wordpress/dataviews/build-style/style.css'` in main SCSS (after all `@use`). |
| Enqueue built CSS with `wp-components` | ✅ | `wp_enqueue_style( ..., array_merge( array( 'wp-components' ), ... ) )`. |
| `view` + `setView` + `onChangeView={setView}` | ✅ | `useState` for view; `setView` passed to DataViews. |
| `filterSortAndPaginate(rawData, view, fields)` | ✅ | Used in `useMemo`; returns `data` and `paginationInfo`. |
| DataViews props: data, fields, view, onChangeView, defaultLayouts, actions, paginationInfo | ✅ | All passed; plus `getItemId`, `search`, `searchLabel`, `isLoading` where applicable. |
| `defaultLayouts` with `layout` per type | ✅ | Table uses `layout: { primaryField }`; list uses `{}`. |
| Actions: id, label, callback or RenderModal | ✅ | View logs and Delete use `RenderModal`; article also documents callback. |
