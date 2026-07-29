# Word Count — standing issues

**What is still true of the shipped code.** Read this before extending the feature.

Every defect found while implementing was fixed, each with a regression test verified to fail
before its fix; the record is `resolution-log.md` and git history. What remains here are
**accepted costs**: consequences of decisions taken with eyes open. They are not bugs and not
a to-do list. Do not "fix" one without re-opening the decision it came from — each says which.

Distinct from `resolution-log.md` on purpose. That file is the *record of the work*, and its
entries stop being actionable once a task is done. Everything here is **still true of the code
on `master`**.

> [!NOTE]
> Every surface of this feature has been driven in a browser (2026-07-28/29,
> `/run-imagoldfish`): both editor kinds (initial count, debounce, reconcile on save), the
> story overview, the act/chapter/scene indexes, the project header, and a search snippet
> across a real block boundary. That last one needs setting up — see *Search snippets* below.

---

## Accepted costs

### The live counter is approximate between saves

`resources/js/word-count.js`'s `countWords()` splits on whitespace and does nothing else: no
fence stripping, no punctuation filter. So the counter climbs while you type inside a fenced
code block and settles back when the field saves.

Closing it means shipping a Markdown parser to the browser to produce a number that is about
to be replaced — every autosave `PATCH` returns the authoritative `word_count` and the counter
reconciles to it. Settled as a binding decision ("the JS counter is indicative, the server
authoritative", `plan/00-overview.md`). Documented in `documentation/word-count.md`.

### `SearchMode::ExactPhrase` cannot match across a block boundary

Task 1 made `RichText::toPlainText()` separate every block with `"\n"`, and
`AccentFolder::fold()` normalises case and accents but never whitespace. So `"One She"` does
not match `<h2>…One</h2><p>She…`.

This is the intended reading — a phrase the writer never wrote on one line is not a phrase
match — and the alternative (folding the separator to a space) silently reintroduces matches
across headings and list items. `ProjectSearchTest::test_an_exact_phrase_does_not_match_across_a_block_boundary`
asserts it both ways, so a future change to the separator has to face the decision rather than
discover it.

### `ProjectController::show()`'s `?? 0` is redundant, on purpose

`Builder::sum()` already returns `0`, not `null`, for zero matching rows (confirmed on an empty
project) — unlike `withSum`, which leaves the raw SQL `NULL` on the model attribute when a
chapter or act has no scenes.

The coalesce stays so the line reads the same "a count is never blank" rule as the two `withSum`
loops in `ActController`/`ChapterController`, instead of depending on the reader knowing
`sum()`'s own default. Removing it is correct and makes the three sites disagree in shape; that
is the trade being accepted.

---

## Not costs — decisions, recorded elsewhere

Don't re-litigate these either; they are settled, with reasoning, in
`plan/00-overview.md` → *Binding decisions* and `expanded/open-questions.md`:

* Counts come from `scenes.contents` only — never `description`, never `notes`.
* `word_count` lives on `scenes` only; ancestors are a `SUM` (benchmarked).
* A word is a whitespace-delimited token with a letter or digit; fenced code is excluded and
  inline code counted.
* The **Words** column is not sortable in this feature.
* Goals, targets and progress are out of scope — the `word-count-goals` follow-up spec.
