# 08 — UI cleanups (findings 6, 7 + ride-alongs)

## Scope

Four small, independent Blade/controller cleanups bundled into one verifiable task.

1. **Controller-passed tags (finding 6)** — `app/Http/Controllers/CodexEntryController.php`:
   `create()` (`:71-77`) and `edit()` (`:113-122`) add
   `'projectTags' => $project->tags()->orderBy('name')->get()`.
   `resources/views/codex/partials/fields.blade.php`: add
   `$projectTags = $projectTags ?? collect();` to the `@php` block (same guard style as
   `$attributes`, line 7) and change line 85 to `:tags="$projectTags"`. Name is
   `projectTags` (not `tags`) to avoid colliding with `$entry->tags` usage and the index
   view's `tags`.
2. **Wildcard upload errors (finding 7)** — same partial, lines 130-131 and 154-155:
   replace `$errors->get('reference_images.0')` with `$errors->get('reference_images.*')`
   (drop the `.0` line, keep the bare-name lookup for array-level rules); same for
   `reference_files`. Precedent: `codex-attributes/partials/fields.blade.php` renders
   `applies_to.*`. Verify once that `x-input-error`
   (`resources/views/components/input-error.blade.php`) flattens the keyed arrays
   `get('x.*')` returns.
3. **`applies_to` narrowing hint (ride-along)** —
   `resources/views/codex-attributes/partials/fields.blade.php`: one help line under the
   type checkboxes: un-ticking a type hides its existing values from sheets and as-of
   panels but does not delete them (they return if re-ticked). Pure text, `text-sm
   text-gray-500` like the partial's existing hints.
4. **Orphaned-tags dropdown filter (ride-along)** —
   `CodexEntryController@index` (`:59`): the tag-filter dropdown query gains
   `->whereHas('entries')` so tags matching nothing stop appearing. Verify the `Tag`
   model's entries relation name first. **Scope note:** apply only to the index filter
   dropdown, *not* to the form's `projectTags` (a just-created tag with no other entries
   must still autocomplete on the form).

Does **not** touch the timeline editor partial (task 04) or the media save flow (task 06).

## Depends on

Nothing structurally; numbered after 07. (If run before 04/06, coordinate only on
`CodexEntryController` line drift.)

## Key decisions already made

- Q5: hint + orphaned-tags filter are in scope (user-confirmed).
- View-data key `projectTags`; index behavior for its own `tags` variable unchanged apart
  from the `whereHas`.

## Consult

`.specs/refactor_codex/expanded/ui-fixes.md` (§§ Findings 6, 7), `open-questions.md` Q5,
`.specs/refactor_codex/spec.md` lower-priority notes.

## Tests

- Finding 6, in `tests/Feature/CodexEntryTest.php`: extend existing create/edit page tests
  with `assertViewHas('projectTags', ...)` containing the project's tags ordered by name.
  Fails before the fix (key absent).
- Finding 7, in `tests/Feature/CodexMediaTest.php`:
  `test_second_invalid_reference_image_error_is_visible` — one valid + one oversized file →
  `assertSessionHasErrors('reference_images.1')`, then follow the redirect and `assertSee`
  the message on the form page (the `assertSee` fails before the Blade fix).
- Orphan filter, in `CodexEntryTest`: a tag attached to an entry appears in the index
  `tags` view data; a tag with no entries does not. Fails before the fix.
- Hint: no test (static text).
