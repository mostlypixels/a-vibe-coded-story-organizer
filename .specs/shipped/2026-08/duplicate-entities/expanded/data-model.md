# Duplicate entities — data model

**No migration.** The feature writes only rows the schema already supports.

## What each duplicate copies

| Source | Copied | Reset / recomputed | Never copied |
|---|---|---|---|
| `scenes` | `name` (new), `description`, `contents`, `notes`, `status`, `chapter_id`, `event_id` | `position` (see below), `word_count` (the `saving` hook) | `share_token`, `share_expires_at`, `id`, timestamps |
| `event_scene` | same `event_id`s re-attached | — | — |
| `codex_entries` | `name` (new), `description`, `type`, `project_id` | — | `id`, timestamps |
| `codex_aliases` | `alias` | — | — |
| `codex_media` | `collection`, `original_name`, `mime_type`, `size`, `position`, `path` (**freshly copied file**) | — | — |
| `codex_attribute_values` | `codex_attribute_id`, `start_event_id`, `value` | — | — |
| `codex_entry_tag` | same `tag_id`s re-attached | — | — |
| `scene_codex_entry` | — | rebuilt by `SceneReferenceMatcher` | never inserted directly |

> [!WARNING]
> `scenes.share_token` carries a **unique** index. Copying it would fail the insert the second
> time a shared scene is duplicated. A copy is always unshared.

`codex_attribute_values` has `unique(codex_entry_id, codex_attribute_id, start_event_id)`. The
copy is a different entry, so replaying the same `(attribute, start_event)` pairs cannot collide.

Tags are re-attached, never re-created: `Tag::count()` must be unchanged after a duplicate.
`firstOrCreate` on a tag name here would be a bug.

## Position (scenes only)

New `position` = `original.position + 1`, after shifting every sibling with
`position > original.position` down by one, inside the same transaction. Codex entries have no
position.

`position` has no unique constraint and gaps are legal, so a plain `max(position) + 1` append
would also be *valid* — it is simply not what the spec asks for. The shift is one `increment()`
over the sibling scope declared by `HasSiblingPosition::siblingScopeColumn()`.

## Name suggestion

`App\Support\DuplicateName::suggest(string $name, iterable $taken): string`

1. Strip a trailing ` (<n>)` from the source name, so duplicating "Arrival (2)" proposes
   "Arrival (3)", not "Arrival (2) (2)".
2. Try `"<base> (2)"`, incrementing until the candidate is free.
3. Comparison is case-insensitive (`mb_strtolower`), mirroring
   `ProjectGraphImporter::collisionFreeName()`.

Scope of "taken":

| Type | Candidate set |
|---|---|
| Scene | `$project->sceneQuery()->pluck('name')` — every scene in the project, not just the chapter |
| CodexEntry | `$project->codexEntries()->where('type', $entry->type)->pluck('name')` |

Nothing in the database enforces name uniqueness and this feature does not introduce it. The
suggestion is advisory: the submitted name is validated `required|string|max:255` and saved as
typed, collision or not. Blocking here would make Duplicate the only screen in the app with a
uniqueness rule that Create and Edit lack.

## Files

Media files are copied on disk to a freshly generated path
(`Storage::disk('public')->copy($from, $to)`), never referenced by the original's path.

New method: `CodexMediaService::copyFile(string $path): string` — generates the fresh path under
the service's own `DIRECTORY`, keeping the naming knowledge where the rest of it already lives.

An imported metadata-only row has `path === null` and must duplicate as a metadata-only row, not
throw.

## Word counts

`Scene::saved` calls `WordCountSnapshotRecorder::record()` for the created scene, so the project
total rises by the copy's words on the writer's day. This is correct and needs no special
handling — a duplicate really does add words to the project.
