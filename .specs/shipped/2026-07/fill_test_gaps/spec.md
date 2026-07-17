---
status: shipped
shipped: 2026-07-08
---

# Fill test gaps

`PlotlineController` and `EventController` have no dedicated feature tests.

* Add `PlotlineTest`: CRUD, project authorization, and the `is_main` plotline
  being un-deletable (403).
* Add `EventTest`: the `is_fixed` bookend events being un-deletable (403) and
  `WithinEventWindow` bounds enforcement on the write paths.
