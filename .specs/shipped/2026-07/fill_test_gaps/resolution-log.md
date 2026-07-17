# Resolution log — Fill test gaps

## Deviation: relocated existing coverage instead of only adding files

The spec reads "Add `PlotlineTest` / `EventTest`", but `ProjectTest` already exercised
most of the plotline and event controllers (index/sort/filter, create, colour rules,
`is_main` un-deletable, `is_fixed` un-deletable, `WithinEventWindow` bounds). Adding
dedicated files while leaving those cases in `ProjectTest` would have duplicated ~15
tests verbatim — a DRY violation the project guidelines call out.

**Resolution.** The plotline/event cases were *moved* into the new `PlotlineTest` /
`EventTest`, and `ProjectTest` was trimmed to project-scoped concerns only: dashboard,
project CRUD/authorization, and the project-creation invariants that seed the main
plotline and the Start/End bookends (plus the `startEvent()`/`endEvent()` helpers). Each
controller's coverage now lives in exactly one place.

## Gaps the move surfaced and filled

While relocating, the following genuinely-missing cases were added:

- **Plotline:** create/edit GET pages render; `update` happy path; non-owner 403 on
  `edit`-target `update` **and** `destroy` (the shallow mutation routes were only
  negative-tested on `store`/`index` before).
- **Event:** create page renders; `plotlines` required (min:1) validation; non-owner 403
  on `update` and `destroy`; `WithinEventWindow` enforced on the **update** path (only the
  store path and the bookend edges were covered before).

## Verification

`composer test` → 243 passed (795 assertions). The three affected classes in isolation
(`PlotlineTest|EventTest|ProjectTest`) → 44 passed. No production code changed; this is a
test-only change, so there is no UI surface to drive beyond the suite itself.
