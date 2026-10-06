---
title: "Task 03 — JSON appearance update"
---

# Task 03 — JSON appearance update

## Scope

- `AppearanceController@update`: when `$request->wantsJson()`, return `response()->noContent()`.
  Otherwise the current redirect. Return type `RedirectResponse|Response`.
- Docblock: one sentence on why two response shapes exist (the quick switcher saves over AJAX).

Does **not** add the switcher route. Task 04 owns it.

## Depends on

Nothing.

## Key decisions already made

- Same shape as `SceneController::reorderResponse()`.
- No new Form Request. `UpdateAppearanceRequest` serves both callers.
- A failed rule gives Laravel's standard 422 JSON. No custom error body.

Detail: `expanded/architecture.md` → *Routes*, *Controllers*.

## Tests

In `tests/Feature/AppearanceSettingsTest.php`:

- JSON `PATCH` with a valid slug → 204; column saved.
- JSON `PATCH` with a tampered slug → 422 JSON; column unchanged.
- **One-field guard:** a user with every column set sends only `theme_slug`; every font column is
  unchanged. Then only `manuscript_leading`; `theme_slug` is unchanged.
- Guest JSON `PATCH` → 401.
