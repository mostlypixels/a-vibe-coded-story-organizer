# 02 — Text matcher

## Scope

- `app/Services/SceneTextMatcher.php`: pure, with no database and no container dependencies.
- Deferred to 03: snippets and context. Deferred to 04: the replacement.

## Depends on

- None.

## Key decisions already made

```php
/** @return list<array{offset: int, length: int}> byte offsets, left to right, no overlaps */
public function matches(string $text, string $find, bool $matchCase, bool $wholeWord): array;
```

- `preg_quote()` and the `u` flag. Add `i` only when `matchCase` is false.
- Whole-word edges are `(?<![\p{L}\p{N}_])` and `(?![\p{L}\p{N}_])`, not `\b`.
- It is always accent-sensitive. Do not use `AccentFolder`.
- An empty `find`, or invalid UTF-8, gives `[]`.

## Docs

- `expanded/architecture.md` → Services.

## Tests

- `tests/Unit/SceneTextMatcherTest.php`, see `expanded/testing.md` → Unit.
