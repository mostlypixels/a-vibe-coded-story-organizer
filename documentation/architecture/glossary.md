# Glossary

[Documentation](../README.md) / [Architecture](README.md) / Glossary

Use this page for project-specific terms. For implementation details, follow the linked guide.

## Story structure

**Project** — The top-level container. It owns books, plotlines, events, Codex entries, and word-count history. All child access derives from the project's `user_id`.

**Book** — One volume in a project. It owns the manuscript, publication data, publication settings, and EPUB cover. Every project has at least one book. The last book cannot be deleted.

**Book name fallback** — `books.name` can be `null`. In that case, `Book::displayName()` returns the project name. Use `displayName()` at every display site. `Book::hasOwnName()` decides when the interface shows the book layer.

**Act → Chapter → Scene** — The three manuscript levels within a book. Each level has one parent.

**Position** — A stored integer that orders siblings. Positions can have gaps. Move actions change positions; they do not change titles.

**Number** — A gap-free display rank calculated by `StoryNumbering`. Numbers are not stored. They restart for each book. See [ordering and numbering](README.md#manuscript-ordering-and-numbering).

**Story overview** — A read-only view of one book. It renders the manuscript by chapter or as a whole book.

## Timeline and Codex

**Plotline** — A narrative thread. Each project has one main plotline and can have more plotlines.

**Main plotline** — The auto-created plotline with `is_main = true`. It stays first and cannot be deleted.

**Event** — A dated timeline item. An event belongs to a project and can belong to several plotlines.

**Bookend events** — The fixed Start and End events. Other events must remain within their dates. The events cannot be deleted, but their dates can change if they still contain all other events.

**Codex entry** — A character, location, or organization reference. All types use `codex_entries` and the `CodexEntryType` enum.

**Attribute definition** — A reusable Codex field, such as hair color. Its `applies_to` value selects the entry types that use it.

**Attribute period** — One value in a start-anchored step function. The value starts at its anchor event and remains active until the next anchor.

**Anchor event** — The event where an attribute value starts. Anchors use `(event_datetime, id)` order so equal dates remain deterministic.

**Baseline** — The required attribute value anchored to the Start event. It prevents a gap before the first later value. See [Codex attributes](../features/codex.md#temporal-attributes).

**Duplicate** — A copy of one scene or Codex entry and its owned rows. It is not an archive import or a revision.

## Revisions and diffs

**Revision** — An immutable record of one field's former value.

**Save point** — All revisions from one manual save or autosave burst. Each row has the same `save_id` ULID. The interface compares and restores save points, not individual rows.

**Snapshot** — The value of every registered field at a selected time. `RevisionSnapshot::asOf()` resolves it from the latest applicable revision for each field.

**Source diff** — A comparison of stored markup. Use it for Markdown that the writer edits directly.

**Visual diff** — A comparison of rendered output. Use it for rich text whose HTML is hidden from the writer.

**Hunk** — One continuous changed region in a diff.

**Compute at write** — Calculate an immutable result once and store it. Revision summaries use this pattern. A later prune can make the stored predecessor summary stale; this accepted limit avoids expensive recomputation.

**Boundary row** — The extra row fetched after a page of revisions. It provides the predecessor needed to describe the page's last visible row.

See [Revisions](../features/revisions.md) for the complete workflow.

## Application patterns

**Aggregate** — A group of related records changed as one unit. `Project` is the main aggregate root.

**Policy** — A class in `app/Policies` that decides whether a user can act on a model. Child resources authorize through their project.

**Form Request** — A class in `app/Http/Requests` that holds authorization and validation rules.

**Custom validation rule** — A reusable rule object in `app/Rules`.

**Service class** — A class in `app/Services` for a reusable, multi-step workflow. Add one when the workflow has more than one real caller.

**Model lifecycle hook** — An Eloquent event registered in `booted()`. Use hooks for invariants such as position assignment. Seeders that use `WithoutModelEvents` must call invariant services directly.

**Backed enum** — A PHP enum stored as a scalar value. Models cast it, requests validate it with `Rule::enum()`, and the enum supplies its label.

**Shallow nested routes** — Collection actions include the parent in the URL. Member actions use only the child identifier.

**Blade component** — A reusable view fragment in `resources/views/components`.

**Combobox** — A text input with a popup list. It requires combobox keyboard and ARIA behavior. A select-only listbox does not accept free text.

**Eager loading** — Preload relations with `with()` to prevent an N+1 query.

**N+1 query** — One list query followed by one relation query for each result.

**Factory** — A class in `database/factories` that creates test or seed data.
