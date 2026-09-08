# Open questions

- **Does the quick-created event stay if the writer then abandons the page?**
  Recommend: yes, as the source spec says. An event is a standalone timeline item, and a
  hidden cleanup that deletes a writer's typing is worse than a stray row. Say so in the
  status line: *"Saved to the timeline."*

- **Does the parent form still need `new_event_title`?**
  Recommend: yes, keep it. It is the no-JavaScript path and the fallback when the request
  fails. Dropping it makes the feature a requirement rather than an enhancement.

- **One endpoint, or reuse `projects.events.store` with `wantsJson()`?**
  Recommend: one endpoint. `store()` requires `plotlines` and redirects; the quick path
  attaches Main itself. See `architecture.md`.

- **Does the picker need the new event's description?**
  Recommend: no. Title and date are what the picker shows. A writer who wants more opens
  the event page later.

- **Should the same button appear on the scene create form, where the scene does not exist
  yet?**
  Recommend: yes. The event belongs to the project, not the scene, so nothing about it
  depends on the scene being saved.

- **What happens when two pickers on one page both have unsaved inline input?**
  Recommend: each Save button saves its own fields only. No page-wide save.
