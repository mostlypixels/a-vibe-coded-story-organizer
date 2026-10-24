# Find And Replace — plan

## Order

| Task | Purpose |
|---|---|
| `01-act-scope.md` | Act filter in `SearchScope`, the search request, the narrow panel and the summary |
| `02-text-matcher.md` | `SceneTextMatcher`: literal, case and whole-word matching, with byte offsets |
| `03-preview.md` | Find and replace page: the form and a server-rendered preview grouped by scene |
| `04-apply.md` | `store`: replace the selected matches, one transaction and one revision per scene |
| `05-selection-ui.md` | Alpine selection, select all and none, fold and unfold all, counter, sticky bar, submit |

## Binding decisions

- Undo is one scene at a time. Each changed scene gets one manual revision labelled `Replace "<find>" with "<replace>"`. There is no batch ID and no undo for the whole replace.
- The page is its own page (`projects.replace.index` / `.store`), linked from the search page header and the project navigation.
- Find matches raw Markdown. `miss Lane` does not match `miss *Lane*`.
- A scene open in an editor gets no special handling. `NoAutosaveConflict` handles the editor's next autosave.
- The preview cap is 2,000 matches. Above it, show the first 2,000 and a notice to narrow the scope. Apply refuses more than the cap.
- Scene groups are open with 20 matches or fewer in total, and folded above that. "Unfold all" and "Fold all" buttons are in the sticky bar.
- Find is always accent-sensitive. It has no option to ignore accents.
- Both actions authorize `update` on the project.
- Act scope belongs to `SearchScope`, so the search page gets it too. An act together with a chapter range is a validation error.
- The find text is literal. Case-sensitive and whole-word are on by default.

## Invariants

- Every scene query goes through the project (`$project->sceneQuery()`). A submitted scene ID from another project is ignored.
- The preview and apply use the same match list. Apply trusts the indexes only when the scene's `FieldHash` still equals the hash from the preview.
- A changed scene saves through `RevisionRecorder::saveWithManualCheckpoint()` and resyncs `SceneReferenceMatcher`. `word_count` follows through `Scene::booted()`.
- Manuscript order is book, act, chapter, scene position, then ID, as in `ProjectSearch`.
- The selection is sent as one JSON field, never one input for each match (`max_input_vars`).
