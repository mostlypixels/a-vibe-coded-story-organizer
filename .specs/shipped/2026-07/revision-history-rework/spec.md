---
status: shipped
shipped: 2026-07-26
planned: 2026-07-25
expanded: 2026-07-25
---

# Revision History Rework

Follow-up to the shipped `autosave-with-revisions` feature. The storage layer is sound;
the *interface* on top of it is not. The brainstorming this draft came from lives beside
it in `notes/`: `handoff-revision-compare-interface.md` (what exists, what's wrong),
`revision-compare-decisions.md` (the decision list), `revision-ui-lexicon.md`
(terminology and prior art).

## Problem

A writer thinks *"what did I do to this scene?"*. The current UI can only answer
*"what happened to `scene.contents`?"* — history is field-scoped down to the route
(`/revisions/{entity}/{id}/{field}`). One Save touching description + notes + contents
produces three unrelated rows in three separate histories, and undoing that save means
three separate reverts. Nothing in the schema records that those three rows came from the
same save, and timestamps are too coarse to reconstruct it.

The compare screen is equally thin:

* it has no revision pickers — you diff whatever pair the link you clicked encoded;
* the pair is only half in the URL (`?from=&to=`) and there is no way to change it in place;
* rich-HTML fields are flattened through `RichText::toPlainText()` before diffing, so a
  writer who bolded a paragraph gets told *"Formatting changed only"* instead of seeing it;
* the history list shows no indication of *what* changed in a row, only when and by whom,
  so choosing which revision to open is guesswork.

## Goals

1. **Group the rows of one save.** Add a `save_id` stamped once per save request onto every
   revision row that request writes, so "undo that whole save" and "one row per save"
   become possible. Capture-time only — existing rows stay `null` and ungroupable.
2. **Entity-level history as the primary view.** `/revisions/{entity}/{id}` lists one row
   per save across all of that entity's fields, showing which fields the save touched. The
   existing field-scoped route survives as a filtered view over the same query.
3. **A useful row summary.** Each row shows the first changed hunk plus a little context,
   bounded by rendered length, with "and X more changes" linking into compare. Computed at
   write time and stored — never at render time.
4. **A compare screen you can steer.** An accessible combobox above each column picks that
   side's revision; left is always the older side and the right combobox disables anything
   not newer; per-side filters (manual-only, date range) live in the dropdown panel; the
   selected pair lives in the URL.
5. **A real visual diff for rich fields.** Diff the *rendered* output of rich-HTML fields,
   with formatting changes visible as markers rather than tag noise.
   `Scene.contents` stays a Markdown source diff — there the markup is what she typed.
6. **Whole-save revert** alongside the existing per-field revert, landing back on the
   entity's edit form with a flash message naming what was restored.

## Non-goals

* Changing the storage model: revisions stay per-field, immutable and append-only. Revert
  keeps writing a new row; nothing ever rewrites history.
* Retention/purge behaviour, the admin revisions panel, and the export/import of revisions.
* Autosave frequency tuning (tracked separately).
* Versioning any new field — the `AutosavableFields::REGISTRY` field list is unchanged.

## Rough approach

Data first, because capture is time-sensitive: a migration adds a nullable `save_id` (ULID)
plus a stored per-row change summary, and `RevisionRecorder` stamps both. The coalescing
window (an autosave that overwrites a still-open row) has to decide whether the row keeps
its original `save_id` or takes the new one — the spec must answer that explicitly.

The reads then rebuild on top: a `RevisionHistory` service producing save-grouped rows
(paginated 20, fetching N+1 so the first row of page 2 has its predecessor), an
entity-level route with the field-scoped one folded into it as a filter, and a compare
screen driven by two comboboxes built to the W3C APG select-only combobox pattern
(Alpine, keyboard contract, `aria-expanded` / `aria-selected` / `aria-activedescendant`).

The visual diff is the largest piece and needs its own evaluation before code: the current
`RevisionDiffer` wraps `jfcherng/php-diff`, which is line/word oriented and not HTML-aware.
The ordering constraint is fixed — already-purified content in → diff → wrap `<ins>`/`<del>`
→ render through a dedicated `x-diff` component with its own allow-list, never re-purified
after wrapping (the author allow-list would eat the markers). The editor's strikethrough
must stay on `<s>` so `<del>` remains the diff layer's alone. Changes are marked with a
background tint *plus* a `+`/`−` gutter mark and visually-hidden "inserted"/"removed" text —
never colour alone, and never strikethrough or underline, which the author can write herself.

Authorization, testing and documentation follow the existing rules: every read authorizes
`view` and every write `update` by walking to the owning `Project`, every action ships a
feature test including the non-owner 403, and `documentation/architecture.md`'s Revisions
section plus `CHANGELOG.md` are updated as part of the work.

## Open questions for expansion

* Which library (or in-house component) performs the HTML-aware visual diff.
* Coalescing window vs `save_id`: keep the original or adopt the new one.
* Does the field-scoped history survive as its own page, or fold entirely into a filter?
* Concurrency: reverting from a compare screen whose head moved since it loaded.
* Who sets a revision's optional `label`, and when.
