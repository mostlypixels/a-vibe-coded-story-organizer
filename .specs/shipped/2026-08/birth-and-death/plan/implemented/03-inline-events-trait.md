# 03 — Inline events trait

## Scope

- New trait `App\Http\Controllers\Concerns\CreatesInlineEvents` with
  `resolveInlineEvent(Project $project, ?string $title, ?string $datetime, ?int $existingId): ?int`
  — creates the event when a title is given (attached to the main plotline) else returns the
  existing id (may be null). This is exactly today's `SceneController::resolveHappensDuringEvent()`
  behavior, generalized.
- `SceneController` uses the trait and drops its private method. No behavior change for scenes.

Does **not**: change the scene Blade, the codex controller, or add codex fields (tasks 04–05).
Blade partial extraction for the picker is task 05, not here.

## Depends on

Nothing (independent of the new columns).

## Key decisions

- Extract at the real second/third caller, per `CLAUDE.md` and `open-questions.md` #9.
- Keep the main-plotline attach inside the trait so every caller behaves the same.

## Consult

`expanded/architecture.md` → Inline event creation. Existing `SceneController` and
`UpdateSceneRequest`.

## Tests

- The existing scene feature tests must stay green (inline "New event" on scene create/update).
- If none directly covers inline event creation, add one: posting a scene with `new_event_title` +
  `new_event_datetime` creates the event, attaches it to the main plotline, and links the scene.
