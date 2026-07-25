# Task 2 — `RevisionRecorder` stamps `save_id`

## Scope

`App\Services\RevisionRecorder`:

* `currentSaveId(Model $entity): string` — memoised per `"<class>:<key>"`, generating
  `(string) Str::ulid()` on first use;
* `startNewSave(Model $entity): void` — drops that memo entry, so a caller can
  deliberately open a new save point inside one request (task 17 uses it);
* `record()` stamps `save_id` on every **insert**;
* `ensureBaseline()` stamps a fresh `save_id` (its own group of one);
* the coalescing branch (`$open->update(...)`) **does not touch `save_id`**.

`app/Providers/AppServiceProvider.php`: register
`$this->app->scoped(RevisionRecorder::class)` so one request shares one instance. Add a
comment saying why — without it, each `app(RevisionRecorder::class)` / method injection
resolves a fresh object and every field of one form submit gets its own save id.

Does **not** write `summary_html`/`change_count` (task 9), and does **not** change the
export/import paths (task 3).

## Depends on

Task 1.

## Key decisions already made

* **One save id per (request, entity)** — not per request. `ProjectGraphImporter` writes
  revisions for hundreds of entities in one request; grouping them all together would make
  "Undo this save" offer to revert a whole imported project.
* **A coalescing update keeps the row's original `save_id`**, exactly as it already keeps
  its original `created_at`: it is the same continuing burst. Binding decision 3, with its
  accepted consequence documented in `expanded/data-model.md`.
* **Baseline rows get their own id** rather than sharing one with the save that triggered
  the seeding — a baseline is the pre-edit state, not part of the save.
* The memo lives on the service, not in a static or the session: it must reset between
  requests, and `scoped()` is exactly that lifetime.

## Consult

* `expanded/data-model.md` — *Who writes `save_id`*, and the `currentSaveId()` sketch.
* `app/Services/RevisionRecorder.php` — existing docblocks; keep their level of detail.
* `app/Http/Controllers/Concerns/RecordsManualRevisions.php` — the manual-save path that
  must now produce one shared id across several fields.

## Tests

`tests/Feature/RevisionSaveGroupingTest.php` (new):

* one form submit changing **two** autosaved fields writes two rows sharing one
  `save_id` — the test that proves the `scoped()` binding; assert it fails meaningfully if
  the binding is removed (comment it in the test);
* a second submit produces a different `save_id`;
* an autosave PATCH and a later form submit have different `save_id`s;
* a coalescing autosave (second PATCH inside `config('revisions.windows')`) leaves the
  row's `save_id` and `created_at` unchanged while updating `value`;
* two different entities saved in one request get different `save_id`s;
* `ensureBaseline()` writes a row whose `save_id` is unique;
* after exercising autosave, manual save, revert and baseline paths,
  `Revision::whereNull('save_id')->count()` is 0.
