# 01 — Section endpoint

## Scope

- Split `EditorCards::forScene()` into `codex()` and `notes()`. `forScene()` keeps its return shape.
- Move the card loop of `editor/partials/card-section.blade.php` into `editor/partials/cards.blade.php`. The page load uses it.
- Add `GET scenes/{scene}/editor-cards/{type}` (`scenes.editor-cards.index`), `EditorCardController::index()`.
- Update `documentation/features/editor.md`: route row, a "Refresh" list under Cards.
- Deferred to 02: all JS, the icon, the Blade `refreshUrl` config.

## Depends on

- None.

## Key decisions already made

- `{type}` is `codex` or `note`, as a route constraint. Another value is a 404.
- Authorize `update` on the scene's project.
- Each section method runs its own `editor_cards` query, so a notes refresh does not resolve the codex.
- The codex section needs `$scene->event` loaded.

```php
public function codex(User $user, Scene $scene, Project $project): Collection; // CodexCard list
public function notes(User $user, Scene $scene): Collection;                   // NoteCard list
```

## Docs

- `expanded/architecture.md` → Route, Service, Partial, Documentation.

## Tests

- `expanded/testing.md` → Feature (both files).
