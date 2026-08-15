# Architecture

## Routes

Keep the one overview route; add a chapter selector as an **optional query
param**, not a path segment — the URL stays `projects.story.overview`, and
`book` mode ignores it.

```
GET /projects/{project}/story/overview?chapter={chapter}   (chapter optional)
```

- `chapter` is a chapter **id** (stable across reorder; `position`/`number`
  shift). Bind it manually in the controller and verify `chapter->act->project`
  is `{project}` — never trust the id alone (CLAUDE.md authorization rule).
- New mode-update route, mirroring the per-project publication-settings PATCH:

```
PATCH /projects/{project}/story/overview/mode   → StoryController::updateMode
        name: projects.story.overview.mode
```

## StoryController::index — two paths on the mode

`$this->authorize('view', $project)` first, as today.

- **`book`**: unchanged from current `index` (full eager tree, `fromActs`
  numbering, whole-story `$wordCount`).
- **`chapter`**: load only the target chapter's scenes fully; derive everything
  else from lightweight queries.

### Selecting the chapter (`chapter` mode)

- No/invalid `chapter` param → first chapter by project-wide order (act
  position, then chapter position). Empty project → the existing "No acts yet."
- Load that one chapter with `scenes.event` eager-loaded, scenes sorted by
  `position` — the only place scene `contents` is fetched.

### Whole-book context without loading contents

Three cheap, contents-free reads:

1. **Numbering** — `StoryNumbering::forProject($project)` already loads only
   id/position/parent-fk across the three levels. Use it (not `fromActs`, which
   needs the full tree). Gives act/chapter/scene numbers for the current chapter
   and the TOC.
2. **TOC** — acts with chapters selecting `id, name, position` (+ `act_id`),
   ordered by position. Names for the sidebar; no scenes, no contents.
3. **Word totals** — one grouped aggregate:
   `Scene::selectRaw('chapters.act_id, sum(word_count) ...')` joined chapter→act,
   filtered to the project. Yields per-act and book totals for the header
   without loading a single scene body. The **current chapter's** total is the
   free in-memory `->sum('word_count')` over its loaded scenes, as today.

Prev/next chapter: derive from the ordered TOC list (id sequence), pass the
neighbour ids to the view.

## StoryController::updateMode

Resolve project → `authorize('update', $project)` → validate
`Rule::enum(StoryOverviewMode::class)` in a Form Request
(`UpdateStoryOverviewModeRequest`, `authorize()` mirrors the policy) → persist →
redirect back to the overview. Follows `PublicationSettingController::update`.

## What does not change

- Scene reorder stays AJAX (`scene-reorder.js`); within one chapter it is
  identical. Reordering a scene to another chapter is not a feature here.
- `StoryNumbering`, `x-word-count`, `x-collapsible-card` reused as-is.
