# Task 01 — Admin shell & navigation skeleton

## Scope

Stand up a fully navigable admin area: the access gate, the `/admin` route group, the shared
layout + sidebar, the top-nav entry point, four thin controllers, and placeholder views for
all four sections. After this task the whole area works end-to-end (every route resolves, the
sidebar highlights the current section), even though three sections are still placeholders.

**Builds:**

- **Access gate.** `Gate::define('access-admin', fn (\App\Models\User $user) => true)` in
  `App\Providers\AppServiceProvider@boot`. Applied as `can:access-admin` middleware on the
  admin group (which is itself inside the existing `auth` group). Add a short comment: this is
  the deliberate continuation of the `CrawlerSetting` any-authenticated-user posture, kept in
  one place so it can be tightened later without touching controllers.
- **Route group** in `routes/web.php`, inside `Route::middleware('auth')->group(...)`:
  ```php
  Route::middleware('can:access-admin')->prefix('admin')->name('admin.')->group(function () {
      Route::get('/', fn () => redirect()->route('admin.settings.edit'))->name('index');
      Route::get('/settings',   [GeneralSettingsController::class, 'edit'])->name('settings.edit');
      Route::get('/appearance', [AppearanceController::class, 'edit'])->name('appearance.edit');
      Route::get('/data',       [DataTransferController::class, 'index'])->name('data.index');
      Route::get('/database',   [DatabaseConfigurationController::class, 'edit'])->name('database.edit');
  });
  ```
  (Task 02 adds `PATCH /admin/settings`; do not add write routes here.)
- **Four thin controllers** (each just returns its view for now):
  `GeneralSettingsController@edit`, `AppearanceController@edit`, `DataTransferController@index`,
  `DatabaseConfigurationController@edit`.
- **`<x-admin-layout>`** (`resources/views/components/admin-layout.blade.php`): wraps
  `<x-app-layout>`, forwards an optional `header` slot, and renders a responsive two-column
  layout — sidebar (`w-64 shrink-0`) beside `{{ $slot }}` on `md+`, stacked on mobile.
  Container mirrors the current settings page (`max-w-7xl mx-auto sm:px-6 lg:px-8 py-12`).
- **Sidebar partial** (`resources/views/admin/partials/sidebar.blade.php`): a
  `<nav aria-label="Configuration">` list linking the four sections (labels: **General
  settings**, **Appearance & accessibility**, **Export & import**, **Database configuration**).
  Active state via a single `@php` block of booleans — `request()->routeIs('admin.settings.*')`,
  `admin.appearance.*`, `admin.data.*`, `admin.database.*` — each link getting
  `aria-current="page"` + highlight classes when active (never colour-only).
- **Top-nav entry relabel** in `resources/views/layouts/navigation.blade.php`: the
  user-dropdown link currently reading **"Site settings"** → `crawler-settings.edit` becomes
  **"Configuration"** → `route('admin.index')`. Update **both** copies (desktop
  `x-dropdown-link` and responsive `x-responsive-nav-link`) identically.
- **Placeholder section views:**
  - `admin/appearance/edit.blade.php` — **final v1 content:** `<x-admin-layout>` with a
    "Appearance & accessibility" heading and one muted paragraph (e.g. "Graphical and
    accessibility options will live here."). No form. This page is done after this task.
  - `admin/settings/edit.blade.php`, `admin/data/index.blade.php`,
    `admin/database/edit.blade.php` — temporary stubs: `<x-admin-layout>` + the section
    heading only. Replaced by tasks 02 / 03 / 04.

## Explicitly NOT in this task

- The real search-engine form and deletion of `crawler-settings.*` → **task 02**. This task
  leaves the existing `CrawlerSettingController`/routes/view **untouched and green**; it only
  removes the *nav link* to them (repointed to `admin.index`).
- The Export/Import tabs interaction → **task 03**.
- The database connection display → **task 04**.

## Depends on

- Nothing. First task.

## Key decisions already made (binding)

- Any authenticated user passes `access-admin`; no `is_admin` role (Q1).
- Nav label is "Configuration", landing on `admin.index` → General settings (Q6).
- Sidebar/nav active state uses the documented `@php`-boolean + `aria-current` pattern
  (invariant 3), not colour-only, desktop/responsive copies identical.
- Only `<x-admin-layout>` + the sidebar partial are new components — no other new UI
  primitives (invariant 4).

## Consult

- `../expanded/architecture.md` (routing, the admin shell, authorization).
- `../expanded/ui.md` (layout diagram, sidebar spec, labels).
- `documentation/architecture.md` → *Navigation active state* for the exact boolean/aria pattern.

## Tests (`tests/Feature/AdminConfigurationTest.php`)

- **Guest redirect:** `GET admin.index`, `admin.settings.edit`, `admin.appearance.edit`,
  `admin.data.index`, `admin.database.edit` as a guest → redirect to `login`.
- **Index redirect:** authenticated `GET admin.index` → redirects to `admin.settings.edit`.
- **All sections load:** authenticated `GET` on each section route → `200`.
- **Any-authenticated-user posture:** a *second* user (not the "first"/owner) also gets `200`
  on the admin routes — assert this with a test name/comment making clear it is the deliberate
  continuation of the `CrawlerSetting` exception (invariant 1), so no later reader "fixes" it.
- **Sidebar active state:** on each section page, its sidebar link carries `aria-current="page"`
  and the others do not.
- **Appearance final page:** `GET admin.appearance.edit` contains its heading and has **no**
  `<form>`/`<input>`.
- **Suite stays green:** the existing crawler-settings test still passes (this task must not
  touch that controller/route).
