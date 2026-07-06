# Share scene — Plan overview

This manual is never implemented or moved. It fixes the execution order, the binding design
decisions, and the invariants every task must preserve. Task files (`NN-*.md`) are moved to
`plan/implemented/` as each is finished and verified.

Detail lives in the sibling spec docs: `../expanded/overview.md`, `../expanded/data-model.md`,
`../expanded/architecture.md`, `../expanded/ui.md`, `../expanded/testing.md`,
`../expanded/open-questions.md`.

## Execution order

| # | Task | Purpose |
|---|------|---------|
| 01 | `01-data-model-and-config.md` | Migration (`share_token`, `share_expires_at`), `Scene` casts + `isShared()`/`shareUrl()` helpers, `config/sharing.php` duration whitelist. |
| 02 | `02-share-management.md` | `SceneShareController` store/destroy, routes, `StoreSceneShareRequest` (duration whitelist), owner authorization. |
| 03 | `03-public-display.md` | Public `SharedSceneController@show`, unauthenticated route, `x-public-layout`, `show` + `expired` views. |
| 04 | `04-owner-edit-ui.md` | Share section on the scene edit page: generate (with duration select), copy, regenerate, revoke. |

Dependencies: 02 → 01; 03 → 01; 04 → 01, 02. Tasks 02 and 03 are independent of each other
(03 seeds a token via factory in its tests, it does not need the management endpoints).

## Binding decisions (do not re-litigate)

- **Storage = stored token on `scenes`.** Two nullable columns (`share_token` unique,
  `share_expires_at`). One revocable link per scene. No `scene_shares` table, no signed URLs.
- **Token is never mass-assigned.** Not in `$fillable`; set explicitly in the controller.
- **Numbering = Arabic** `chapter.position` in the public title (`Chapter 1 — {chapter}: {scene}`),
  em-dash separator, matching the rest of the app.
- **Duration = owner picks per share** from a whitelist (24h / 7d / 30d) defined in
  `config/sharing.php`; validated with `Rule::in`. No hard-coded literals.
- **Expired/unknown = friendly branded expired page** (410, `shared/scenes/expired.blade.php`) for
  an expired token; **404** for an unknown token.
- **Public layout = dedicated** `layouts/public.blade.php` (`<x-public-layout>`): wide reading
  column, `<meta name="robots" content="noindex, nofollow">`, no nav.
- **Public fields = title, description (collapsed card), contents only.** `notes`, status, event/
  plotline links, and the codex "as of" panel are **never** on the public page.
- **Contents render path** = `Str::markdown($scene->contents)` (Markdown-only field, same as the
  Story overview). **Description** renders via `<x-rich-text>` (already-sanitized HTML).
- **Regenerate** = re-POST the store route; it rotates the token and resets expiry, invalidating
  the previous URL.

## Invariants every task must preserve

- **Authorization walks up to the project.** Every authenticated action authorizes via
  `ProjectPolicy` through `$scene->chapter->act->project` (owner succeeds, non-owner 403).
  Mirror the check in the Form Request's `authorize()`. The **public** show action is the single
  deliberate exception — no policy; the token is the gate. Comment it as such.
- **The public route lives outside the `auth` group.** It is the only new unauthenticated app
  route. Do not widen the existing `auth` group; every other scene route stays authenticated.
- **`notes` is private.** No task may render `scene.notes` on the public page; a test asserts it
  never appears in the shared HTML.
- **Expired links are inert server-side.** Validity is checked with `Scene::isShared()`
  (token set AND expiry in the future); never trust the presence of a token alone.
- **Config over literals.** Durations come from `config/sharing.php`; no magic strings/numbers.

## Cross-cutting requirements (every task)

- Ship feature tests with each task (happy path + authorization negative + validation failure +
  any invariant touched), runnable in isolation via `composer test`.
- Update `CHANGELOG.md` `## [Unreleased]` per task (Added/Changed).
- Append per-task notes/decisions/issues to `../resolution-log.md`.
- Task 03 also adds a short "Scene sharing" note to `documentation/architecture.md` (the public
  unauthenticated route + token model is architecturally notable for junior devs).
