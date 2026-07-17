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
| 04 | publication-setting-config-form | Sonnet | ⬜ next | |
| 05 | publication-setting-in-archive | Opus | ⬜ | |
| 06 | shared-cover-image-service | Haiku | ⬜ | |
| 07 | chapter-cover-infrastructure | Opus | ⬜ | |
| 08 | exporter-metadata-and-cover-toggles | Sonnet | ⬜ (owns defaults===v1 regression guard) | |
| 09 | exporter-content-options | Opus | ⬜ | |
| 10 | exporter-toc-depth | Opus | ⬜ | |
| 11 | exporter-front-back-matter | Sonnet | ⬜ | |
| 12 | exporter-chapter-cover-pages | Sonnet | ⬜ | |
| 13 | codex-appendix | Opus | ⬜ | |

## How to resume

1. `bash scripts/plan-next-task.sh epub-configuration` → lowest remaining task (should be 04).
2. Spawn `plan-implementer` (Agent tool) for that ONE task on its assigned model; prompt it to
   read `00-overview.md`, `../expanded/*.md`, and `resolution-log.md` first, verify with
   `composer test`, append to `resolution-log.md`, and move the task file to `plan/implemented/`.
3. Confirm the `.md` moved to `plan/implemented/`; if verification failed, **stop the loop**.
4. Stop after each task and report to the user (quota check).

## Notes

- Task 02 owns the **single** manifest-version bump (`DATA_VERSION 1→2`; supported `[1,2]`).
  No later task should bump it again.
- Task 08 owns the **defaults===v1 byte-for-byte regression test**; every later exporter task
  (09–13) must keep it green.
- Completion / deviations logged per task in `resolution-log.md`.
- After task 13: final sanity pass, then ship-plan step 8 (`spec-advance.sh epub-configuration
  shipped`) + ask before committing via `ship-pr`.
- `.specs/draft/author-notes/` in the working tree is **unrelated** WIP — do not commit it.
