# Onboarding — plan overview

The manual for this feature's tasks. Never implemented or moved.

## Task order

| # | Task | Purpose |
| --- | --- | --- |
| 01 | genre-enum-and-column | `Genre` enum + nullable `projects.genre` column, cast, fillable |
| 02 | bundle-classes | One support class per genre bundle + a contract + a resolver |
| 03 | genre-seed-action | Shared action: create project, apply the genre bundle (events on) |
| 04 | seed-project-command | `app:seed-project` — thin wrapper over the action |
| 05 | seeders-accept-a-user | Melusine + SecondUser seeders run for a given user |
| 06 | install-demo-command | `app:install-demo` — Melusine for a user, events off |
| 07 | install-test-fixtures-command | `app:install-test-fixtures` — second user + demo |
| 08 | db-seed-cleanup | `db:seed` admin-only; Makefile targets; seeder tests move |
| 09 | onboarding-controller | Routes, request, show/store/demo actions, hint flash |
| 10 | onboarding-view | Rework the page: explainers, genre picker, demo, skip |
| 11 | post-seed-hint | Dismissible banner on the project home |

## Binding decisions (do not re-litigate)

- Genre is a stored label only in v1. No behavior driven by it. (Later: maybe epub
  metadata — not now.)
- Bundles ship **thin** (1–2 attributes + tags each). Full content is a later pass, out of
  this plan's scope.
- `Genre` enum in `app/Enums`. One support class per bundle in `app/Support`, behind a
  shared contract, resolved from the enum. `Blank` is a real case that yields empty lists.
- Two install commands, kept apart:
  - `app:install-demo` = Melusine only, run with model events **off** (`WithoutModelEvents`).
  - `app:install-test-fixtures` = `SecondUserSeeder` + the demo (calls install-demo's path).
- `LongNovelSeeder` is local-only and gitignored. **Never reference it** from committed
  code — the class does not exist in CI or a fresh clone.
- `db:seed` creates the admin user only. No demo, no second user.
- Makefile: `make seed` / `make fresh` run `db:seed` then `install-test-fixtures`. New
  `make demo` runs `install-demo`.
- Genre seed runs with model events **on**: `Project::created` builds the book, main
  plotline, and Start/End bookends; the bundle layers on top.
- Skip reuses the create path with `genre = Blank`. One seed path.
- One page, no wizard.
- A user only ever seeds/installs for themselves in the web flow. Never take a user id from
  request input.

## Invariants every task must keep

- **Leading-anchor.** Every seeded attribute value has a Start value. Write values only
  through `AttributeTimeline` (`ensureBaseline` / `upsertAt` at `Project::startEvent()`).
  See `expanded/data-model.md` and `documentation/features/codex.md`.
- **No double book.** The genre seed relies on the `created` hook for the first book; it
  must not create a second. The demo seeders build their own book and so must run with
  events off.
- **Scene references.** If any bundle seeds scenes, run `SceneReferenceMatcher::syncProject`
  last. Thin v1 bundles seed no scenes.
- **Attribute position** is set per project (the model hook does it when events are on).
- **Authorization.** Onboarding routes sit behind `auth`; the acting user is always the
  owner. No policy needed, but never trust input for the user.
