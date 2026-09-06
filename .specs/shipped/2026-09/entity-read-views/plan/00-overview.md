# Entity read views — plan overview

Read-only `show` pages for plotline, act, chapter, scene and event, following the shape
`codex.show` proved in #144.

## Order

| # | Task | Purpose |
|---|---|---|
| 01 | `x-scene-prose` | Extract the prose article shared by three callers |
| 02 | Plotline read page | Smallest entity; proves the page + index-row shape |
| 03 | Event read page | Adds `EventLifespanEntries` |
| 04 | Act read page | Chapters with scenes nested |
| 05 | Chapter read page | Scenes, act, word count |
| 06 | Scene read page | Prose, notes, codex references, events |
| 07 | Cross-links | `SearchDomain::viewRoute()`, codex show, story overview |

Tasks 02-06 each own their entity end to end: route, controller action, view, index-row
changes, tests. 07 waits until all five routes exist.

## Binding decisions

From the grill. Do not re-litigate:

- Plotline lists its **events only**. No `Plotline::scenes()` relation.
- Act nests scene titles under each chapter, capped at 20 rows with the `showAll` toggle.
- Scene `notes` renders in its own card below the prose.
- The story overview row gains a view icon beside its edit icon.
- `app/Services/RecentlyEdited.php` keeps its `*.edit` URLs. Untouched.
- `redirectAfterSave()` still lands on `*.edit`. Untouched.
- `shared/scenes/show.blade.php` stays a separate page. Only the prose block is shared.
- No schema change, no new relation except the `EventLifespanEntries` query in 03.

## Invariants every task preserves

- **Authorize `view`, not `update`.** Walk to the owning `Project` the way the entity's
  `edit()` does. Non-owner gets 403.
- **No form on a read page.** No `<form>`, no `<input>`, no autosave attribute. Alpine only
  for disclosure.
- **Sections omit, never empty.** No card for content that is not there.
- **Eager-load every child list.** The act, chapter and event pages walk `chapter.act`.
- **Story numbers come from `StoryNumbering::forBook()`**, counted over the whole book.
- **Index rows keep their edit and delete icons.** The view icon goes first, before edit.
