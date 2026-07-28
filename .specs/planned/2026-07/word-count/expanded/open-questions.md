# Word Count — open questions

**All resolved in the `plan-tasks` grill, 2026-07-27.** Kept because the reasoning is the
part worth not re-deriving; the answers are binding on the plan.

## Q1 — What separates two words? ✅ Whitespace only

`preg_split('/\s+/u')`. `one—two` (em-dash, no spaces) is **one** word, `jack-o'-lantern`
is one, `1,234` is one.

**Why:** it matches Word, Scrivener and Google Docs, so the number agrees with whatever the
writer compares it against — for a submission length, agreeing with the recipient's counter
matters more than being philosophically right. Splitting on punctuation has no natural
stopping point (`Mr.`, `don't`, `3.5`, `...`).

## Q2 — Do code fences count? ✅ No — excluded from the stored count

Fenced blocks are stripped **before** counting. Inline code (`` `like this` ``) **is**
counted.

**Why the split, which is deliberate and not an oversight:** a fenced block is text the
writer marked as *not prose*; inline code sits inside a sentence and reads as part of it, so
removing it mid-sentence makes the number feel arbitrary.

## Q2b — What is a "non-word"? ✅ No letter and no digit

After rendering, drop any token matching neither `\p{L}` nor `\p{N}`.

* Dropped: `* * *` and `---` scene dividers, a standalone `—`, stray `"`, `...`, table `|`.
* Kept: `1,234`, `3.5`, `don't`, `état`.

## Q3 — Does the autosave response carry the count? ✅ Yes — load-bearing

`FieldAutosaveController::update()`'s JSON gains `word_count`.

**Why it stopped being optional:** the JS counter is explicitly *indicative* (Q7), so the
live number and the stored number legitimately differ — a scene with a fenced block reads
high while typing. The response is how the indicative number **snaps to the true one on
every save**. Without it, the two just disagree forever and the writer learns not to trust
either.

## Q4 — Accessor on Chapter/Act? ✅ No

Controllers `withSum` or eager-load; views render what they are given. An accessor hides
whether a query fires — the exact ambiguity that produces N+1 on a list page.

## Q5 — Which fields get the live counter? ✅ All 14

Every field rendered through `x-autosave-field`: each `description`, scene
`contents`/`notes`, project `dedication`/`acknowledgements`/`preface`/`postface`/`rights`.

Counter **bottom-right**, autosave badge **bottom-left**, so neither shifts as the other's
text changes.

> [!NOTE]
> Only `scenes.contents` is ever *stored* or summed. A counter on `notes` or `rights` is a
> local convenience and feeds no total. `rights` is `FieldKind::Plain` and a counter there
> is admittedly odd; it was accepted rather than special-cased, because "every prose field
> has one" is a rule a reader can predict.

## Q6 — Sortable "Words" column? ✅ Not in this spec

`ResolvesIndexSorting` takes an allow-list of **real columns** and a `SUM` alias is not one.
Ship unsorted; add sorting as its own change if someone asks.

## Q7 — Live vs stored: which text? ✅ Dissolved, then reframed

The original worry — that markdown mode would feed raw markup to `getText()` — is **wrong**.
`resources/js/wysiwyg.js` uses `@tiptap/markdown` over a real ProseMirror document and only
serializes markdown on save (`getMarkdown()`), so `getText()` already returns rendered text.

What replaces it is a **deliberate** difference: the JS count is *indicative*. It does not
strip fences and does not apply the non-word rule. The server is authoritative, and Q3 is
how they reconcile.

## Q8 — Which field feeds the totals? ✅ `scenes.contents`, only

Not `description`, not `notes`, not act/chapter/project descriptions. A writer asking "how
long is my book" means the prose.

## Q9 — `RichText::toPlainText()` reuse ✅ Fix it first (new, found during the grill)

It converts only `</p>` and `<br>` to newlines, so every other block boundary **glues words
together**. Measured:

| Input | Output | Counted |
|---|---|---|
| `<h1>Chapter One</h1><p>She waited.</p>` | `Chapter OneShe waited.` | 3, should be 4 |
| `<ul><li>alpha</li><li>beta</li></ul>` | `alphabeta` | 1, should be 2 |

Scene contents render through `Str::markdown()`, so any heading or list — most chapters —
would undercount.

**This is a live bug in shipped code, not only a counting problem.** `ProjectSearch` uses
the same helper: matching is `str_contains` so terms still match (substring), but **snippets
render the glued text** and `SearchMode::ExactPhrase` cannot match across a glued boundary.
Fixed in task 1, ahead of anything that counts.
