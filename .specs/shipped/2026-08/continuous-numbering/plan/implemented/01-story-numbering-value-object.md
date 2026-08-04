# 01 — `StoryNumbering` value object

## Scope

`app/Support/StoryNumbering.php` — a read-only value object holding three `id → number`
maps (acts, chapters, scenes), plus its unit test. Nothing consumes it yet.

Not in scope: every call site (tasks 02–08).

## Depends on

Nothing.

## Key decisions

- `app/Support`, not `app/Services` — a lookup table, not a workflow (`PlotlineColors` is
  the neighbour).
- Two constructors: `forProject(Project)` for callers holding no tree, `fromActs(Collection)`
  for callers that already eager-loaded one. `fromActs()` must fire zero queries; the
  collection it takes is the full tree, ordered at every level.
- Accessors `act()`, `chapter()`, `scene()` each take a model or an int id.
- `forProject()` selects id/position/parent-fk only.
- See `expanded/architecture.md` → *Derive, don't store* and the ordering-key and
  tie-break rules in `00-overview.md`.

## Tests — `tests/Unit/StoryNumberingTest.php`

- Two acts × two chapters → chapters number 1,2,3,4; scenes run unbroken across both
  chapter and act boundaries.
- Gap compaction: delete an act's middle chapter → survivors read 1,2,3 while `position`
  reads 1,3.
- Act reorder renumbers: swap two acts → the chapters that were 3,4 become 1,2, with no
  write to `chapters.position`.
- Acts themselves compact: delete act 2 → the remaining acts number 1,2,3.
- Deterministic tie-break: two siblings sharing a `position` order by `id`.
- `fromActs()` and `forProject()` produce identical maps for one project.
- `fromActs()` fires zero queries.
- Unknown id throws.
