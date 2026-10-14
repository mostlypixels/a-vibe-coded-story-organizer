# 02 — Parse and sanitize

## Scope

- `App\Support\EntityLinks` (pure, no database):

  ```php
  /** @return list<array{type: LinkTargetType, id: int}> */
  public static function find(string $text): array;
  /** $replace gets (type, id) and returns a new href, or null to unwrap the link to its text. */
  public static function rewrite(string $text, Closure $replace): string;
  public static function strip(string $text): string; // unwraps every entity link
  ```

  Each method works on HTML and on Markdown (`[text](entity:scene/42)`).
- `App\Support\Purifier\EntityUriScheme`: accepts only `<LinkTargetType value>/<digits>`. No host, query or fragment.
- Register it in `HtmlSanitizer` for both profiles. Add `entity` to `RichTextFields::ALLOWED_SCHEMES`.
- `ContentSanitizer::assertHtmlAllowed()` accepts the scheme.
- Tiptap link `protocols` in `resources/js/wysiwyg.js` gets `entity`. The URL dialog accepts it.
- Update `documentation/features/rich-text.md` → Allow-list.
- Deferred: rendering → 03; editor picker → 08.

## Depends on

- 01.

## Key decisions already made

- The `entity:` scheme in `href` is the only link form. See `expanded/overview.md` → Decision.
- `rewrite()` with a null result keeps the link text. Import (05) and exports (06) rely on that.

## Docs

- `expanded/architecture.md` → Sanitizer.

## Tests

- `EntityLinks` unit tests for HTML and Markdown: several links, links inside marks, `entity:` in plain text that is not a link, web links untouched.
- Sanitizer, both profiles: keeps `entity:scene/42` and `entity:note/7`. Removes `entity:unknown/1`, `entity:scene/abc`, `entity:scene/1?x`, `entity://host/1`.
- Import sanitizer accepts a fragment with an entity link.
- Vitest: Tiptap keeps an `entity:` href through an HTML round trip and a Markdown round trip.
