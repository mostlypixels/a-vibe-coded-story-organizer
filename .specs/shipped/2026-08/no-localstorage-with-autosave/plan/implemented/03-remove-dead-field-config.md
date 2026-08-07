# 03 — Remove the dead field config

**Depends on:** 01 (the modal was the last reader of both).

The `compareUrl` chain and the `data-hash` attribute exist only to feed the recovery modal.
Neither has a runtime reader once it is gone.

## Scope

`resources/views/components/autosave-field.blade.php`:

- Drop the `$compareUrl` assignment and the `compareUrl:` entry in the `x-data` config.
- Drop `data-hash="{{ $hash }}"` from **both** branches — the `<x-textarea>` and the
  `<x-wysiwyg>`.
- Keep `$hash` itself. `baseHash` in the `x-data` config still needs it, and it is the value
  the PATCH sends as `base_hash`.
- Keep `$historyUrl` and the History icon.

`resources/js/autosave/field.js`:

- Remove `compareUrls: {}` from the store initializer, its write in `init()`, and its `delete`
  in `destroy()`.

## Not in this task

- The `revisions.compare` route, `RevisionCompareTest`, and the whole compare UI. Untouched —
  only this component's precomputed link to the route goes.

## Key decisions

- `data-hash` and `baseHash` were always the same `hash('sha256', $currentValue)` value reaching
  the client two ways. Only the DOM-attribute path is deleted.

## Tests

`resources/js/autosave/field.test.js` — delete the two `store.compareUrls` tests.

`tests/Feature/AutosaveFieldComponentTest.php`:

- Delete `test_compare_url_is_passed_into_the_alpine_config`.
- Delete `test_data_hash_matches_the_currently_stored_value` and
  `test_data_hash_of_an_empty_field_matches_the_empty_string_hash`.
- Widen the existing `test_the_inline_draft_banner_no_longer_renders`: also assert the rendered
  field contains no `compareUrl:` and no `data-hash=`. Rename it to cover what it now guards.
