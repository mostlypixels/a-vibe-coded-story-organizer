# Data model

No new migrations, columns, or models. This feature is read-only over existing tables:
`acts`, `chapters`, `scenes`, `events`, `plotlines`, `codex_entries` (all already scoped to
`project_id`, directly or via a parent chain — see `architecture.md` → *Query shape*).

## Indexing

Deliberately **no new indexes**. A leading-wildcard `LIKE '%term%'` cannot use a plain B-tree
index on any of the four supported drivers (`sqlite`, `mysql`, `pgsql`, `sqlsrv` — see
`architecture.md` → *Library choice*) — only a trailing-wildcard `LIKE 'term%'` benefits, which
doesn't match this feature's "contains" semantics. Adding indexes here would be dead weight
(`CLAUDE.md` § Database: "add indexes deliberately based on query patterns"). If result-set
size ever makes `LIKE` scans slow, that's the trigger to revisit — likely via Scout/FTS
(`architecture.md`), not a plain index.

## New enum

`app/Enums/SearchMode.php` (backed string enum, no table): `AllTerms = 'all'`,
`AnyTerm = 'any'`, `ExactPhrase = 'exact'`. Purely a request-parameter/UI value — nothing is
persisted.

## Invariants touched

None. This feature does not create, update, delete, or reorder any of the entities it
searches — it has no interaction with the `position` invariant, the main-plotline invariant,
or any lifecycle hook.
