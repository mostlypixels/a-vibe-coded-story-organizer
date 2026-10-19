# Architecture

## Layout

| Path | Contents |
|---|---|
| `routes/ai.php` | `Mcp::local('imagoldfish', ImagoldfishServer::class)` |
| `app/Mcp/Servers/ImagoldfishServer.php` | Server name, instructions, tool list |
| `app/Mcp/Tools/` | One class per tool. Each class sets `$name` explicitly. |
| `.mcp.json` | Project MCP config, committed |

- A tool resolves the entity, validates the input, calls a service, and returns text. It holds no domain logic. This is the controller rule from `CLAUDE.md`.
- Read tools carry the `IsReadOnly` annotation. Write tools carry `IsIdempotent` where it is true (updates), and none carry `IsDestructive`.
- Output is Markdown text, not JSON. Claude reads it, and Markdown is shorter. IDs show as `#143`.

## Tool catalog

Read tools take a `type` argument where the shape is the same. This keeps the definitions small. Write tools are one per entity and job, so each permission rule names one job.

### Read

| Tool | Input | Source |
|---|---|---|
| `list-projects` | — | `Project` |
| `project-outline` | `project`, optional `book` | Book → act → chapter → scene tree: ID, name, status, word count. Build with `StoryNumbering` over the complete book. |
| `list` | `project`, `type` (plotline, event, note, note-category, character, location, organization) | Name, ID, short description. Notes group by category. |
| `show` | `type`, `id` (project, book, act, chapter, scene, plotline, event, note, codex) | All readable fields, links, aliases. A scene shows `contents` in full. |
| `scene-context` | `scene`, optional `full` | Port of `scene:review-context` |
| `search` | `project`, `query`, `in` (`SearchDomain`), `mode` (`SearchMode`) | `ProjectSearch::searchDomain()` |

### Write

| Tool | Fields | Path |
|---|---|---|
| `update-project` | name, description | as `ProjectController@update` |
| `update-book` | name, description, language, author, publisher, rights, isbn | as `BookController@update` |
| `create-act`, `update-act` | name, description | as `ActController` |
| `create-chapter`, `update-chapter` | name, description | as `ChapterController` |
| `create-scene` | chapter, name, description, status, event, viewpoint | `SceneSaver::create()`, `contents` empty |
| `update-scene` | name, description, status, event, viewpoint | `SceneSaver::update()` without `contents` |
| `create-plotline`, `update-plotline` | name, description, color | as `PlotlineController` |
| `create-event`, `update-event` | title, description, datetime, `is_fixed`, `is_approximate` | as `EventController` |
| `create-codex-entry`, `update-codex-entry` | type, name, description, aliases | `CodexEntrySaver`, no media |
| `set-codex-attribute` | entry, attribute name, value, start event | as `StoreAttributeValueRequest` path |
| `create-note`, `update-note` | title, body, category, links | as `NoteController` |
| `create-note-category`, `rename-note-category` | name, parent | as `NoteCategoryController` |
| `save-scene-review` | scene, body | `SceneReviewNoteWriter` |

> [!WARNING]
> `SceneSaver::update()` takes the full form. A partial update must keep `contents` and every field the tool does not send. Merge the current values first, or add a narrower method. Never pass a missing `contents` through: it would empty the scene.

## Rules for every write tool

- **Validation.** Reuse the rules of the matching `Store*Request` / `Update*Request`. Move each rule set to a static method on the request, so the controller and the tool share it. Autosaved fields use the `AutosavableFields` registry rule, see `documentation/features/revisions.md`.
- **Revisions.** Revisioned fields go through `RevisionRecorder::recordManualChanges()` with the label `__('Claude')` and the project owner as user. No new `RevisionOrigin` case.
- **Open editor tab.** A tool write changes the field hash. The author's next autosave then gets the 409 conflict and the "Keep mine / Load saved text" choice. This is the existing two-tab behavior. Tools do not take a base hash.
- **References.** Create and update go through `SceneSaver` / `CodexEntrySaver`, so `SceneReferenceMatcher` resyncs as it does for the UI.
- **Transactions.** One tool call is one transaction.
- **Errors.** `ModelNotFoundException` and `ValidationException` become `Response::error()` with a short message.

## Shared logic with the artisan commands

- `ResolvesScene` moves to a support class that returns matches, not console output. The tools and the `scene:*` commands both use it.
- `scene:review-context` moves its text building to a service. The command and `scene-context` both print it.
- The `scene:*` commands stay for now. Remove them when nothing calls them.

## Permissions

`.claude/settings.json`:

- Allow `mcp__imagoldfish__list-projects`, `…__project-outline`, `…__list`, `…__show`, `…__scene-context`, `…__search`.
- Write tools are not listed. Claude Code asks once per tool, and the author can allow each one.
- Remove `Bash(sqlite3 *)`. Add `Bash(bash scripts/db.sh *)` only if the read-only SQL script also ships (see open questions).

## Docker down

`docker compose exec` fails, and Claude Code marks the server as failed at session start. The server instructions and `mcp-server.md` say: run `make up`, then `/mcp` to reconnect. No extra code.
