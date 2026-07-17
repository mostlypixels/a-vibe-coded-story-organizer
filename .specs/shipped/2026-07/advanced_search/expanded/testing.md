# Testing

New `tests/Feature/SearchTest.php`, following the existing style (`ProjectTest.php` /
`EventTest.php`): plain PHPUnit, `RefreshDatabase`, factories, `actingAs($user)`,
`route('projects.search.index', $project)`.

## Coverage

* **Happy path**: seed one of each searchable entity (`Act`, `Chapter`, `Scene`, `Event`,
  `Plotline`, `CodexEntry`) with a known distinctive term in different fields (e.g. name vs.
  description vs. `Scene::contents` vs. `Scene::notes`), search for that term, assert every
  entity type shows up in its expected section/column with the right `fieldLabel`.
* **Authorization**: a non-owner's project search returns 403 (standard `ProjectPolicy::view`
  negative-case test, per `CLAUDE.md` § Testing).
* **Empty query**: `GET .../search` with no `q` renders the form with no results section (not
  a validation error, not a 500).
* **Validation**: `mode` outside the `SearchMode` enum values → `assertSessionHasErrors`
  (`Rule::enum`, per `CLAUDE.md` § Input validation).
* **AND mode**: two terms where only some entities contain *both* — assert only those entities
  match.
* **OR mode**: same fixture — assert entities containing *either* term match (superset of the
  AND-mode result).
* **Exact-phrase mode**: a multi-word phrase — assert only rows containing the phrase
  verbatim match (word-order-sensitive, unlike AND mode).
* **Cross-project isolation**: an entity belonging to a different project with a matching term
  must not appear (the `project_id` / parent-chain scoping in `architecture.md` → *Query
  shape*).
* **Highlighting**: assert the rendered snippet contains `<mark>` around the matched term and
  that surrounding user-supplied content is HTML-escaped (feed a term containing `<script>`-like
  text into a scene's `contents` and assert it's not executable in the response — the
  "escape output" rule in `CLAUDE.md` § Security, and the deliberate `{!! !!}` noted in
  `ui.md`).
* **`Scene.contents`/`Scene.notes` searched, not rendered output**: assert a Markdown-only
  substring (e.g. raw `**bold**` asterisks) is matchable, confirming search runs against the
  stored value per `architecture.md`'s note, not `Scene::renderedContents`.
* **No N+1**: assert query count stays flat regardless of how many rows match per entity type
  (`assertQueryCountLessThan` if the test suite already has a helper for this — check
  `tests/Feature/StoryTest.php`/`AttributeTimelineTest.php` for an existing pattern before
  introducing a new one).

## Edge cases from the domain invariants

* A codex entry's `type` still renders correctly in the Codex column grouping (all three
  `CodexEntryType` cases covered) — reuse `CodexEntryTest.php`'s factory states.
* Deleted/soft-irrelevant edge: none of these models use soft deletes today — confirm search
  doesn't need to account for that (no `SoftDeletes` trait on any of the six models as of this
  writing; re-check before shipping in case that's changed).
