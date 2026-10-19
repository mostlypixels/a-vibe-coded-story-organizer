# New Scene Autosave — plan

## Order

| Task | Purpose |
|---|---|
| `01-create-form-without-prose.md` | New Scene page drops Description and Contents; create opens the Write page. |
| `02-write-page-new-scene-dialog.md` | "New scene" dialog in the Write page's left panel: chapter + title. |

## Decided (binding)

- After create, the writer lands in the Write page (`scenes.write`), not the edit page or index.
- No prose on any create surface. Description is set later on the edit page.
- No leave warning on the create form.
- The dialog asks only Chapter (default: the current scene's chapter) and Title. Status is Draft.
- A new scene goes at the end of its chapter (existing `SceneSaver::create()` behaviour).
- An empty scene left after create is kept. No cleanup job.
- No `localStorage` draft (`.specs/shipped/2026-08/no-localstorage-with-autosave/`).

## Invariants

- Every create goes through `SceneSaver::create()`. No second create path.
- Authorize through the book's `Project` (`update`), in the Form Request and with a 403 test.
- The chapter must belong to the route's book (same rule as `StoreSceneRequest::rulesFor()`).
- Leaving the Write page never drops a pending autosave: flush first, ask on failure
  (`settleAutosaves()` in `resources/js/navigation-guard.js`).
