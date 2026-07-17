# Testing — per-finding test plan

Conventions (`.claude/guidelines.md`): every bug fix ships a test that **fails before the
fix**; plain PHPUnit + `RefreshDatabase` + factories + `actingAs` + `route()`; cover happy
path, 403 for non-owner, `assertSessionHasErrors`, and the touched invariant. Existing files
to extend: `tests/Feature/AttributeTimelineTest.php`, `CodexMediaTest.php`,
`CodexEntryTest.php`, `ProjectTest.php`. No new test files are strictly needed except
possibly reusing `ProfileTest.php` for the account-deletion purge.

## Finding 1 — gap-free invariant on period store (`AttributeTimelineTest`)

- **Amend `test_store_creates_a_period` (line 203)** — it currently codifies the hole. After
  posting the Halloween period on a never-valued pair, additionally assert a Start-anchored
  row exists:

  ```php
  $this->assertDatabaseHas('codex_attribute_values', [
      'codex_entry_id' => $entry->id,
      'codex_attribute_id' => $attribute->id,
      'start_event_id' => $project->startEvent()->id,
      'value' => '',
  ]);
  ```

- New service-level test: `upsertAt` at a mid-timeline event on an unvalued pair →
  `valueAt($momentBeforeAnchor)` is non-null (returns the `''` baseline), i.e. no hole.
- Guard the no-double-write case: `upsertAt` at the **Start** event on an unvalued pair
  creates exactly **one** row carrying the posted value (not a `''` baseline overwritten —
  assert count 1 and value).

## Finding 2 — media purge on project/user delete (`CodexMediaTest`, `ProjectTest`/`ProfileTest`)

Template: `CodexMediaTest::test_destroying_an_entry_removes_all_its_media_files` (uses
`Storage::fake('public')`).

- `test_destroying_a_project_removes_codex_media_files`: project with ≥2 entries, each with a
  cover + reference file → `delete(route('projects.destroy', $project))` →
  `Storage::disk('public')->assertMissing($path)` for every path, and rows gone (cascade).
- `test_deleting_the_account_removes_codex_media_files`: same setup, Breeze
  `delete(route('profile.destroy'), ['password' => 'password'])` → files missing.
- Negative case: non-owner deleting the project already 403s via `ProjectPolicy` — existing
  coverage in `ProjectTest`; no new negative needed for the hook itself.

## Finding 3 — disk I/O vs transaction boundary (`CodexMediaTest`)

Deterministic failure injection is the hard part. Options, most-practical first:

1. **Partial-failure upload test**: update an entry posting `remove_media[]` for an existing
   image **and** an invalid later step is hard to trigger post-validation — instead test the
   observable contract:
   - after a *successful* update with removals + uploads, old files missing, new files
     present (regression guard for the reshuffle);
   - unit-test `CodexMediaService::store()`'s cleanup: mock/replace the model save to throw
     (e.g. drop the `codex_media` table mid-test or use a saving-listener that throws) and
     assert the just-written file was unlinked.
2. **Row/file consistency test for removals**: make the post-removal step throw via an
   `Event::listen`/model observer registered in the test that throws on `CodexMedia` creation,
   post an update that removes image A and uploads image B → assert image A's **row still
   exists and its file still exists** (rollback restored the row; the file must not have been
   deleted pre-commit). This is the test that fails before the fix.

If option 2 proves too contrived, document the gap in the test file and rely on 1; but try 2
first — it is exactly the review's failure scenario.

## Finding 4 — frozen bookend datetimes (`ProjectTest` or a new `EventTest` section)

- `test_fixed_event_datetime_cannot_be_changed`: owner PATCHes the Start event with a new
  `event_datetime` → `assertSessionHasErrors('event_datetime')` (rule `prohibited`), datetime
  unchanged in DB; title edit still succeeds.
- Regression guard for the invariant chain: after attempting the edit, `Project::startEvent()`
  still resolves to the original Start event.

## Finding 5 — timeline editor errors & empty values (`AttributeTimelineTest`)

- `test_store_allows_an_empty_value`: post `value => ''` at the Start anchor →
  row persists with `''`, redirect without errors (fails today: `required`).
- `test_store_without_an_event_shows_a_validation_error`: post the add-period form with
  `start_event_id => ''` → `assertSessionHasErrors('start_event_id')`.
- View-level check (cheap, worth it): after an invalid store, following the redirect to
  `codex.edit` shows the message — `get(route('codex.edit', $entry))->assertSee(...)` or at
  minimum assert the error key exists in session. (Blade rendering of `x-input-error` is
  otherwise untested; the `assertSee` variant actually catches the missing-render bug.)

## Finding 6 — controller-passed tags (`CodexEntryTest`)

- Extend the existing create/edit page tests: `assertViewHas('projectTags')` containing the
  project's tags ordered by name. (Before the fix the view key doesn't exist — fails first.)

## Finding 7 — wildcard upload errors (`CodexMediaTest`)

- `test_second_invalid_reference_image_error_is_visible`: post one valid + one oversized
  image → `assertSessionHasErrors('reference_images.1')`, then follow the redirect and
  `assertSee` the message text on the form page (this is the part that fails before the
  Blade fix).

## Finding 8 — `Project::startEvent()/endEvent()` (`ProjectTest`)

- `test_start_and_end_event_helpers_resolve_the_bookends`: fresh project →
  `startEvent()` is the `is_fixed` year-0000 event, `endEvent()` the year-3000 one.
- Tie-break determinism: two fixed events sharing a datetime (only constructible directly in
  the test) resolve by lowest/highest id — mirrors the existing canonical-order tests in
  `AttributeTimelineTest`.

## Finding 9 — enum-derived routes/nav (route + smoke tests, `CodexEntryTest`)

- Existing tests already cover each type's index resolving and unknown type 404 — keep them
  green (they are the regression net for the constraint refactor).
- `test_navigation_lists_every_codex_type`: authenticated GET of a project page →
  `assertSee` each `pluralLabel()` (guards the nav loop rendering all enum cases).
- Unit-ish: `CodexEntryType::routeKeys()` returns exactly
  `['characters', 'locations', 'organizations']` (pins the contract `fromRouteKey` and the
  route constraint share).

## Suite hygiene

- Run `composer test` (in-memory SQLite; migrations fresh per run).
- `vendor/bin/pint` before committing.
- The seeder path (`MelusineSeeder` calling `AttributeTimeline` directly) is exercised by
  seeding tests if any exist — finding 1's `upsertAt` change affects the seeder too; re-run
  `php artisan db:seed` locally once to confirm idempotency still holds.
