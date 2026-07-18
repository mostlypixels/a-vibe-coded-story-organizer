# Handoff — epub-configuration ship-plan

**Branch:** `epub-configuration`
**Plan:** `.specs/planned/2026-07/epub-configuration/plan/` (00-overview + 13 tasks)
**Command:** `/ship-plan epub-configuration` — implement tasks in dependency order, verify each
with `composer test`, spawn each task's `plan-implementer` on the model in the "Model assignment"
table in `00-overview.md`, **stop between each task** for the user's quota check.

## Model assignment (from 00-overview.md)

- **Opus:** 05, 07, 09, 10, 13
- **Haiku:** 01, 06
- **Sonnet:** 02, 03, 04, 08, 11, 12
- **Fable:** used nowhere.

## Progress

| # | Task | Model | Status | Tests |
|---|------|-------|--------|-------|
| 01 | enums-and-publication-setting | Haiku | ✅ done | 614 |
| 02 | project-frontmatter-fields | Sonnet | ✅ done | 620 |
| 03 | split-export-import-pages | Sonnet | ✅ done | 625 (UI driven in browser) |
| 04 | publication-setting-config-form | Sonnet | ✅ done | 641 (UI driven in browser) |
| 05 | publication-setting-in-archive | Opus | ✅ done | 647 |
| 06 | shared-cover-image-service | Haiku | ✅ done | 657 |
| 07 | chapter-cover-infrastructure | Opus | ✅ done | 670 |
| 08 | exporter-metadata-and-cover-toggles | Sonnet | ✅ done (owns defaults===v1 regression guard) | 676 |
| 09 | exporter-content-options | Opus | ✅ done (hardened task-08's regression guard against a `time()` race) | 684 |
| 10 | exporter-toc-depth | Opus | ✅ done (Acts=combined page; Scenes=3rd nav level via anchors) | 687 |
| 11 | exporter-front-back-matter | Sonnet | ✅ done (section_order-driven addSections() walk) | 692 |
| 12 | exporter-chapter-cover-pages | Sonnet | ✅ done (filled `include_chapter_covers` gap end-to-end) | 695 |
| 13 | codex-appendix | Opus | ✅ done (3 sub-steps: skeleton / images / docs+changelog) | 702 |

## Shipped

All 13 tasks complete, 702/702 tests green. Spec advanced to `.specs/shipped/2026-07/epub-configuration`.
Committed locally only — no push/PR until the user has reviewed.

## How to resume

1. `bash scripts/plan-next-task.sh epub-configuration` → lowest remaining task (should be 04).
2. Spawn `plan-implementer` (Agent tool) for that ONE task on its assigned model; prompt it to
   read `00-overview.md`, `../expanded/*.md`, and `resolution-log.md` first, verify with
   `composer test`, append to `resolution-log.md`, and move the task file to `plan/implemented/`.
3. Confirm the `.md` moved to `plan/implemented/`; if verification failed, **stop the loop**.
4. Stop after each task and report to the user (quota check).

## Session pause (2026-07-17)

Stopped after task 07 for the day (session usage was at 53%, weekly at 39% on the Pro plan).
Next session: resume with task 08 (Sonnet) — see "How to resume" above. No blockers; tasks
01–07 all verified green, nothing left mid-flight.

## Notes

- Task 02 owns the **single** manifest-version bump (`DATA_VERSION 1→2`; supported `[1,2]`).
  No later task should bump it again.
- Task 08 owns the **defaults===v1 byte-for-byte regression test**; every later exporter task
  (09–13) must keep it green.
- Completion / deviations logged per task in `resolution-log.md`.
- After task 13: final sanity pass, then ship-plan step 8 (`spec-advance.sh epub-configuration
  shipped`) + ask before committing via `ship-pr`.
- `.specs/draft/author-notes/` in the working tree is **unrelated** WIP — do not commit it.
- **Task 04 gap-fill:** task 01 spec'd a `section_order` column but never migrated it. Task 04
  added a **follow-up migration** (`2026_07_17_000001_add_section_order_to_publication_settings_table.php`)
  plus `SECTION_KEYS`/`PINNED_FIRST_SECTION` constants and `moveSectionUp/Down` on the model. So
  task 01's original migration is incomplete-by-design and patched by this later one — expected,
  not a bug. Section reordering ships as 3 routes (mirrors `ActController::moveUp`).
- **Toolchain:** global Composer was self-updated 2.5.1 → 2.10.2 to clear PHP 8.5 deprecation
  notices (no project files changed).
- **Task 05:** `PublicationSetting` now round-trips through export/import as
  `data/publication-setting.json` (untrusted on import — malformed/missing falls back to the lazy
  default, project content still imports). Caught a class-load fatal: `FormRequest` already
  defines `validationRules()`-shaped internals, so the shared static rules method was renamed to
  `configRules()` — worth grepping for elsewhere if a future task adds a similarly-named static
  method to a Form Request.
