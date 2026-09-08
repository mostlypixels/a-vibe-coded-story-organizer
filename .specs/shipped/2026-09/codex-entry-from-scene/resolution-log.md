# Codex entry from the scene editor — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

- The reference list keeps one template. `ui.md` had the Blade `@foreach` become an Alpine
  `x-for` seeded from the server, which is two templates for one list that must be kept
  looking identical forever. The endpoint returns the list's rendered HTML instead and the
  client swaps it in.
- A success always shows a confirmation line naming the created entry. The sidebar lists
  only names found in the scene text, so creating an entry for a name not yet written would
  have shown nothing at all and read as a failure. `testing.md` asserted that absence
  without saying what the writer sees.
- The autosave flush is made awaitable rather than reached into. `flush()` dropped the
  promise from `save()`, so "await the flush" was not possible as written, and the store
  exposed only the field's DOM node. `flush()` now returns its promise and the store gains
  `flush(key)`.
- The flush runs with `runMatcher: false`. Running the matcher there would search for an
  entry that does not exist yet, and the endpoint syncs the scene after creating it.
- `CodexMediaUploads` needs no `empty()` named constructor, contrary to `architecture.md`'s
  note to check — every constructor argument already defaults.
- The slash item is opt-in, not unconditional. `buildSlashItems()` serves every editor in
  the app (codex descriptions, book blurbs), and only the scene editor has the dialog. The
  item appears only when an `onCodexEntry` callback is passed, carried from a
  `quickCodexEntry` prop on `x-autosave-field` → `x-wysiwyg`. Everywhere else the menu is
  unchanged instead of showing an item that does nothing.
- Ordering against `codex-attributes-on-demand` (open question 3) and `ajax-inline-events`
  (open question 6) is moot: both shipped on 2026-09-08, before this plan was written.

## Deviations from the spec/plan

_None yet._

## Issues → resolutions

- `x-modal` focuses its own first control 100 ms after it opens, which is the dialog's
  close button. Two symptoms: the name field never got the caret despite `autofocus`, and a
  dialog closed inside that window had its focus stolen back from the editor. The component
  now schedules one `claimFocus()` after that delay — it selects the name field while the
  dialog is open, and puts the caret back in the prose when the dialog already closed. The
  green suite could not see either: both are timing inside another component.
- The dialog does not use Alpine `$refs` for the name field. `x-modal` carries its own
  `x-data`, so a ref inside the dialog belongs to the modal, not to the component around it.
  The field is addressed by id instead, passed in as `nameSelector`.
