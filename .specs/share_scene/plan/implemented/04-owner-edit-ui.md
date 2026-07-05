# 04 — Owner share UI on the scene edit page

## Scope

The share controls the owner uses, added to the existing scene edit page. Wires the UI to the
task-02 endpoints; adds no new backend behavior.

**Builds** — a "Share this scene" section in `resources/views/scenes/edit.blade.php` (its own
`x-card`, e.g. after the delete form), with two states driven by `$scene->isShared()`:

- **Not shared:** a form POSTing `scenes.share.store` with a **duration `<select>`** populated
  from `config('sharing.scene_link_durations')` (default preselected from
  `scene_link_default_duration` if present), and a `x-primary-button` "Generate share link". Show
  helper text listing the choices.
- **Shared (active link):**
  - The URL (`$scene->shareUrl()`) in a read-only `x-text-input` with a **Copy** button (inline
    Alpine `navigator.clipboard.writeText`, accessible label + "Copied!" confirmation).
  - Expiry shown both absolute (`format('M j, Y H:i')`) and relative (`diffForHumans()`).
  - **Regenerate** — re-POST `scenes.share.store` (rotates token), `x-secondary-button`.
  - **Revoke** — DELETE `scenes.share.destroy`, `x-danger-button`, in its own `@csrf`/`@method`
    form.
- Reuse existing components only (`x-card`, `x-primary-button`, `x-secondary-button`,
  `x-danger-button`, `x-text-input`, `x-input-label`); no new component or table.
- Surface `duration` validation errors (`$errors->get('duration')`) and preserve `old()`.

**Does NOT build:** any new route/controller/request (task 02 owns them) or the public page
(task 03). No scenes-index share affordance (out of scope for this feature).

## Depends on

- **01** (`isShared()`, `shareUrl()`, config) and **02** (the `scenes.share.*` routes) in
  `plan/implemented/`.

## Key decisions already made

- Duration picker from the config whitelist; regenerate rotates; components are reused, not new.
  See `00-overview.md` and `../ui.md`.

## Consult

`../ui.md` ("Owner management UI"), `00-overview.md`.

## Tests (`tests/Feature/SceneShareTest.php`)

- Owner GET of the scene edit page: **unshared** scene shows the "Generate share link" control and
  the duration options; **shared** scene shows the URL (`$scene->shareUrl()`), the expiry, and the
  Revoke control (`assertSee`).
- Non-owner GET of the edit page → 403 (existing scene-edit authorization; assert it still holds).
- Keyboard/accessibility: the copy button has an accessible label (assert the `aria-label`/
  `sr-only` text is present).
