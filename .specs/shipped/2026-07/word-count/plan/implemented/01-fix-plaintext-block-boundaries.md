# Task 1 — Fix `RichText::toPlainText()` gluing words across block boundaries

## Scope

`App\Support\RichText::toPlainText()` converts only `</p>` and `<br>` to newlines. Every
other block boundary is stripped with no separator, so the last word of one block and the
first of the next become **one word**. Measured on `master`:

| Input | Today | Should be |
|---|---|---|
| `<h1>Chapter One</h1><p>She waited.</p>` | `Chapter OneShe waited.` | `Chapter One\nShe waited.` |
| `<ul><li>alpha</li><li>beta</li></ul>` | `alphabeta` | `alpha\nbeta` |
| `<blockquote>quoted words</blockquote><p>after</p>` | `quoted wordsafter` | `quoted words\nafter` |

Extend the boundary regex to every block-level closing tag the sanitizer allows —
`</h1>`…`</h6>`, `</li>`, `</blockquote>`, `</div>`, `</tr>`, `</pre>`, `</figcaption>`,
plus closing `</ul>`/`</ol>`/`</table>`. Check the allow-list in `App\Services\HtmlSanitizer`
rather than guessing the tag set.

Everything else about the method is unchanged: entity decoding, the 3+-newline collapse, the
trailing-space trim, `''` for null/empty.

## Why this is task 1

Two reasons, and the second is the load-bearing one:

1. Scene contents render through `Str::markdown()` before counting, so a heading or a list —
   most chapters — would undercount without this.
2. **It is a live bug in shipped code.** `ProjectSearch::plainFieldValues()` uses this helper
   for both matching and snippets.

## This is a behaviour change to search — handle it deliberately

* **Matching still works**: `ProjectSearch::entityMatches()` uses `str_contains`, i.e.
  substring, so `One` already matches `OneShe`. Inserting a newline cannot lose a real
  match — only junk like searching `oneshe`.
* **Snippets change visibly**, and for the better: results currently render
  `Chapter OneShe waited`.
* **`SearchMode::ExactPhrase`** is the mode to check explicitly. A phrase spanning a glued
  boundary cannot match today. `AccentFolder::fold()` is lowercase + accent mapping only and
  does **not** normalise whitespace, so a phrase still will not match across the new `\n`
  either — confirm that is the intent and note it in `resolution-log.md` rather than
  silently leaving it.

Expect to update existing assertions in `tests/Unit/…` / `tests/Feature/ProjectSearchTest`
that pin the glued output. **Updating such an assertion is fixing it, not weakening it** —
say so in `resolution-log.md`.

## Depends on

Nothing. First task.

## Key decisions already made

* Separator is `"\n"`, matching what `</p>` already produces — not a space.
* Fix the shared helper rather than giving `WordCounter` a private extractor: two helpers
  disagreeing about the text of a document is worse than one change with a blast radius.

## Consult

`../expanded/open-questions.md` Q9, `../expanded/architecture.md`.

## Tests

* `tests/Unit/Support/RichTextTest.php` (create if absent): each row of the table above, plus
  nesting (`<blockquote><p>a</p></blockquote><p>b</p>`) and the unchanged cases —
  `</p>`/`<br>`, entity decoding, blank-line collapsing, null → `''`.
* Search: a snippet spanning a heading→paragraph boundary renders with the words separated.
* Search: an `ExactPhrase` query behaves as decided above, asserted either way.
