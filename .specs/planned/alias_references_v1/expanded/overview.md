# Overview — Alias references v1

## Problem

Codex entries (`CodexEntry`) already carry free-text **aliases** (`codex_aliases`, see
`CodexEntryController::syncAliases`). Scenes (`Scene.contents`, Markdown) mention characters,
locations, and organizations by name or alias, but there is no queryable link between a scene
and the codex entries it mentions — a writer can't answer "which scenes mention Mélusine" or
"what does this scene already reference" without full-text guessing.

## Goal

Introduce a **persisted many-to-many relationship** between `Scene` and `CodexEntry`
(`scene_codex_entry`), maintained automatically:

- Recomputed whenever a scene is saved (store/update), by scanning `Scene.contents` for
  every alias of every codex entry in the same project.
- Recomputed whenever a codex entry's aliases change (an alias added/removed/edited on the
  entry edit form re-scans that project's scenes).
- Kept in sync when a codex entry is deleted (cascade) or when aliases are cleared.

This is a **derived cache**, not user-editable data — no UI ever lets a user hand-add or
remove a single scene↔entry link. The only user-facing controls are the alias list (which
drives what's searched for) and the two read-only displays below.

## Non-goals (v1)

- No live/AJAX re-scan while typing in the scene editor — recomputation happens on save only
  (explicit in the source spec).
- No fuzzy/partial matching, no stemming, no plural handling — literal, **case-sensitive**,
  whole-word matching only. Aliases under 3 characters are never matched. Matching **is**
  locale-robust in one specific sense worth calling out as in-scope, not a non-goal: both sides
  are normalized to Unicode NFC before comparison, so accented French/Italian names match
  regardless of which normalization form the source text used (see `architecture.md`).
- No cross-project matching — an entry only matches scenes in its own project.
- No matching inside `Scene.description` or `Scene.notes` — only `Scene.contents` (the
  manuscript text) is scanned. `description`/`notes` are rich HTML, not prose content, and
  `notes` is explicitly private (architecture.md).
- No UI to browse "all references" globally — only the two per-record sidebars the spec asks
  for (codex edit page, scene edit page).
- **Not exported.** `scene_codex_entry` is a derived cache (see the *Invariant* in
  `data-model.md`), so `StaticSiteExporter` never writes it to `scene.json` — an export archive
  carries no reference data at all. Import instead **regenerates** it once, after the graph is
  fully rebuilt (see `architecture.md` → *Import/export interaction*).

## User stories

1. As a writer, when I save a scene, any codex entry whose name or alias appears as a whole
   word in the contents gets automatically linked, so I don't have to tag entities by hand.
2. As a writer, editing a codex entry's aliases (the edit page, help text under the field)
   makes me aware that overlapping aliases across entries can produce ambiguous/ambiguous-looking
   matches.
3. As a writer, on a codex entry's edit page I can see every scene that currently references
   it, ordered along the story's timeline, so I can jump to relevant scenes.
4. As a writer, on a scene's edit page I can see every codex entry the current contents
   reference, in the sidebar, without leaving the page (populated on load from the saved
   relationship — updates only after the next Save, per the source spec's "no AJAX" rule).

## Acceptance criteria

- Saving a scene with contents containing an entry's name or alias as a whole word creates
  the `scene_codex_entry` row; a scene with `melody` and an entry aliased `Mel` does **not**
  link (word-boundary match only).
- Removing the matching text from a scene and saving removes the relationship row.
- Deleting a codex entry removes all its `scene_codex_entry` rows (cascade).
- Deleting/editing an alias so it no longer matches removes the relationship rows that existed
  only because of that alias, on the next re-scan trigger (scene save or entry save).
- Codex edit page: help text is visible under the aliases field; sidebar lists every
  referencing scene in timeline order.
- Scene edit page: sidebar lists every referenced codex entry, grouped/labeled by type
  (matching the existing `CodexAsOfResolver` grouping convention), reflecting the
  **last-saved** state (no live JS).
- A non-owner cannot see another project's references (authorization already flows through
  `Project` on both edit actions — no new gap to introduce).

## Performance note (per source spec)

The source spec explicitly calls out considering performance before implementation. See
`architecture.md` for the chosen approach (regex built once per project, applied per scene) and
`open-questions.md` for the batching question this raises for entry-alias-change re-scans.
