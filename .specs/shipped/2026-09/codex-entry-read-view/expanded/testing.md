# Testing

Feature tests beside the existing codex tests. Plain PHPUnit, `RefreshDatabase`, factories,
`actingAs()`, named routes.

## `codex.show`

- Renders name, an alias, a tag, the description, and a set attribute value.
- An attribute the project defines but the entry has no value for is **absent** from the
  response.
- Response contains no `name="name"` input and no autosave field — the guard against the
  read page drifting back into a form.
- Inception and termination events appear when set; the lifespan section is absent when neither is.
- Referencing scenes appear in timeline order: scenes with events first by
  `event_datetime`, then unassigned scenes by act, chapter, scene position. Same invariant
  `ReferencingScenes` now owns — assert it against the service directly as well, so the
  extraction is covered once the two callers move.
- A user who does not own the project gets 403.
- A guest is redirected to login.

## Links

- The codex index links an entry name to `codex.show` and keeps the edit icon on `codex.edit`.
- A codex search result links to `codex.show`; a scene result still links to its edit route
  (`SearchDomain::viewRoute()` falls through).
- The scene edit reference sidebar links to `codex.show`.

## Extraction regression

`codex.edit` keeps every existing test green. `CodexAttributeSheets::forEntry()` returns the
same shape `timelineSheets()` did, including the empty attributes — `edit` must not start
hiding them.
