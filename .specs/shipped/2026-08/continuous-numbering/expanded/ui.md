# UI

## `resources/views/story/index.blade.php`

- TOC chapter links and the `<h3>` chapter headings: `$numbering->chapter($chapter)` in place
  of `$chapter->position` (4 sites, lines ~35/43/66/82). The two act sites (~35/66) take
  `$numbering->act($act)` the same way — acts are rank-derived too.
- Scenes gain a visible number: prefix the collapse button's label with
  `{{ $numbering->scene($scene) }}.` in a muted span. It sits inside the existing
  `flex items-center gap-2` row, before `{{ $scene->name }}`.
- Give that span a hook (`data-scene-number`) — `moveScene` swaps the two nodes' text after a
  successful reorder.

## `resources/views/chapters/index.blade.php`

- `#` cell → `$numbering->chapter($chapter)`.
- Header and move-button logic unchanged.

## `resources/views/scenes/index.blade.php`

- `#` cell → `$numbering->scene($scene)`.
- **New column** immediately after the existing "Chapter" column: "In chapter", value
  `$scene->position`, plain `<x-table-heading>` (not sortable — see `open-questions.md` #3).
  It sits beside the chapter name so the two read as one idea.
- Bump `<x-table-empty :colspan="7">` → `8`.
- The empty-`event` danger border stays on the `#` cell.

## Edit pages

Both hints read "Currently chapter #:position within its act" today, which is now only half
the truth. No ordinals anywhere — nothing to generate, nothing to mistranslate — and the
sibling count is more useful than "2nd" because it explains what the move buttons can do.

- `chapters/edit.blade.php` → `Chapter :number — :position of :total in :act. Use the move up/down buttons on the list to reorder.`
- `scenes/edit.blade.php` → same shape with the chapter name.
- `acts/edit.blade.php` → `Act :number of :total. Use the move up/down buttons on the list to reorder.`
- `acts/index.blade.php` → `#` cell reads the derived number (open question #6 is *yes*).

## `resources/views/shared/scenes/show.blade.php`

`Chapter :number` takes the continuous number. The stale comment ("Arabic chapter.position")
goes with it.

## No change

- `components/search/result-table.blade.php` — search results show names, not numbers.
- `components/delete-with-move-dialog.blade.php` — destinations are listed by name.
- `components/breadcrumbs.blade.php` — no numbers.

## Copy

Numbers are rendered bare (`3`, `12`) — no zero padding, no "Chapter 03". `__('Chapter :number')`
and `__('Act :number')` keys are already in place and keep their names.
