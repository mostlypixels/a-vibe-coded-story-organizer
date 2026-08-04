# Continuous numbering — plan overview

The manual for this feature's tasks. Never implemented, never moved to `implemented/`.

## Execution order

| # | Task | Purpose |
|---|---|---|
| 01 | `story-numbering-value-object` | The derivation itself: one object, three id → number maps |
| 02 | `index-sort-by-story-order` | Fix the `#` column sort on the chapters and scenes lists |
| 03 | `list-pages-and-edit-hints` | Chapters/scenes/acts lists + the three edit-page hints |
| 04 | `story-overview-and-share-page` | Story overview (acts, chapters, scene prefixes) + public scene page |
| 05 | `scene-reorder-js-module` | Extract `moveScene`, swap number text after a reorder, vitest |
| 06 | `epub-exports-the-full-outline` | EPUB stops dropping empty chapters/acts; the guard moves |
| 07 | `epub-continuous-numbers` | Continuous act + chapter numbers through the EPUB |
| 08 | `website-book-numbers` | Website book layer gains numbers, honouring the publication setting |
| 09 | `documentation` | `documentation/` + `CHANGELOG.md` |

Dependencies: 03, 04 → 01. 05 → 04. 07 → 01, 06. 08 → 01. 09 → all. 02 and 06 stand alone.

## Binding decisions

Settled in the expanded docs and the planning grill. Do not re-litigate.

- **Derive, never store.** No migration, no `number` column, no renumber-on-reorder.
- **`position` is untouched** — still per-parent, still gappy, still the only thing move
  up/down writes.
- **Acts, chapters and scenes are all rank-derived.** One rule, no exception: a displayed
  number never has a gap.
- **Numbers are project-wide.** Filtering a list to one act or chapter never renumbers it.
- **Exports omit nothing.** A chapter with no scenes exports as a heading-only stub page; an
  act with no chapters keeps its divider. Export numbers therefore always equal app numbers,
  and never shift as the author fills placeholders in.
- **The EPUB refuses only when the project has zero scenes**, with today's message.
- **Untitled scenes keep per-chapter EPUB nav labels** ("Scene 3"). That label is the only
  place scene numbers reach a reader, and a project-wide count means nothing to them.
- **Static-site chapter hrefs are file identity.** `chapterHref()` keeps `%02d/%02d.html`
  from raw act position + per-act chapter position. Never feed it a derived number.
- **The archive `data/` layer keeps `position`.** Import round-trip unaffected.
- **`?sort=position` stays as the URL token.** Only what it orders by changes.

## Invariants every task must preserve

- **Ordering key, with an id tie-break at every level.** Acts by `(position, id)`; chapters
  by `(act.position, act.id, position, id)`; scenes one level deeper. `position` has no
  unique constraint, so two siblings can share one and must still number deterministically.
- **The map is built from the whole tree**, never a filtered or paginated subset. On an
  index page that means the whole project; in an exporter, the whole exported tree.
- **Unknown id throws.** A missing number is a bug, not a blank cell.
- **Authorization is unchanged** — no new endpoints, no policy edits. Existing owner/403
  coverage stands.
- **Never call `select()` after `withCount`/`withSum`.** It resets the column list and
  silently drops their subquery aliases (`withAggregate` inserts `<table>.*` itself when no
  columns are set yet).
- **Every column in a joined index query must be table-qualified.** `Project::chapterQuery()`
  is a Builder rather than a `hasManyThrough` precisely because `acts` also has `name` and
  `position` — see its docblock.
