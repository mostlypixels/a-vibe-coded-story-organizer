# UI

## New component

`resources/views/components/pagination-bar.blade.php`

```
@props(['paginator'])
```

One row under every `x-table`, always rendered:

- Left: `{{ __('Showing :first-:last of :total') }}` from `firstItem()`,
  `lastItem()`, `total()`. An empty list shows the count as 0 rather than
  hiding — the bar never disappears.
- Middle: `{{ $paginator->links() }}`. Laravel's default Tailwind view already
  renders on `revisions/index` and `search/domain`; nothing is published to
  `resources/views/vendor/pagination` and nothing needs to be.
- Right: the size select — a `PATCH` form to `preferences.page-size.update` with
  a hidden `return_to` of `request()->fullUrlWithoutQuery('page')`, the four
  `PageSize::sizes()` options in an `x-select`, and `onchange` submit. No Apply
  button; the existing `x-select` is the control.

Placed after the closing `</x-table>` in all nine index views, matching where
`revisions/index` puts `->links()`.

## Move buttons

`acts`, `books`, `chapters` and `scenes` index rows disable move up/down with
`$loop->first` / `$loop->last`. On page 2 that wrongly disables "up" on a row
that can move up.

Fix: `:disabled="$loop->first && $acts->onFirstPage()"` and
`:disabled="$loop->last && $acts->onLastPage()"`.

Pre-existing and **not** fixed here: `$loop->first` is already wrong under a
name sort or a descending direction, where the visually first row is not the
positionally first sibling. Pagination does not make that worse.

## Filter dropdowns

The scene list's chapter select renders every chapter in the book — 400 options
for the serial writer. Unchanged by this feature and out of scope; see
`open-questions.md`.

## Empty state

`x-table-empty` keeps its per-list message. The bar sits below it.
