# Open questions

1. **A plotline has no scenes, only events.** The source spec asks for "the scenes on it".
   *Recommended:* list its events; add a second list of the scenes on those events only if
   it proves needed. Do not add a `Plotline::scenes()` relation for this page.

2. **Does the story overview keep linking to edit?** `components/story-chapter.blade.php`
   is a drag-and-reorder surface. *Recommended:* add a view icon beside the edit icon,
   keep both.

3. **Does "recently edited" link to the read page?** `app/Services/RecentlyEdited.php`
   builds `*.edit` URLs, including for the codex, which already has a read page.
   *Recommended:* leave it on edit — the list is what you were editing — and fix the
   inconsistency only if it reads wrong.

4. **Where does saving land?** `redirectAfterSave()` sends every update back to `*.edit`.
   *Recommended:* unchanged. "Save and close" already goes to the index; adding a third
   destination is its own feature.

5. **Act page depth.** Chapters only, or chapters with their scenes nested?
   *Recommended:* chapters with scene titles nested, capped at twenty rows total with the
   `showAll` toggle. An act with only chapter names says too little.

6. **Scene notes on the read page.** `notes` is a private field, not part of the prose.
   *Recommended:* include it in its own card, below the prose. It is the writer's page.

7. **Does the shared public scene page reuse `scenes.show`?** *Recommended:* no. It has a
   different layout (`x-public-layout`) and no edit controls. Only the prose article is
   shared, as `x-scene-prose`.
