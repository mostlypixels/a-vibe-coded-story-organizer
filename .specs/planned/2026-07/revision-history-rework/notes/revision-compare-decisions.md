---
title: Revision compare UI — decisions so far
context: a-vibe-coded-story-organizer / revision system rework
status: draft notes (not a spec artifact)
date: 2026-07-24
---

# Revision compare UI — decisions so far

## Data & computation

- Revisions are immutable; nothing rewrites an existing revision.
- Revert creates a new revision — history is append-only.
- The diff between two revisions is a constant.
- Compute each revision's diff summary once, at write time.
- Do not compute diffs at render time, and not lazily either.
- Changing the diff algorithm later requires a backfill migration.

## Granularity & grouping

- Only long-form text fields are versioned; `title` is not.
- Per-field revision streams stay as the storage model.
- Add a `save_id` (ULID or UUID) to the revisions table.
- One `save_id` generated per save request, inside the transaction.
- Every revision row written by that request carries the same `save_id`.
- Captured at write time only — it cannot be reconstructed afterwards.
- Existing revisions keep a null `save_id` and stay permanently ungroupable.
- This buys whole-entity revert without rewriting the shipped feature.

## History list

- Primary history is per entity: "everything I did to this scene."
- Per-field history becomes a filtered view over the same rows.
- One row per save, grouped by `save_id`.
- Paginated, 20 rows per page.
- Visual indicator distinguishes manual saves from autosaves.
- Each row has a revert button.
- Fetch N+1 rows per page so row 21 has its predecessor.

## Row summary

- A row exists to decide whether to open it, not to read the change.
- Show the first changed hunk plus a little surrounding context.
- Bound the excerpt by rendered length, not by hunk count.
- Append "and X more changes" when truncated.
- That link opens the compare screen with `from`/`to` prefilled.
- Cap word-level diff complexity; degrade to line-level past the cap.
- Complexity cap follows wikidiff2's `maxWordLevelDiffComplexity` approach.

## Compare screen

- Two side-by-side columns: old on the left, new on the right.
- A combobox above each column picks that side's revision.
- Left combobox defaults to the revision being opened.
- Right combobox defaults to the previous revision.
- Left is always older; the right combobox disables anything not newer.
- No auto-swapping, no backwards diffs, no error state.
- Diff direction is never the user's choice.
- Word-level highlighting inside prose, not line-level.
- Revert button on each side, hidden on the current version.
- A "current" marker in option labels signals head explicitly.
- The revision pair lives in the URL (`?from=12&to=40`).
- Compare page is a pure GET; revert is POST/PATCH.
- After a revert, redirect to the entity's edit form showing the restored content.
- Flash message confirms which revision was restored.

## Diff strategy

- `Scene.contents` is Markdown — diff the source directly.
- Tiptap HTML fields — visual diff over rendered output, not source.
- Rationale: the writer never authors or reads that HTML.
- Formatting changes must be visible, as markers rather than tag noise.
- Source is always what gets stored; revert depends on it.

## Diff rendering & sanitisation

- Order: already-purified content in → diff → wrap changes → render.
- Diff output never travels the author-content rendering path.
- Dedicated `x-diff` component with its own allow-list.
- Never purify after wrapping — the author allow-list eats the markers.
- Never let unpurified content reach the diff renderer.
- Constrain Tiptap strikethrough to `<s>`, never `<del>`.
- `<s>` means "no longer accurate"; `<del>` means "removed" — different tags.
- `<ins>` and `<del>` stay reserved for the diff layer.

## Diff appearance

- Never carry meaning in colour alone.
- Background tint plus a `+` / `−` gutter mark.
- Strikethrough and underline are unusable — the author can write both.
- Visually-hidden "inserted" / "removed" text for screen readers.
- `<ins>` / `<del>` announcement is inconsistent across screen readers.

## The comboboxes

- Not native `<select>` — a custom combobox with a dropdown panel.
- Option label: revision number, date, optional label, manual-save hint.
- Filters live inside the dropdown panel.
- Filters: save type (manual only) and date range.
- Filters are independent per side, deliberately not synced.
- Rationale: recover a bad save by comparing a manual save against nearby autosaves.
- Payload size is not the constraint; human scanability is.
- Build against the W3C APG combobox pattern.
- Keyboard contract: arrows, Enter, Escape, Home/End, typing filters.
- Maintain `aria-expanded`, `aria-selected`, `aria-activedescendant`.

## Open / deferred

- Autosave frequency will be tuned later (not every two words).
- Which PHP library performs the HTML-aware visual diff.
- Storage growth and whether old autosave revisions get pruned.
- Concurrency: reverting from a compare screen whose head has moved.
- Whether the history list also needs the save-type filter.
- Where the "current" marker renders beyond the option label.
- Who sets a revision's optional label, and when.

## Reference points

- MediaWiki versions the whole page — one revision per save, no grouping needed.
- Its source diff (`wikidiff2`) is line-level, then word-level under a complexity cap.
- Its visual diff (VisualEditor, default since MW 1.41) compares rendered output.
- MediaWiki signals changes with colour and borders, never typographic marks.
- `git diff --stat` and GitHub's collapsed diffs: magnitude in the list, detail on demand.
