# 03 — Chapter-mode render

## Scope

Make `StoryController::index` branch on `$project->overview_render_mode` and
render **one chapter** with whole-book context when it is `Chapter`.

- Route: accept optional `?chapter={id}` on `projects.story.overview` (query
  param, not a path segment; `book` mode ignores it).
- **Chapter selection**: resolve the `chapter` id, else the first chapter by
  project-wide order (act `position`, then chapter `position`). Empty project →
  existing "No acts yet." path.
- **Authorization**: `authorize('view', $project)` as today; additionally walk
  `chapter->act->project` and 403 if it is not the route `{project}`. Never
  trust the id.
- Load only that chapter with `scenes.event` eager-loaded, scenes ordered by
  `position` — the sole `contents` fetch.
- **Whole-book context, contents-free** (see `../expanded/architecture.md`):
  - Numbering via `StoryNumbering::forProject($project)` (not `fromActs`).
  - TOC data: acts + chapters selecting `id, name, position, act_id` only.
  - Word totals: one grouped aggregate join scene→chapter→act filtered to the
    project → per-act + book totals. Current chapter total stays the in-memory
    `->sum('word_count')`.
- **View**: render the task-02 chapter partial once, under an **always-shown act
  header** for the current chapter's act (reuse the `bg-nav` act bar styling,
  scoped to one chapter's act, with that act's total from the aggregate).
- `book` mode unchanged (still the full tree, `fromActs`, whole `$wordCount`).

Does **not**: prev/next pager or `?chapter=` TOC links (task 04) — the TOC may
still link with today's `#chapter-{id}` anchors in this task. No mode-switch UI
(task 05).

## Depends on

01, 02.

## Key decisions

- Chapter by **id**; default = first chapter. Binding decisions in `00-overview.md`.
- Null-safe aggregate: a chapter/act with no scenes totals 0, not null.

## Consult

`../expanded/architecture.md` (StoryController two paths), `../expanded/overview.md`
(acceptance criteria).

## Tests (extend `StoryTest`)

- Default `chapter` mode: response has the first chapter's scene bodies; a later
  chapter's scene body is **absent**.
- `?chapter={id}`: that chapter's scenes present, siblings' bodies absent.
- Mid-story chapter shows correct project-wide numbering (e.g. "Chapter 15")
  though only it is loaded — guards `forProject` vs `fromActs`.
- Header act + book word totals equal whole-tree sums, not the loaded chapter.
- `?chapter={id}` for a chapter in **another** project → 403.
- Query budget: response produced in a bounded query count independent of
  chapter/scene count (no N+1, no non-current `contents`).
- `book` mode still renders every chapter's bodies (parity).
