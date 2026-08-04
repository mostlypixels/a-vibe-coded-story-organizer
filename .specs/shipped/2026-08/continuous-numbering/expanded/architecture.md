# Architecture

## Derive, don't store

Numbers are **rank in the ordered tree**, computed at render time. Rejected alternative: a
stored `number` column — it needs a migration, a backfill, and recomputation on every
create/delete/move/reparent, and every path that forgets it leaves stale numbers on screen.
Derivation has no stale state to get wrong.

Cost is one small extra query on pages that don't already hold the tree; zero on the ones
that do (story overview, both exporters).

## `app/Support/StoryNumbering.php`

A read-only value object holding two `id → number` maps. `app/Support` is where
constant/reference data lives (`PlotlineColors`); this is a lookup table, not a workflow, so
it does not belong in `app/Services`.

```php
final class StoryNumbering
{
    /** Loads a minimal id/position tree. Use when the caller has no tree in hand. */
    public static function forProject(Project $project): self;

    /** Zero queries. $acts must be the full project tree, ordered at every level. */
    public static function fromActs(Collection $acts): self;

    public function act(Act|int $act): int;
    public function chapter(Chapter|int $chapter): int;
    public function scene(Scene|int $scene): int;
}
```

- Ordering key: acts by `position`, chapters by `(act.position, position)`, scenes by
  `(act.position, chapter.position, position)`. Tie-break on `id` at every level —
  `position` is not unique-constrained, and two rows sharing one must still number
  deterministically.
- Numbers are the 1-based rank, so `position` gaps vanish for free.
- Unknown id → throw. A missing number is a bug, not a blank cell.
- `forProject()` selects id/position/parent-fk only.

> [!WARNING]
> On a filtered list page the map must be built from the **whole project**, never from the
> filtered result set. Filtering by act must not renumber chapter 7 to chapter 1.

## Call sites

| Site | How it gets the map |
|---|---|
| `StoryController::index` | `fromActs($acts)` — tree already eager-loaded |
| `ChapterController::index` | `forProject($project)` |
| `SceneController::index` | `forProject($project)` |
| `ActController::index` | `forProject($project)` |
| `EpubExporter` | `fromActs($this->bookTree($project))` |
| `StaticSiteExporter` book layer | `fromActs($this->loadBookTree($project))` |
| Public scene page (`shared/scenes/show`) | `forProject($scene->chapter->act->project)` |

Controllers pass the object into the view as `$numbering`; the exporters fold the resolved
integer into the data arrays they already build (`actViewData`, `chapterViewData`, `$toc`) so
Blade never calls the lookup itself.

## Index sorting

`ChapterController::index` and `SceneController::index` currently do
`->when($sort === 'position', fn ($q) => $q->orderBy('act_id'))` (resp. `chapter_id`). That
groups by foreign key, which only coincides with story order when acts were never reordered.

Replace with a join so the `#` column sorts by the number it displays:

- chapters: `join acts` → order by `acts.position`, `acts.id`, `chapters.position`, `chapters.id`
- scenes: `join chapters` + `join acts` → same, one level deeper

> [!WARNING]
> **Table-qualify every column, not just the new `orderBy`s.** `Project::chapterQuery()` is a
> Builder rather than a `hasManyThrough` precisely because `acts` also has `name` and
> `position` (see its docblock) — so the `where('name','like', …)` search filter and the
> `orderBy($sort, …)` for `?sort=name` become ambiguous the moment the join lands.

`$direction` must survive the rewrite: `x-sortable-header` toggles it and renders a ▼, so
descending is reachable, and descending must reverse *every* key so the list reads as the
story backwards.

`withCount`/`withSum` insert `<table>.*` themselves when no columns are set yet, so the
chapters query needs no explicit select — and **must not gain one after them**, since
`select()` resets the column list and silently drops their subquery aliases.
`SceneController::index` has no aggregate, so it does need `select('scenes.*')`.

The `?sort=position` URL token stays as-is — it is a public contract in bookmarks; only what
it orders by changes. `ResolvesIndexSorting`'s allow-list is unchanged (`$sort` still never
reaches `orderBy()` unvalidated).

## EPUB

- `ChapterTitleFormat::format()` takes a plain int already — rename the parameter
  `$position` → `$number` and update the docblock. No behaviour change.
- `chapterViewData()`, `actViewData()`, `chapterNavTitle()`, `actNavTitle()` take the number
  from the map instead of `->position`. `renderToc()` inherits both through the nav-title
  helpers.
- **`sceneNavTitle()` is *not* touched** — untitled scenes keep per-chapter labels
  ("Scene 3"). See `open-questions.md` #9.
- The `position` key passed to the export templates becomes `number` — **including
  `partials/`**: `partials/act-body.blade.php` renders `<h1>Act {{ $position }}</h1>`, and
  `act.blade.php` / `act-combined.blade.php` / `chapter.blade.php` use it in their layout
  titles. Nothing in the export layer should still call a display number "position".
- File names (`act-{id}.xhtml`, `chapter-{id}.xhtml`) are id-keyed — untouched.
- **The exporter no longer filters.** `filteredTree()` drops its skip-empty pass and is
  renamed `bookTree()`; empty chapters export as heading-only pages and acts with no
  chapters keep their divider, so export numbers always equal app numbers. The
  `nothingToExport` guard moves to "the project has no scenes anywhere". See
  `open-questions.md` #1 — this reverses that question's original answer.

## Static site

- `book/` layer: it shows **titles only** today — there is no number there to make
  continuous, so this *adds* them. `$toc` entries and chapter page headings go through
  `ChapterTitleFormat::format()`, the same publication setting the EPUB obeys (it has never
  applied to this export). Act entries take the derived act number. See
  `open-questions.md` #10.
- `loadBookTree()` already publishes empty chapters as heading-only pages — which is exactly
  what the EPUB change above makes the EPUB do. Nothing to change there.
- `chapterHref()` keeps `%02d/%02d.html` from act position + per-act chapter position.
  **Do not** feed it the continuous number — every exported URL would change.
- `data/` layer (`'position' => $chapter->position`) is the archive round-trip consumed by
  `ProjectGraphImporter`/`ArchiveValidator`. Untouched.

## Story overview AJAX

`window.moveScene` (`resources/js/app.js`) reorders two adjacent scene `<section>`s in place
after a `PATCH`. Two adjacent scenes in one chapter are adjacent in the continuous sequence
too, so a move swaps exactly their two numbers — the JS swaps the two rendered number nodes,
no reload and no recompute. `SceneController::reorderResponse` keeps answering
`{position: …}`; the client does not need it for this.

## Documentation to update

- `documentation/architecture.md` — short "Continuous numbering" section: number vs
  position, where the map is built, the three do-not-touch sites (hrefs, archive JSON,
  reorder logic).
- `documentation/glossary.md` — *position* (per-parent sort key, gappy) vs *number*
  (project-wide display rank, derived).
- `documentation/epub-export.md` — chapter numbers are continuous and derived from the
  filtered tree.
- `documentation/export-format.md` — no change; say nothing, it stays true.
