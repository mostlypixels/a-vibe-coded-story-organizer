# Task 9 — Totals on the index pages and the project header

## Scope

A **Words** column, right-aligned, on:

| View | Shows | Source |
|---|---|---|
| `scenes/index.blade.php` | per scene | `scenes.word_count` — already on the row |
| `chapters/index.blade.php` | per chapter | `withSum` in `ChapterController::index()` |
| `acts/index.blade.php` | per act | `withSum` in `ActController::index()` |
| project header / `projects` view | project total | `withSum` in `ProjectController` |

```php
$chapters = $act->chapters()->withSum('scenes as word_count', 'word_count')->get();
$acts = $project->acts()->withSum('chapters.scenes as word_count', 'word_count')->get();
```

Header via `x-table-heading`, cells via `x-word-count variant="inline"`. Coalesce `NULL`
(no rows) to `0` **in the controller**, not the view.

## Depends on

Tasks 4 and 6. Independent of 8 — either can land first.

## Key decisions already made

* **`withSum` in the controller**, per `CLAUDE.md` keeping index querying in `index()`. Not
  a query scope, not an accessor.
* **Not sortable.** `ResolvesIndexSorting` takes an allow-list of real columns and a `SUM`
  alias is not one; wiring an aggregate into that concern is a bigger change than this
  feature asked for. Ship the column unsorted.
* A chapter with no scenes shows `0 words`, not blank.

## Consult

`../expanded/ui.md` (the table of screens), `../expanded/architecture.md` (reading totals).

## Tests

Extend `SceneTest` / `ChapterTest` / `ActTest` / `ProjectTest`, or gather in `WordCountTest`:

* Each index page renders the right total per row — `assertSee` the number in the HTML.
* **No N+1**: the chapter index issues one grouped query regardless of how many chapters
  exist. Assert with a query count over a fixture of, say, 10 chapters — a naive
  implementation issues 10.
* A chapter with no scenes renders `0 words` (the `NULL` coalesce).
* Authorization is untouched: a non-owner still gets 403 on each index (these are existing
  assertions — confirm they still pass rather than adding new ones).
