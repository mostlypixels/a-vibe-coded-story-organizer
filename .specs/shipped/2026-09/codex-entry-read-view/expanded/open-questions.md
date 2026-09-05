# Resolved questions

Settled in the grill before `mp-plan-tasks` decomposed this. Binding on the plan.

- **How much attribute history does the read page show?** The full timeline, every period,
  baseline first. The codex has no "now" — an attribute is a sequence of values along the
  timeline, and only a scene gives one primacy. The first draft of this document recommended a
  "current value", which does not exist.

- **Does the entry page get an "as of" viewpoint picker?** No. The as-of panel on the scene
  editor owns that question, and duplicating it here adds state to a page whose value is that
  it has none.

- **Cap the referencing-scene list?** Yes, 20 in timeline order with a "show all" toggle that
  expands in place. Not a link to a filtered scene list: `SceneController::index` is
  book-scoped, filters on search and chapter only, and does not paginate, so that page does not
  exist and building it is its own feature.

- **Do reference images and files appear?** Yes. Cover in the header, reference images as
  thumbnails, reference files as a download list. Reading is what reference material is for.

- **Where does plain "Save" land?** `codex.show`. "Save and stay" still returns to the form.
  A one-line change in `CodexEntryController::update()`; no other entity changes.

- **`view` or `update` as the gate?** `view`, matching `index`. `ProjectPolicy::view` and
  `::update` are identical today, so the choice costs nothing and is right when it stops
  being so.

- **Does `codex.edit` stay reachable by URL?** Yes, unchanged.

## Deferred

- A hover card on the "codex as of this scene" panel instead of a link to the read page.
  Better answer, bigger feature.
- Filtering the scene index by codex entry, with pagination.
