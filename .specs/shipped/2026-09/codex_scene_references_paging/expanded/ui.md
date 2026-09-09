# UI

## Two row components

`resources/views/components/references/scene-row.blade.php` and `entry-row.blade.php`.
Shared by each direction's card and its full page. Do not unify them — different columns.

- **scene-row** — scene name (link to `scenes.show`), chapter, act, book when
  `$showBook`, event title + date or `—`. `$showBook` is passed in, never derived per row.
- **entry-row** — entry name (link to `codex.show`), type label, cover thumbnail where the
  existing sidebar shows one.

## Cards

| View | Card | Change |
|---|---|---|
| `codex/show.blade.php` | Referenced in scenes | Drop the `x-data="{ showAll }"` block and the hardcoded 20. Cap at `config('search.cap')`, footer link. |
| `codex/edit.blade.php` | Referenced in scenes | Same cap + link; keep it full-width below the timeline. |
| `scenes/edit.blade.php` | Codex references | `x-collapsible-card` stays. Cap + link inside it. |
| `scenes/show.blade.php` | Codex references | Cap + link. The spec forgot this one. |

Footer link text: `See all :count results`, matching
`components/search/result-table.blade.php` word for word.

## Full pages

`resources/views/references/scenes.blade.php` and `references/entries.blade.php`, copied
from `search/domain.blade.php`: heading, a muted line naming the other side, a back link,
`x-table`, `x-row-range` + `$paginator->links()`, and the empty state.

Back link targets the page the writer came from — `codex.show` for the scenes page,
`scenes.edit` for the entries page. Do not use `url()->previous()`.

## Book name

`$showBook = $project->books()->count() > 1`. Resolve once in the controller, pass down.
Print `Book::displayName()`, never `->name` and never `#<id>` — an unnamed book borrows
the project name.
