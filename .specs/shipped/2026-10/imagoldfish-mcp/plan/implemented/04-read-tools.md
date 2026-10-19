# 04 — Read tools

## Scope

| Tool | Notes |
|---|---|
| `project-outline` | `project`, optional `book`. Full tree: book → act → chapter → scene, with ID, display number, name, status, word count. Numbers from `StoryNumbering` over the complete book. |
| `list` | `project`, `type` (plotline, event, note, note-category, character, location, organization), optional `offset`. Cap 200 rows, then a "more, offset N" line. Notes group by category. |
| `show` | `type` (project, book, act, chapter, scene, plotline, event, note, codex), `id`. All readable fields. Scene shows full `contents`, event, viewpoint, linked notes. Codex shows aliases and attribute values. |
| `search` | `project`, `query`, `in` (`SearchDomain` values), `mode` (`SearchMode` values). Calls `ProjectSearch::searchDomain()`. |
| `scene-context` | `scene` (ID or name), optional `project`, optional `full`. Uses the service from 03. |

- Fix the counts in `list-projects` (02): "1 book", not "1 books". Use `trans_choice` or `Str::plural`, and apply the same rule to counts in the new tools.
- All carry `IsReadOnly`.
- Eager-load what each output reads.
- Deferred: writes (05–07).

## Depends on

- 02, 03.

## Key decisions already made

- Generic `show` and `list` with a `type` enum, not one tool per entity.
- Scene names resolve with the 03 resolver. Several matches return the list as a tool error.

## Docs

- `expanded/architecture.md` → Tool catalog → Read.
- `expanded/testing.md` → Invariants (outline numbering, search parity).

## Tests

- One test class per tool in `tests/Feature/Mcp/`.
- `project-outline` numbers match `StoryNumbering`.
- `search` returns the same IDs as `ProjectSearch`.
- `list` caps at 200 and the offset works.
- Unknown `type` and unknown ID give tool errors.
- A query-count test for `project-outline` on a seeded book: no N+1.
