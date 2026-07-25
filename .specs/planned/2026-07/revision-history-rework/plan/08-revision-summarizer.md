# Task 8 — `RevisionSummarizer`

## Scope

`App\Services\RevisionSummarizer::summarize(FieldKind $kind, ?string $previousValue, string $newValue): RevisionSummary`

returning a readonly `App\Support\RevisionSummary { ?string $summaryHtml; int $changeCount; }`:

* runs the same differ the compare screen uses (task 7's router), so the list and the
  detail can never describe different changes;
* `changeCount` = the number of changed hunks (changed block opcodes for Rich, hunks for
  Markdown/Plain);
* `summaryHtml` = the **first** changed hunk plus a little unchanged context either side,
  rendered in the renderer's `inline` mode, then truncated by **rendered text length** to
  `config('revisions.summary.max_length')` (default 200) — counting text, never markup,
  and never splitting a marker element (close it, then append an ellipsis);
* a null `$previousValue` (nothing before it) → `summaryHtml: null`, `changeCount: 0`.

Add the `summary.max_length` key to `config/revisions.php` with a comment block.

Not yet called by anything (task 9 wires it into the recorder).

## Depends on

Task 7.

## Key decisions already made

* **Bound by rendered length, not hunk count.** A find-and-replace on a character name
  produces forty hunks; forty hunks in a list row is unreadable, and "first hunk only" on
  a one-word change is uselessly terse. Length is the honest bound.
* **Marks are stripped in summary mode** — only `<ins>`/`<del>` and text survive.
* **Computed once, at write time.** A diff between two immutable revisions is a constant.
  Binding decision 8.

## Consult

* `expanded/diffing.md` — *Summaries*.
* `expanded/data-model.md` — *Who writes `summary_html` / `change_count`*.
* `config/revisions.php` — key placement and comment style.

## Tests

`tests/Unit/Services/RevisionSummarizerTest.php`:

* a one-word change produces a short summary containing `<ins>` and `<del>` and
  `changeCount = 1`;
* a 40-hunk find-and-replace produces `changeCount = 40` and a summary whose **text**
  length is ≤ `max_length` (assert with the config value, not the literal 200);
* truncation never leaves an unclosed `<ins>`/`<del>`;
* summary HTML escapes content (`<script>`, `&`) — assert on the returned string;
* a null previous value → `null` summary, `0` count;
* identical values → `0` count (the caller should not have written at all, but the
  summarizer must not invent a change).
