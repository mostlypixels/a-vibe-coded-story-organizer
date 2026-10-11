---
title: "Task 01 — Run mode from config"
---

# Task 01 — Run mode from config

## Scope

- `config/import.php`: rename `default_run_in_background` → `run_in_background`; update its comment (a runtime fact, not a writer setting).
- Migration: drop `import_settings.run_in_background`.
- `ImportSetting`: remove the field from `$fillable`, `casts()` and `current()`.
- `ImportController::queueInBackground()`: read `config('import.run_in_background')`.
- `ImportSettingController` / `UpdateImportSettingRequest`: remove the field.
- `admin/data/import.blade.php`: replace the checkbox with read-only text ("Imports run in the background" / "Imports run during the upload"). Pass the flag from `DataTransferController::import()`.
- `docker-compose.yml`, `docker-compose.dev.yml`: `IMPORT_RUN_IN_BACKGROUND=true`.
- `.env.example`: `IMPORT_RUN_IN_BACKGROUND=false` with a one-line comment.

Defers the job settings to 03 and the page states to 06.

## Depends on

Nothing.

## Key decisions already made

- Overview decision 1. A destructive migration is fine (pre-V1).
- Keep `queued` as is here; task 02 replaces it.

Docs: `expanded/architecture.md` → *Run mode*; `expanded/ui.md` → *Settings card*.

## Tests

- Background mode follows `config(['import.run_in_background' => true])`; inline when false.
- Rewrite `test_resume_follows_the_current_toggle_value_not_the_original` to use config.
- Remove the toggle cases from the settings tests; the settings form still saves the size cap.
- The import page shows the matching read-only text for each mode.
