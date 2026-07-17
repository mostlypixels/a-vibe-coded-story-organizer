---
status: shipped
shipped: 2026-07-17
---

# Search Accent Folder

## Problem

Advanced search is accent-sensitive: searching `Melusine` does not find a character
named `Mélusine`, and vice-versa. Because the seeded content is French/Italian
(`MelusineSeeder`), accented names are the norm, so this is a routine miss for the
target users rather than an edge case.

The default database connection is **SQLite** in both dev and production
(`config/database.php`, `.env.example`). SQLite's `LIKE` and `lower()` case-fold ASCII
only — they never fold accents — and no `unaccent`/ICU collation is available. So the
fix cannot lean on a database collation; the app must fold accents itself, and it must
do so **identically on the SQL side and the PHP side**, and **portably across every
supported engine** (see the companion spec `multiple-database-engines`).

Matching currently happens in **three coordinated places**, all of which must agree —
if only one is made accent-insensitive, a row can pass the SQL filter but then be
dropped (or shown unhighlighted) in PHP:

1. **SQL predicate** — `ProjectSearch::orLikeAnyColumn` (`app/Services/ProjectSearch.php`),
   a portable `LIKE '%term%'` via `orWhereRaw` with an `ESCAPE` clause.
2. **PHP field re-check** — `ProjectSearch::fieldContainsAnyTerm` (uses `mb_stripos`,
   which is case-insensitive but accent-**sensitive**); decides which fields get labelled.
3. **Snippet offset + highlight** — `app/Support/SearchSnippet.php` (`mb_stripos` +
   a `preg_split` `/iu` alternation); decides the excerpt window and the `<mark>` spans.

## Goals

- Searching a term matches its accented and non-accented variants in **both directions**
  (`Melusine` ⇄ `Mélusine`), across every searchable field of every searchable entity.
- Works on the default SQLite install with **no migration and no stored/normalized
  column**, and stays correct on all other supported engines (verified by the CI matrix
  in `multiple-database-engines`).
- Highlighted snippets keep showing the **original accented text**, with the matched
  span correctly wrapped in `<mark class="bg-sun-200">`.

## Non-goals

- Expanding ligatures (`ß` → `ss`, `æ`, `œ`). These would break the offset invariant
  below and are a documented known limitation, not part of this work.
- Stemming, synonyms, fuzzy/edit-distance matching, or a full-text engine (FTS/FULLTEXT)
  — the portable `LIKE` design is retained on purpose.

## Rough approach

Introduce **one** stateless helper, `app/Support/AccentFolder.php` (sibling of
`SearchSnippet` / `PlotlineColors`), backed by a **single** character map of
French/Italian Latin accented characters → their lowercase base ASCII letter. Everything
folds through that one map so the SQL and PHP sides can never drift:

- `AccentFolder::fold(string): string` — PHP fold, `strtolower(strtr($value, self::MAP))`,
  chosen to mirror SQLite `lower(replace(...))` exactly.
- `AccentFolder::sqlColumnExpression(string $column): string` — the portable SQL that
  folds a column, `lower(` + a nested `replace(...)` chain built from the **same** map +
  `)`. Uses only `lower`/`replace`/`like` (all ANSI), so no driver-specific SQL.

**Load-bearing invariant:** the map is strictly **1 character → 1 character**, so a
folded string keeps the exact character offsets of the original. That is what lets
`SearchSnippet` locate a match on the *folded* text and then slice/highlight the
*original* accented text at the same offsets.

Wire the helper into all three places: fold the bound pattern and the column in the SQL
predicate (fold **before** `escapeLikeWildcards` — folding never produces `%`/`_`/`\`,
so wildcard escaping is unaffected; keep the existing `ESCAPE` clause); fold both sides
of the PHP re-check; and in `SearchSnippet`, compute offsets and the highlight
alternation from folded text while emitting the original text.

**Bonus fix:** because folding lowercases both sides, case-insensitivity becomes uniform
across engines — this incidentally fixes today's case-**sensitive** `LIKE` behavior on
PostgreSQL (see `multiple-database-engines`).

No changes to `SearchController`, `SearchRequest`, `SearchMode`, the result DTOs, routes,
or the Blade view. No stored column: a substring `LIKE '%…%'` can't use an index on any
engine anyway (leading wildcard) and per-project datasets are small, so a shadow column
would add a migration, a backfill, and a model-hook sync risk for no benefit.

## Testing

- `tests/Unit/AccentFolderTest.php` (new): `fold('Mélusine') === 'melusine'`, case
  folding, unmapped characters pass through unchanged, and the `sqlColumnExpression`
  shape.
- `tests/Feature/ProjectSearchTest.php` (existing style, service level): `Melusine`
  matches a `Mélusine` name and the reverse; also on a non-`name` field (e.g.
  `description`); and the existing wildcard-escape (`50%`, `a_b`) and fixed 6-query-count
  tests still pass.
- `tests/Feature/SearchTest.php` (existing style, HTTP + highlight): the end-to-end query
  surfaces the accented row and `<mark class="bg-sun-200">` wraps the **original accented**
  text.

## Documentation

Add a note to the search section of `documentation/architecture.md` (the folding helper,
the three-layer coordination, and the 1:1-map / ligature limitation) and a dated entry to
`CHANGELOG.md`.

## Related

- `multiple-database-engines` — the portability of `AccentFolder::sqlColumnExpression`
  across engines is *verified* by that spec's CI test matrix; this is the first feature it
  protects.
