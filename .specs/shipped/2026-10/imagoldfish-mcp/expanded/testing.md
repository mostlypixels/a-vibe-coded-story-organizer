# Testing

Use the `laravel/mcp` test helpers (`ImagoldfishServer::tool(Tool::class, [...])`) from plain PHPUnit, with `RefreshDatabase` and factories. One test class per tool group in `tests/Feature/Mcp/`.

## Each write tool

- Happy path: the row changes, and the response names the entity and ID.
- Invalid input: a tool error with the validation message, and no change.
- Unknown ID: a tool error, not an exception.
- A revisioned field records one manual revision, labelled "Claude", credited to the project owner.
- An unchanged value records no revision.

## Invariants to guard

- `update-scene` without `contents` leaves `contents` and `word_count` as they are. This is the test that matters most.
- No tool input schema accepts `contents`, `dedication`, `acknowledgements`, `preface`, `postface`, `is_main` or `position`. One test walks the server tool list and checks each schema.
- No registered tool has the `IsDestructive` annotation.
- `create-scene`, `create-chapter`, `create-act` append at the end of the parent (position).
- `update-codex-entry` with a new alias resyncs the scene references, the same as the UI save.
- Rich HTML input with a `<script>` tag is stored sanitized.
- `project-outline` numbers match `StoryNumbering` for the complete book.
- `search` returns the same IDs as `ProjectSearch` for the same query.

## Shared logic

- The `scene:*` command tests in `tests/Feature/Console` still pass after the move to shared services.
- The refactored Form Request rule methods: the existing controller validation tests still pass.

## Config

- A test reads `.mcp.json` and checks the server name and the Docker command, like `DockerComposeDevTest`.
- A test checks that every read tool name is in the `.claude/settings.json` allow list.
