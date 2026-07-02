# Architecture

This is a Laravel 12 app (Breeze auth, Blade + Tailwind, Alpine.js — no SPA framework)
for tracking a writing project's plotlines, timeline events, and manuscript structure.

## Domain model

```
User
 └── Project                (belongs to a user)
      ├── Plotline          (one is the "main plotline")
      ├── Event             (many-to-many with Plotline)
      └── Act
           └── Chapter
                └── Scene
```

- A `User` has many `Project`s.
- Each `Project` has many `Plotline`s, `Event`s, and `Act`s.
- `Event` ↔ `Plotline` is many-to-many (an event can touch several plotlines).
- `Act` → `Chapter` → `Scene` is a strict three-level hierarchy (no many-to-many).

The manuscript hierarchy is an **aggregate** rooted at `Project`: you almost never load
a `Scene` in isolation without caring which `Project` owns it. That ownership root drives
authorization (below).

## Authorization flows from the Project

There is a single policy, `App\Policies\ProjectPolicy`, with three abilities — `view`,
`update`, `delete` — each checking `$user->id === $project->user_id`.

Child resources do **not** have their own policies. Instead each controller walks up to the
owning project and authorizes against it:

```php
// SceneController@edit
$this->authorize('update', $scene->chapter->act->project);
```

Form Requests mirror the same check in their `authorize()` method.

> [!IMPORTANT]
> Every action that reads or writes a resource must authorize through the project. If you
> add a new child controller, authorize via `->...->project`, and add a test proving a
> non-owner gets a `403`. Route model binding alone is **not** access control.

## The main plotline invariant

Every `Project` auto-creates one special `Plotline` (`is_main = true`, name "Main plotline")
in a `Project::booted()` `created` hook. This plotline **cannot be deleted** —
`PlotlineController@destroy` calls `abort_if($plotline->is_main, 403)`.

> [!WARNING]
> Any UI or logic that lists plotlines must account for the main plotline being
> un-deletable, and it should generally stay pinned first in listings.

## Act / Chapter / Scene ordering

Each of `Act`, `Chapter`, `Scene` has a `position` integer, auto-assigned as
`max(position) + 1` scoped to its parent (project for acts, act for chapters, chapter for
scenes) via a `creating` hook in the model's `booted()` method.

- Titles are freeform and must **not** encode the number (no "Act 1" in the name). The
  position is the number, rendered separately in a `#` column.
- Reordering swaps `position` with the adjacent sibling via `moveUp` / `moveDown` controller
  actions (`PATCH /acts/{act}/move-up`, etc.). There is no drag-and-drop.
- Index views only show move buttons when the list is genuinely ordered by position for a
  single parent (i.e. filtered to one act/chapter), because numbering restarts per parent.

> [!WARNING]
> **Seeding caveat.** `DatabaseSeeder` uses `WithoutModelEvents`, which suppresses the
> `creating` hook. `MelusineSeeder` therefore sets `position` explicitly (and creates the
> main plotline manually) — if you add seeded acts/chapters/scenes, set `position` yourself.

## Routing (shallow nested resources)

Nested resource routes use Laravel's shallow nesting:

```php
Route::resource('projects.scenes', SceneController::class)->shallow();
```

- `index` / `create` / `store` are nested under `/projects/{project}/...`.
- `edit` / `update` / `destroy` are flat (`/scenes/{scene}`) — the child model alone
  resolves the route.
- Acts/chapters/scenes additionally have flat `PATCH .../move-up` and `.../move-down` routes.
- All routes require the `auth` middleware.

## Story overview

`StoryController@index` (`GET /projects/{project}/story`) is a read-only page combining the
full act/chapter/scene tree. Chapters render as `<article>`, scenes as `<section>`, and
`Scene::contents` is rendered as Markdown via `Illuminate\Support\Str::markdown()`.

> [!NOTE]
> `Str::markdown()` is backed by `league/commonmark`, which is present as a **transitive**
> dependency of `laravel/framework` (via `composer.lock`), not in `composer.json`'s own
> `require`. Don't assume it survives a dependency prune without checking.

## Enum convention

Enums live in `app/Enums`. The pattern (see `SceneStatus`):

- A **string-backed** enum with a `label()` method (via `match`) for display.
- Stored in a plain `string` DB column with a default — not a native DB enum.
- Cast on the model (`protected $casts = ['status' => SceneStatus::class]`).
- Validated in the Form Request with `Rule::enum(SceneStatus::class)`.
- Rendered through a dedicated Blade badge component (`scene-status-badge`).

## Where things live

| Concern | Location |
| --- | --- |
| Input validation | `app/Http/Requests` (Form Requests), `app/Rules` (reusable rules) |
| Authorization | `app/Policies/ProjectPolicy` |
| Domain invariants / lifecycle | Model `booted()` hooks |
| Reusable domain workflows | A Service/Action class — create `app/Services` when first needed |
| Constant / reference data | `app/Support` (e.g. `PlotlineColors`), `app/Enums` |
| Reusable UI | `resources/views/components` (Blade components) |
