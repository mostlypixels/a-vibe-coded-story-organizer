# Entity links — data model

## Target type enum

- Rename `App\Enums\NoteLinkType` → `App\Enums\LinkTargetType`. Values do not change, so archives and `notables` rows need no migration.
- Add `Note` (`note`). The structured note-link picker hides it unless note-to-note pivot links are accepted (see open questions).
- Add `path(): string` for the `entity:` path segment if the value is not used directly. Prefer using the value.

## `entity_links` table

Derived index of the links in saved text. It is never edited by hand.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | id | |
| `project_id` | foreign id, cascade delete | scopes backlink queries |
| `source_type` | string | morph class, as `notables` stores it |
| `source_id` | unsigned big int | |
| `source_field` | string | `description`, `body`, `contents` |
| `target_type` | string | `LinkTargetType` value |
| `target_id` | unsigned big int | |

- Unique: (`source_type`, `source_id`, `source_field`, `target_type`, `target_id`).
- Index: (`target_type`, `target_id`) for "what links here".
- No foreign key on source or target: both are polymorphic. The same reason applies as for `notables`.
- Rows exist for missing targets too. The table records what the text says; the resolver decides what exists.

## Invariants

- Each sync replaces all rows of one (source, field) with `sync`-style delete and insert. It never adds rows one at a time. This follows the `SceneReferenceMatcher` rule, so the table cannot drift.
- Deleting a source deletes its rows. Deleting a target deletes the rows that point at it. A link to it in the text renders as missing.
- Cascade deletes fire no model events.

> [!WARNING]
> Book, Act and Chapter cascade to their children. Their `deleting` hooks already call `Notable::deleteFor()` and `Revision::deleteFor()`. Add `EntityLink::deleteFor()` beside them, for sources and targets.

## Seeding

- Seed data has no links. `WithoutModelEvents` skips the save hook, which does no harm here.
- Add one link to the Melusine seed note so the demo shows the feature. The seeder then calls the index sync directly, the same way `SyncsCodexReferences` does.
