---
status: shipped
shipped: 2026-07-16
---

# Advanced search

The admin must have a search page that will be listed at the end of the menu.

It will be able to run a search over the string and text fields of the codex entries, scenes, chapters,  acts, events and plotlines of the current project.

* It does not run in ajax: it's a standalone controller.
* The search form allows to search several word as an and/or/exact string.
* the search results are divided into the same sections as the menu:
  * timeline
  * story
  * codex
* the sections are divided in 3 columns each (for now)
* the columns have tables with the name of the entity matching in the first column (with small muted field name), and a portion of the matching sentence with the terms highlighted

Suggest appropriate libraries for implementation
