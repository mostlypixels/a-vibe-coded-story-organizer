# Admin Configuration — Architecture

Everything here reuses existing conventions: thin controllers, Form Requests for validation,
the nav active-state pattern, and the `CrawlerSetting` singleton. The only genuinely new
pieces are the **admin shell layout** and (conditionally) the export/import + database
workflows, which belong in `app/Services` because they are multi-step.

## Routing

Group all sections under a single `/admin` prefix + `admin.` route-name namespace, inside the
existing `auth` group in `routes/web.php`. Add one access check (see *Authorization* below).

```php
// routes/web.php — inside Route::middleware('auth')->group(...)
Route::prefix('admin')->name('admin.')->group(function () {
    // Landing → first section.
    Route::get('/', fn () => redirect()->route('admin.settings.edit'))->name('index');

    // 1. General settings = the existing search-engine form, relocated.
    Route::get('/settings', [GeneralSettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings', [GeneralSettingsController::class, 'update'])->name('settings.update');

    // 2. Appearance & accessibility — placeholder (GET only for now).
    Route::get('/appearance', [AppearanceController::class, 'edit'])->name('appearance.edit');

    // 3. Export & import — one page, two tabs. (Scope per open-questions Q3.)
    Route::get('/data', [DataTransferController::class, 'index'])->name('data.index');
    Route::post('/data/export', [DataTransferController::class, 'export'])->name('data.export');
    Route::post('/data/import', [DataTransferController::class, 'import'])->name('data.import');

    // 4. Database configuration. (Scope per open-questions Q4 — default v1 is READ-ONLY.)
    Route::get('/database', [DatabaseConfigurationController::class, 'edit'])->name('database.edit');
    // Any write route here is gated on Q4 being answered "yes, build it".
});
```

### What happens to `/settings/crawlers`?

The current `crawler-settings.edit` / `crawler-settings.update` routes are the search-engine
form. Two clean options (Q2):

- **Recommended:** rename the controller/route to `admin.settings.*` and **delete** the old
  `crawler-settings.*` routes, updating the two nav links and the one view. The whitelist
  form fields and `UpdateCrawlerSettingRequest` are unchanged; only the wrapper layout and
  route names move.
- Alternative: keep `CrawlerSettingController` and add a `301`/named alias. Not recommended —
  it leaves two names for one screen.

Either way, keep the **live `/robots.txt` route and `RobotsTxtController` exactly as-is** —
they are unrelated to this refactor and must not move (`documentation/architecture.md` →
*Hidden from crawlers*).

## Controllers (all thin: resolve → authorize → delegate → respond)

| Controller | Actions | Delegates to |
| --- | --- | --- |
| `GeneralSettingsController` | `edit`, `update` | `CrawlerSetting::current()` + `UpdateCrawlerSettingRequest` (rename of today's `CrawlerSettingController`) |
| `AppearanceController` | `edit` | nothing — returns a placeholder view |
| `DataTransferController` | `index`, `export`, `import` | `App\Services\SiteExporter` / `SiteImporter` (only if Q3 approves building it) |
| `DatabaseConfigurationController` | `edit` (+ writes only if Q4 approves) | read-only: `config('database')` / `DB::connection()->getName()` |

`GeneralSettingsController` is a straight rename+relocate of `CrawlerSettingController`; its
body stays identical apart from the redirect target (`admin.settings.edit`). Keep
`UpdateCrawlerSettingRequest` and its `authorize()` as they are (any authenticated user),
consistent with Q1's default answer.

## Authorization

> [!WARNING]
> This is the **central authorization decision** — resolve Q1 before implementing.

The app has **no `is_admin` role**. `CrawlerSetting` is deliberately editable by *any*
authenticated user (`UpdateCrawlerSettingRequest::authorize()` returns `$this->user() !== null`;
`documentation/architecture.md` → *Hidden from crawlers*, "Authorization exception").

- **Default (recommended for v1):** the whole `/admin` area continues that posture — behind
  `auth`, no role gate. Encode it once as a route-level check so it isn't reinvented per
  controller. Use a single **Gate** (`Gate::define('access-admin', fn (User $user) => true)`)
  or a `can:access-admin` middleware on the group, so tightening it later (Q1 → roles) is a
  one-line change in one place instead of editing four Form Requests.
- Because these routes are **not** owned by a `Project`, they do **not** use `ProjectPolicy`'s
  walk. That is the same documented exception as `CrawlerSetting` — do **not** invent a
  project walk here.

> [!IMPORTANT]
> Export/import and (especially) database conversion are far more destructive than a robots
> toggle. If Q1 keeps "any authenticated user", the plan must call that out as an accepted
> risk in `CHANGELOG.md`/PR text, and Q3/Q4 should lean conservative. If the app is ever
> multi-user, "any authenticated user can export all data / drop the database" is a serious
> exposure — this is exactly why Q1 must be answered, not defaulted silently.

## The admin shell (layout + sidebar)

Add a reusable layout so every section shares the sidebar. Follow the existing component
convention (`resources/views/components/*`, `x-app-layout` is `layouts/app.blade.php`).

- **`resources/views/components/admin-layout.blade.php`** — wraps `<x-app-layout>`, renders a
  two-column grid: the sidebar partial on the left, `{{ $slot }}` on the right. Accepts an
  optional `$header` slot forwarded to `x-app-layout`.
- **`resources/views/admin/partials/sidebar.blade.php`** — a `<nav aria-label="Configuration">`
  listing the four sections as links. Active state uses the **documented nav pattern**: one
  `@php` block of `request()->routeIs('admin.settings.*')`-style booleans, applied to each
  link with `aria-current="page"` when active (mirror `x-nav-link`, never colour-only).

This keeps presentation out of the controllers and gives sections 2–4 a consistent frame.
Per KISS and "no abstraction before a second caller", the sidebar is a **partial**, not a new
`Nav` support class or view composer — the same reasoning the architecture doc gives for the
top nav.

## Section 3 — Export / Import (only if Q3 approves building now)

If built, the multi-step work lives in **`app/Services`** (the project's established home for
non-trivial reusable workflows, per the Codex `AttributeTimeline`/`CodexMediaService`
precedent), **not** in the controller:

- `App\Services\SiteExporter` — serialises the chosen scope (Q3) to a downloadable artifact
  (recommended: a `.zip` containing a `data.json` of the User→Project→…→Scene / Codex
  aggregate plus the `codex_media` files, since media lives on disk, not in the DB).
- `App\Services\SiteImporter` — validates and restores such an artifact **inside a
  `DB::transaction`**, following the Codex rule that **disk writes stay outside the
  transaction** (`documentation/architecture.md` → *The Codex*, post-commit file handling).

Validate the uploaded file in a Form Request (`ValidateImportRequest`): mime/zip, size limit,
and a manifest/version check before touching the DB. Never trust the archive's contents —
treat every value as user input and re-run model-level sanitizers (rich-text fields are
HTMLPurifier-mutated on write; that must still happen on import).

> [!WARNING]
> Import is **destructive/merging** — decide replace-vs-merge semantics (Q3). A naive "import"
> that truncates tables can wipe the site. If replace semantics are chosen, guard it behind an
> explicit confirmation and a transaction, and document it loudly.

## Section 4 — Database configuration (default v1 = READ-ONLY)

> [!WARNING]
> **Live switching of `DB_CONNECTION` and SQLite⇄MySQL conversion from a web request is
> genuinely dangerous** and is recommended **out of scope for v1** (Q4). It requires:
> rewriting `.env` (`DB_CONNECTION`, credentials) from a web process; running `migrate` against
> a possibly-empty target; streaming every row across drivers (type/collation/auto-increment/JSON
> differences between SQLite and MySQL); handling partial-failure and downtime; and doing all of
> this while the app is serving the very request that triggers it. A failure can leave the app
> pointing at an empty or half-populated database. This is an **ops/CLI task** (an Artisan
> command run with the app offline), not a click in a settings page maintained by junior devs.

**Recommended v1:** `DatabaseConfigurationController@edit` renders a **read-only** page showing
the active connection (`DB::connection()->getName()`, driver, database name — *never* the
password) and documented guidance/links for migrating backends via the CLI. No write routes.

If Q4 insists on building conversion now, it must be an **Artisan command** (`php artisan
db:convert ...`) that the page can *describe* or *queue*, executed by a worker with the site in
maintenance mode — not a synchronous controller action. Scope that as its **own spec**.

## Where logic lives (summary)

| Concern | Location |
| --- | --- |
| Admin access check | one Gate/middleware (`access-admin`) on the `admin.` route group |
| Search-engine validation | existing `UpdateCrawlerSettingRequest` (unchanged) |
| Import file validation | new `ValidateImportRequest` Form Request |
| Export/import workflow | `App\Services\SiteExporter` / `SiteImporter` |
| Current-DB read | controller reads `config()`/`DB` (trivial, no service) |
| Sidebar active state | `@php` boolean block in the sidebar partial (nav convention) |
| Reusable UI (layout, tabs) | `resources/views/components/*` |
