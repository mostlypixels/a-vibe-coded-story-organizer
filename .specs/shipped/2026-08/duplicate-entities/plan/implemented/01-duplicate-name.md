# 01 — DuplicateName support class

**Depends on:** nothing.

## Scope

`App\Support\DuplicateName` with one static method:

```php
public static function suggest(string $name, iterable $taken): string;
```

Not in scope: any caller. Tasks 02 and 04 wire it to the scene and codex candidate sets.

## Key decisions

* Strip a trailing ` (<n>)` from `$name` to get the base, then try `"<base> (2)"`, incrementing
  until free. A source name that is *already* suffixed and free still comes back re-suffixed from
  its base — that is the rule, not a bug (see the last row of the table in
  `expanded/testing.md`).
* Case-insensitive comparison via `mb_strtolower`, mirroring
  `ProjectGraphImporter::collisionFreeName()`.
* The class knows nothing about models or queries — callers hand it a list of names. This is what
  makes it one unit test instead of a database test.

## Consult

`expanded/data-model.md` → *Name suggestion*.

## Tests

`tests/Unit/DuplicateNameTest.php` — the full table in `expanded/testing.md` → *Name suggestion*,
including the case-insensitivity row and the already-suffixed source.
