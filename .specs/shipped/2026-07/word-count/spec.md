---
status: shipped
shipped: 2026-07-29
planned: 2026-07-27
expanded: 2026-07-27
---

# Word Count

Add word counter to fields:

* Live word counter on text fields, displayed in the bottom right corner, muted color.
* Cached word counts in scenes, saved to the database.
  * The chapter word count is the sum of the word counts in the scenes.
  * The act word count is the sum of the word counts in the chapters.
  * The project word count is the sum of the word counts in the acts.
* The cached word counts are updated when a scene is saved.
* The cached word counts are displayed in the chapter and act views, as well as the project view.
* The cached word counts are shown in lists.


Word_count on scenes only, SUM for ancestors.

## Non-goals

Targets, deadlines and progress tracking (a word-count goal with a due date, "words written
today", streaks, projected finish) are **out of scope here** and become a follow-up spec,
`word-count-goals`, which depends on this one. Counting needs one integer per scene;
progress needs a per-day time series — a different data model carrying different risk, and
there is no reason to hold the counter hostage to it.
