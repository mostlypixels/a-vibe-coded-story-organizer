# 10 — Onboarding view

## Scope

Rework `resources/views/onboarding.blade.php` into the one-page flow. Sections top to bottom:

1. Welcome heading + intro.
2. "What's a project?" and "What are attributes?" explainer blocks.
3. Form: project name field + genre picker + "Create project" (posts to `store`).
4. "Install demo projects" secondary action (posts to `/onboarding/demo`).
5. Skip link (posts to `store` with `genre = Blank`).

Copy is final in `spec.md` → "Onboarding copy". Wrap all strings in `__()`.

Not in scope: controller/routes (09); the project-home banner (11).

## Depends on

- 09.

## Key decisions

- Genre picker is a **radio group** in a `<fieldset><legend>`, not a `<select>` (each option
  has a one-line description). `Blank` is a listed option.
- Accessibility (CLAUDE.md): keyboard-reachable radios, labels tied to inputs, visible focus.
- Reuse existing components: `x-card`, `x-heading`, `x-button`, and the app's form controls
  (see `resources/views/projects/create.blade.php`). Do not hand-roll inputs.

## Consult

- `expanded/ui.md`.
- `spec.md` → "Onboarding copy".

## Tests

- Feature test: the page renders the genre options, the demo action, and the skip control
  (assert on the copy / control presence).
- `BladeComponentCompilationTest` stays green.
