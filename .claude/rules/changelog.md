---
paths:
  - "CHANGELOG.md"
---

<!-- Moved out of the root CLAUDE.md so it loads only when CHANGELOG.md is touched.
     The ship-pr skill writes the entries and points here. -->

#### Changelog

* Every commit message body explains *why* the change was made and the intent behind it — this is the
  per-commit record (git already links, blames, and diffs it; no separate per-commit files).
* Maintain a single `CHANGELOG.md` at the repo root in [Keep a Changelog](https://keepachangelog.com)
  format, adapted so the heading answers *when something shipped*: each PR adds its own dated
  `## YYYY-MM-DD — <title> (#PR)` section at the top (below `[Unreleased]`), grouping its entries by
  `Added` / `Changed` / `Fixed` / `Removed`. Update it per feature or pull request (not per commit);
  `[Unreleased]` holds only work not yet merged to `master`. Richer rationale for a change set belongs
  in the PR description, which links its commits automatically.

##### Entry style

`CHANGELOG.md` is append-only and never pruned, so it must stay readable at ten times its
current length. Every entry is a line someone will scroll past for years — earn it.

* **One line, one change.** One sentence, ~20 words. Needing a second sentence means the
  detail belongs in the PR description.
* **What changed, not how or why.** No class names, file paths, method or prop signatures —
  unless the path *is* the change (`public/robots.txt` removed). No before/after narration,
  no worked examples, no counts of internals.
* **No bold lead-ins.** An entry is not a headline with a body.
* **A normal PR is 1–5 entries.** More usually means implementation steps were listed
  instead of user-visible changes.

> [!WARNING]
> Sections dated before `2026-08-02` predate this rule and read as PR descriptions. Do not
> imitate them, and do not rewrite them — the history is fine where it is.
