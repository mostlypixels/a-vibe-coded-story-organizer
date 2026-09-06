# Overview

Five entities open their edit form when clicked: plotline, act, chapter, scene, event.
Only `Project`, `Book`, the codex entry and the public shared scene have a read page.
Reading a scene means loading an autosaving form.

`codex.show` (#144) proved the shape. This applies it to the rest.

## Goals

- `show` route, controller action, and Blade view for each of the five.
- List rows carry a view button (`x-icon-view-link`, eye icon) and link the name to the
  read page. Edit and delete icons stay.
- Search results reach the read page: `SearchDomain::viewRoute()` stops falling back to
  `editRoute()`.
- Read pages show what the entity is and what it belongs to, with no form control.

## Non-goals

- No edit form changes.
- No schema change. Every page reads what the model already holds.
- No public or shareable link. The `shared.scenes.show` page stays as it is.
- No new history surface. Link to `revisions.index` with the existing registry slug.
- No generic "entity show" base class or shared controller trait.

## What each page shows

| Entity | Content | Belongs to / children |
|---|---|---|
| Plotline | name, colour dot, main badge, description | its events, by `event_datetime` |
| Act | name, description, story number | its chapters, then their scenes, in story order |
| Chapter | name, cover, description, word count, scene count | its scenes in order; its act |
| Scene | name, status, word count, description, notes, prose | chapter, act, book; "happens during" event; mentioned events; referenced codex entries |
| Event | title, datetime, fixed badge, description | plotlines; scenes on it; scenes mentioning it; codex entries it starts or ends |

`Plotline` has **no** `scenes()` relation — only `events()`. The source spec's "the scenes
on it" is not available without going through events. Listing its events is the honest
page; see `open-questions.md`.

## Acceptance criteria

- Each read page returns 200 for the project owner and 403 for anyone else.
- Each read page's HTML contains no `<form`, no `<input`, and no autosave attribute — the
  check `CodexEntryTest::test_show_page_has_no_form_input_or_autosave_field` already makes.
- Each index page links the row name to `show` and renders a view icon.
- Search rows for all eight domains link to a `show` route.
