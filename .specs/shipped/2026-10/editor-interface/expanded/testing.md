# Testing

## Feature tests (PHPUnit)

`tests/Feature/EditorTest.php`

- `projects.write` redirects to the newest-`updated_at` scene, across books.
- `projects.write` with no scenes shows the empty state.
- `scenes.write` renders the autosave field for `scene:<id>:contents` and the recent list with `scenes.write` URLs.
- Recent list marks the current scene with `aria-current`.
- Layout from `users.editor_layout` reaches the page (widths, collapsed flags); null gives defaults.
- Non-owner: 403 on `projects.write` and `scenes.write`.
- Guest: redirect to login.

`tests/Feature/EditorCardTest.php`

- Codex card shows description and attributes as of the given scene's event.
- Codex card with an unassigned scene shows the undetermined state.
- Codex card with `?scene=` of another project: 404.
- Note card shows body; `entity:` links resolve.
- Non-owner: 403 on both cards.

`tests/Feature/EditorLayoutPreferenceTest.php` (model on `PageSizePreferenceTest.php`)

- Valid payload saves; 204.
- Unknown keys dropped; widths out of range rejected (422).
- Writes only the acting user's row.

Also:

- `NavigationTest.php`: "Write" link after Dashboard, desktop and responsive; active on both editor routes; Dashboard not active there.
- `CodexAsOfTest.php`: `resolveEntry()` agrees with `resolve()` for the same entry and moment, including entries before inception or after termination.
- `RecentlyEdited` route parameter: default still `scenes.edit`.
- `EditorLayout::resolve()` unit cases: null, partial, clamped, unknown keys.

## JavaScript tests (Vitest, next to source)

- `editor/layout.test.js`: clamp, keyboard steps, collapse toggle, one debounced PATCH for many changes.
- `editor/cards.test.js`: add, no duplicate, close, collapse, `sessionStorage` round-trip, storage that throws.

## Manual check

- Run on the Les Misérables demo (large codex): search speed, card fetch time, scroll per column.
- Autosave then click a recent scene: no lost text, no conflict.
- Two tabs on the same scene: tab lock still works.
