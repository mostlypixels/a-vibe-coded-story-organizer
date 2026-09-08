# Testing

`tests/Feature/SceneCodexEntryTest.php`, plus a Vitest file beside the JS.

## Endpoint

- Creates the entry with the posted name and type; the response carries its id and url.
- Scene `updated_at`, `contents` and `word_count` are unchanged by the call.
- `referenced_entries` includes the new entry when the stored contents hold the name, and
  excludes it when they do not.
- `syncProject()` does **not** run: another scene elsewhere in the project containing the
  same name keeps its pivot untouched. Assert on the pivot rows, and say in the test name
  that this is the accepted debt, not a defect.
- Duplicate name of the same type → 422, message carries the existing entry id.
- Same name, different type → allowed.
- Non-owner → 403. A scene from another project → 403, not 404 by accident.
- Validation: blank name, whitespace-only name, name over 255, unknown type.

## Attribute rows

- A quick-created entry has zero `codex_attribute_values` rows. This passes only once
  `codex-attributes-on-demand` lands; write it now and let it set the ordering.

## JS (Vitest)

- Prefill: the selection text becomes the name field value, trimmed.
- Submit flushes the autosave before posting. Assert the order — the whole feature turns
  on it.
- A 422 keeps the dialog open and preserves the typed name.
- Success replaces the sidebar list rather than appending, so a dropped entry disappears.

## Regression

- The normal codex create form still runs `syncProject()` through the default parameter,
  so renaming an entry there still rescans the project.
