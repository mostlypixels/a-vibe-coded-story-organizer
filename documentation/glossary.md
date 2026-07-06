# Glossary

Higher-level concepts, domain terms, and design patterns used in this codebase. When a term
appears in code review or docs and you're unsure what it means, start here.

## Domain terms

**Project** — the top-level container a user works in. Owns plotlines, events, and the
manuscript structure. Ownership of every child resource is derived from the project's
`user_id`.

**Plotline** — a narrative thread through the story. A project has many. One is the
**main plotline**.

**Main plotline** — the special, auto-created plotline (`is_main = true`) that every project
gets on creation. It **cannot be deleted** and generally stays pinned first in listings.

**Event** — a timeline event. Belongs to a project and can touch multiple plotlines
(many-to-many).

**Bookend events (Start / End)** — the two `is_fixed` events every project is auto-created
with (year 0001 "Start" and year 3000 "End"). They are the sentinels the attribute step
function resolves against, so their contract is stronger than the main plotline's: they
**cannot be deleted**, and their `event_datetime` is **editable but forms a containment
window** — every non-fixed event must satisfy `Start ≤ event_datetime ≤ End`, and a bookend
edit may not swallow an existing event (`App\Rules\WithinEventWindow`). Because Start stays
the earliest `is_fixed` event, the timeline anchor never moves. Resolved through the single
`Project::startEvent()` / `Project::endEvent()` methods in canonical `(event_datetime, id)`
order — never re-queried elsewhere.

**Act → Chapter → Scene** — the three-level manuscript hierarchy. An act has many chapters;
a chapter has many scenes. Strictly nested (no many-to-many).

**Position** — the integer that orders acts within a project, chapters within an act, and
scenes within a chapter. Auto-assigned on create and swapped by move-up/move-down. It **is**
the displayed number; titles never encode it.

**Story overview** — the read-only page (`projects.story.index`) that renders the whole
act/chapter/scene tree with a table of contents and Markdown-rendered scene contents.

**Codex entry** — a reference sheet for a story entity: a **character**, **location**, or
**organization**. All three share one `codex_entries` table, distinguished by a `type` enum
(`CodexEntryType`); an entry has aliases, tags, media, and temporal attribute values. The
kind is a route segment (`/projects/{project}/codex/characters`, `.../locations`, `...`).

**Attribute definition** — a named, reusable attribute (`codex_attributes`, e.g. "Hair
color", "Frescoes") the project owner defines once. Its `applies_to` array decides which
entry types show it on their sheet. Distinct from its *values*, which are temporal (below).

**Attribute period / step function** — an attribute's value over time is a **start-anchored
step function**: each `codex_attribute_values` row means *"from this event onward, the value
is X."* A **period** runs from its anchor until the next one (or the *End* event); there is no
stored end, so periods tile the timeline with no gaps or overlaps. The value "as of" a moment
is the anchor whose datetime is the greatest ≤ that moment. See
[attribute timeline](architecture.md#attribute-definitions-and-the-step-function).

**Anchor event** — the `Event` a period is pinned to (`start_event_id`): the value takes
effect *from* that event. Anchors are ordered canonically by `(event_datetime, events.id)` —
never datetime alone, since two events may share a datetime.

**Baseline (Start-anchored value)** — the mandatory period every valued (entry, attribute)
pair has anchored at the project's **Start** event. It guarantees no leading hole, so
resolution is total for any moment ≥ Start. Created by `AttributeTimeline::ensureBaseline()`,
and `upsertAt()` calls it automatically whenever a non-Start period is stored — so no write
path can leave a leading hole. Like the main plotline, it cannot be deleted while later periods
exist (the guard returns a `403`).

## Design patterns & Laravel concepts

**Aggregate** (domain-driven design) — a cluster of related objects treated as a unit for data
changes, with one entity as the root. Here, `Project` is the aggregate root for the manuscript
hierarchy; you authorize and reason about scenes *through* their project.

**Policy** — a class holding authorization logic for a model (`app/Policies/ProjectPolicy`).
Controllers call `$this->authorize('update', $project)`; the policy returns `true`/`false`.
Child resources authorize against the parent project's policy rather than defining their own.

**Form Request** — a dedicated request class (`app/Http/Requests`) that holds `authorize()` and
`rules()`. Type-hinting it in a controller action runs authorization + validation automatically;
`$request->validated()` returns only the permitted fields. This keeps validation out of
controllers.

**Custom validation rule** — a reusable rule object in `app/Rules` (e.g. `ValidMarkdown`) used
inside a Form Request's rules array.

**Service class** — a plain class in `app/Services` holding a reusable, multi-step domain
workflow that is too involved for a controller and is not a model lifecycle invariant.
Introduced with the Codex: `AttributeTimeline` (temporal value resolution + gap-free
mutations) and `CodexMediaService` (file storage, single-cover rule, disk cleanup). Kept out
of `booted()` hooks deliberately — the seeder runs `WithoutModelEvents` yet must call them.
Follow the guideline: **don't add a service before there is a real second caller.**

**Model lifecycle hook (`booted()`)** — Eloquent event listeners registered in a model's
`booted()` method (`creating`, `created`, ...). Used here to enforce **invariants**:
auto-assigning `position` and auto-creating the main plotline. Distinct from application
workflow, which does *not* belong in models.

> [!WARNING]
> Lifecycle hooks are suppressed when a seeder uses `WithoutModelEvents`. See the seeding
> caveat in [architecture](architecture.md#act--chapter--scene-ordering).

**Backed enum** — a PHP enum with a scalar value (`enum SceneStatus: string`). Stored as a
string column, cast on the model, validated with `Rule::enum()`, and given a human label via a
`label()` method. See [architecture](architecture.md#enum-convention).

**Shallow nested routes** — `Route::resource(...)->shallow()`: list/create/store live under the
parent (`/projects/{project}/scenes`), while edit/update/destroy are flat (`/scenes/{scene}`)
because the child id alone resolves the route.

**Blade component** — a reusable view fragment in `resources/views/components`, invoked as
`<x-scene-status-badge />`. The reuse layer for buttons, badges, table rows, and icon links.

**Eager loading** — pre-fetching relations with `->with('chapter.act')` to avoid the **N+1
query problem** (one query per row when a relation is accessed in a loop).

**N+1 query problem** — the performance bug where rendering a list of N records triggers 1
query for the list plus 1 per record for a lazily-accessed relation. Fixed by eager loading the
relations a view will render.

**Factory** — a class in `database/factories` that builds model instances with fake data for
tests and seeders (e.g. `Scene::factory()->for($chapter)->create()`).
