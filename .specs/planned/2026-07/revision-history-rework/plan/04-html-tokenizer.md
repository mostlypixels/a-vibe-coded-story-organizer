# Task 4 — `HtmlTokenizer`: purified HTML → blocks and inline tokens

## Scope

Two new classes, no wiring — nothing calls them yet.

`App\Support\HtmlBlock` (readonly value object):

```php
public string $tag;          // p, h1..h4, li, blockquote, pre, tr, hr, img
public array  $attributes;   // only the ones the renderer may re-emit (task 6)
public string $text;         // normalised concatenated text, whitespace collapsed
public array  $tokens;       // list<InlineToken>: word + mark stack in force
public string $signature;    // stable fingerprint of the mark-stack transitions
```

`App\Services\Diff\HtmlTokenizer`:

* `tokenize(?string $html): array` → `list<HtmlBlock>`;
* parses with `DOMDocument::loadHTML` (libxml errors captured and discarded, exactly as
  `App\Support\RichText::toXhtmlFragment()` already does) and walks the body's children;
* one block per block-level element; `li` yields one block per item (carrying its list
  type and nesting depth), `tr` one per row (cells joined with a separator);
* inline marks in force at each word — `strong`, `em`, `u`, `s`, `code`, `a[href]` — are
  captured on the token, and the block's `signature` is derived from their transitions;
* tokens are comparable as single strings (`"<marks>\u{1F}<word>"`) so a plain sequence
  matcher sees a formatting change as a real change with no special-casing;
* whitespace is normalised (runs collapse to one space) so re-serialised-but-identical
  HTML tokenises identically.

## Depends on

Nothing (pure logic; can be built in parallel with phase A).

## Key decisions already made

* **Input is always already purified** (`HtmlSanitizer` ran on write). The tokenizer's job
  is not safety; safety is the renderer's (task 6) and the sanitizer's.
* Vocabulary is bounded by `App\Support\RichTextFields::ALLOWED_TAGS` — the tokenizer
  handles exactly that set and ignores anything else rather than inventing behaviour.
* Never used for `FieldKind::Markdown` / `Plain`. `Scene.contents` must not reach it
  (architectural Markdown/Rich split).

## Consult

* `expanded/diffing.md` — *1. Tokenise*.
* `app/Support/RichText.php` — the existing `DOMDocument` round-trip and error handling.
* `app/Support/RichTextFields.php` — `ALLOWED_TAGS` / `ALLOWED_ATTRIBUTES`, the exact
  vocabulary to cover (including TipTap's task-list markup and `data-callout-type`).

## Tests

`tests/Unit/Services/HtmlTokenizerTest.php`:

* each allowed block element produces the expected block (paragraph, the four headings,
  list item with type/depth, blockquote incl. `data-callout-type`, `pre`, table row,
  `hr`, `img`);
* whitespace normalisation: `<p>a  b</p>` and `<p>a b</p>` tokenise identically;
* nested marks (`<strong><em>x</em></strong>`) produce the expected mark stack, and the
  same words with and without a mark produce **different** signatures but the **same**
  `text`;
* an empty / null value yields an empty list;
* malformed HTML (an unclosed `<p>`) is repaired rather than throwing;
* a tag outside the allow-list is ignored, not emitted as a block.
