# Word Count — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

Settled in the `plan-tasks` grill, 2026-07-27. Reasoning in `expanded/open-questions.md`.

* **Totals count `scenes.contents` only**; the live counter appears on all 14
  `x-autosave-field` fields. Confirmed explicitly — a counter on `notes` or `rights` feeds
  no total and is a local convenience.
* **A word is a whitespace-delimited token containing a letter or a digit.** `one—two` is
  one word (matches Word/Scrivener, which is what a writer compares against); `* * *` is
  zero.
* **Fenced code blocks are excluded from the stored count; inline code is counted.** A fence
  is marked as not-prose; inline code sits inside a sentence.
* **The JS counter is indicative, the server authoritative**, reconciled by `word_count` in
  the autosave response. This changed the response key from a nice-to-have into a
  requirement, and released the JS from matching the PHP fixture table.
* **`word_count` on scenes only, ancestors summed.** Benchmarked before deciding: 150 / 960
  / 4,320 scenes, widest gap 0.6 ms at 6.3 M words, and the story overview already
  eager-loads scenes. Denormalising every level would have bought an imperceptible read and
  paid four write paths that must each fix up two ancestors.
* **Goals/targets/progress deferred** to a `word-count-goals` follow-up: counting needs one
  integer per scene, progress needs a per-day time series.

## Deviations from the spec/plan

* **Task 1's block-tag set is the allow-list ∪ what `Str::markdown()` emits, not the task's
  literal list.** Added `h5`/`h6` (absent from `RichTextFields::ALLOWED_TAGS`, which stops at
  `h4`, but commonmark emits all six and `Scene.contents` renders through it) and `td`/`th`
  (the task named only `tr`, yet `<td>a</td><td>b</td>` is exactly the gluing bug). Dropped
  `figcaption`: neither the sanitizer nor commonmark can produce it.
* **Tests went into the existing `tests/Unit/RichTextTest.php`**, not the
  `tests/Unit/Support/` path the task named — the class already existed one directory up, and
  a second `RichTextTest` would have been a duplicate rather than a move.

## Issues → resolutions

* **`RichText::toPlainText()` glued words across block boundaries** (found while grilling
  the design, before any code). It converted only `</p>` and `<br>` to newlines, so
  `<h1>Chapter One</h1><p>She waited.</p>` became `Chapter OneShe waited.` — 3 words instead
  of 4, and lists collapsed entirely. Scene contents render through `Str::markdown()`, so
  any chapter with a heading or a list would have undercounted.

  It is also a **live bug in shipped search**: `ProjectSearch` uses the same helper, so
  snippets render the glued text and `SearchMode::ExactPhrase` cannot match across a
  boundary. Matching itself is unaffected (`str_contains` is substring). Resolved by fixing
  the shared helper in task 1, ahead of anything that counts, rather than giving
  `WordCounter` a private extractor — two helpers disagreeing about the text of a document
  is worse than one change with a blast radius.

  **`SearchMode::ExactPhrase` still cannot span a block boundary, and that is intended.**
  The separator is `"\n"` and `AccentFolder::fold()` normalises case/accents only, never
  whitespace — so "One She" does not match `<h2>…One</h2><p>She…`. A phrase the writer never
  wrote on one line is not a phrase match. Asserted both ways in
  `ProjectSearchTest::test_an_exact_phrase_does_not_match_across_a_block_boundary` so the
  next change to the separator has to face the decision.

  No existing assertion pinned the glued output, so the task's expected "update the tests
  that encode the bug" turned out to be nothing to update — the whole suite (1133) was green
  on the fix alone.
