# Word Count — UI

## Live counter in the field

Lives in `resources/views/components/autosave-field.blade.php`, so **every** registered
autosaved field gets it from one edit — the same "one registry line + one blade line"
property that component already has.

* Bottom-right of the field's own box, muted (`text-xs text-gray-400`), never bold, never
  coloured. It is reference, not feedback.
* Sits beside the existing autosave status badge; the badge keeps priority — see
  `open-questions.md` Q5 on crowding.
* Text: `1,234 words` / `1 word` / `0 words` (pluralised, thousands-separated via
  `number_format`, translatable with `trans_choice`).
* `aria-live="off"` — a count that changes on every keystroke must not be announced. The
  count is available on demand to a screen-reader user reading the field; it is not an
  event.

> [!NOTE]
> The counter is not a save indicator. `x-autosave-status-badge` says whether work is safe;
> this says how much there is. Keeping them visually distinct (weight and colour, not just
> position) is what stops the counter reading as "saved".

## New component: `x-word-count`

`resources/views/components/word-count.blade.php`

```blade
<x-word-count :count="$chapter->word_count" />
```

* Props: `count` (int), `variant` (`inline` | `muted`, default `muted`).
* Renders the pluralised, formatted string. One place formats a count, so a list and a
  header can never disagree about `1,234` vs `1234`.
* In tables, render inside the existing `x-table-row` cells — no new table machinery.

## Where counts appear

| Screen | Shows | Source |
|---|---|---|
| `story/index.blade.php` | per chapter, per act, project total | scenes already eager-loaded → PHP `sum()`, no extra query |
| `scenes/index.blade.php` | per scene, column | `scenes.word_count` |
| `chapters/index.blade.php` | per chapter, column | `withSum` in controller |
| `acts/index.blade.php` | per act, column | `withSum` in controller |
| `projects/show` / project header | project total | `withSum` in controller |

* Column header: **Words**, right-aligned (numeric), using `x-table-heading`.
* Sortable via the existing `x-sortable-header` + `ResolvesIndexSorting` **only if** it
  falls out cheaply — the `SUM` alias is sortable, but confirm it against that concern's
  allow-list rather than assuming (`open-questions.md` Q6).

## Empty and zero states

* A scene with no `contents` shows `0 words`, not a blank cell or a dash. Zero is a real,
  useful answer; blank reads as "unknown".
* A chapter with no scenes shows `0 words`. `withSum` yields `NULL` for no rows — coalesce
  in the controller (`?? 0`), not in the view.

## Accessibility

* The table column is a normal `<th scope="col">Words</th>`; no icon-only header.
* Counts are plain text, not `title=`-only, so they survive zoom and screen readers.
* Nothing here is colour-only.
