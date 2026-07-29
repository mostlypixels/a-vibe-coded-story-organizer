# Word Count — overview

## Problem

Writers measure work in words. This app stores a whole novel and cannot tell you how long
any of it is. A writer needs that number often enough that having to leave the app for it
is the difference between using the app and not.

## Goals

* A **live** word count while typing, on the prose fields, in the field's bottom-right corner.
* A **stored** count per scene, so lists and overviews can show length without re-reading
  every scene's text.
* Chapter / act / project totals derived from those stored counts.
* The number is **right** — after an autosave, a manual save, a revert, an import, a scene
  moved to another chapter, or a chapter deleted.

## Non-goals

* **Targets, deadlines, progress** — the `word-count-goals` follow-up spec. Counting needs
  one integer per scene; progress needs a per-day time series. Different data model,
  different risk, no reason to hold the counter hostage to it.
* Counting anything but scene `contents` toward totals. Descriptions and notes get a live
  counter because it costs nothing, but they are apparatus, not the book.
* Character counts, reading time, per-session counts.

## User stories

* As a writer, I see my scene's word count update as I type, without it pulling my eye.
* As a writer, I open the story overview and see how long each chapter and act is.
* As a writer working to a length, I check my project total and trust it.
* As a writer, I move a scene to another chapter and both chapters' totals are immediately
  right.

## Acceptance criteria

1. Typing in a prose field updates a muted counter in its bottom-right corner, without a
   round trip.
2. `scenes.word_count` matches the stored `contents` after **every** write path: autosave
   PATCH, manual save, `RevisionReverter` revert/undo, project import, seeders.
3. Chapter / act / project totals equal the sum of their descendant scenes' counts, with no
   stored duplicate to drift (see `data-model.md`).
4. Totals appear on the story overview, the chapter/act/scene index pages, and the project
   view.
5. A scene reparented or cascade-deleted changes both affected ancestors' totals with no
   fix-up code.
6. Live (JS) and stored (PHP) counts agree on the same text — one documented rule, tested on
   both sides.

## Load-bearing decisions already taken

* **`word_count` on `scenes` only; ancestors are a `SUM`.** Benchmarked at 150 / 960 / 4,320
  scenes: the widest gap versus a fully denormalised tree was 0.6 ms at 6.3 M words, and the
  story overview already eager-loads `chapters.scenes` so its totals cost zero extra queries.
  Denormalising every level buys an imperceptible read and pays for it with four write paths
  that must each fix up two ancestors — a total that drifts silently is worse than a total
  that takes a millisecond. Revisit only with a real slow page, not a hypothetical one.
* **Goals are a separate spec** (above).
