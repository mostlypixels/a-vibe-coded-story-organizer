# Codex — Data model

All new tables are **project-scoped** and authorize via the owning `Project` (mirroring `events`, `acts`, etc.). The temporal value tables are described here structurally; the *behavior* (gap-free resolution, event anchoring) lives in [`attribute-timeline.md`](attribute-timeline.md).

## Enums (`app/Enums`)

- `CodexEntryType: string` — `Character = 'character'`, `Location = 'location'`, `Organization = 'organization'`. Add a `label()` and a `pluralLabel()` (matching `SceneStatus::label()`), plus a `routeKey()` (`characters`/`locations`/`organizations`) used in the `{type}` route segment.
- `CodexMediaCollection: string` — `Cover = 'cover'`, `ReferenceImage = 'reference_image'`, `ReferenceFile = 'reference_file'`.

Validate everywhere with `Rule::enum(...)` (guidelines).

## Single-table entity: `codex_entries`

Recommended over three separate tables (see rationale in [`open-questions.md`](open-questions.md)): the columns are identical across types and the *type-specific* data is exactly what the flexible attribute system handles, so one table keeps it DRY.

Migration `create_codex_entries_table`:

| column | type | notes |
|---|---|---|
| `id` | id | |
| `project_id` | foreignId | `constrained()->cascadeOnDelete()` |
| `type` | string | cast to `CodexEntryType` |
| `name` | string | required |
| `description` | text | nullable |
| `timestamps` | | |

There is deliberately **no `cover_media_id` column**: the cover is the `codex_media` row with `collection = Cover` (see Media below). A FK here would be a second source of truth *and* a circular reference (`codex_entries` → `codex_media` → `codex_entries`) that forces awkward migration ordering on SQLite.

Index: `['project_id', 'type']` (every index page filters on exactly this pair).

`App\Models\CodexEntry`:
- `casts`: `type => CodexEntryType::class`.
- Relations: `project()` belongsTo; `aliases()` hasMany; `tags()` belongsToMany; `media()` hasMany `CodexMedia`; `attributeValues()` hasMany `CodexAttributeValue`; `cover()` hasOne `CodexMedia` filtered `where('collection', CodexMediaCollection::Cover)` — single source of truth, no FK column.
- Optional convenience: type-scoped query helpers instead of separate models (KISS). If child models are preferred later, use single-table inheritance via a global scope — flagged in open-questions.

## Aliases: `codex_aliases`

A child table (not a JSON column) so the index `search` can `LIKE`-match aliases portably on SQLite.

| column | type | notes |
|---|---|---|
| `id` | id | |
| `codex_entry_id` | foreignId | `constrained()->cascadeOnDelete()` |
| `alias` | string | required |

`App\Models\CodexAlias` — thin; `entry()` belongsTo. Aliases are sync-managed from a repeatable text input (like plotline checkboxes are managed on the event form). Index search: `->when(search, fn ($q) => $q->where('name','like',…)->orWhereHas('aliases', …))`.

## Tags: `tags` + `codex_entry_tag`

Project-scoped, reusable across entry types.

`create_tags_table`: `id`, `project_id` (`cascadeOnDelete`), `name`. Unique `['project_id','name']`.
`create_codex_entry_tag_table`: `codex_entry_id`, `tag_id` — pivot with `cascadeOnDelete` both sides; unique pair.

`App\Models\Tag`: `project()` belongsTo, `entries()` belongsToMany. On the form, tags are `sync()`ed from `tags[]`; new tag names are `firstOrCreate`d within the project (a small `resolveTags()` controller helper, analogous to `SceneController::resolveHappensDuringEvent`).

> [!NOTE]
> The spec says "tags / categories." v1 treats these as one flat tag taxonomy. If categories must be a *separate* dimension, see [`open-questions.md`](open-questions.md).

## Media: `codex_media`

One table for cover + reference images + reference files; the `collection` enum distinguishes them.

| column | type | notes |
|---|---|---|
| `id` | id | |
| `codex_entry_id` | foreignId | `constrained()->cascadeOnDelete()` |
| `collection` | string | cast to `CodexMediaCollection` |
| `path` | string | path on the `public` disk |
| `original_name` | string | for display/download |
| `mime_type` | string | |
| `size` | unsignedBigInteger | bytes |
| `position` | unsignedInteger | ordering within a collection — the `max(position)+1` `creating` hook (pattern from `Scene`) must scope to **(entry, collection)**, not just the entry, so reference images and reference files each get their own sequence |
| `timestamps` | | |

`App\Models\CodexMedia`: `entry()` belongsTo. **Cover is single** — enforced in the upload service by replacing any existing `Cover` row (delete old row + file, insert new), and surfaced via the `CodexEntry::cover()` hasOne on `collection = Cover`. Deleting media rows must also delete the stored file — do file cleanup in a `deleting` model hook or (preferred) the upload service, not the controller.

> [!WARNING]
> `cascadeOnDelete` on `codex_media` removes the DB rows when an entry is deleted but **not** the files on disk. `CodexEntry` needs a `deleting` hook (or the destroy service) that iterates `media` and calls `Storage::disk('public')->delete(...)` before the cascade runs.

## Attribute definitions: `codex_attributes`

Named `codex_attributes` (not `attributes`) to avoid confusion with Eloquent's `$model->attributes`.

| column | type | notes |
|---|---|---|
| `id` | id | |
| `project_id` | foreignId | `cascadeOnDelete` |
| `name` | string | e.g. "Hair color", "Terrain" |
| `applies_to` | json | array of `CodexEntryType` values; which sheets show this attribute |
| `position` | unsignedInteger | display order on the sheet (reuse `creating` hook) |
| `timestamps` | | |

`App\Models\CodexAttribute`:
- `casts`: `applies_to => 'array'` (or an `AsEnumCollection` cast of `CodexEntryType`).
- Relations: `project()` belongsTo; `values()` hasMany `CodexAttributeValue`.
- Scope/helper `appliesTo(CodexEntryType $type)` for filtering the sheet.

> [!NOTE]
> `applies_to` as a JSON array (not a pivot) is deliberate KISS: there are only three fixed types and filtering happens in PHP/collections. A `codex_attribute_type` pivot is the alternative if you need to query "attributes for characters" in SQL — flagged in open-questions.

## Attribute values (temporal periods): `codex_attribute_values`

The core temporal table — a **step function anchored to a start event**. See [`attribute-timeline.md`](attribute-timeline.md) for the full behavior; the schema:

| column | type | notes |
|---|---|---|
| `id` | id | |
| `codex_entry_id` | foreignId | `cascadeOnDelete` |
| `codex_attribute_id` | foreignId | `cascadeOnDelete` |
| `start_event_id` | foreignId | `constrained('events')->cascadeOnDelete()` — the event this value takes effect from |
| `value` | text | plain text in v1 (e.g. "blonde") |
| `timestamps` | | |

Constraints & indexes:
- **Unique** `['codex_entry_id','codex_attribute_id','start_event_id']` — at most one value per attribute per anchoring event (no overlaps at the same instant). This is a **backstop only**: the store endpoint upserts on this key rather than rejecting duplicates (see [`attribute-timeline.md`](attribute-timeline.md)).
- Index `['codex_entry_id','codex_attribute_id']` — every timeline query loads one attribute's periods for one entry.

`App\Models\CodexAttributeValue`: `entry()`, `attribute()`, `startEvent()` (belongsTo `Event`). Ordering is by the **start event's `event_datetime`, tie-broken by `events.id`** (nothing stops two events sharing a datetime — see the tie-break rule in [`attribute-timeline.md`](attribute-timeline.md)), so timeline queries `->join`/`->with('startEvent')` and sort by `(event_datetime, events.id)`, not by `start_event_id`.

## Invariants

1. **Leading anchor** — for every (entry, attribute) that has any value, exactly one value is anchored at the project's **Start** event (guarantees no leading hole). The service that creates the first value for a pair must anchor it at Start (or create a Start-anchored baseline first).
2. **Gap-free step function** — a value holds from its `start_event.event_datetime` until the next value's start (or the *End* event). Because periods are defined as "the value whose start ≤ t is greatest," deleting a middle anchor never creates a hole — the previous period simply extends. This is why `start_event_id` can safely `cascadeOnDelete`.
3. **Fixed events undeletable** — Start/End are `is_fixed` and cannot be deleted (existing `EventController@destroy` `abort_if`), so the Start anchor can never be orphaned.
4. **Authorization-via-project** unchanged across all new tables.

## Seeding impact (`MelusineSeeder`)

`DatabaseSeeder` runs with `WithoutModelEvents`, which suppresses the `creating` position hooks and any `booted()` defaults. Same caveat that already forces `MelusineSeeder` to set `position` manually and `firstOrCreate` the main plotline applies here:
- Set `position` explicitly on seeded `codex_media` and `codex_attributes`.
- When seeding attribute values, **manually create the Start-anchored baseline** (the invariant-enforcing service won't run under `WithoutModelEvents` if that logic lives in a hook — prefer putting it in the service the seeder can call directly).
- Reference the project's Start event via `firstOrCreate`/lookup, matching how the seeder already handles fixed events.
