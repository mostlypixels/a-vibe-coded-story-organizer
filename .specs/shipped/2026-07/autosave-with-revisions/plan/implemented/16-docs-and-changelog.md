# Task 16 — Documentation + CHANGELOG

## Scope

* `documentation/architecture.md` — a new "Revisions" section (matching the existing
  "Hidden from crawlers" section's style, per CLAUDE.md's convention of one section per
  major feature): the `revisions` table shape, the `AutosavableFields` registry as the
  single source of truth, the coalescing-window model, the origin/prune/purge
  distinction, and a pointer to `handoff.md`/this plan folder for the full rationale
  trail (`.specs/shipped/2026-07/autosave-with-revisions/` once shipped).
* `CHANGELOG.md` — a new dated `## YYYY-MM-DD — Autosave with revisions (#PR)` section
  under `[Unreleased]` at merge time (per CLAUDE.md's changelog convention — this task
  drafts the entries; the actual PR number/date are filled in at ship time), grouped
  `Added`/`Changed`. **Must explicitly note the known gap**: short fields (`name`,
  `chapter_id`, `status`, `event_id`, `mentioned_events`) still only save on manual form
  submit — closing that gap is `.specs/draft/data-loss-warnings`' job, deliberately not
  part of this feature (grilling decision: ships independently).
* A short note (in `documentation/architecture.md` or wherever this project tracks
  cross-spec claims — check for a precedent) that **`Ctrl-S` is now claimed** by
  autosave's flush-and-close behavior, for whoever picks up
  `.specs/draft/keyboard-shortcuts` later.
* If `documentation/best-practices.md` or `code-style.md` has a "where does X kind of
  logic live" list (check before assuming), add `RevisionRecorder`/`RevisionPurger` as
  examples of the Service-class pattern CLAUDE.md already documents.

Does **not** include any code change — this is the final, documentation-only task.

## Depends on

All of tasks 1–15 (documents what was actually built, not what was planned — if any
task deviated from this plan during implementation, per the `plan-implementer`
convention that's recorded in `resolution-log.md`; this task should read that log
before writing docs, so the documentation reflects reality).

## Key decisions already made

* CLAUDE.md's "npm run test" canonical command is already present (confirmed this
  session, added by `expand-tip-tap`) — no CLAUDE.md change needed for that; this
  feature doesn't introduce any new canonical command.
* Changelog entries are per PR, not per commit — one dated section, `Added`/`Changed`
  grouping, per CLAUDE.md.

## Consult

* `../resolution-log.md` — read in full before writing; it is this plan's own record of
  what actually happened during implementation.
* `expanded/overview.md` — the goals/non-goals list is the source for what the
  changelog should claim was added.
* CLAUDE.md's Documentation and Changelog sections — format requirements.
* `documentation/architecture.md`'s existing "Hidden from crawlers" section — the
  structural example to match (GFM alert callouts for warnings/notes, `> [!WARNING]`
  for pitfalls).

## Tests

None — documentation-only task. If this project has a docs-lint or a test asserting
`documentation/` files stay in sync with something (check for a precedent, e.g. the
`SpecsStatusConsistencyTest` pattern CLAUDE.md mentions for spec status), run it, but do
not invent a new automated check here.
