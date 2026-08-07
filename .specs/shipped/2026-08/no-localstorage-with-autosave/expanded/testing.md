# Testing

Mostly deletion. The suite currently proves the mirror works; it must end up proving nothing
writes to `localStorage` at all.

## JS — `npm run test`

### `resources/js/autosave/draft-recovery.test.js`
Delete the file with its module.

### `resources/js/autosave/store.test.js`
Delete `describe('triageDraft — localStorage discard rule')` and
`describe('isDraftExpired — 4-hour flat TTL')`, and drop `DRAFT_TTL_MS`, `isDraftExpired`,
`triageDraft` from the import.

### `resources/js/autosave/field.test.js`
Delete:

- `describe('draft mirror (readDraft/writeDraft/clearDraft)')`.
- the `beforeunload` snapshot describe, including `an explicit-leave suppresses the beforeunload
  write for every field`.
- the two `store.compareUrls` tests.

Rename the `storageKeyFor` tests to `fieldKeyFor`, and delete the `new:`-prefix case with the
branch it covers.

### Two tests to add
Both are cheap regression guards against the feature creeping back:

- A dirty field that receives `beforeunload` leaves `window.localStorage.length === 0`.
- A successful `save()` on a dirty field leaves `window.localStorage.length === 0` (the old
  `clearDraft()` call is gone; assert nothing wrote in the first place).

> [!WARNING]
> Assert on `window.localStorage` directly, not on a `writeDraft` spy. The exported functions
> are gone, so a spy has nothing to attach to and would pass vacuously.

## PHP — `composer test`

### `tests/Feature/AutosaveFieldComponentTest.php`
- Delete `test_compare_url_is_passed_into_the_alpine_config`.
- Delete `test_data_hash_matches_the_currently_stored_value` and
  `test_data_hash_of_an_empty_field_matches_the_empty_string_hash` — the attribute goes with them.
- Keep `test_the_inline_draft_banner_no_longer_renders` and widen it: also assert the rendered
  field contains no `compareUrl:` and no `data-hash=`.

### `tests/Feature/ProjectTest.php`
Invert `test_the_page_level_draft_recovery_modal_is_mounted_once_on_a_page_with_autosave_fields`
into a negative guard: a page with autosave fields must **not** contain `'draft-recovery'`.
Rename accordingly. Do not simply delete it — an inverted assertion is what stops the modal
being re-mounted by a future layout edit.

Every other autosave/revision feature test is untouched and must stay green — that is the
signal that the removal took nothing load-bearing with it.

## Manual verification

Drive the real app with the `run-imagoldfish` skill. Tests cannot see the Tiptap boundary that
caused the bug (`draft-recovery.test.js` used a bare `<textarea>` fixture — the one case that
worked, which is why the suite passed while the feature was broken).

1. Edit a scene, wait past the debounce, reload. Text is there. No modal.
2. Edit a scene, click away to blur the editor, reload. Text is there (the `focusout` flush).
3. Edit a scene, close the tab inside the 2 s window, answer the native prompt with *Leave*,
   reopen. **No modal, and no phantom text.** The last couple of seconds are lost — that is the
   accepted trade, and confirming it is silent and consistent is the point of this step.
4. Click an in-app link with a dirty field: the unsaved-changes modal still appears, and
   *Leave* still navigates. Confirms removing the `autosave:explicit-leave` dispatch broke nothing.
5. Press Save on an edit page: no "leave site?" native prompt. Confirms `savingViaForm` survived.
6. DevTools → Application → Local Storage is empty after all of the above.
