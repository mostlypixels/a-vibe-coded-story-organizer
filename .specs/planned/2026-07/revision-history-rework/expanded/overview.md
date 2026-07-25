# Overview — Revision History Rework

Expanded from `spec.md`. Source notes, in this feature's own `notes/` folder:
`handoff-revision-compare-interface.md`, `revision-compare-decisions.md`,
`revision-ui-lexicon.md`.

This feature reworks the **read** side of the shipped `autosave-with-revisions` feature
(`documentation/architecture.md` → *Revisions (autosave + field history)*), plus the one
write-side addition the read side needs (`save_id`). The storage model — per-field,
immutable, append-only rows in `revisions` — does not change.

## Problem

The revision system records the right facts and presents them at the wrong altitude.

1. **History is field-scoped down to the route.** `GET /revisions/{entity}/{id}/{field}`
   (`RevisionController::index`) can only answer *"what happened to `scene.contents`?"*.
   A writer asks *"what did I do to this scene?"*. One Save touching `description`,
   `notes` and `contents` writes three rows the writer has to find in three separate
   pages, and undoing that save costs three separate reverts.
2. **Nothing links the rows of one save.** The `revisions` table has no correlation
   column, and `created_at` is too coarse (three rows written inside one transaction
   share a second, and so do three rows from three unrelated autosaves).
3. **A history row says nothing about the change.** Date, author, label, origin — never
   *what* changed. Choosing which revision to open is guesswork, so the reader opens
   compare repeatedly until they find the edit they remember.
4. **Compare cannot be steered.** `RevisionController::compare` accepts `?from=&to=` but
   the page offers no control to change them; you get the pair encoded in whatever link
   you clicked. Every other pair means going back to the list.
5. **Rich fields lose their formatting in the diff.** `RevisionDiffer` projects
   `FieldKind::Rich` values through `RichText::toPlainText()` before diffing, then returns
   `RevisionDiffResult::formattingChangedOnly()` when only markup moved. A writer who
   bolded a sentence is told *"Formatting changed only."* and shown nothing.

## Goals

| # | Goal | Shape of "done" |
|---|------|-----------------|
| G1 | Rows of one save are linked | `revisions.save_id`, stamped at write time by `RevisionRecorder` |
| G2 | History is entity-level | `GET /revisions/{entity}/{id}` lists one row per save across all fields; `?field=` filters |
| G3 | A row shows what changed | Stored `summary_html` + `change_count` per revision, computed at write time |
| G4 | Compare is entity-level and steerable | Two **save points** picked with APG-conformant comboboxes; every field that differs gets its own diff section; `?from=&to=` in the URL |
| G5 | Rich fields get a real visual diff | Rendered-output diff with formatting changes shown as markers |
| G6 | A whole save can be undone | One button reverts every field the save touched, in one transaction |

> [!IMPORTANT]
> **The unit of the interface changes from *field* to *entity*.** Storage stays per-field
> (immutable, append-only — that is not up for renegotiation), but every screen above it
> speaks in *save points*: "the state of this scene right after that save". History lists
> save points; compare diffs two of them across all fields; revert undoes one. A single
> field becomes a **filter** (`?field=`) on those views, never a parallel set of pages.
> `architecture.md` → *Snapshots* defines what "the state of the entity at a save point"
> means when a save only touched one of its fields.

## Non-goals

* **No storage-model change.** Revisions stay per-field, immutable, append-only. Revert
  keeps writing a new row (`RevisionOrigin::Revert`); nothing rewrites history.
* **No new versioned fields.** `AutosavableFields::REGISTRY` is untouched.
* **No retention/purge changes.** `Revision::prunable()`, `RevisionPurger`,
  `RevisionSetting` and the admin panel are out of scope (one consequence is recorded in
  `data-model.md` → *Stale summaries after a prune*).
* **No autosave-frequency tuning** (`config('revisions.windows')` untouched).
* **No label editor.** Who sets a revision's optional `label` and when stays as it is
  today (auto-generated on manual save / revert); see `open-questions.md` Q5.
* **No cross-entity "project timeline".** The revisions browser sidebar
  (`ProjectRevisionsBrowser`) keeps its current shape, retargeted at the new URLs.

## User stories

1. *As a writer* I open a scene's history and see **one row per time I saved**, each row
   naming the fields that save touched, so I recognise my own working session.
2. *As a writer* I read a row's one-line summary — the first thing that changed, with a
   little context — and decide whether to open it, without opening it.
3. *As a writer* I realise a save was a mistake and press **Undo this save**: every field
   that save changed goes back, in one action, and I land on the edit form looking at the
   restored text.
4. *As a writer* I compare this morning's save with last night's and see **every field of
   the scene that differs** — the synopsis, the notes and the text — each with its own
   diff, instead of having to check three pages one at a time.
5. *As a writer* comparing two versions of a description, I see that a paragraph is now
   **bold** — rendered bold, marked as changed — rather than being told "formatting
   changed only".
6. *As a writer* on the compare screen I change the right-hand version with a dropdown,
   filter it to *manual saves only* to skip the autosave noise, and the URL updates so I
   can bookmark the comparison.
7. *As a keyboard-only user* I operate both version pickers with arrows / Enter / Escape /
   Home / End, and my screen reader announces the current option and whether a diff
   passage was inserted or removed.

## Acceptance criteria

**Data (G1, G3)**

* Every revision written after this ships carries a non-null `save_id`; two rows written
  by the same request for the same entity share it; two different requests never do.
* Every non-baseline revision carries a `summary_html` and a `change_count` computed at
  write time. No page ever computes a diff to render a *list*.
* Pre-existing revision rows are **deleted** by the migration, so no read path ever has to
  special-case a row with no `save_id` and no summary. History restarts from a `baseline`
  row per field, re-seeded on that field's next write.

**History (G2)**

* `GET /revisions/{entity}/{id}` renders save points, newest first, 20 per page.
* `?field=<field>` filters to one field's rows and is the only remaining field scoping;
  the legacy `/revisions/{entity}/{id}/{field}` path redirects to it.
* A save point shows: date, author, origin badge, the fields it touched, its label if any,
  a per-field summary line, and (unless it is the current state) *Undo this save*.
* The list never selects `revisions.value` (existing invariant, `documentation/architecture.md`).

**Compare (G4, G5)**

* `GET /revisions/{entity}/{id}/compare?from=<saveId>&to=<saveId>` renders **one diff
  section per field that differs** between the two save points, in registry field order,
  with a count of the fields that are unchanged. Both comboboxes reflect the URL;
  changing either navigates.
* A field neither save touched but that changed in between still appears — the two sides
  are *snapshots of the entity*, not the rows those two saves happen to have written
  (`architecture.md` → *Snapshots*).
* `?field=` narrows the page to one field's section without changing the pair.
* The right combobox never offers a save point older than or equal to the left selection.
  There is no swap control and no backwards diff.
* A `FieldKind::Rich` field diffs its **rendered** output: text changes are word-level
  `<ins>`/`<del>`, formatting-only changes are shown as the rendered formatting plus a
  "formatting changed" marker on that block.
* `Scene.contents` (`FieldKind::Markdown`) and `Project.rights` (`FieldKind::Plain`) keep
  the current source diff.
* Diff markup is emitted by the app, never re-purified after wrapping, and never reaches
  the author-content rendering path (`x-rich-text`).
* Every change carries a background tint **and** a `+`/`−` gutter mark **and**
  visually-hidden "inserted"/"removed" text. Colour alone never carries meaning, and
  neither strikethrough nor underline is used as a diff marker.

**Revert (G6)**

* *Undo this save* reverts every field in the group inside one `DB::transaction`, writes
  one new save group with `RevisionOrigin::Revert`, redirects to the entity's edit form,
  and flashes a message naming what was restored.
* If any field in the group changed since the page loaded, nothing is written and the
  request 409s (same base-hash contract as `FieldAutosaveController`).
* Per-field revert keeps working exactly as it does today.

**Cross-cutting**

* Reads authorize `view` on the owning `Project`, writes authorize `update`
  (`HasRevisions::revisionProject()` walk). A non-owner gets 403 on every new route.
* `documentation/architecture.md` → *Revisions* and `CHANGELOG.md` are updated.
