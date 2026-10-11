# Overview

## Findings beyond the spec

- **The unpack runs in the request, also in background mode.** `ProjectImporter::start()` validates, stores **and extracts** the archive before the controller queues the job. Les Misérables: 88 s unpack → 504 before the job is queued. Background mode alone does not fix the 504.
- **The env var does not reach existing installs.** `ImportSetting::current()` reads `IMPORT_RUN_IN_BACKGROUND` only when it creates the singleton row. The Docker dev database already has a row (named volume, #323).
- **No `pcntl` in either Docker image.** Without it, `queue:work` cannot enforce a job timeout. The worker cannot kill a hung import. A second worker could still pick the job up again after `retry_after`.
- **The `queued` flag is never cleared.** A finished or crashed queued import looks the same on the page.

## User stories

- I upload a big archive in Docker. The page returns at once and shows the current phase. When the import is done, it shows a link to the new project.
- I click Import twice, or reload the page. I get one project.
- I upload an archive that I imported before. I see a warning that names that project, and I can choose to import it again.
- I upload an archive that is still importing, or that stopped part way. I am told to resume or discard the existing import.

## Acceptance criteria

- Docker: the Les Misérables archive imports with no 504. The upload request returns in less than 60 s.
- Native: imports stay inline. The behaviour does not change, except for the duplicate checks.
- One `Import` row runs in one process at a time: queue retry, a second worker, a Resume click and an inline run all respect this.
- The queue does not retry a failed or timed-out import. The row stays resumable.
- The import page shows the phase of a running import and updates it without a reload. It shows the failure message when the import fails.
- Same archive bytes, same user:
  - non-terminal import exists → upload is refused, with a pointer to that import.
  - completed import whose project still exists → warning plus "Import anyway".
- A non-owner gets 403 from the status endpoint.
