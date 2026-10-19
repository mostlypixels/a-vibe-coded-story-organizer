# Overview

## Decision

"Create early" with no new JavaScript: the New Scene page stops asking for prose.

- The form keeps only the short fields: chapter, title, status, event, viewpoint, mentioned events.
- **Create Scene** saves and goes straight to the Write page (`scenes.write`). There the body
  has focus and autosave.
- So no prose is ever typed before the scene exists. The only thing that can still be lost is
  a title and a few selects, which take seconds to type again.

Rejected: create the scene in the background on title blur and swap the page into the edit form.
It needs a JS create call, a second save path and a hand-over of focus and cursor. The redirect
gets the same result with code that exists.

## User stories

- I click **New scene**, pick a chapter, type a title, press Enter. I land in the Write page and
  type the body. A reload or a nav click after that loses nothing.
- I start from the empty Write page (**Create a scene**). Same flow, and I end in the Write page.

## Acceptance criteria

- The create form has no Description and no Contents field.
- A successful create redirects to the Write page of the new scene.
- In the Write page, the body has focus and autosaves (existing behaviour, `editor/show.blade.php`).
- Validation errors still return to the form with `old()` values.
- Description stays editable on the scene edit page (autosave field, as today).
