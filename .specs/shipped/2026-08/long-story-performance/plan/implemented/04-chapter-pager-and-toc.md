# 04 — Chapter pager and TOC links

## Scope

Navigation *between* chapters in `chapter` mode.

- **Prev/next neighbours**: from the project-wide ordered chapter list (already
  built for the TOC in task 03), find the current chapter's neighbours; pass
  their ids + display labels (number + name) to the view.
- **Pager**: prev / next chapter links at top and bottom of the content column.
  Real `<a>` links to `?chapter={id}`; disabled at the ends (first disables
  "previous", last disables "next"). Crosses act boundaries naturally (last
  chapter of an act → first of the next).
- **TOC links** (`chapter` mode): chapter links become `?chapter={id}`
  (same-project). Act links point at the first chapter id of that act. Keep the
  `#chapter-{id}` fragment so the target scrolls into view.
- `book` mode: TOC stays today's same-page `#` anchors, no pager.

Does **not**: change the controller's data assembly beyond exposing the
neighbour ids (the ordered list already exists from task 03). No mode switch
(task 05).

## Depends on

01, 02, 03.

## Key decisions

- Neighbours by project-wide order, not within-act — pager walks the whole book.
- Links carry chapter **id**.

## Consult

`../expanded/ui.md` → "Chapter mode".

## Tests (extend `StoryTest`)

- First chapter page: "previous" disabled; "next" links to the second chapter's
  id.
- Last chapter page: "next" disabled; "previous" links to the penultimate id.
- A middle chapter links to the correct prev and next ids.
- Pager crosses an act boundary to the adjacent act's chapter.
- `chapter`-mode TOC hrefs carry `?chapter={id}`; act link targets the act's
  first chapter.
