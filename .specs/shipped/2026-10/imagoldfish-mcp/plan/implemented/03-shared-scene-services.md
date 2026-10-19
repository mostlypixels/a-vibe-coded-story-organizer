# 03 — Shared scene services

## Scope

- Move the matching in `app/Console/Commands/Concerns/ResolvesScene.php` to a support class. The class returns the matches. Console output stays in the trait.
- Move the text building of `SceneReviewContextCommand` to a service. The command prints the service output.
- Do the same for the `SceneShowCommand` text, if `show` (04) can reuse it.
- Deferred: the MCP tools that call these (04, 05).

## Depends on

- None.

## Key decisions already made

- The `scene:*` commands stay and keep their output byte-identical.
- No behavior change. This is a refactor.

## Docs

- `expanded/architecture.md` → Shared logic with the artisan commands.

## Tests

- `tests/Feature/Console/SceneReviewCommandsTest.php` passes unchanged.
- Unit or feature tests for the new resolver: ID, exact name, partial name, several matches, no match.
