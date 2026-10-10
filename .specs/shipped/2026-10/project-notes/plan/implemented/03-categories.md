---
title: "Task 03 — Categories"
---

# Task 03 — Categories

## Scope

- `NoteCategoryController` (store, update for rename and move, destroy) and Form Requests.
- `App\Rules\ValidNoteCategoryParent`: same project, not itself, not a descendant, depth ≤ 3.
- Sibling-name uniqueness in the request, null parent included.
- `App\Services\NoteCategoryDeleter`: move notes and children to the parent, then delete, in one
  transaction.
- `App\Support\NoteCategoryTree`: one query, built in PHP; feeds the sidebar and the indented select.
- `App\Services\StarterNoteCategories::createFor(Project)`; call it from `ProjectController@store`,
  `OnboardingController@store` and `InstallsDemoProjects`.
- Index sidebar tree with "All notes", "Uncategorized", create/rename/move/delete dialogs.
- Category select on note create and edit; `?category=` on the index (direct notes only;
  `none` = uncategorized).

Defers: other index filters (06).

## Depends on

02.

## Key decisions already made

- Depth limit 3, enforced on the server.
- Delete moves contents up one level. No block, no cascade.
- Direct notes only under a selected category.
- Starters are top level only, never a `Project::created` hook, never on archive import.
- Names: Planning, Research, Continuity, Plot threads, Publishing, Ideas.

## Docs

`expanded/data-model.md` → Starter categories; `expanded/architecture.md` → Where logic lives;
`expanded/ui.md` → Notes index.

## Tests

- `NoteCategoryControllerTest`: every case in `testing.md` → Categories.
- Starter categories on project create, onboarding and demo install; none extra on import is
  tested in 10.
- A note's category from another project is rejected.
