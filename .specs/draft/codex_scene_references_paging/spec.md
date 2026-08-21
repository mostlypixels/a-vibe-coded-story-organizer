---
status: draft
---

# Codex ↔ scene references: capped lists with a "see all" page

## Problem

`alias_references_v1` (shipped) added the derived `scene_codex_entry` pivot and two read-only
cards that render it in full, with no cap:

- **Codex entry edit page** — "Referenced in scenes", full-width below the attribute timeline
  (`resources/views/codex/edit.blade.php`), fed by
  `CodexEntryController::referencingScenesInTimelineOrder()`.
- **Scene edit page** — "Codex references", an `x-collapsible-card` in the `lg:col-span-3`
  sidebar (`resources/views/scenes/edit.blade.php`), fed by `$referencedEntries` from
  `SceneController::edit()` (`(type, name)`, eager-loads `cover`).

A well-referenced entry (a protagonist in most scenes) produces a long inline list. The codex
side is the one that runs long; the scene card is collapsible, so it is the lesser case.

## Orderings (preserve both, card and full page)

- Codex → scenes: **sorted in PHP** on a 6-part key — unassigned scenes last, then
  `(event_datetime, id)`, tiebroken by `(act.position, chapter.position, position)`. Not a SQL
  order. The codex belongs to the project, so this list crosses every book: the manuscript
  tiebreak must gain `book.position` in front of `act.position`, or two books interleave by act
  number. See *Fix in passing*.
- Scene → entries: SQL `(type, name)`.

## Goals

- Cap each card, reusing the `search_pagination` convention shipped in #108: a config cap of 5
  inline rows, then a footer link `See all :count results`.
- Add one full-list page per direction, paginated at 20 rows — same shape as
  `search/domain.blade.php`.
- Authorize by walking to the owning `Project` via `ProjectPolicy`, as everywhere else.

## Non-goals

- No change to `SceneReferenceMatcher` or to when the pivot is recomputed.
- No change to the "Resync codex references" command/button.
- No AJAX expand-in-place — this app prefers real pages.

## Rough approach

- Config keys mirroring `config/search.php` (`cap`, `per_page`).
- Two actions, not one direction-parameterized action: the directions bind different models.
  - `GET /codex/{codexEntry}/scenes` → `CodexEntryController`
  - `GET /scenes/{scene}/codex-references` → `SceneController`
- Codex direction pages with `Collection::forPage` + a hand-built `LengthAwarePaginator`, as in
  `SearchController::domain()` — sorting runs in PHP, so SQL `LIMIT/OFFSET` would page the
  wrong set.
- One row component per direction (scene row, entry row), shared by the capped card and its
  full page. Do not unify the two rows — they render different fields.
- Templates to copy: `components/search/result-table.blade.php` (cap + footer link) and
  `search/domain.blade.php` (full page + `$paginator->links()`).

## Fix in passing

- `CodexEntryController.php:159` cites "the sidebar card in `codex/partials/fields.blade.php`";
  the card is in `codex/edit.blade.php` and is not a sidebar.
- `referencingScenesInTimelineOrder()` still sorts on `(act.position, chapter.position,
  position)` and eager-loads `chapter.act`. Since acts hang off a book, a scene from book 2 act 1
  sorts above a scene from book 1 act 2. Add `book.position` to the key and `chapter.act.book` to
  the eager load.
- The scene row must name its book when the project holds more than one, through
  `Book::displayName()` — an unnamed book borrows the project name and must never print `#<id>`.
