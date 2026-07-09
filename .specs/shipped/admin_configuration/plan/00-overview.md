# Admin Configuration — Plan Overview

This is the manual for the plan. It is never implemented or moved. Read it before any task.

The feature adds an **Admin Configuration** area: a `/admin` shell with a left sidebar and
four sections. After the grill (see `../resolution-log.md` → *Feedback & decisions*), the
scope is deliberately lean — a working shell plus one real relocated form and three light
section pages. **No export engine, no importer, no DB conversion, no new tables, no migration.**

## Execution order

| # | Task | Purpose |
| --- | --- | --- |
| 01 | `01-admin-shell.md` | The `access-admin` gate, `/admin` route group, `<x-admin-layout>` + sidebar, nav entry relabel, four thin controllers, and placeholder section views. **The Appearance page's placeholder is its final v1 form** (no later task enriches it). |
| 02 | `02-general-settings.md` | Relocate the search-engine (crawler) form into Admin → General settings; delete the old `crawler-settings.*` controller/routes/view; migrate its test. |
| 03 | `03-export-import-stub.md` | The Export/Import page: inline-Alpine tabs (Export, Import) with accessible roles and a "coming soon" body. No backup engine. |
| 04 | `04-database-readonly.md` | Database configuration: read-only display of the active connection. Never renders the password. |

Tasks 02, 03, 04 each **depend only on 01** and are independent of one another.

## Binding design defaults (decided in the grill — do not re-litigate)

- **Q1 — access:** every `/admin` route is behind `auth` **and** a single `access-admin` Gate
  that returns `true` for any authenticated user. This is the *deliberate continuation* of the
  `CrawlerSetting` "any authenticated user" exception (`documentation/architecture.md` →
  *Hidden from crawlers*), encoded **once** on the group so it can be tightened later in one
  place. There is **no `is_admin` role** and none is added.
- **Q2 — old route:** the crawler settings screen is **renamed and its old route deleted**
  (`crawler-settings.*` → `admin.settings.*`), not aliased.
- **Q3 — export/import:** **stub only.** Both tabs render with a short "coming soon" line; the
  real backup/restore engine is a separate future spec.
- **Q4 — database:** **read-only** connection display, and **no** explanatory/CLI note in the
  UI (that rationale stays in the spec docs, not on screen).
- **Q5 — tabs:** **inline Alpine**, no reusable `x-tabs` component.
- **Q6 — nav label:** the user-dropdown entry reads **"Configuration"** and lands on
  `admin.index` (→ General settings).

## Invariants every task must preserve

1. **Authorization posture.** Every admin route sits behind `auth` + `can:access-admin`.
   These routes are **not** `Project`-owned, so they do **not** use `ProjectPolicy` or any
   project walk — do not invent one. Every task that adds an admin route adds a **guest →
   login redirect** test, and the plan asserts (once) that a *second* authenticated user also
   gets `200` — proving the any-authenticated-user posture is intentional, not a missing check.
2. **One name per screen.** After task 02 there is exactly one route/controller/view for the
   search-engine settings (`admin.settings.*` / `GeneralSettingsController`); the old
   `crawler-settings.*` names are gone with no dangling references.
3. **Nav active state follows the documented convention.** Both the top-nav entry and the
   admin sidebar use a single `@php` block of `request()->routeIs('admin.*')` booleans, apply
   `aria-current="page"` (never colour-only), and keep any desktop/responsive copies identical
   (`documentation/architecture.md` → *Navigation active state*).
4. **Reuse, don't reinvent.** Use `x-app-layout`, `x-primary-button`, `x-input-label`,
   `x-input-error`, `x-card`, existing card/heading treatments (`documentation/ui-components.md`).
   No new component beyond `<x-admin-layout>` and the sidebar partial; **no** `x-tabs`.
5. **Never leak the DB password.** The database page renders driver / database name-or-path /
   host only; the `password` (and ideally `username`) config value must never reach the HTML.
6. **Leave the rest untouched.** `CrawlerSetting` (model, columns, `current()`,
   `UpdateCrawlerSettingRequest`) is unchanged. `/robots.txt`, `RobotsTxtController`, and the
   `x-robots-meta` head tag (inherited via `x-app-layout`) are not modified.
7. **Green at every boundary.** `composer test` passes after each task. No task may leave a
   broken route, a sidebar link to a non-existent route, or a removed route still referenced.

## Where things live (this feature)

| Concern | Location |
| --- | --- |
| Admin access check | `Gate::define('access-admin', …)` in `AppServiceProvider@boot`; `can:access-admin` middleware on the `/admin` group |
| Admin routes | `routes/web.php`, `Route::prefix('admin')->name('admin.')` inside the `auth` group |
| Section controllers | `app/Http/Controllers/GeneralSettingsController`, `AppearanceController`, `DataTransferController`, `DatabaseConfigurationController` (all thin) |
| Search-engine validation | existing `UpdateCrawlerSettingRequest` (unchanged) |
| Admin shell UI | `resources/views/components/admin-layout.blade.php`, `resources/views/admin/partials/sidebar.blade.php` |
| Section views | `resources/views/admin/{settings,appearance,data,database}/*.blade.php` |

Consult `../expanded/architecture.md`, `../expanded/ui.md`, and `../expanded/testing.md` for detail.
