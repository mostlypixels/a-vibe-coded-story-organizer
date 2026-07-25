# Task 5 — `VisualHtmlDiffer`: block diff, then word diff

## Scope

`App\Services\Diff\VisualHtmlDiffer::diff(?string $old, ?string $new): array` returning a
structured list of diff blocks (**not** HTML — rendering is task 6):

1. tokenize both sides (task 4);
2. sequence-diff the two block lists keyed by `HtmlBlock::$text`, using
   `Jfcherng\Diff\SequenceMatcher` (already in `vendor/`, BSD-3) → `eq`/`ins`/`del`/`rep`
   opcodes;
3. `eq` blocks whose `signature` differs → emit once, flagged **formatting changed**, with
   the marks added/removed listed (for the badge task 12 renders);
4. `rep` blocks → pair them in order and sequence-diff their inline token streams; runs of
   inserted/removed tokens become `inserted`/`removed` spans in the structure;
5. `ins`/`del` blocks → whole-block added / removed;
6. **complexity cap**: before step 4, if
   `count(oldTokens) * count(newTokens) > config('revisions.diff.max_word_complexity')`,
   skip the inline diff and emit the pair as one removed + one added block.

Add the `diff.max_word_complexity` key (default `2_000_000`) to `config/revisions.php`
with a comment block in the file's existing style, citing wikidiff2's
`maxWordLevelDiffComplexity`.

Also returns the **hunk count** (number of non-`eq` block opcodes), which task 8 stores as
`change_count`.

Does **not** render HTML (task 6) and is not yet reachable from `RevisionDiffer` (task 7).

## Depends on

Task 4.

## Key decisions already made

* **`jfcherng/php-sequence-matcher`, not a new dependency.** It is already installed as a
  transitive dependency of `jfcherng/php-diff` and is a plain Myers matcher over arbitrary
  string arrays — exactly the primitive needed. Binding decision 6.
* **Two levels, like wikidiff2**: block first, words inside changed blocks only. Never one
  giant word-level diff over the whole field.
* **A moved paragraph reports as delete + insert.** Known limitation, asserted in a test so
  it stays deliberate rather than becoming a surprise later.
* The cap is configuration, never a literal.

## Consult

* `expanded/diffing.md` — *2. Diff blocks, then words*, and the complexity-cap rule.
* `vendor/jfcherng/php-sequence-matcher/src/SequenceMatcher.php` — `setSequences()` /
  `getOpcodes()` are the only methods needed.
* `config/revisions.php` — the comment-block style for the new key.

## Tests

`tests/Unit/Services/VisualHtmlDifferTest.php`:

* a word changed inside a paragraph → inline inserted/removed spans, the rest of the
  paragraph unchanged;
* bold added with no wording change → that block flagged *formatting changed*, listing
  `strong` as added;
* a paragraph inserted / removed → whole-block insert / delete;
* a paragraph moved → delete + insert (the documented limitation);
* the complexity cap: with `revisions.diff.max_word_complexity` set to a tiny value via
  `config()->set()`, a large replace degrades to block level;
* hunk count matches the number of changed blocks;
* identical input → no changed blocks and a hunk count of 0.
