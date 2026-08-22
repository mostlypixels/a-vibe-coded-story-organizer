# Onboarding — UI

Rework `resources/views/onboarding.blade.php`. Today it is one card with a "New Project"
button. It becomes the guided flow. Copy is final in `spec.md` → "Onboarding copy".

## Layout

One page, no multi-page wizard needed for v1. Sections top to bottom:

1. **Welcome** heading + intro line.
2. **What's a project? / What are attributes?** short explainer blocks (collapsible or
   plain text). Human copy from the spec.
3. **The form** — project name field + genre picker + primary "Create project" button.
4. **Install demo projects** — secondary action (posts to `POST /onboarding/demo`).
5. **Skip** link — creates a blank project.

## Genre picker

- A radio group, not a `<select>`: options are few and each wants a one-line description.
- Reuse the app's form control components (see `resources/views/projects/create.blade.php`
  and existing `x-` form partials). Do not hand-roll inputs.
- Accessibility (per `CLAUDE.md`): a real `<fieldset><legend>`, radios reachable by
  keyboard, labels tied to inputs, visible focus ring. No mouse-only behavior.
- Blank is a listed option ("Something else / start blank").

## Post-seed hint

- On `projects.show`, a dismissible banner when the `onboarding-seeded` status is flashed.
- Reuse the existing alert/flash component the app already uses for `status` messages.
- Dismiss is client-side only (no persistence needed — the flash shows once).

## Reuse, don't invent

- Buttons: `x-button` (`variant="primary"` / secondary), already used in the current view.
- Card + heading: `x-card`, `x-heading`, already used.
- Empty state: `projects.index` still redirects here when the user has no projects, so this
  page is the de-facto empty state — no `x-table-empty` change needed.
