# UI

## One dropdown, two buttons

The scene toolbar keeps its single `<select name="chapter">` and gains a second submit
button beside **Filter**:

```
[ Search… ]  [ Chapter 218 — Salt and Thorn ▾ ]  [ Filter ]  [ Go to ]     [ New Scene ]
```

- **Filter** — today's behaviour, unchanged. Narrows the list to that chapter.
- **Go to** — `name="jump" value="1"`. Jumps to it and shows the whole book.

Both live in `x-index-toolbar`'s **existing** slot, which is already inside the filter
form. No `after` slot, no second form. Settled in the grill against a separate Go-to
select: two dropdowns of 400 chapters side by side is clutter, and pairing the buttons is
what makes the filter-vs-jump difference legible.

The chapter list gets the same pair over its `act` select.

Options are grouped by act with `<optgroup>` on the scene list — what makes 400 options
scannable. The flat `Act — Chapter` label goes.

After a jump the URL has `highlight` but no `chapter`, so the select shows its blank
option. Correct: nothing is filtered. The page-range line and the marked rows say where
you are.

## Highlighted rows

`x-table-row` gains a `highlighted` prop (default `false`), beside `striped`:

- `highlighted` wins over `striped` — `bg-highlight text-highlight-content`
- plus a left marker on the first cell, the shape the "no event" state already uses
  (`border-l-4`), in `border-accent`

The scene list passes `:highlighted="request('highlight') == $scene->chapter_id"`, the
chapter list `== $chapter->act_id`.

Colour is not the only signal: the range line names the chapter in text and every row
already carries a Chapter column. No `aria-current` — these rows are not a navigation
position.

## The anchor

The **first** highlighted row on the page carries `id="chapter-<id>"` (`act-<id>` on the
chapter list) — the target of the redirect's fragment. First row only: an `id` must be
unique, and it is the top of the group the browser should land on.

## The page-range line

`x-pagination-bar` gains an optional `range` prop (`?string`, default `null`). When set it
renders above the existing row of controls, full width, `text-content` semibold:

> **Chapter 214 — Ash and Rust to Chapter 231 — Salt and Thorn**

The bar is `flex flex-wrap`, so the line is a `basis-full` item ahead of `x-row-range`.
Nothing else moves, and every list that passes no `range` renders exactly as today.

One name and no "to" when the page holds one group.

## Files touched

| File | Change |
| --- | --- |
| `resources/views/components/pagination-bar.blade.php` | new `range` prop |
| `resources/views/components/table-row.blade.php` | new `highlighted` prop |
| `resources/views/scenes/index.blade.php` | Go-to button, `optgroup`s, highlight, anchor, range |
| `resources/views/chapters/index.blade.php` | same, for acts |
| `documentation/interface/components.md` | note `range` on the bar, `highlighted` on the row |

`x-index-toolbar` is **not** touched. No Alpine, no JavaScript, no new CSS.
