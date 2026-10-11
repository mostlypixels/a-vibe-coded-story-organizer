# UI — `resources/views/admin/data/import.blade.php`

## After upload (background mode)

- Redirect to the import page, as today. Flash: "Import started."
- The in-progress list shows the new row at the top.

## In-progress row

| State | Status text | Actions |
|---|---|---|
| queued | "Waiting to start" | Discard |
| running | phase label, for example "Story imported", plus a spinner | none |
| stalled | phase label — `failure_message` | Resume, Discard |
| completed (live, from a poll) | "Done" | link "Open project" |
| pending duplicate | "You imported this archive on :date as :project." | **Import anyway** (= Resume route), Discard |

- Discard while running is hidden. It would delete rows under a running phase.
- Compute the state in the controller or on the model (`isRunning()`/`isQueued()`), not in Blade.

## Polling

- A small Alpine component on each row that is queued or running. Fetch `admin.data.imports.status` every 3 s.
- Stop when the state is `completed` or `stalled`. On `stalled`, reload the page so the server renders the Resume and Discard forms.
- Use `aria-live="polite"` on the status text.
- There is no poll pattern in the project yet. Keep it local to this view, no shared component (no second caller).

## Duplicate refused

- A field error on `archive`: "This archive is already being imported. Resume or discard it below."

## Settings card

- Remove the "Process imports in the background" checkbox if open question #1 is accepted. Show read-only text instead: "Imports run in the background" or "Imports run during the upload".
