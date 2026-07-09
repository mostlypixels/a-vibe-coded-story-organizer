# Task 03 — Export / Import stub page

## Scope

Turn the Export/Import placeholder from task 01 into the real page **shell** — two accessible
tabs — but with **placeholder content only**. No backup engine, no upload handling.

**Builds:**

- **Replace `admin/data/index.blade.php`** (task-01 stub) with an `<x-admin-layout>` page whose
  heading is "Export & import" and body is an **inline-Alpine tab interface**:
  - `x-data="{ tab: 'export' }"`, a `role="tablist"` with two `role="tab"` buttons (Export,
    Import) toggling `tab`, and two `role="tabpanel"` panels shown via `x-show`.
  - Accessibility: `aria-selected` on the active tab, `aria-controls`/`id` wiring between tab
    and panel, arrow-key navigation between tabs, and a visible focus ring. Keyboard operable
    without a mouse.
  - Each panel body is a short **"coming soon"** line (e.g. "Exporting your data will be
    available soon." / "Importing a backup will be available soon."). No forms, no buttons that
    post anywhere.
- **No new routes or controller actions.** `DataTransferController@index` (from task 01) still
  just returns this view. Do **not** add `export`/`import` POST routes yet — they belong to the
  future backup spec.

## Explicitly NOT in this task

- `App\Services\SiteExporter` / `SiteImporter`, any download or upload, any `ValidateImportRequest`,
  any `admin.data.export` / `admin.data.import` routes → a **separate future spec** (Q3). Do not
  scaffold empty versions of these.

## Depends on

- **Task 01** (admin group, `DataTransferController`, `<x-admin-layout>`, sidebar).

## Key decisions already made (binding)

- Export/Import is **stubbed** for v1 (Q3) — page + tabs + "coming soon" only.
- Tabs are **inline Alpine**, not a reusable `x-tabs` component (Q5); extract a component only
  when a second screen needs tabs.
- Tabs must be keyboard-accessible with ARIA roles (project frontend rule: keyboard
  accessibility, semantic HTML).

## Consult

- `../expanded/ui.md` → *Section 3. Export & import* (tab structure, ARIA, "coming soon").
- `../expanded/architecture.md` → *Section 3* (why the engine is deferred — for context only).

## Tests

- **Renders:** authenticated `GET admin.data.index` → `200`, contains the "Export & import"
  heading and both tab labels (Export, Import).
- **Sidebar active state:** the Export & import sidebar link carries `aria-current="page"` on
  this route.
- **Authorization:** guest → login redirect.
- (Tab *switching* is client-side Alpine — no server assertion needed; assert both panels'
  "coming soon" text is present in the rendered HTML.)
