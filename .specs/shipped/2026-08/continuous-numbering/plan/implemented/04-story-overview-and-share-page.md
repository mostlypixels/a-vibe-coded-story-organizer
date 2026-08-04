# 04 — Story overview and public scene page

## Scope

- `story/index.blade.php` — act and chapter numbers in both the TOC and the body headings
  (4 chapter sites, 2 act sites), plus a **new muted scene number** prefixing each scene's
  collapse-button label, inside the existing `flex items-center gap-2` row before
  `{{ $scene->name }}`. Give that span a `data-scene-number` hook.
- `shared/scenes/show.blade.php` — `Chapter :number` takes the continuous number; delete the
  stale "Arabic chapter.position" comment.
- `StoryController::index` builds the map with `fromActs($acts)` (the tree is already
  eager-loaded — zero extra queries). `SharedSceneController` uses
  `forProject($scene->chapter->act->project)`.

Not in scope: the JS that keeps those numbers right after a drag-free reorder (05).

## Depends on

01.

## Key decisions

- One extra query on the public scene page is accepted — two indexed columns over one
  project. Measure before adding a cache.
- Stale numbers in a tab left open while another tab reorders an act are accepted. Full page
  loads are correct; this is not a collaborative editor.
- See `expanded/ui.md` and `expanded/architecture.md` → *Call sites*.

## Tests

`tests/Feature/StoryTest.php`:

- TOC and headings show continuous chapter numbers across the act boundary.
- Act numbers close the gap left by a deleted act.
- Scene numbers render and run unbroken across chapters.
- Query count does not regress — the page still loads the tree once and derives from it.

Public scene page: `shared/scenes/show` renders the continuous chapter number for a chapter
in act II.
