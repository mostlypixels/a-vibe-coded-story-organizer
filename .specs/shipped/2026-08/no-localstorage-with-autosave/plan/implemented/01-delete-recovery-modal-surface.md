# 01 — Delete the recovery modal surface

**Depends on:** nothing.

## Scope

Delete:

- `resources/js/autosave/draft-recovery.js`
- `resources/js/autosave/draft-recovery.test.js`
- `resources/views/components/autosave-draft-recovery-modal.blade.php`

Edit:

- `resources/js/app.js` — remove the `registerDraftRecoveryModal` import and its call.
- `resources/views/layouts/app.blade.php` — remove the `<x-autosave-draft-recovery-modal />`
  mount (~line 113). The `unsaved-changes-guard` mount beside it is a different component and
  stays.

## Not in this task

- Every draft function in `field.js` and `store.js` — task 02. They become unused exports here.
  That is expected and harmless; do not chase them.
- `compareUrls` in the store — task 03.

## Key decisions

- The modal had one entry point (`app.js`) and one mount (the layout). Nothing else references
  it; a grep for `draftRecoveryModal` / `draft-recovery` confirms the surface before you delete.
- No replacement UI. A page load shows no dialog at all.

## Tests

Invert `ProjectTest::test_the_page_level_draft_recovery_modal_is_mounted_once_on_a_page_with_autosave_fields`
into a regression guard and rename it to match:

- A page with autosave fields must **not** contain `== 'draft-recovery'`.
- Keep it rather than delete it — the inverted assertion is what stops a future layout edit
  re-mounting the modal.

`composer test` and `npm run test` green.
