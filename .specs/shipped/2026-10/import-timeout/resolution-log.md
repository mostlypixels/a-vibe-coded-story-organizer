# Import Timeout — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

An exception log: a task that went to plan gets no entry, because the diff and the task
file already record what was built. Bullets under the headings below, root cause first.

## Feedback & decisions

- 2026-10-11 grill: run mode from config only; the DB column and checkbox go (the env var cannot reach an existing row).
- Duplicate warning lasts while the earlier project exists; no time window.
- Duplicate checks apply to native inline imports too.
- The job sets its own tries (1) and timeout; the worker command is unchanged; `retry_after` 1860 s.
- Poll every 3 s; on failure, reload to the stalled row.
- Validate + hash stay in the request: measured 0.8 s + 0.16 s in Docker (Les Misérables).
- Discard hidden while running; kept for queued rows (`$deleteWhenMissingModels`).
- `pcntl` in both Dockerfiles; native Windows PHP has none and runs inline.

## Deviations from the spec/plan

- `resume()` also checks `isRunning()` before it queues: in background mode `run()` happens in the worker, so the exception alone cannot stop a second dispatch. The catch stays for the inline race.
- `release()` clears `running_since` only where it still equals this run's claim time. A run that outlives the timeout must not clear the claim of the run that took over.
- `claim()` refreshes the model after the update, so a takeover starts from the checkpoint the crashed run saved last.
- `extract()` unpacks into `<uuid>.partial`, then renames it. With the unpack in the job, a timeout can stop it midway; a partial folder at the final path would pass the `is_dir()` check on resume. Discard also deletes the side folder.
- An extraction failure in `run()` saves a `failure_message` ("could not unpack"). Before, the unpack ran before the row existed; now an empty message would show the job's timeout text.
- `failed()` picks its message by exception: `TimeoutExceededException` gets the timeout text; any other exception without a saved message gets "The import stopped." `failed()` runs for every job failure, not only timeouts.
- No public `ProjectImporter::findDuplicate()`. `start()` uses a private `findUnfinished()` for the refusal. `Import::duplicateOf()` finds the completed match, so the import page can ask it on any render without a new column.
- `duplicateOf()` returns a value only for a row that never started (Pending, not queued, not running, no failure message). After "Import anyway" the row is the writer's choice and no longer a duplicate.
- A non-terminal match is any phase other than Completed, as the import page list does. A second upload of a duplicate that still waits as a Pending row is refused.
- The warning flash is key `warning` and renders with `x-alert` on the import page. The row label and the "Import anyway" button text belong to the next page task.
- The status text drops the old "Status:" prefix, to match the `expanded/ui.md` row table.
- Only a queued store flashes "Import started."; a queued Resume keeps "Import queued." `queueInBackground()` takes the flash text from its caller.
- The poll code is `resources/js/import-status.js` (Alpine `importStatus`, registered in `app.js`), with a Vitest test. Its only caller is the import page.
- A running row renders no Discard at all. A queued row renders Discard and hides it with `x-show` when a poll reports `running`.
- The view gets `imports` as rows (`import`, `state`, `duplicate`) built in `DataTransferController::importRow()`. `state` adds `duplicate` on top of `Import::state()`.

## Issues → resolutions

- `assertSee(route('admin.data.imports.destroy', $import))` passes by accident: the destroy URL is a prefix of the resume URL, and `x-delete-button` JSON-escapes its own URL. The new row tests assert `$dispatch('open-confirm-delete'` instead.
- The Docker Vite dev server took about 40 s to serve its first module after a cold start. The browser driver's 30 s `nav` timed out until it was warm.
- `assets-state.sh` fails on `public/hot` while the Docker dev stack runs: its Vite dev server owns that file. Not a stale hot file. The Docker stack and the native checkout have separate databases (Docker uses a volume), so a new migration needs `php artisan migrate` in both places.
- The running dev container keeps its old env until `make up` recreates it, so it shows "during the upload" until then.
- `pcntl` needs an image rebuild (`make build`, then recreate the container). Checked 2026-10-11 in the dev container: `php -m` lists `pcntl`, `retry_after` reads 1860, nginx has `fastcgi_read_timeout 300s`.
- `ImportRoundTripTest` re-imported the same zip and expected a second project. That upload is now a duplicate: the test posts it, then resumes the Pending row.
- The job's `timeout()` method was dead code. The queue payload reads the `$timeout` property or the `#[Timeout]` attribute, never a method. The worker fell back to its 60 s default and killed a real Les Misérables import during the unzip (the failure text still said "30 minutes"). The green suite only called the method. `ProjectImportJob` now sets `public int $timeout` in its constructor, and a test dispatches to the `database` queue and reads the `timeout` key from the stored payload.
- The old stalled-row test in `ImportTest` asserted `__('Discard')` and the destroy URL, both weak (see the first bullet here). It now asserts the Discard button's `$dispatch('open-confirm-delete'` handler, as the row tests do.
- Docker check, Les Misérables (57 MB, 4,700 files): the upload returns at once with no 504; the row goes queued, running, completed. A double POST to Resume gave one project and no stray job. The Windows bind mount makes the unzip and the working-file cleanup take minutes. The phase label stays "Pending" for the whole unzip. A second upload of the same file gave the duplicate row with "Import anyway".
- Open: `running_since` stays set for about 1.5 min after the phase reads `completed`, because `run()` deletes the working files before it releases the claim. The page shows "completed" (the state checks the phase first). A follow-up could release the claim before the cleanup.
- The Playwright driver could not finish a login in the Docker dev stack: `screenshot` hangs at "waiting for fonts" and `nav` times out on the cold Vite server. The Docker run used `curl` with a session cookie against the same routes. The page JavaScript was checked earlier with the task 06 browser run.
