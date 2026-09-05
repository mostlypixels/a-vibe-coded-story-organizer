# 03 — Markdown and HTML shredders

**Depends on:** 02.

## Scope

Add to `CanonicalPunctuation`:

```php
public static function inMarkdown(string $markdown): string;
public static function inHtml(string $html): string;
```

Both split their input into prose runs and code runs, send prose through `inPlainText()`, and
return code byte-identical.

- `inMarkdown` — skip fenced blocks (``` and ~~~), indented code blocks, and backtick code
  spans.
- `inHtml` — reuse the `DOMDocument` approach already in `App\Support\RichText`. Walk
  `//text()[not(ancestor::pre) and not(ancestor::code)]`. Take its encoding and entity handling
  as-is; do not re-derive it.

Unit tests for both, covering the skip cases.

**Not in scope:** wiring into any importer (task 04).

## Key decisions

- **Do not round-trip Markdown through CommonMark.** There is no Markdown writer, and the
  stored value must stay the author's source.
- HTML attribute values are not text nodes, so the XPath walk already leaves them alone —
  assert it anyway.
- Idempotent, same as task 02.

## Consult

`expanded/architecture.md`, `app/Support/RichText.php` for the DOM pattern.
