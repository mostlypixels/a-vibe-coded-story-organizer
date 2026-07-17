# refactor_codex — implementation plan (manual)

This file is the plan's manual: never implemented, never moved to `implemented/`.
Source spec: `.specs/refactor_codex/spec.md` (code review, 9 ranked findings). Design docs:
`.specs/refactor_codex/expanded/*.md`. Tasks live beside this file as `NN-<slug>.md` and move to
`plan/implemented/` when done.

## Execution order

| # | Task | Purpose |
|---|------|---------|
| 01 | `01-project-bookend-helpers.md` | Extract `Project::startEvent()/endEvent()`, delete the two duplicated queries (finding 8). |
| 02 | `02-freeze-bookend-datetimes.md` | Forbid `event_datetime` edits on `is_fixed` events (finding 4). |
| 03 | `03-gap-free-upsert.md` | `AttributeTimeline::upsertAt()` ensures a Start baseline; fix the test that codifies the hole (finding 1). |
| 04 | `04-timeline-editor-validation-ux.md` | Timeline editor: render errors, allow empty values, preserve `old()`, `removeAt` guard → 403 (finding 5 + ride-alongs). |
| 05 | `05-media-purge-on-cascade-deletes.md` | Purge media files on project **and** account deletion (finding 2). |
| 06 | `06-media-transaction-boundary.md` | Move disk I/O out of `DB::transaction` in the entry save flow (finding 3). |
| 07 | `07-enum-derived-routes-and-nav.md` | Derive route constraints and nav links from `CodexEntryType` (finding 9). |
| 08 | `08-ui-cleanups.md` | Controller-passed tags, wildcard upload errors, `applies_to` hint, orphaned-tags filter (findings 6, 7 + ride-alongs). |
| 09 | `09-docs-and-changelog.md` | Sync CLAUDE.md / `documentation/` / CHANGELOG with the changed invariants. |

Dependency chains: 01 → 02 → 03 → 04 (timeline), 05 → 06 (media, both reshape
`CodexMediaService`), 07 and 08 independent, 09 last. Tasks 05–08 can run any time after
01 if reordering is ever needed, but the numbered order is the default.

## Binding decisions (do not re-litigate in tasks)

Resolved from `open-questions.md` — Q1 and Q5 confirmed by the user on 2026-07-04, the
rest are the spec's recommendations, now binding:

- **Q1 — Bookends:** freeze `event_datetime` on `is_fixed` events (`prohibited` rule +
  hidden/disabled input). **No** `is_start` migration, no schema change.
- **Q2 — Empty values:** `''` is a first-class stored value ("recorded as blank").
  `value` validates as `['present', 'string', 'max:255']`. Display of blanks is out of scope.
- **Q3 — Error scoping:** the timeline editor's small forms share the default session error
  bag and `old()` values. No named error bags, no per-attribute keying.
- **Q4 — Nav active state:** while converting nav links to an enum loop, fix the quirk —
  highlight the *current* codex type, not always the first link. CHANGELOG `Changed` entry.
- **Q5 — Ride-alongs (all three in scope):** `removeAt` RuntimeException → 403 `abort_if`
  (task 04); `applies_to` narrowing hint on the attribute form (task 08); `whereHas('entries')`
  on the index tag-filter dropdown (task 08). All other lower-priority notes stay deferred.
- **Q6 — Account deletion purge:** the `User` `deleting` hook deletes projects **through
  Eloquent** (`$user->projects->each->delete()`) so `Project`'s hook is the single purge
  trigger. `CodexMediaService::purgeProject()` has exactly one caller.
- **Media transaction split:** decide-inside / act-after-commit, deletes before writes,
  per-file catch-and-unlink on upload row failure (see `media-lifecycle.md`). Prefer running
  disk ops after `DB::transaction()` returns over `DB::afterCommit`.

## Invariants every task must preserve

- **Gap-free step function:** every valued (entry, attribute) pair has exactly one value
  anchored at the project's Start event; `valueAt` is total for `t ≥ Start`. Task 03
  strengthens enforcement; nothing may weaken it.
- **Canonical anchor order** is `(event_datetime, events.id)` — never datetime alone.
  After task 01 the *only* definition of Start/End lives in `Project::startEvent()/endEvent()`.
- **Authorization walks up to `Project`** via `ProjectPolicy` (`view`/`update`/`delete`),
  mirrored in each FormRequest's `authorize()`. No new policies. Non-owner 403 covered in
  every new test.
- **Undeletable specials:** main plotline (`is_main`), bookend events (`is_fixed`), and the
  Start baseline row (while siblings exist) stay undeletable; guard style is `abort_if` → 403.
- **Files never leak, rows never dangle:** every path that drops `codex_media` rows (entry,
  project, user deletion; cover replacement; `remove_media[]`) must delete the files —
  and never delete a file whose row could survive a rollback.
- **Cover invariant:** the cover is the single `codex_media` row with `collection = Cover`;
  no `cover_media_id` column may be introduced.
- **`WithoutModelEvents` seeding:** `MelusineSeeder` bypasses model hooks — any new hook
  (task 05) must not be load-bearing for seeding; the seeder seeds no `codex_media`, keep it
  that way.
- **Where logic lives:** timeline math in `App\Services\AttributeTimeline`, media disk/paths
  in `App\Services\CodexMediaService`, lifecycle invariants in `booted()`, validation in
  FormRequests, no queries in Blade.
- **Testing bar (guidelines):** each bug-fix test fails before its fix; plain PHPUnit,
  `RefreshDatabase`, factories, `actingAs`, `route()` helper. `composer test` green and
  `vendor/bin/pint` clean at the end of every task.
