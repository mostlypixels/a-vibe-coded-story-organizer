# Testing

New file `tests/Feature/ListJumpTest.php`. `ListPaginationTest` stays as it is — it guards
the bar and the paginator, this guards the aiming.

Fixture: one book, 3 acts × 4 chapters × 5 scenes = 60 scenes, page size 10. Six pages,
and every chapter's first scene lands at a known offset, so an off-by-one is visible.

## Jump resolution

| Test | Asserts |
| --- | --- |
| lands on the right page | `?chapter=<7th>&jump=1` redirects to `page=4` (30 rows before it ÷ 10 + 1) |
| the redirect carries the fragment | `Location` ends `#chapter-<id>` |
| `jump` never survives | landed URL has `page`, `highlight`, `sort`, `direction` — no `jump` |
| the list is not filtered | the landed page holds rows from neighbouring chapters too |
| **the search is always cleared** | `?search=x&chapter=<id>&jump=1` → no `search` on the landed URL, even when the chapter *does* match `x` |
| the filter is always cleared | no `chapter` on the landed URL |
| **the sort is always forced** | jumping from `?sort=name&direction=desc` lands on `sort=position&direction=asc` |
| first group → page 1 | no redirect loop |
| last group → last page | the target's first row is on it, not past the end |
| page size is honoured | a user with `page_size = 25` gets a different page for the same chapter |
| a foreign group id | id from another book → plain page 1, no `highlight`, no `jump`, no leak |
| a non-owner | 403 on the jump URL, before any redirect |

## Filter is untouched

The regression that matters most — `Filter` and `Go to` share one select.

- `?chapter=<id>` with no `jump` filters exactly as today: same rows, `chapter` kept in the
  URL, move buttons rendered.
- `ListPaginationTest::test_scene_page_links_keep_the_chapter_filter_and_the_sort` still
  passes untouched.

## Highlight and anchor

- `?highlight=<id>` marks exactly that group's rows and no others.
- the **first** marked row on the page carries `id="chapter-<id>"`; later ones do not — the
  id is unique in the document.
- paging forward keeps `highlight` in the links (`withQueryString()`).
- an unknown `highlight` id marks nothing, renders no anchor, and does not error.

## Page range

- a page inside one group prints that one name, no "to".
- a page spanning two prints first and last.
- `sort=name` prints no range line.
- an empty list prints no range line.
- every other paginated index still renders its bar with no range — one test over the
  `ListPaginationTest::indexes()` provider, so a regression in the shared component is
  caught on all nine lists.

## The ordering-drift guard

The one that matters. `ListJump` is correct only while `chaptersFor()` orders chapters
exactly as `SceneController::index` orders scenes' chapters — same four keys, `id`
tie-breaks included. Drift makes the jump land a page off, silently.

Test: give two sibling chapters the **same** `position`, then jump to the second. It must
land on the page holding its first scene, not the first chapter's. This fails the moment
either side drops an `id` tie-break, which is the realistic way this breaks.

Same test for two acts sharing a position on the chapter list.

## Acts list

The chapter list's act jump gets: lands-on-the-right-page, search-cleared, foreign-id, and
non-owner. The rest is the same code path, covered above.
