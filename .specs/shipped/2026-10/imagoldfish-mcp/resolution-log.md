# Imagoldfish MCP — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

An exception log: a task that went to plan gets no entry, because the diff and the task
file already record what was built. Bullets under the headings below, root cause first.

## Feedback & decisions

- Grill, 2026-10-18. These answers override `expanded/` where they differ:
  - Scope: read tools plus 7 write tools only (`update-scene`, `save-scene-review`, `create-note`, `update-note`, `create-codex-entry`, `update-codex-entry`, `set-codex-attribute`). Acts, chapters, books, plotlines, events, note categories, project writes go to a second plan.
  - History: manual revision, label "Claude". No new `RevisionOrigin`.
  - No automatic backup. Document a manual `BackupDatabase` run.
  - `scene-reviewer` moves to MCP. The `scene:*` commands stay, on shared services.
  - `scripts/db.sh` ships and replaces `Bash(sqlite3 *)`.
  - All read and write tools are allowed in `.claude/settings.json`.
  - Generic `show` / `list` read tools, Markdown output.
- `list-projects` printed "1 books". The user asked for the plural fix in task 04.
- `PowerShell(sqlite3 *)` stays in `.claude/settings.json`. Tasks 01 and 08 did not cover it; the user decides.

## Deviations from the spec/plan

- `SceneShowCommand` text is not moved. It is a 3-line loop, and `show` (04) is a generic Markdown tool with its own format.
- `SceneReviewContext` has `lines()` next to `build()`. The existing command tests assert on each console write, so the command must write line by line. `build()` (joined with `\n`) is for the MCP tool.
- `search` prints at most 50 rows and says so. The task named no cap, but a common word in a long book would flood Claude's context. `SearchTool::LIMIT` holds the number.
- No non-owner 403 test for the read tools. Tools act as the project owner and call no policy (see the overview), so there is no 403 path.
- `update-scene` and `save-scene-review` also take an optional `project`, like `scene-context`, to narrow a scene name. The shared lookup and its errors live in `App\Mcp\Tools\Concerns\FindsOneScene`; later scene tools must use it.
- `updateDetails()` does not resync codex references. The matcher reads only `contents`, which the method never changes.
- `saveWithManualCheckpoint()` and `CodexEntrySaver::update()` take an optional `$label`. The saver records its checkpoint inside the recorder, so `update-codex-entry` had no other way to label it "Claude".
- `set-codex-attribute` takes an attribute name or ID. Attribute names are not unique, so several matches give an error that lists the IDs, as note categories do.
- `create-note` requires `body`. The controller allows a null body, so the tool is stricter than the UI.
- `StoreAttributeValueRequest::fieldRules($project)` holds the shared rules without `required` on the start event, so the tool can default it to Start.

## Issues → resolutions

- `CodexEntrySaver::update()` saves the full edit form: a missing `tags`, `description` or lifespan event clears it. `update-codex-entry` fills every field it does not send from the entry. A test checks that tags, aliases and the inception event stay.
- The server listed `CreateNoteTool` and `UpdateNoteTool` twice (task 06). The suite was green, because the library keys tools by name. Fixed, and a test now checks that each tool is registered once.
- `laravel/mcp` throws a protocol error for any exception thrown in `handle()`, because `Tool` is not `Errable`. Claude would see an opaque failure. Fix: tools wrap their body in `ReturnsToolErrors::guard()`, which maps `ModelNotFoundException` and `ValidationException` to `Response::error()`. Later tools must use it.
- A throwaway tool cannot be called through `Server::tool()` in a test: `CallTool` only finds tools the server registers. The error mapping is tested on the trait directly (`tests/Feature/Mcp/ReturnsToolErrorsTest.php`).
- The Docker `vendor` volume is anonymous, so the running container had no `laravel/mcp` after `composer require`. `make rebuild` fixes it for good. For the manual check, `docker compose -f docker-compose.dev.yml exec -T -u laravel app composer install` filled the volume. The stdio handshake and `list-projects` then worked against the Docker database.
- `CountPhrase::make(0, ...)` returned " 0 notes" with a leading space: a count of 0 matches no range in the key, and the fallback text keeps its space. Old callers hid it in HTML. Fix: `trim()` in `CountPhrase`. The MCP text showed it, and a test caught it.
- Rich fields print as stored HTML in `show`, not as plain text, so Claude can edit a field and send it back to a write tool. Lists use a short plain-text form.
- A manual save of a field with no revisions yet writes a baseline row with the *new* value, not the old one, when the old value is empty: `RevisionRecorder::record()` calls `ensureBaseline()` with no pre-edit value. Existing behaviour, not changed. A test that counts revision rows after a first change from empty sees 2, not 1; count `origin = manual` rows instead.
- The server is named `avcso` (project name AVCSO), not `imagoldfish`. Renamed after shipping, at the user's request: `AvcsoServer`, `mcp:start avcso`, tool names `mcp__avcso__<name>`.
