# Architecture

## The fixture file is the deliverable

`resources/js/punctuation-fixtures.json` — a flat list of `{input, expected, note?}`.

```json
[{ "input": "the '90s", "expected": "the \u201990s", "note": "elision, not an open quote" }]
```

Placed under `resources/js/` so Vitest imports it directly (no build step, no path helper), and
PHP reads it with `base_path('resources/js/punctuation-fixtures.json')`. It sits beside
`wysiwyg.js`, the implementation hardest to change. Alternative locations in
`open-questions.md`.

## New: `app/Support/CanonicalPunctuation.php`

Model it on `App\Support\AccentFolder` — a single-source-of-truth Support class with a hand
map and a docblock saying why hand-rolled. Not a Service: no dependencies, no I/O, pure text.

```php
public static function inPlainText(string $text): string;   // one text run, no markup
public static function inMarkdown(string $markdown): string; // skips fences + code spans
public static function inHtml(string $html): string;         // walks text nodes, skips pre/code
```

- `inPlainText` holds the whole convention. The other two are shredders that route runs through it.
- `inMarkdown` splits on fenced blocks (``` / ~~~), indented code blocks, and backtick code
  spans; normalizes only the prose between. Do **not** round-trip through CommonMark.
- `inHtml` reuses the `DOMDocument` approach already in `App\Support\RichText`; walk
  `XPATH //text()[not(ancestor::pre) and not(ancestor::code)]`. Take the encoding/entity
  handling from `RichText`, do not re-derive it.

Quote direction rule (this is what `'90s` turns on): a `'` or `"` opens only when the preceding
character is start-of-run or whitespace **and** the following character is not a digit. Digit
after means elision, so it closes. This is a simplification of CommonMark's left/right-flanking
delimiter run; the fixtures are what proves it close enough.

## Where it hooks in

`app/Services/Import/ProjectGraphImporter.php`, two existing methods, both already the choke
point:

| Method | line | change |
|---|---|---|
| `readHtmlField()` | ~708 | after `assertHtmlAllowed()`, `return CanonicalPunctuation::inHtml($html)` |
| `readMarkdownField()` | ~720 | after `assertMarkdownAllowed()`, `return CanonicalPunctuation::inMarkdown($markdown)` |

Order matters: validate first, then normalize. Normalizing first would let a rejected import
be judged on text the archive never contained.

Titles, names and other short JSON string fields are **not** normalized. They go through
`readJson()`, not these two methods, and are not prose.

## The `ContentSanitizer` principle

`ContentSanitizer`'s docblock says imports fail rather than change bulk content silently. This
feature changes bulk content silently. Add a paragraph to that docblock naming the exception
and its bound: *the allow-list still fails; punctuation is normalized after the allow-list has
passed, and only punctuation*. The normalization does not live in `ContentSanitizer` — it is a
different concern with a different failure mode — but the exception must be written where the
rule is.

## Removals

- `EpubExporter::converter()` — drop the `SmartPunctExtension` line and its import
  (`app/Services/EpubExporter.php:24`, `:911`). Keep Strikethrough/StrikethroughS/TaskList.
- `EpubExporter.php:51` and `:889` docblocks, and
  `database/migrations/2026_07_13_000000_add_frontmatter_to_projects_table.php:17` — all name
  the SmartPunct converter. Update, do not leave dangling.
- `documentation/export-import/epub.md:27`, `documentation/features/rich-text.md:90,127,136` —
  rewrite; line 136 states the exact invariant this feature deletes.
- `resources/js/wysiwyg.js:41,72` comments — they justify the editor's rules by agreement with
  the exporter's pass. Rewrite to point at the fixture file instead.

## JS side

Fix `openSingleQuote` so it does not fire before a digit (`'90s`). Everything else in
`TYPOGRAPHY_RULES` stays. `EmDashFromThreeHyphens` is unaffected.

## Existing data

Pre-V1: no backfill command. `php artisan migrate:fresh --seed` after merge. If the seed
archives themselves carry ASCII punctuation, they now import canonical — that is the change
landing, not a problem.
