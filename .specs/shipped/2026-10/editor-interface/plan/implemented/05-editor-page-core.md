---
title: "Task 05 — Editor page core"
---

# Task 05 — Editor page core

## Scope

- Routes `projects.write` and `scenes.write`, `EditorController@home` and `@show`.
- `resources/views/editor/show.blade.php`, `editor/empty.blade.php`, component `x-editor-panel` (header, collapse button placeholder, slot).
- Full-width, full-height three-column grid. Widths and collapsed flags from `EditorLayout::resolve()` as CSS variables and classes. Panels are empty shells.
- Centre: scene name `h1`, context line, `x-icon-edit-link` to `scenes.edit`, `x-autosave-field entity="scene" field="contents" :quick-codex-entry="true"`, focus on load.
- `x-wysiwyg` opt-in `sticky-toolbar` prop; `x-autosave-field` passes it through.
- `ProjectNavigation::$writeActive`; "Write" `x-nav-link` after Dashboard in `project-menu.blade.php` and `responsive-project-menu.blade.php`. Dashboard not active on editor routes.
- Breadcrumbs: project › book › Write › scene.

Defers: header extras (06), left panel content (07), right panel content (08), resize and collapse JS (09).

## Depends on

02.

## Key decisions already made

- `home`: newest `updated_at` scene of the project, across books; none → empty state with a link to create a scene.
- `show`: authorize `update` on `$scene->book()->project`.
- Layout height: window minus the nav and any submenu. `body` does not scroll; each column has `overflow-y: auto`. Use `dvh` so mobile toolbars do not cut the bottom.
- Ignore `page_width`. Prose column uses the manuscript font settings and `max-w-prose`, centred.
- Sticky toolbar sticks inside the centre column's scroll box, not the window.
- Full page navigation away keeps the `navigationGuard` flush.

Detail: `expanded/architecture.md` → *Routes*, *Controllers*, *Navigation*; `expanded/ui.md` → *Layout*, *Centre*.

## Tests

- `tests/Feature/EditorTest.php`: `home` redirect to newest scene across books; empty state; `show` renders `data-autosave-field="scene:<id>:contents"`; layout from the user column, defaults for null; non-owner 403 on both routes; guest redirect.
- `NavigationTest.php`: "Write" after Dashboard on desktop and responsive menus; active on both routes; Dashboard not active there.
- `x-wysiwyg` without the prop renders as before; with it, the toolbar has the sticky class.
