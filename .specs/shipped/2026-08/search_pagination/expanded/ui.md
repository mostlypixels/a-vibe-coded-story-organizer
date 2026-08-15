# UI

Reuse the existing search components — the per-domain page renders the same table chrome, just
paginated and for one column.

## `x-search.result-table` — add the cap and the "See all" link

Current props: `title`, `rows`, `editRoute`, `nameField`. Add:

* `:cap` — max rows to show. Render `$rows->take($cap)` instead of all `$rows`.
* `:domain` — the `SearchDomain` case, so the component can build the "See all" link and (via
  the enum) drop the now-redundant `editRoute`/`nameField` props at the call site.
* `q` / `mode` — needed to build the link. Pass through, or read from the request in the view.

Below the table, when `$rows->count() > $cap`:

```blade
<a href="{{ route('projects.search.domain', ['project' => $project, 'domain' => $domain->value, 'q' => $query, 'mode' => $mode->value]) }}">
    {{ __('See all :count results', ['count' => $rows->count()]) }}
</a>
```

Style it as a standard text link (`text-link`), left-aligned under the table. The row markup
(`x-search.result-row`) is unchanged.

## `search.index` — pass cap + domain

The eight `<x-search.result-table>` calls each gain `:cap` and `:domain="\App\Enums\SearchDomain::Scenes"`
(etc.). Since `SearchDomain` now owns `editRoute()`/`nameField()`, the call sites can shrink to
`:domain` + `:rows` + `:title` and let the component read the route/name-field off the enum —
one place instead of eight literals. `q` and `mode` are already in scope in this view.

## `search.domain` — new view

New `resources/views/search/domain.blade.php`. Structure mirrors `search.index`:

* `<x-app-layout>` + `<x-page-heading>`: `{{ $project->name }} — {{ $domain->label() }}` with
  the query, e.g. *"Scenes matching “dragon”"*.
* A **"← Back to search"** link to `projects.search.index` carrying `q` + `mode`, so the reader
  returns to the capped page with their query intact.
* One `x-table` (same head as `x-search.result-table`: Name / Matched in / Preview / actions),
  looping `$paginator` rows through `x-search.result-row`.
* `{{ $paginator->links() }}` below the table — the standard paginator, same as
  `revisions/index.blade.php:102`. `appends()` in the controller keeps `q`/`mode` on every link.
* Empty page guard: `$paginator` is never empty in practice (blank `q` redirects, and the link
  only appears when matches exist), but if a stale `?page=99` lands past the end, show a
  "No more results" line rather than a blank table.

## Accessibility / semantics

* "See all N" and "Back to search" are real `<a>` links (bookmarkable `GET`), keyboard-reachable.
* The paginator component is Laravel's default — already accessible.
* Heading hierarchy on the domain page: page `<h1>` (heading component) → the table needs no
  `<h3>` since there is only one column.
