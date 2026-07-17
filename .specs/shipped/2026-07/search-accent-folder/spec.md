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

Matching happens in **three coordinated places**, all of which must agree — if only one
is made accent-insensitive, an entity can be kept but then shown with no field label or an
unhighlighted snippet (or matched in one place and not another):

1. **Entity gate** — `ProjectSearch` decides which entities match the query/mode.
2. **Per-field label check** — `ProjectSearch::fieldContainsAnyTerm`; decides which fields
   get labelled ("Matched in").
3. **Snippet offset + highlight** — `app/Support/SearchSnippet.php`; decides the excerpt
   window and the `<mark>` spans.

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
- Stemming, synonyms, fuzzy/edit-distance matching, or a full-text engine (FTS/FULLTEXT).

## Rough approach

Introduce **one** stateless helper, `app/Support/AccentFolder.php` (sibling of
`SearchSnippet` / `PlotlineColors`), backed by a **single** character map of
French/Italian Latin accented characters → their lowercase base ASCII letter, with one
method:

- `AccentFolder::fold(string): string` — `strtolower(strtr($value, self::MAP))`, yielding a
  plain lowercase accent-free string for a literal `str_contains` match.

**Load-bearing invariant:** the map is strictly **1 character → 1 character**, so a
folded string keeps the exact character offsets of the original. That is what lets
`SearchSnippet` locate a match on the *folded* text and then slice/highlight the
*original* accented text at the same offsets.

**Matching runs in PHP, not SQL.** `ProjectSearch` fetches each entity's project-scoped
rows (still six queries) and matches them in PHP against folded terms — the entity gate
(`entityMatches`), the per-field label check (`fieldContainsAnyTerm`), and `SearchSnippet`
all fold through `AccentFolder::fold`, so they cannot drift.

> **Why PHP, not a folding SQL expression** (learned during implementation): the first cut
> folded the *column* in SQL via `lower(replace(replace(…)))` — one `replace` per accent.
> That passed locally but **overflowed SQLite's parser stack on CI** (`parser stack
> overflow`), because the ~50-deep nested `REPLACE` exceeds some SQLite builds' parser
> limit. Folding in PHP against fetched rows removes the SQL-depth landmine entirely and is
> identical on every driver. It is also no more expensive at this app's scale: a
> leading-wildcard `LIKE '%…%'` already forces a full scan, so materialising the
> project's rows costs the same order of magnitude.

**Bonus fix:** because folding lowercases both sides, case-insensitivity becomes uniform
across engines — this incidentally fixes today's case-**sensitive** `LIKE` behavior on
PostgreSQL (see `multiple-database-engines`).

No changes to `SearchController`, `SearchRequest`, `SearchMode`, the result DTOs, routes,
or the Blade view. No stored column: per-project datasets are small and a full scan is
already the cost of a substring search, so a shadow column would add a migration, a
backfill, and a model-hook sync risk for no benefit.

## Testing

- `tests/Unit/AccentFolderTest.php` (new): `fold('Mélusine') === 'melusine'`, case
  folding, coverage of the accent set, unmapped characters pass through unchanged, and the
  1:1 length invariant.
- `tests/Feature/ProjectSearchTest.php` (existing style, service level): `Melusine`
  matches a `Mélusine` name and the reverse; also on a non-`name` field (e.g.
  `contents`); and the existing literal-`%`/`_` (`50%`, `a_b`) and fixed 6-query-count
  tests still pass.
- `tests/Feature/SearchTest.php` (existing style, HTTP + highlight): the end-to-end query
  surfaces the accented row and `<mark class="bg-sun-200">` wraps the **original accented**
  text.

## Documentation

Add a note to the search section of `documentation/architecture.md` (the folding helper,
the three-layer coordination, and the 1:1-map / ligature limitation) and a dated entry to
`CHANGELOG.md`.

## Related

- `multiple-database-engines` — because matching now runs in PHP there is no engine-specific
  SQL to break, but that spec's CI test matrix is what *verifies* search behaves identically
  on every driver; this is the first feature it protects.
