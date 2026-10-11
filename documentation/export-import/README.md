# Export and import

[Documentation](../README.md) › Export and import

| Page | Purpose |
| --- | --- |
| [Archive format](archive-format.md) | Contract for the lossless `data/` layer and readable `books/` layer |
| [EPUB](epub.md) | Rules for generating one publication-ready book |

Treat both documents as compatibility contracts. Change them with their tests.

An unfinished import keeps its ZIP and extracted folder under `storage/app/private/imports` so the writer can resume it. The daily `imports:purge` removes them, and the import row, after `import.purge_after_days` (7 by default). It also removes old files that no import row owns.

The purge keeps the partial project, because the writer can have edited it. `projects.import_unfinished` marks it, so the project page shows an "import did not finish" note. The flag is set when the project phase creates the project and cleared when the import completes. The writer can also dismiss the note.

## Running an import

`ProjectImporter::start()` validates the archive and creates the `Import` row. `run()` imports the graph phase by phase.

### Run mode

- `config('import.run_in_background')` (env `IMPORT_RUN_IN_BACKGROUND`) picks the mode. It is not a writer setting and has no database column.
- **Background** (Docker sets it): the store action queues `ProjectImportJob`. The queue worker runs the import, so no web request waits for it.
- **Inline** (native default): the upload request runs the import. Native Windows PHP has no `pcntl` and no worker.
- Validation and the archive hash always run in the request (about 1 s for a large archive). The unzip happens in `run()`, so it is part of the timed run.

### One run per import

- Every path into `run()` (job, inline store, resume) first takes a claim: `imports.running_since`.
- A second caller gets `ImportAlreadyRunningException`. The job ignores it, because the other run finishes the import. A double click gives one project.
- A claim older than `import.timeout_seconds` (1800 by default) counts as a crashed run. A new run takes it over from the saved checkpoint.
- `queued_at` marks a row that waits for the worker.
- The job runs once (`tries = 1`) and sets its own timeout. A retry would repeat work that the writer can resume.

### Failure

- A failed run keeps its `phase` and `id_maps` checkpoint. The row stays as a stalled import with `failure_message`.
- On a job failure, `ProjectImportJob::failed()` saves the message and clears the claim. A timeout kills the worker before `run()` can do it.
- The writer resumes or discards the row. A discarded import leaves the queued job nothing to run.

### Duplicate archives

`start()` stores the SHA-256 of the archive in `imports.archive_sha256`. The checks run in both modes, before the run mode is read.

| Earlier import of the same archive | Result |
| --- | --- |
| Not completed (pending, running, or stalled) | The upload is refused. No row or file is written. |
| Completed, and its project still exists | The upload is accepted and the import page shows a warning with "Import anyway". |
| Completed, and its project was deleted | No warning. |

### Import page

- Each row shows one state: waiting to start, running (with the phase), stalled (with the failure message), or duplicate.
- The page polls `admin.data.imports.status` (JSON, owner only) every 3 seconds, only while a row is queued or running. When a run fails or finishes, the page reloads.
- A running row hides Resume and Discard. A queued row keeps Discard.
