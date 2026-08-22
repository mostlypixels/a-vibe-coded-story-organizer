# Onboarding — data model

## `projects.genre`

- New nullable string column on `projects`. Backed by a `Genre` enum cast.
- Add `genre` to `Project::$fillable` and `$casts` (`'genre' => Genre::class`).
- Nullable: projects made before this feature, and Blank-genre projects, have `null`.

## `Genre` enum (`app/Enums/Genre.php`)

String-backed, mirrors `CodexEntryType`'s shape (`label()`, `cases()`):

```
Contemporary, Historical, Fantasy, ScienceFiction, Blank
```

- `Blank` is a real case (stored) meaning "no bundle". It carries no attributes/tags.
- `label()` for the picker copy. No `routeKey()` needed — genre is not a URL segment.

## Bundle content (`app/Support`)

A bundle is fixed preset data for one genre. Model it as support classes (like
`PlotlineColors`), keyed by `Genre`. Each bundle declares:

| Part | Shape |
| --- | --- |
| Attributes | name, `applies_to` (list of `CodexEntryType`) |
| Tags | list of names |
| Sample entries | type, name, description, tag names, per-attribute Start values |
| Skeleton | acts, each with chapters (names only) |

- Sample-entry attribute values are keyed by attribute name and set at Start only.
- Blank returns empty lists everywhere.

## What already exists (do not recreate)

`Project::created` (model hook) already makes: the first book (unnamed), the main plotline,
and the Start/End bookend events. The seed action runs **after** create, so it must not
duplicate these. It seeds onto the existing first book and reads `Project::startEvent()`
for baselines.

> [!WARNING]
> `Project::created` fires only when model events are on. The seed action assumes events
> are on (web request and normal `artisan` both qualify). Do **not** call it inside a
> `WithoutModelEvents` seeder — the book, plotline, and bookends would be missing.

## Seeding rules (from `documentation/features/codex.md`)

- Set `CodexAttribute::position` explicitly per project (the model hook does it on create;
  fine here since events are on).
- Create attribute values through `AttributeTimeline` (`ensureBaseline` / `upsertAt` at the
  Start event), never direct writes — keeps the leading-anchor invariant.
- Run `SceneReferenceMatcher::syncProject()` last, only if the bundle seeds scenes.

## Demo install

- Melusine seeders (`MelusineSeederEn/Fr/It`) are idempotent (`firstOrCreate` guards) and
  already accept an existing user. Reuse them unchanged; only their caller moves.
- `DatabaseSeeder::run()` drops the three Melusine calls and `SecondUserSeeder`. It keeps
  the admin-user block. The demo becomes a separate command / onboarding action.
