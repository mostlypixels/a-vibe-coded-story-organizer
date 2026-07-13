# Task 07 — Queued mode

## Scope

Add the `ImportSetting.run_in_background` branch to `ImportController` and the job
that carries it out. **Does not** change `ProjectImporter::run()` itself — the job
is a thin wrapper, per `architecture.md`.

* `app/Jobs/ProjectImportJob.php`: `implements ShouldQueue`, constructor takes
  `Import $import`, `handle()` calls `app(ProjectImporter::class)->run($this->import)`.
* `ImportController::store()`: after `ProjectImporter::start()` succeeds, branch on
  `ImportSetting::current()->run_in_background` — dispatch `ProjectImportJob` and
  redirect with "Import queued." (never calling `run()` inline in this branch), or
  fall through to the existing inline `run()` call from task 06.
* `ImportController::resume()`: same branch — dispatch or run inline depending on
  the current toggle value (not whatever it was when the import was first started;
  the toggle can change between attempts).
* Mark the dispatched `Import->queued = true` before dispatch (already a column from
  task 01) so task 08's UI can distinguish "queued, maybe still running" from
  "ran inline and crashed" if it ever wants to (not required to look different, but
  the data should be there).

## Depends on

Task 06 (`ImportController`, routes), Task 05 (`ProjectImporter::run()`).

## Key decisions already made

* Validation (`start()`) is never queued, regardless of the toggle — only `run()`.
* The job contains no logic of its own; a job failure leaves the `Import` row at its
  last committed phase exactly like an inline crash would, and the same
  resume/discard actions handle both (no job-specific retry logic).

## Docs to consult

`architecture.md` → *Queued mode — `ProjectImportJob`*.

## Tests

* `Queue::fake()`: with `run_in_background = false` (default), `POST
  admin.data.import` never pushes anything (`assertNothingPushed()`) and the project
  exists immediately after the request.
* With `run_in_background = true`, the same request pushes `ProjectImportJob`
  (`assertPushed(ProjectImportJob::class)`), redirects with "Import queued.", and
  the project does **not** exist yet; manually executing the faked job (or calling
  `ProjectImporter::run()` directly, simulating the job having run) completes it.
* Regardless of the toggle, an archive that fails validation never reaches the queue
  (`assertNothingPushed()` even with `run_in_background = true`).
* `resume()` respects the **current** toggle value at resume time, not whatever it
  was when the import was originally started (flip the setting between the initial
  attempt and the resume call in the test, assert the resume follows the new value).
