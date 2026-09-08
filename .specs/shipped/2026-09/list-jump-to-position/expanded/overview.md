# Overview

## The problem, restated against the code

Pagination shipped. A 1600-scene book is now 16 pages of 100, and the only control that
reaches page 9 is the page number. The writer knows the chapter, not the page.

**The source spec's premise is partly stale.** It says
`SceneController::chaptersFor()` orders chapter options by `name`. It does not — it
already joins `acts` and orders `acts.position, acts.id, chapters.position, chapters.id`.
`ChapterController::actsFor()` orders by `position`. The one-line ordering fix in the
spec's Approach is **already done**; nothing in this expansion repeats it.

What is left is the real feature: a control that aims at a chapter and lands on the page,
without throwing the rest of the list away.

## Goals

- Reach a chapter on the scene list, or an act on the chapter list, by naming it.
- Land on the page holding its first row, with the whole list still there.
- Say which groups the current page covers, so "where am I" is always answered.
- Keep the sort, the search and the page size the lists already honour.

## Non-goals

- Ordering the dropdowns. Already correct — see above.
- Remembering where the writer left off. Cut by the prototype; see the source spec's
  *Settled by prototype*. No new preference column, no new migration.
- A chapter sidebar. Considered and rejected at serial scale.
- Pagination, the bar, the size preference, infinite scroll, full-text search, new sorts.

## User stories

| As a | I want | So that |
| --- | --- | --- |
| Serial writer, 400 chapters | to pick chapter 218 and land on it | I stop counting pages |
| Serial writer | the rows after it still visible | I can read on into 219 |
| Series planner, 6 acts | to pick an act on the chapter list | same aim, coarser grain |
| Anyone | to know which chapters a page holds | I can tell where I am without jumping |

## Acceptance criteria

1. The scene toolbar has a **Go to** button beside the existing **Filter**, both acting on
   the one chapter select. The chapter list has the same over its act select.
2. **Go to** lands on the page holding that group's first row, showing the whole book: the
   search and the filter are cleared, and the sort is forced to story order ascending.
   Always — not only when the target has no matches.
3. The landed URL carries `page`, `highlight` and a `#chapter-<id>` fragment, and no
   `jump`. It is shareable, the back button steps out of the jump, and the browser scrolls
   to the group itself.
4. Rows belonging to the highlighted group are visibly marked; the first of them is the
   fragment's anchor.
5. A line above the pagination bar names the groups this page covers: "Chapter 214 to
   Chapter 231", or one name when the page holds one.
6. **Filter** behaves exactly as it does today. Existing filter URLs still work.
7. A jump id that is not one of this book's groups lands on a plain page 1, leaking
   nothing.
8. A non-owner gets 403 on a jump URL, same as every other index request.
