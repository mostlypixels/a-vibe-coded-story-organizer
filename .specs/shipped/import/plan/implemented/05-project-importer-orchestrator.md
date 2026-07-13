# Task 05 — `ProjectImporter` orchestrator

## Scope

Tie tasks 02–04 together behind the three entry points `architecture.md` defines,
adding checkpoint persistence and temp-storage lifecycle — the piece that makes
resume/discard actually work. **Does not** touch HTTP/controllers (task 06) or the
queue (task 07); this task's tests call `ProjectImporter` directly with an
`UploadedFile`/`Import` model, no route involved.

* `app/Services/ProjectImporter.php`:
  * `start(UploadedFile $archive, User $user): Import` — runs `ArchiveValidator`
    (task 02) against the uploaded file; on failure, throws
    `ImportValidationException` and **creates no `Import` row at all** (a validation
    failure never even reaches `phase = pending`). On success: stores the archive to
    `storage/app/imports/<uuid>.zip`, extracts it to
    `storage/app/imports/<uuid>/`, creates the `Import` row (`phase = pending`,
    `archive_path`, `archive_original_name` from the upload's original client name),
    returns it. Always synchronous — never deferred, regardless of
    `ImportSetting::current()->run_in_background`.
  * `run(Import $import): void` — calls `ProjectGraphImporter`'s phase methods in
    order starting **after** `$import->phase` (resuming mid-sequence uses the
    persisted `id_maps` instead of rebuilding earlier phases), persisting
    `$import->phase` and the accumulated `id_maps` to the row immediately after each
    phase's transaction commits. On success of the `codex` phase, sets
    `phase = completed`, deletes `archive_path` and the extracted directory. On any
    exception from a phase, leaves the row at its last successfully committed phase
    and rethrows (or logs — but does **not** swallow; the caller, task 06's
    controller or task 07's job, decides how to surface it) — sets
    `failure_message` to a safe, specific string before rethrowing.
  * `discard(Import $import): void` — deletes `$import->project` if set (cascades
    handle its children/media per existing model `booted()` hooks), deletes
    `archive_path` and the extracted directory off disk, deletes the `Import` row.

## Depends on

Task 02 (`ArchiveValidator`), Task 03 (`ContentSanitizer` — invoked by
`ProjectGraphImporter` from within task 04, already wired), Task 04
(`ProjectGraphImporter`), Task 01 (`Import` model/`ImportPhase`).

## Key decisions already made

* Validation is always synchronous; only phases 1–4 (the actual writes) are ever
  deferred to a queue (task 07 wires that).
* Each phase is its own transaction (already built in task 04) — this task's job is
  persisting the checkpoint *between* transactions, not changing their boundaries.
* The extracted directory and `archive_path` are **not** cleaned up in a `finally`
  like the exporter's temp file — they must survive a crash to support resume, and
  are only deleted on `completed` or `discard`.

## Docs to consult

`data-model.md` → *Checkpointing & resumability* (this task implements that section
almost verbatim); `architecture.md` → *Service — `ProjectImporter`*.

## Tests

* `start()` on a valid archive returns an `Import` at `phase = pending` with
  `archive_path`/extracted directory present on disk.
* `start()` on an invalid archive throws `ImportValidationException` and creates
  **no** `Import` row (assert `Import::count()` unchanged).
* `run()` on a fresh `pending` `Import` completes all 4 phases, ends at
  `phase = completed`, and deletes `archive_path`/the extracted directory.
* Forcing `ProjectGraphImporter::importCodex()` to throw (partial mock/stub) after
  `importStory()` succeeded: `run()` leaves the `Import` at `phase = story`, the
  `Project`/Acts/Chapters/Scenes exist in the DB, no Codex rows exist, and
  `failure_message` is set to something safe to display.
* Calling `run()` again on that same stalled `Import` (simulating "Resume") completes
  only the remaining phase(s) — assert Act/Chapter/Scene row counts are unchanged by
  the second `run()` call, only Codex rows are newly added, and no duplicate Acts/
  Chapters/Scenes appear.
* `discard()` on a stalled `Import` deletes its `Project` (and cascades), the
  archive file, the extracted directory, and the `Import` row itself.
