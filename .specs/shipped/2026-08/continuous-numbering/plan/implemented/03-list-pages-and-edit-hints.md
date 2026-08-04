# 03 — List pages and edit hints

## Scope

The three index pages and the three edit-page hints.

- `chapters/index` — `#` cell reads the derived number.
- `scenes/index` — `#` cell reads the derived number; **new "In chapter" column**
  immediately after the existing "Chapter" column, showing `$scene->position`, plain
  `<x-table-heading>` (not sortable). Bump `<x-table-empty :colspan="7">` → `8`. The
  empty-`event` danger border stays on the `#` cell.
- `acts/index` — `#` cell reads the derived number.
- `chapters/edit`, `scenes/edit`, `acts/edit` — the position hint.

The three controllers build the map with `forProject($project)` and pass it as `$numbering`.

Not in scope: story overview and share page (04).

## Depends on

01.

## Key decisions

- Sorting `#` is task 02's job; this task only changes what the cell prints.
- **Filtering never renumbers.** The map is built from the whole project, so a chapters list
  filtered to act II still starts at 3.
- Edit hint copy: `Chapter 7 — 2 of 5 in Act II. Use the move up/down buttons on the list to
  reorder.` Scenes take the same shape with the chapter name; acts read `Act 2 of 3.` — no
  ordinals anywhere, so nothing to generate and nothing to mistranslate. The count comes from
  the parent's sibling total.
- Numbers render bare — no zero padding, no "Chapter 03". The `__('Chapter :number')` /
  `__('Act :number')` keys keep their names.
- No change to `components/search/result-table`, `delete-with-move-dialog` or `breadcrumbs` —
  none of them show numbers.
- See `expanded/ui.md`.

## Tests

`tests/Feature/ChapterTest.php`, `SceneTest.php`, `ActTest.php`:

- Each index shows the continuous number, not the per-parent position.
- Filtering by act (resp. chapter) leaves numbers project-wide — filtered to act II, the
  first row still reads 3.
- The scenes list's "In chapter" column shows `position`, and move up/down still writes only
  `position`.
- Each edit page renders the new hint with both numbers.
