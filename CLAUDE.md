# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Guidelines & documentation

- **Coding guidelines:** [`.claude/guidelines.md`](.claude/guidelines.md) — architecture/style preferences, where logic lives, security, authorization, testing, and documentation rules. Read before planning or writing code.
- **Project docs:** [`documentation/`](documentation/) — [architecture](documentation/architecture.md), [code style](documentation/code-style.md), [best practices](documentation/best-practices.md), and [glossary](documentation/glossary.md). Keep these in sync when architecture or workflows change.
- **Changelog:** [`CHANGELOG.md`](CHANGELOG.md) — Keep a Changelog format; update the `[Unreleased]` section per feature/PR.

## Commands

- Run full test suite: `composer test` (clears config cache, then `php artisan test`)
- Run a single test file: `php artisan test tests/Feature/ProjectTest.php`
- Run a single test by name: `php artisan test --filter=test_method_name`
- Local dev (server + queue + logs + vite, all concurrently): `composer dev`
- Build frontend assets: `npm run build`
- Frontend dev server only: `npm run dev`
- Lint/format PHP (Laravel Pint): `vendor/bin/pint`

## Architecture

This is a Laravel 12 app (Breeze auth scaffolding, Blade + Tailwind, Alpine.js, no SPA framework) for tracking a writing project's plotlines, timeline events, and manuscript structure (acts/chapters/scenes).

**Domain model:** `User` has many `Project`s. Each `Project` has many `Plotline`s, `Event`s, and `Act`s. `Event` and `Plotline` have a many-to-many relationship (an event can touch multiple plotlines). `Act` has many `Chapter`s, `Chapter` has many `Scene`s (a strict three-level manuscript hierarchy — no many-to-many). Ownership/authorization flows from `Project`: `ProjectPolicy` checks `user_id === $project->user_id`, and child-resource controllers (`PlotlineController`, `EventController`, `ActController`, `ChapterController`, `SceneController`) authorize via the parent project (e.g. `$this->authorize('update', $plotline->project)`, `$this->authorize('update', $chapter->act->project)`) rather than having their own policies.

**Main plotline invariant:** every `Project` auto-creates a special `Plotline` (`is_main = true`, name "Main plotline") in a `booted()` model event hook when the project is created. This plotline cannot be deleted — `PlotlineController@destroy` calls `abort_if($plotline->is_main, 403)`. Any UI or logic touching plotline lists must account for this plotline being un-deletable (and it should generally stay first/pinned in listings).

**Act/Chapter/Scene ordering:** each of `Act`, `Chapter`, `Scene` has a `position` integer, auto-assigned (`max(position) + 1`, scoped to the parent — project for acts, act for chapters, chapter for scenes) via a `creating` model event hook in `booted()`. Titles are freeform and should not encode the number (e.g. no "Act 1" in the name) — the position is the number, rendered separately in a `#` column. Reordering happens via `moveUp`/`moveDown` controller actions that swap `position` with the adjacent sibling, exposed as `PATCH /acts/{act}/move-up` etc. (see Routing below); there's no drag-and-drop. Index views only show the move buttons when the list is genuinely ordered by position for a single parent — for chapters/scenes that means filtered to one act/chapter, since position numbering restarts per-parent and a flat "all acts" list interleaves independent sequences. **Seeding caveat:** `DatabaseSeeder` uses `WithoutModelEvents`, which suppresses the `creating` hook, so `MelusineSeeder` must set `position` explicitly when creating acts/chapters/scenes (same reason it has a manual fallback for main-plotline creation).

**Routing:** nested resource routes use Laravel's shallow nesting (`Route::resource('projects.plotlines', ...)->shallow()`, same for `projects.events`, `projects.acts`, `projects.chapters`, `projects.scenes`). This means `index`/`create`/`store` are nested under `/projects/{project}/...` but `edit`/`update`/`destroy` are flat (`/plotlines/{plotline}`, `/events/{event}`, `/acts/{act}`, `/chapters/{chapter}`, `/scenes/{scene}`) since the child model alone is enough to resolve the route. Acts/chapters/scenes additionally have flat `PATCH .../move-up` and `.../move-down` routes (`acts.move-up`, `chapters.move-down`, etc.) defined alongside their resource routes. All routes require `auth` middleware.

**Plotline colors:** `App\Support\PlotlineColors::PRESETS` is the fixed palette (500/700 shades of 16 Tailwind color families) used by the `x-color-picker` component; new plotlines default to `PRESETS[0]`.

**Story overview:** `StoryController@index` (`GET /projects/{project}/story`, route `projects.story.index`) is a read-only view combining the full act/chapter/scene tree into one page — chapters render as `<article>`, scenes as `<section>`, and `Scene::contents` is rendered as Markdown via `Illuminate\Support\Str::markdown()` (backed by `league/commonmark`, present in `composer.lock` as a transitive dependency of `laravel/framework` — not in `composer.json`'s own `require`, so don't assume it survives a dependency prune without checking). It's the first item in the "Story" nav dropdown, above Acts/Chapters/Scenes, and includes a collapsible table of contents (acts + chapters only, anchor-linked) above the rendered content.

**Views:** Blade components live in `resources/views/components/` and are the reuse layer for list/table rows (e.g. `x-icon-edit-link`, `x-icon-delete-button`, `x-icon-move-up-button`, `x-icon-move-down-button`, `x-sortable-header`, `x-color-picker`). The index tables (plotlines/events/acts/chapters/scenes) share an `x-table` family — `x-table` (card + `<table>` + `head` slot), `x-table-heading` (non-sortable header cell, paired with `x-sortable-header`), `x-table-row` (striped body row via `:striped="$loop->even"`), and `x-table-empty` (no-results row via `:colspan`); the stripe/header colors live in those components, not the views. Index views for plotlines/events/acts/chapters/scenes support query-string sort (`sort`, `direction`) and search/filter (`search`, `plotline`/`act`/`chapter`) handled entirely in the controller's `index` method, not via query scopes on the model. Acts/chapters/scenes default-sort by `position` rather than `name`.

## Testing notes

- Tests run against an in-memory SQLite DB (`tests/TestCase.php` + `phpunit.xml`), so migrations run fresh per test run.
- `tests/Feature/ProjectTest.php` is the only feature test covering the project/plotline/event domain; there are currently no dedicated `PlotlineTest`/`EventTest`/`ActTest`/`ChapterTest`/`SceneTest` files even though those controllers exist — check there before assuming coverage exists.
