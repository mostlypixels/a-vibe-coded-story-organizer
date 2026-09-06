# Entity read views — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

- Plotline lists **events only**. It has no `scenes()` relation and gains none; the source
  spec's "the scenes on it" was not backed by the data model.
- Act nests scene titles under each chapter, capped at 20 rows with the `showAll` toggle.
  Chapter names alone say too little about an act.
- Scene `notes` renders on the read page, in its own card below the prose. The page is the
  writer's own, behind login.
- The story overview row gains a view icon, making four icons. Consistency won over density.
- `RecentlyEdited` keeps `*.edit` URLs: the list is what you were editing, so clicking
  resumes the edit.
- `redirectAfterSave()` unchanged. "Save and close" already reaches the index; a third
  destination is its own feature.
- The public shared scene page stays separate. Only the prose block is shared, as
  `x-scene-prose` — three callers, so the component is earned.

## Deviations from the spec/plan

- The act page renders each chapter as one table row with its scene titles as a comma-separated
  list in a `Scenes` column, not as nested rows under the chapter. The plan said "nested". The
  row form reads better at Melusine's scale and never reaches the 20-row cap, so the cap and its
  `showAll` toggle are unused on that page today. Revisit if an act grows past a screen.

## Issues → resolutions

_None yet._
