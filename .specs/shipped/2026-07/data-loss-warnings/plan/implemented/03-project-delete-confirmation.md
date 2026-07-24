---
title: "Task 03 — Project delete confirmation"
---

# Task 03 — Project delete confirmation

## Scope

Replace the static "Are you sure you want to delete this project?" confirm string with
one that names the non-zero counts of what's nested underneath (acts, plotlines,
events, codex entries). No reassignment option — a project's direct children have no
natural "other project" destination, per the grilled decision to scope reassignment to
Act/Chapter only.

Does **not** touch `ActController`/`ChapterController`, the navigation guard, or add
any new dialog component — this task stays inside `ProjectController` and
`projects/edit.blade.php`'s existing native `confirm()` mechanism.

## Depends on

Nothing — independent of tasks 01/02/04/05, safe to implement in any order relative to
them.

## Key decisions already made

* Plain cascade-count string, native `confirm()`, unchanged mechanism — only the
  *string* changes.
* List **only non-zero categories**: e.g. "This project has 2 acts and 5 codex
  entries, which will also be deleted." A brand-new project (only its auto-created,
  un-deletable main plotline) falls back to the original unqualified text — never "0
  acts, 0 events…".
* No new Form Request, no new authorization path — `ProjectController::destroy()`
  and its policy check are untouched.

## Where to make the change

* `ProjectController::edit()` (`app/Http/Controllers/ProjectController.php`):
  `$project->loadCount(['acts', 'plotlines', 'events', 'codexEntries'])` (relations
  already exist at `app/Models/Project.php:58-78`).
* `projects/edit.blade.php`'s `:delete-confirm` prop (currently the static string, see
  `:110` from this session's `grep`): build the sentence from only the non-zero counts.
  `trans_choice()` per category (e.g. "1 act" vs "2 acts"), joined with commas/"and",
  matching this app's existing i18n style (`__()`/`trans_choice()` calls elsewhere in
  the same file).

Consult `architecture.md` §4a and `ui.md`'s "Project delete confirmation" section for
the exact design.

## Tests to add

Extend `tests/Feature/ProjectTest.php` (existing file):

* A project with a non-zero mix of acts/plotlines/events/codex entries renders an edit
  page whose delete-confirm string lists only those non-zero categories (assert via
  `assertSee`, matching this app's existing Blade-content assertion style).
* A brand-new project (only its main plotline) renders the original unqualified
  message — assert the "0 …" wording never appears.
* No new authorization test needed — `destroy()` itself is untouched.

Run `composer test -- --filter=ProjectTest` to verify in isolation.
