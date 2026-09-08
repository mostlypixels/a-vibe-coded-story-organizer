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
- Ordering against `codex-attributes-on-demand` (open question 3) and `ajax-inline-events`
  (open question 6) is moot: both shipped on 2026-09-08, before this plan was written.

## Deviations from the spec/plan

_None yet._

## Issues → resolutions

_None yet._
