# Task 12 — `RevisionSetting` + scheduling + `RevisionPurger` + purge command

## Scope

* `App\Models\RevisionSetting` — singleton, mirroring `App\Models\ImportSetting`
  exactly (read this session): `retention_days` fillable, `current()` lazily creates
  from `config('revisions.retention_days')`.
* Migration `create_revision_settings_table` (one nullable-free row, `retention_days`
  unsigned int, column default mirroring the config default — same "duplicated by
  design" pattern as `import_settings`, per that migration's own docblock convention).
* **Swap `Revision::prunable()` (task 1) to read `RevisionSetting::current()-
  >retention_days`** instead of the raw config value — this is the one edit to
  existing code from an earlier task; do it as a small, clearly-flagged change at the
  start of this task, not a silent drive-by.
* `routes/console.php`: `Schedule::command('model:prune', ['--model' =>
  [Revision::class]])->daily()` — the existing Laravel scheduler file, no custom
  command for the daily run itself.
* `App\Services\RevisionPurger` — the one place purge rules live: methods to delete by
  category (automatic / manual / labeled / imported) and/or age cutoff, always
  respecting the three non-negotiable rules (never purge a labeled revision unless the
  category explicitly is "labeled" and the user asked for it — re-read `handoff.md`
  §4.3 carefully: purge is explicit and destructive by design, it is **allowed** to
  remove exempted rows, unlike prune). Returns a count/byte-total of what was removed
  (or would be removed, for dry-run).
* `App\Console\Commands\PurgeRevisions` (`revisions:purge --project= --category=
  --before= --dry-run`) — calls `RevisionPurger`, nothing else.

Does **not** include the admin UI panel (task 13 — that's the second caller of
`RevisionPurger`, added next, so both entry points can be tested against the identical
service in one place first).

## Depends on

Task 1 (`Revision`, `prunable()`), task 4 (write path already produces the categorized
data to purge against).

## Key decisions already made

* **Prune ≠ purge.** Prune (task 1's `prunable()`, run daily via `model:prune`) is the
  safety-preserving sweep — never touches labeled or non-`automatic` rows. Purge is an
  explicit, user-requested, genuinely destructive action that *can* remove those
  (`handoff.md` §4.3) — because without it, imported revisions and a two-year `manual`
  history are a one-way ratchet with no release valve.
* **Both the command and the panel controller (task 13) call the same
  `RevisionPurger`** — this is the whole reason the service exists as a separate class
  rather than logic embedded in the command.
* **`retention_days` bounds: `min:7`, `max:3650`, no "0 = never"** — enforced in
  `UpdateRevisionSettingRequest` (task 13 writes the actual request class; this task
  just ensures the model/migration don't contradict those bounds with, say, a nullable
  column that implies "unset = never").
* **`RevisionSetting::current()` is deliberately not memoized**, matching
  `ImportSetting`'s own docblock reasoning (value can change within one request
  lifecycle, e.g. a settings update followed immediately by a prune-count query in the
  same test).

## Consult

* `expanded/data-model.md` — `RevisionSetting` code sketch.
* `expanded/architecture.md` — "Retention: `Prunable` + `RevisionPurger`" section.
* `app/Models/ImportSetting.php` + `app/Http/Controllers/ImportSettingController.php`
  (already read this session) — the pattern to mirror line-for-line where it applies.
* `handoff.md` §4.1, §4.2, §4.3, §9.11.

## Tests

* `RevisionSetting::current()` lazily creates from config on first read, matching
  `ImportSetting`'s own test shape if one exists (check `tests/Feature` for an
  `ImportSetting` test to mirror).
* `Revision::prunable()` now reflects a changed `RevisionSetting::current()-
  >retention_days` value (change the setting, assert the prunable set shifts
  accordingly) — proves the swap from task 1's raw config read actually took effect.
* `model:prune --model=App\Models\Revision` (Artisan call in test) removes only
  prunable rows; `--pretend` removes none but reports the count.
* `RevisionPurger`, one test per category: `automatic`, `manual`, `labeled`, `imported`,
  each removing exactly the rows in that category and no others.
* `RevisionPurger` with an age cutoff (`--before`) removes only rows older than that
  date within the selected category.
* `RevisionPurger` dry-run mode returns the count/byte-total without deleting anything.
* `revisions:purge --dry-run` and the real run produce matching counts when run
  back-to-back (dry-run accurately predicts the real run).
* Purge **can** remove a labeled or `manual`-origin revision when explicitly targeted by
  category — this is the test that proves purge is allowed to do what prune is
  forbidden from doing; get this distinction explicitly under test, not just prune's
  restriction.
