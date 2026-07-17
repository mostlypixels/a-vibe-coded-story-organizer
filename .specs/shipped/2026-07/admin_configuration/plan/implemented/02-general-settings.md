# Task 02 — General settings (relocate the crawler form)

## Scope

Turn the General settings placeholder from task 01 into the real search-engine visibility
form, relocated verbatim from today's crawler settings screen, and retire the old screen.

**Builds:**

- **Flesh out `GeneralSettingsController`** (created as a stub in task 01):
  - `edit()` → `view('admin.settings.edit', ['setting' => CrawlerSetting::current()])`.
  - `update(UpdateCrawlerSettingRequest $request)` → `CrawlerSetting::current()->update($request->validated())`
    then `redirect()->route('admin.settings.edit')->with('status', 'crawler-settings-updated')`.
    (Logic copied byte-for-byte from the current `CrawlerSettingController`; only the redirect
    route name changes.)
- **Add the write route** in the admin group:
  `Route::patch('/settings', [GeneralSettingsController::class, 'update'])->name('settings.update');`
- **Replace `admin/settings/edit.blade.php`** (the task-01 stub) with the real page: an
  `<x-admin-layout>` containing one card whose heading is **"General settings"** (the source
  spec's requested main title), followed by the **existing search-engine form moved verbatim**
  from `resources/views/settings/crawlers/edit.blade.php` — the hidden-mode checkbox, the
  whitelist textarea with its `old()`-vs-DB value reconstruction, the **Save** button, the
  **Preview robots.txt** link, and the `session('status') === 'crawler-settings-updated'`
  "Saved." flash. Keep the form fields identical; only the wrapper and the `action` (→
  `route('admin.settings.update')`) change.
- **Delete the old screen:** remove `CrawlerSettingController`, the `crawler-settings.edit` /
  `crawler-settings.update` routes, and `resources/views/settings/crawlers/edit.blade.php`
  (and the now-empty `resources/views/settings/crawlers/` directory). Grep the codebase for
  any remaining `crawler-settings.` reference and remove it — there should be none after task
  01 repointed the nav link.

## Explicitly NOT in this task

- Any change to `CrawlerSetting` (model, columns, `current()`) or `UpdateCrawlerSettingRequest`
  — they are reused **unchanged**.
- `/robots.txt`, `RobotsTxtController`, `RobotsTxtGenerator`, `x-robots-meta` — untouched.

## Depends on

- **Task 01** (needs the admin group, `GeneralSettingsController` stub, `<x-admin-layout>`,
  and the nav link already repointed to `admin.index`).

## Key decisions already made (binding)

- Rename-and-delete, **not** an alias (Q2): after this task, `crawler-settings.*` no longer
  exists anywhere.
- Page heading is "General settings" (source spec).
- Validation rules stay centralized in `UpdateCrawlerSettingRequest`; do not duplicate them in
  the controller or view.

## Consult

- `../expanded/ui.md` → *Section 1. General settings* (what to keep verbatim).
- `../expanded/architecture.md` → *What happens to `/settings/crawlers`?*.
- The current `app/Http/Controllers/CrawlerSettingController.php` and
  `resources/views/settings/crawlers/edit.blade.php` as the source to relocate.

## Tests

Migrate the existing crawler-settings feature test to the new route (don't delete the
coverage — relocate it), and add:

- **Renders:** authenticated `GET admin.settings.edit` → `200`, contains the "General
  settings" heading, the checkbox, and the whitelist textarea.
- **Save works:** `PATCH admin.settings.update` with a valid toggle + whitelist updates
  `CrawlerSetting::current()` and redirects back with the `crawler-settings-updated` status.
- **End-to-end wiring:** after saving "hidden", `GET /robots.txt` reflects the change (guards
  against the relocation silently breaking the singleton wiring).
- **Validation failure:** an invalid whitelist term (CR/LF, `:`, or `#`) →
  `assertSessionHasErrors` (reuse the existing rule's cases; don't re-test the rule in depth).
- **Old route gone:** `GET /settings/crawlers` → `404` (route removed).
- **Authorization:** guest → login redirect on `admin.settings.edit`/`update`.
