# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

- Run full test suite: `composer test` (clears config cache, then `php artisan test`)
- Run a single test file: `php artisan test tests/Feature/ProjectTest.php`
- Run a single test by name: `php artisan test --filter=test_method_name`
- Local dev (server + queue + logs + vite, all concurrently): `composer dev`
- Build frontend assets: `npm run build`
- Frontend dev server only: `npm run dev`
- Lint/format PHP (Laravel Pint): `vendor/bin/pint`

## Architecture

This is a Laravel 12 app (Breeze auth scaffolding, Blade + Tailwind, Alpine.js, no SPA framework) for tracking a writing project's plotlines and timeline events.

**Domain model:** `User` has many `Project`s. Each `Project` has many `Plotline`s and `Event`s. `Event` and `Plotline` have a many-to-many relationship (an event can touch multiple plotlines). Ownership/authorization flows from `Project`: `ProjectPolicy` checks `user_id === $project->user_id`, and child-resource controllers (`PlotlineController`, `EventController`) authorize via the parent project (e.g. `$this->authorize('update', $plotline->project)`) rather than having their own policies.

**Main plotline invariant:** every `Project` auto-creates a special `Plotline` (`is_main = true`, name "Main plotline") in a `booted()` model event hook when the project is created. This plotline cannot be deleted — `PlotlineController@destroy` calls `abort_if($plotline->is_main, 403)`. Any UI or logic touching plotline lists must account for this plotline being un-deletable (and it should generally stay first/pinned in listings).

**Routing:** nested resource routes use Laravel's shallow nesting (`Route::resource('projects.plotlines', ...)->shallow()`, same for `projects.events`). This means `index`/`create`/`store` are nested under `/projects/{project}/...` but `edit`/`update`/`destroy` are flat (`/plotlines/{plotline}`, `/events/{event}`) since the child model alone is enough to resolve the route. All routes require `auth` middleware.

**Plotline colors:** `App\Support\PlotlineColors::PRESETS` is the fixed palette (500/700 shades of 16 Tailwind color families) used by the `x-color-picker` component; new plotlines default to `PRESETS[0]`.

**Views:** Blade components live in `resources/views/components/` and are the reuse layer for list/table rows (e.g. `x-icon-edit-link`, `x-icon-delete-button`, `x-sortable-header`, `x-color-picker`). Index views for plotlines/events support query-string sort (`sort`, `direction`) and search/filter (`search`, `plotline`) handled entirely in the controller's `index` method, not via query scopes on the model.

## Testing notes

- Tests run against an in-memory SQLite DB (`tests/TestCase.php` + `phpunit.xml`), so migrations run fresh per test run.
- `tests/Feature/ProjectTest.php` is the only feature test covering the project/plotline/event domain; there are currently no dedicated `PlotlineTest`/`EventTest` files even though those controllers exist — check there before assuming coverage exists.
