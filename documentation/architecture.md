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

## The Codex (characters, locations, organizations)

The **Codex** is a project-scoped reference aggregate for the story's entities. It reuses
every existing convention — authorization walks up to `Project`, shallow routes, Form
Requests, index filtering in the controller — and adds the project's **first `app/Services`
layer** for the one genuinely non-trivial piece: temporal attribute values.

### One table, one controller, a type enum

All three entity kinds live in a single `codex_entries` table with a `type` column cast to
`App\Enums\CodexEntryType` (`Character` / `Location` / `Organization`). The columns are
identical across types, and the *type-specific* data is exactly what the flexible attribute
system handles — so one table stays DRY. A single `CodexEntryController` serves all three;
the type is a **route segment** (`{type}` ∈ `characters|locations|organizations`), resolved
via `CodexEntryType::fromRouteKey()`:

```php
Route::get('/projects/{project}/codex/{type}', [CodexEntryController::class, 'index'])
    ->whereIn('type', ['characters', 'locations', 'organizations']);
```

`edit` / `update` / `destroy` are flat (`/codex/{codexEntry}`) — the entry alone resolves them.

Around each entry hang: **aliases** (`codex_aliases`, sync-managed from a repeatable input),
flat **tags** (`tags` + `codex_entry_tag`, `firstOrCreate`d per project and `sync`ed), and
**media** (`codex_media`).

> [!NOTE]
> There is deliberately **no `cover_media_id` column**. The cover is simply the `codex_media`
> row whose `collection` is `Cover`, exposed via a `CodexEntry::cover()` `hasOne`. A FK would
> be a second source of truth *and* a circular reference (`codex_entries` → `codex_media` →
> `codex_entries`).

### Attribute definitions and the step function

An **attribute definition** (`codex_attributes`: e.g. "Hair color", "Frescoes") carries an
`applies_to` JSON array of `CodexEntryType` values deciding which sheets show it. Its
**values** (`codex_attribute_values`) are temporal: each row says *"from this event onward,
the value is X"* — a **start-anchored step function**. There is no stored end event; a period
runs from its `start_event`'s datetime until the next anchor (or the project's **End**), so
periods tile the timeline with **no holes or overlaps by construction**, and deleting a middle
anchor simply lets the previous value extend (which is why `start_event_id` can safely
`cascadeOnDelete`).

Resolving a value **at moment `t`** = the anchor whose datetime is the greatest `≤ t`.
Ordering is always the canonical `(event_datetime, events.id)` — never datetime alone —
because two events may share a datetime. When resolving *at an event*, an **anchor-identity
match wins first**: a scene "during Halloween" sees the Halloween value even if another event
shares its datetime.

All of this lives in **`App\Services\AttributeTimeline`** (constructed for one entry+attribute
pair), not in the controller or a model hook:

- `valueAt(Event|Carbon)` — the resolution above (used by scene/event "as of" panels via the
  thin `CodexEntry::attributeValueAt()` wrapper).
- `ensureBaseline()` / `upsertAt()` / `removeAt()` — gap-free mutations. `upsertAt` is an
  **upsert** (`updateOrCreate` on the anchor), so the store endpoint has **no update route**:
  editing an existing period posts the same route with the row's anchor. `removeAt` refuses to
  delete the Start baseline while other values exist.

> [!IMPORTANT]
> **Invariant — leading anchor at Start.** Every (entry, attribute) with any value has exactly
> one value anchored at the project's *Start* event, so `valueAt(t)` is **total** for
> `t ≥ Start` and callers never handle "no value". The Start/End events are `is_fixed` and
> undeletable, so the anchor can never be orphaned. This invariant lives in
> `AttributeTimeline` (a service the seeder can call directly), **not** a `booted()` hook —
> hooks are suppressed under `WithoutModelEvents`.

`App\Services\CodexMediaService` is the second service: it owns the storage path/naming, the
single-cover rule (replace the existing `Cover` row + its file), position assignment, and —
critically — **deleting files off disk** on every removal path. `CodexEntry`'s `deleting` hook
calls `purge()` *before* the FK cascade drops the rows, because `cascadeOnDelete` removes the
DB rows but never the files.

### Seeding caveat

Like acts/chapters and the main plotline, the Codex is subject to `WithoutModelEvents`:
`MelusineSeeder` sets `position` explicitly on `codex_attributes`, and seeds temporal values
by calling `AttributeTimeline::ensureBaseline` / `upsertAt` **directly** rather than relying on
any hook. It seeds the hair-color story (Mélusine: raven black → silver on Saturdays after the
curse → wild once she transforms) end to end.

## Where things live

| Concern | Location |
| --- | --- |
| Input validation | `app/Http/Requests` (Form Requests), `app/Rules` (reusable rules) |
| Authorization | `app/Policies/ProjectPolicy` |
| Domain invariants / lifecycle | Model `booted()` hooks |
| Reusable domain workflows | A Service/Action class — create `app/Services` when first needed |
| Constant / reference data | `app/Support` (e.g. `PlotlineColors`), `app/Enums` |
| Reusable UI | `resources/views/components` (Blade components) |
