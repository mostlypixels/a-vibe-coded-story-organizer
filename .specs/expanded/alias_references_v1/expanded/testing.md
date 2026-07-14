# Testing — Alias references v1

Follow the existing style: plain PHPUnit, `RefreshDatabase`, factories, `actingAs($user)`,
`route()` helper. Likely homes: a new `tests/Unit/SceneReferenceMatcherTest.php` for the pure
matching logic, plus additions to `SceneTest.php` and `CodexEntryTest.php` for the
integration/HTTP paths.

## Unit: `SceneReferenceMatcherTest`

- Whole-word match: alias `Mel` matches "Mel said hello" but not "melody" (the exact case from
  the source spec).
- Case-insensitive: alias `Mel` matches "MEL was there".
- Matches the entry's `name` as well as its aliases.
- Punctuation-adjacent match: alias matches when directly followed/preceded by punctuation
  (`"Mel," she said` / `(Mel)`), per whatever boundary rule is settled in `open-questions.md`.
- No match across scenes in a different project (two projects, overlapping alias text, only the
  same-project scene links).
- Overlapping aliases: two entries in one project sharing/overlapping alias text (e.g. entry A
  alias "Mel", entry B alias "Melusine") — assert the resolved behavior from
  `open-questions.md` (longest-match-wins or both-link).

## Feature: `SceneTest.php` additions

- Saving a scene whose `contents` mentions a codex entry's alias creates the
  `scene_codex_entry` row (`assertDatabaseHas`).
- Editing a scene to remove the mentioning text and saving again removes the row
  (`assertDatabaseMissing`).
- The scene edit page's response includes the referenced entry (assert the view/HTML, following
  the existing "renders X" test style in this file).
- A non-owner's attempt to view another user's scene edit page still gets `403` (no new gap —
  regression guard).

## Feature: `CodexEntryTest.php` additions

- Saving a scene that mentions an entry, then editing the entry's alias list to no longer
  include the matching term, and re-triggering the project rescan (either via a follow-up scene
  save, or directly asserting `CodexEntryController::update` triggers it — pick based on
  `architecture.md`'s decision) removes the relationship.
- Deleting a codex entry that has referencing scenes cascades the `scene_codex_entry` rows
  (extend `test_destroy_cascades_aliases_tags_and_attribute_values`).
- The codex entry edit page lists referencing scenes in timeline (event datetime) order —
  seed scenes with events in constructed order, assert page order.
- A scene with **no assigned event** still appears in the referencing-scenes list at whatever
  position `open-questions.md` settles (assert it doesn't silently disappear).
- Help text under aliases is present on the edit page (`assertSee`).
- A non-owner is forbidden from the codex entry edit page (existing
  `test_non_owner_is_forbidden_from_every_action` should already cover this — verify it still
  passes, no new test needed unless the new eager-load introduces a new query path).

## Domain invariant to guard

- **Full-resync correctness**: after any scene save or any entry alias/name save, the pivot
  table must exactly equal "every entry whose name/alias whole-word-matches this scene's
  contents, in this scene's project" — no stale rows survive a save. Write at least one test
  that seeds a stale row (attach an entry to a scene that no longer matches) directly via the
  pivot, saves the scene, and asserts the stale row is gone — this catches an implementation
  that adds-only instead of `sync()`-ing.
