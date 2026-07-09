# Admin Configuration — UI

Reuse existing components before adding new ones (`documentation/ui-components.md`,
`resources/views/components/*`). New UI is limited to the **admin shell** (layout + sidebar)
and — if Q3 approves — a small **tabs** interaction for Export/Import.

## Layout: sidebar + content pane

All admin pages render inside a new **`<x-admin-layout>`** (`components/admin-layout.blade.php`),
which wraps `<x-app-layout>` and lays out a responsive two-column grid:

```
┌───────────────────────────────────────────────┐
│ (top nav — unchanged x-app-layout)            │
├──────────────┬────────────────────────────────┤
│  Sidebar     │  Page header (x-slot header)    │
│  ▸ General   │                                 │
│    settings  │  ┌───────────────────────────┐  │
│  ▸ Appearance│  │  section content (cards)  │  │
│  ▸ Export &  │  │                           │  │
│    import    │  └───────────────────────────┘  │
│  ▸ Database  │                                 │
└──────────────┴────────────────────────────────┘
```

- Container mirrors the current settings page: `max-w-7xl mx-auto sm:px-6 lg:px-8 py-12`.
- Two columns on `md+` (e.g. `flex gap-8` with a `w-64 shrink-0` sidebar); on mobile the
  sidebar stacks **above** the content (semantic order already correct).
- Content cards reuse the existing card treatment
  (`p-4 sm:p-8 bg-white shadow sm:rounded-lg`, `space-y-6`) so General settings looks
  identical to today inside the new frame.

## Sidebar (`admin/partials/sidebar.blade.php`)

- Semantic `<nav aria-label="Configuration">` → `<ul>` of links.
- Reuse the **documented nav active-state convention** (`documentation/architecture.md` →
  *Navigation active state*): a single `@php` block of route-match booleans

  ```php
  $generalActive    = request()->routeIs('admin.settings.*');
  $appearanceActive = request()->routeIs('admin.appearance.*');
  $dataActive       = request()->routeIs('admin.data.*');
  $databaseActive   = request()->routeIs('admin.database.*');
  ```

  and each link gets `aria-current="page"` + the highlight classes when active — **never
  colour-only** (tests assert on `aria-current`, per the nav convention).
- Labels: **General settings**, **Appearance & accessibility**, **Export & import**,
  **Database configuration**.

> [!NOTE]
> The sidebar is the section switcher **within** admin; it is not the top nav. It does not
> touch `layouts/navigation.blade.php` except for the single entry-point link below.

## Top-nav entry point

In `resources/views/layouts/navigation.blade.php`, the user-dropdown link currently reading
**"Site settings"** → `crawler-settings.edit` becomes the admin entry:

- Label → **"Configuration"** (or "Admin") pointing at `route('admin.index')` (or
  `admin.settings.edit`).
- Update **both** copies — the desktop `x-dropdown-link` and the responsive
  `x-responsive-nav-link` — per the doc's "the desktop and responsive copies stay identical".

## Section pages

### 1. General settings (`admin/settings/edit.blade.php`)

- Page `<x-slot name="header">` → "Configuration" (or the section name).
- One card with heading **"General settings"** (the source spec's requested main title),
  then the **existing search-engine form moved verbatim** from
  `settings/crawlers/edit.blade.php`: the hidden-mode checkbox, the whitelist textarea (with
  its `old()`-vs-DB value reconstruction), the **Save** button, the **Preview robots.txt**
  link, and the `session('status') === 'crawler-settings-updated'` "Saved." flash. Keep the
  form fields byte-for-byte to avoid regressing validated behaviour; only the wrapper and
  `action`/route name change.

### 2. Appearance & accessibility (`admin/appearance/edit.blade.php`)

- One card, heading **"Appearance & accessibility"**, and a short muted paragraph
  ("Graphical and accessibility options will live here.") — **no form**. Deliberately empty
  placeholder so the sidebar is complete.

### 3. Export & import (`admin/data/index.blade.php`) — pending Q3 scope

- Two **tabs**: *Export* and *Import*. There is **no `x-tabs` component yet** — implement
  with **inline Alpine** (`x-data="{ tab: 'export' }"`, buttons toggling `tab`, panels shown
  with `x-show`), which matches the project's "no abstraction before a second caller" rule.
  Only extract an `x-tabs` component if a second screen needs tabs later.
  - Tabs must be keyboard-accessible: `role="tablist"`/`role="tab"`/`role="tabpanel"`,
    `aria-selected`, arrow-key navigation, and a visible focus ring (KISS: a small Alpine
    block is fine; don't pull a library).
- **Export tab:** a short description + a **Download** button posting to `admin.data.export`
  (returns a file download). Scope of the artifact = Q3.
- **Import tab:** an upload `<form enctype="multipart/form-data">` posting to
  `admin.data.import`, with `x-input-error` for validation messages and — if import is
  destructive/replace — an explicit confirmation (checkbox or `x-modal`, both already exist).

### 4. Database configuration (`admin/database/edit.blade.php`) — default read-only (Q4)

- One card, heading **"Database configuration"**, showing the **current** connection: driver,
  database name/path, host (**never the password**), read from `config()`/`DB`.
- A muted explanation that switching backends / converting SQLite⇄MySQL is a CLI/ops
  operation, with guidance — **no dangerous buttons** in v1 unless Q4 approves and scopes it.

## Accessibility & reuse checklist

- Sidebar + tabs fully keyboard-navigable; active/selected state carries an ARIA attribute,
  not just colour.
- Reuse `x-primary-button`, `x-input-label`, `x-input-error`, `x-card`, `x-modal`,
  `x-alert` — do not hand-roll buttons/inputs (`documentation/ui-components.md`).
- Every page renders through `x-app-layout`, so the `x-robots-meta` head tag and top nav are
  inherited unchanged.
