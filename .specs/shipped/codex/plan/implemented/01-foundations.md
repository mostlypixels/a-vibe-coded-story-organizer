# Codex plan — 01 · Foundations: enums, migrations, models, factories

## Goal

All Codex tables, enums, Eloquent models, and factories exist so every later task builds on a stable schema. No routes, controllers, or UI yet.

## Depends on

Nothing (first task).

## Spec references

- [`../data-model.md`](../data-model.md) — the authoritative schema.
- [`../overview.md`](../overview.md) — related conventions.

## Files to create

### Enums (`app/Enums`)

- `CodexEntryType.php` — string-backed: `Character = 'character'`, `Location = 'location'`, `Organization = 'organization'`. Methods: `label()`, `pluralLabel()` (mirror `app/Enums/SceneStatus.php:label()`), `routeKey()` returning `characters`/`locations`/`organizations`, and a static `fromRouteKey(string)` for the `{type}` route segment (task 03).
- `CodexMediaCollection.php` — `Cover = 'cover'`, `ReferenceImage = 'reference_image'`, `ReferenceFile = 'reference_file'`.

### Migrations (one per table, in this order)

1. `create_codex_entries_table` — `project_id` (`constrained()->cascadeOnDelete()`), `type` string, `name`, `description` nullable text, timestamps. Index `['project_id', 'type']`. **No `cover_media_id`** (decided — cover is a hasOne on the media collection).
2. `create_codex_aliases_table` — `codex_entry_id` cascade, `alias` string.
3. `create_tags_table` — `project_id` cascade, `name`; unique `['project_id', 'name']`.
4. `create_codex_entry_tag_table` — `codex_entry_id` + `tag_id`, both cascade, unique pair.
5. `create_codex_media_table` — `codex_entry_id` cascade, `collection` string, `path`, `original_name`, `mime_type`, `size` unsignedBigInteger, `position` unsignedInteger, timestamps.
6. `create_codex_attributes_table` — `project_id` cascade, `name`, `applies_to` json, `position` unsignedInteger, timestamps.
7. `create_codex_attribute_values_table` — `codex_entry_id` cascade, `codex_attribute_id` cascade, `start_event_id` `constrained('events')->cascadeOnDelete()`, `value` text, timestamps. **Unique** `['codex_entry_id','codex_attribute_id','start_event_id']` (backstop only — store is an upsert, task 06). Index `['codex_entry_id','codex_attribute_id']`.

### Models (`app/Models`)

- `CodexEntry` — cast `type => CodexEntryType::class`; relations `project()`, `aliases()`, `tags()` belongsToMany, `media()` hasMany, `attributeValues()` hasMany, `cover()` **hasOne** `CodexMedia` filtered `where('collection', CodexMediaCollection::Cover)`.
- `CodexAlias` — thin; `entry()` belongsTo.
- `Tag` — `project()`, `entries()` belongsToMany.
- `CodexMedia` — cast `collection => CodexMediaCollection::class`; `entry()` belongsTo; `creating` hook assigning `position = max(position)+1` scoped to **(entry, collection)** — pattern from `Scene::booted()`, but note the extra collection scope.
- `CodexAttribute` — cast `applies_to` to an array of `CodexEntryType` (`AsEnumCollection` or plain `'array'` + accessor); `project()`, `values()`; helper `appliesTo(CodexEntryType $type): bool`; `creating` position hook scoped to project.
- `CodexAttributeValue` — `entry()`, `attribute()`, `startEvent()` belongsTo `Event`. No ordering logic here (canonical ordering lives in the service, task 02).

### Factories (`database/factories`)

`CodexEntryFactory` (default `character`; states `->character()` / `->location()` / `->organization()`), `CodexAliasFactory`, `TagFactory`, `CodexMediaFactory` (states per collection), `CodexAttributeFactory` (default `applies_to` all three types), `CodexAttributeValueFactory` (needs an event — default to the project's Start via a configuring closure or explicit `for()`).

## Key decisions already made

Single table + type enum; JSON `applies_to`; no `cover_media_id`; unique triplet is a backstop; positions scoped per parent (+collection for media).

## Tests

No dedicated test file — coverage arrives with the features (tasks 02–07). Sanity: `composer test` still green (migrations run fresh in the in-memory SQLite suite), and a quick `php artisan migrate:fresh` locally.

## Done when

Migrations run fresh without error, all factories build valid models (spot-check via tinker or the task-02 tests that follow immediately), pint clean.
