# Task 13 — Admin "Revisions" settings page

## Scope

A new, dedicated admin page (not folded into "Export & import" or "General settings" —
confirmed in grilling) at (e.g.) `/admin/revisions`, named `admin.revisions.edit`
following the `admin.` prefix/name-group convention already in `routes/web.php`:

* **Retention form**: one field, `retention_days`, via `UpdateRevisionSettingRequest`
  (`min:7`, `max:3650`). **Confirm-gated when lowering the value**: submitting a lower
  number returns a confirmation screen/step showing the exact count the next nightly
  prune would remove — computed from the **real** `Revision::prunable()` query object
  (task 1/12) evaluated as if the new value were already active, never a hand-rolled
  estimate — with Confirm/Cancel; nothing changes until confirmed. Raising the value
  skips confirmation (per `handoff.md` §9.11, it cannot delete anything). Works without
  JavaScript — a plain two-step POST/confirm form.
* **Storage panel**: counts + `SUM(size_bytes)` total, broken down by origin
  (automatic/manual/labeled/imported), via `RevisionPurger`/direct query (never
  hydrating `value`). Bulk-delete actions per category and per age ("imported", "auto
  older than 1 year"), each behind the existing `x-dialog` confirm component, calling
  `RevisionPurger` (task 12) — the second of its two call sites.
* Nav entry for the new page in wherever the admin sidebar/nav partial lives (locate it
  before adding — likely alongside the existing `admin.settings`/`admin.data` entries).

Does **not** include any change to `RevisionPurger`, `revisions:purge`, or
`RevisionSetting` themselves (task 12) — this task is the second caller only.

## Depends on

Task 12 (`RevisionSetting`, `RevisionPurger`).

## Key decisions already made

* **Dedicated page, not folded into an existing admin section** (grilling decision this
  session) — mirrors how `DatabaseConfigurationController` already gets its own
  `/admin/database` page for a similarly narrow, storage-adjacent concern, rather than
  being crammed into General settings.
* **No live AJAX count-as-you-type widget** — the confirm-count is computed
  server-side on submit, exactly the query behind `--pretend`, per `handoff.md` §9.11's
  explicit rejection of a live-fetch widget ("a slow or failed lookup shows a blank or
  stale figure exactly when accuracy matters").
* **`access-admin` gate**, matching every other page under the `admin.` route group in
  `routes/web.php` (confirmed this session — `Route::middleware('can:access-admin')
  ->prefix('admin')->name('admin.')`).

## Consult

* `expanded/ui.md` — "'Revision storage' panel", "Retention setting" sections.
* `expanded/architecture.md` — the `RevisionSetting` controller's confirm-count logic
  description.
* `handoff.md` §4.3, §9.11.
* `routes/web.php`'s existing `admin.*` group (already read this session) — match its
  exact structure for the new routes.
* `app/Http/Controllers/ImportSettingController.php` — the thin-controller pattern to
  mirror for the retention form's non-confirm path.

## Tests

* Page renders 200 behind `access-admin`, 403/redirect for a non-admin (whatever this
  app's existing gate-denial behavior is — check an existing `admin.*` test for the
  expected response shape).
* Raising `retention_days` saves immediately, no confirmation step shown.
* Lowering `retention_days` returns a confirmation screen with a count matching a
  directly-computed `Revision::prunable()`-style query seeded with known rows (assert
  the exact number, not just "a number").
* Confirming the lowered value actually persists it; canceling leaves the prior value
  unchanged and deletes nothing.
* Storage panel displays correct per-category counts/totals against seeded rows of each
  origin.
* Each bulk-delete action removes exactly the targeted category/age slice and nothing
  else (mirrors task 12's `RevisionPurger` tests, but exercised through this page's
  controller action specifically, proving the second call site is wired correctly).
