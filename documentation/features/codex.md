# Codex

[Documentation](../README.md) › [Features](README.md) › Codex

## Entry model

Characters, locations, and organizations share `codex_entries`. `App\Enums\CodexEntryType` supplies the type, route key, and labels.

- One `CodexEntryController` serves all types.
- Route constraints and navigation derive from the enum.
- Aliases, tags, attributes, and media belong to an entry.
- The cover is the media row in the `Cover` collection. There is no second `cover_media_id` source of truth.

## Temporal attributes

An attribute definition lists the entry types it applies to. Attribute values form a start-anchored step function.

- A row means “use this value from this event onward.”
- A period ends at the next anchor or the project End event.
- Resolution selects the latest anchor at or before the target time.
- Canonical event order is `(event_datetime, id)`.
- When resolving at an event, an anchor with the same event ID wins.

`Project::startEvent()` and `endEvent()` are the only sentinel definitions. `WithinEventWindow` keeps all regular events between them and prevents a bookend edit from crossing existing events.

### Leading-anchor invariant

Every entry and attribute pair that has a value has one value at Start. `App\Services\AttributeTimeline` enforces this rule on every write.

| Method | Purpose |
| --- | --- |
| `valueAt()` | Resolve a value at an event or time |
| `ensureBaseline()` | Create the Start value |
| `upsertAt()` | Create or replace an anchored value |
| `removeAt()` | Remove a value without breaking the baseline |

An empty string is a valid recorded value. The request accepts `present`, `nullable`, and `string`; the controller converts `null` back to an empty string after `ConvertEmptyStringsToNull` runs.

> [!IMPORTANT]
> Keep the invariant in `AttributeTimeline`, not a model hook. Seeders use `WithoutModelEvents` and call the service directly.

## Media

`App\Services\CodexMediaService` owns paths, names, positions, cover replacement, and file deletion.

- Delete files before a database cascade removes the path rows.
- `Project::deleting` purges project media because database cascades do not fire entry hooks.
- `User::deleting` deletes projects through Eloquent so project cleanup runs.
- Keep disk I/O outside database transactions.

Post-commit upload failure can save an entry with fewer files than requested. This is safer than rolling back the database after disk changes.

## Scene references

`App\Services\SceneReferenceMatcher` stores derived entry mentions in `scene_codex_entry`.

- Match entry names and aliases in raw scene Markdown.
- Match whole words with case-sensitive Unicode rules.
- Exclude aliases shorter than three characters.
- Treat hyphens as part of a word.
- Normalize both sides to Unicode NFC.
- Replace the complete pivot set with `sync()`.
- Log malformed UTF-8 and return no matches. Do not block a save.

Entry search is different. It uses a case-insensitive SQL substring match to help users find entries.

> [!IMPORTANT]
> Keep reference matching in the service. Entry updates can require a project-wide rescan, and seeders must call it directly.

Normal edits synchronize references automatically. `codex:sync-references` and the project edit action are recovery tools.

## Seeder requirements

Seeders must:

1. Set attribute positions explicitly.
2. Call `AttributeTimeline` for temporal values.
3. Call `SceneReferenceMatcher::syncProject()` after scenes and entries exist.

## Related documentation

- [Architecture](../architecture/README.md)
- [Revisions](revisions.md)
- [Archive format](../export-import/archive-format.md)
