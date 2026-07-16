# Task 01 — SearchMode enum + SearchSnippet helper

## Scope

Build the two small, dependency-free building blocks the rest of the feature is built on:

* `app/Enums/SearchMode.php` — backed string enum: `AllTerms = 'all'`, `AnyTerm = 'any'`,
  `ExactPhrase = 'exact'`. Add a `label()` method (`'Match all words'` / `'Match any word'` /
  `'Exact phrase'` or similar), following the existing `SceneStatus`/`CodexEntryType` enum
  pattern (`app/Enums/CodexEntryType.php`) — backed string enum, `label()` returning a
  human-readable string via `match ($this) { ... }`.
* `app/Support/SearchSnippet.php` — a stateless helper (same home as `PlotlineColors`,
  `app/Support/`) that, given a field's full raw text and the term(s) to highlight, returns
  **pre-escaped HTML** (safe for `{!! !!}`) containing:
  * ~120 characters of context centered on the first case-insensitive match.
  * The matched term(s) wrapped in `<mark class="bg-sun-200">...</mark>` (`#ffe494`; the `sun`
    palette's `200` shade was filled in during the pre-ship review — see `00-overview.md`).
  * Everything else HTML-escaped (`e()`/`htmlspecialchars`) — the method must be safe to call
    on arbitrary user-supplied `Scene::contents`/`notes` text, including text containing
    `<script>`-like substrings.

This task does **not** build the search queries themselves (task 02) or decide how AND/OR
term-splitting works at the query level — `SearchSnippet` just needs to accept "the term(s)
to highlight" as an input (a string or array of strings) and stays agnostic to which mode
produced them.

## Depends on

Nothing — first task.

## Key decisions already made (binding, see `00-overview.md`)

* Snippet length ~120 characters, centered on the first match.
* Highlight style: `<mark>` with `bg-sun-200` (`#ffe494`, filled into the Tailwind palette).
* Escape-then-highlight, not the reverse — never let raw user content reach the view
  unescaped.

## Docs to consult

* `expanded/architecture.md` → *Library choice* (why this is a hand-written helper, not a
  package) and *Query shape* note about raw stored values.
* `expanded/ui.md` → *Highlighting style* and *Result row*.
* `expanded/open-questions.md` items 5 and 7 (color/length — already resolved, but shows the
  reasoning).

## Tests

`tests/Unit/SearchSnippetTest.php` (new — plain PHPUnit, no DB needed, matches the project's
existing `tests/Unit/` style for pure-logic helpers):

* A term in the middle of a long string produces a snippet truncated to ~120 chars around it,
  not the full string.
* The matched term is wrapped in `<mark>`.
* Matching is case-insensitive (searching "Dragon" highlights "dragon" in the source text).
* HTML-special characters in the surrounding text (e.g. a literal `<` or `&` elsewhere in the
  string, unrelated to the match) are escaped in the output.
* A string containing `<script>alert(1)</script>` around/adjacent to the match is not
  reproduced as executable markup in the output — only escaped text plus the one intentional
  `<mark>` wrapper.
* Multiple terms (array input) are all highlighted when each appears in the text.

Also add a small enum test (or fold into the same file) asserting `SearchMode::cases()` has
exactly the three expected values and each has a non-empty `label()`.
