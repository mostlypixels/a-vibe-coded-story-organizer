# Data model

## Tables

`note_categories`

| Column | Type | Notes |
|---|---|---|
| `project_id` | FK `projects` | cascade on delete |
| `parent_id` | FK `note_categories`, nullable | null on delete — a safety net; the service moves children first |
| `name` | string(255) | unique among siblings, checked in the request (SQLite treats null parents as distinct) |

Index: `(project_id, parent_id)`.

`notes`

| Column | Type | Notes |
|---|---|---|
| `project_id` | FK `projects` | cascade on delete |
| `note_category_id` | FK `note_categories`, nullable | null on delete |
| `title` | string(255) | required |
| `body` | longText, nullable | sanitized rich HTML |
| timestamps | | `updated_at` sorts the index |

Index: `(project_id, note_category_id)`, `(project_id, updated_at)`.

`notables` (polymorphic pivot)

| Column | Type | Notes |
|---|---|---|
| `note_id` | FK `notes` | cascade on delete |
| `notable_type` | string | model class name, same convention as `revisions.revisionable_type` |
| `notable_id` | unsigned big int | no FK possible |

Unique `(note_id, notable_type, notable_id)`. Index `(notable_type, notable_id)` for the Notes card.

## Models

- `App\Models\Note` — `HasRevisions`, `SanitizesRichHtml`; `project()`, `category()`, and one
  `morphedByMany` per linkable type (`scenes()`, `chapters()`, …). `revisionProject()` returns `project`.
- `App\Models\NoteCategory` — `project()`, `parent()`, `children()`, `notes()`. `depth()` walks
  `parent` (max 3 queries).
- `App\Models\Notable` — pivot model. Holds `deleteFor(Builder $query)`, the same shape as
  `Revision::deleteFor()`.
- `App\Models\Concerns\HasNotes` — trait on the seven linkables: `notes()` (`morphToMany`) and a
  `deleted` hook that deletes the entity's `notables` rows.
- `Project` gets `notes()` and `noteCategories()`.

## Linkable types

`App\Enums\NoteLinkType` is the single list: `book`, `act`, `chapter`, `scene`, `event`,
`plotline`, `codex`. Per case: model class, label, show route, and a query scoped to a project.
Routes, validation, the picker and the archive all read it.

## Invariants

- A link joins a note and an entity of the same project. Checked in the request, not the model.
- A category's parent is in the same project, is not itself, and is not a descendant.
- Category depth ≤ 3.

> [!WARNING]
> A database cascade skips the `HasNotes` hook. Book, Act and Chapter `deleting` must call
> `Notable::deleteFor()` for their cascaded children, next to their `Revision::deleteFor()` calls.
> Otherwise dead `notables` rows point at deleted scenes. Project delete is safe: `notes` cascade,
> and `notables` cascade from `notes`.

## Starter categories

Not a `Project::created` hook. A project is valid with no categories, and the importer and
seeders must not get them. `App\Services\StarterNoteCategories::createFor(Project)` runs from
`ProjectController@store` and `OnboardingController@store`. The six names live in a constant there.

## Scene notes migration

One migration, query builder only (no models, so no hooks):

1. For each scene with a non-blank `notes`: insert a note (project from the scene's book;
   title `Notes: <scene name>`; body copied as is, it is already sanitized; no category),
   then a `notables` row to the scene.
2. Delete `revisions` rows with `revisionable_type = Scene` and `field = 'notes'`. The field is
   gone, so History could not show them.
3. Drop `scenes.notes`.

Pre-V1 data is demo only, so `down()` re-adds the column empty.

## Seeding

- `LongNovelSeeder` and `database/seeders/data/long-novel.json` stop writing `notes`.
- The demo seeders create a few notes, categories and links, so the Notes page is not empty in
  dev. Use `Notable` rows directly (`WithoutModelEvents`).
