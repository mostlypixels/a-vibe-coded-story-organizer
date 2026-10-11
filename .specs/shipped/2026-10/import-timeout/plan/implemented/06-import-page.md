---
title: "Task 06 — Import page"
---

# Task 06 — Import page

## Scope

- `admin/data/import.blade.php`, in-progress rows by state (see `expanded/ui.md` → *In-progress row*):
  - queued: "Waiting to start", Discard only.
  - running: phase label + spinner, no actions.
  - stalled: label + `failure_message`, Resume + Discard.
  - pending duplicate: "You imported this archive on :date as :project.", **Import anyway** (resume) + Discard.
- Alpine component per queued/running row: fetch `admin.data.imports.status` every 3 s. On `completed`, show "Done" + an "Open project" link. On `stalled`, reload. Stop polling at either.
- `aria-live="polite"` on the status text.
- Flash "Import started." after a queued store.
- Refused duplicate error text: "This archive is already being imported. Resume or discard it below."

## Depends on

01, 04, 05.

## Key decisions already made

- Overview decisions 5, 6, 8.
- Row state is computed in the controller or model, not Blade.
- Poll code stays local to this view (no second caller). If it lives in `resources/js`, add a Vitest test next to it.

Docs: `expanded/ui.md`.

## Tests

- Each row state renders its text and only its actions.
- A running row has no Resume or Discard form.
- A duplicate row shows "Import anyway" posting to `admin.data.imports.resume`.
- Polling markup is present only for queued/running rows.
