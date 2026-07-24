---
status: shipped
shipped: 2026-07-24
planned: 2026-07-24
expanded: 2026-07-24
---

# Autosave Storage Improvements

## Problem

`autosave-with-revisions` (see `.specs/planned/2026-07/autosave-with-revisions/`,
handoff.md §3.4/§9.7) mirrors every dirty keystroke to `localStorage`, keyed
`entity:id:field`, so a crash/closed-laptop/expired-session leaves text recoverable.
In practice this always-on mirror causes more friction than it prevents: any time the
server value has moved since the draft was written — another session's edit, editing
the same record in two tabs, or (found in manual testing) a dev `migrate:fresh --seed`
reissuing the same autoincrement ids — the writer gets an "offer-compare-only" prompt
that reads as a conflict when nothing meaningful actually happened. The reseed
collision is a symptom of this, not a one-off bug: an indefinitely-lived, continuously
written draft is inherently likely to collide with *something* eventually.

Meanwhile, now that the server-side autosave itself exists (2-second debounce with
retry), the mirror's continuous writes protect a much thinner slice than the original
design assumed — mostly the last couple of seconds before a hard crash, and text typed
during a session-expiry replay window. Once `data-loss-warnings`' in-app navigation
guard exists (sibling draft spec), the "walked away from a dirty field" case is also
covered by a different, non-fragile mechanism (an explicit confirm dialog), further
shrinking what the mirror needs to protect against.

## Goals

* Stop mirroring on every keystroke. Write the draft **once, at the point of
  departure** — inside a `beforeunload`/`pagehide` handler — rather than continuously
  on `input`. This is a synchronous, reliable last-chance snapshot rather than a race
  against the debounce timer, and cuts the write volume and stale-draft surface
  dramatically.
* Give every draft a **short, hard TTL** (e.g. a few hours — same-day, not
  indefinite). On load, a draft past its TTL is dropped silently, no prompt at all.
  This is what actually defuses the reseed-collision class of bug and the "stranded
  laptop draft resurfaces weeks later and clobbers newer desktop text" scenario the
  original hash-comparison design existed to guard against — shrinking the window
  makes collisions rare instead of designing elaborate comparison logic to tolerate
  them.
* Suppress the write when the departure was an **explicit, informed "leave without
  saving"** answered through `data-loss-warnings`' in-app navigation modal — an
  intentional discard shouldn't be second-guessed by silently keeping a copy anyway.
  This signal only exists for in-app navigation (a custom modal we control); native
  `beforeunload` (real tab-close/browser-quit) cannot report which button the user
  clicked, so that path always writes defensively regardless of outcome.
* Move the recovery prompt from an inline per-field banner to a **single modal per
  page load**, offered when the writer returns to a page with at least one
  non-expired draft. List each affected field with its own Restore/Discard, plus a
  Restore All / Discard All shortcut, rather than stacking a banner per field (the
  original inline design was sized for a mirror that fired routinely across many
  fields at once; this mechanism is meant to be rare enough that a modal is the right
  weight, and closing/dismissing it must not implicitly discard — only an explicit
  choice or TTL expiry should remove a draft).
* Keep the existing `baseHash`-vs-server three-way triage (`drop-silently` /
  `offer-restore` / `offer-compare-only`, handoff.md §9.7) as the underlying decision
  logic — a same-day TTL makes hash mismatches rarer, not impossible (another session
  could still edit the same record within the window), so the distinction is still
  worth keeping rather than re-litigating.

## Non-goals

* Recovering from a true crash with no `beforeunload` opportunity at all (power loss,
  OS kill) beyond what the 2-second server-side autosave already covers. Accepted
  trade: rare, and the case autosave already handles best (nothing to lose but the
  last couple of seconds of typing).
* Redesigning the server-side autosave debounce/retry/state-machine — untouched by
  this spec.
* The in-app "are you sure you want to leave" modal itself — that's
  `data-loss-warnings`. This spec only consumes its explicit-leave signal to decide
  whether to skip a draft write.

## Rough approach

* `resources/js/autosave/field.js`: replace the `mirrorDraft()` call in `onInput()`
  with a `beforeunload`/`pagehide` listener that snapshots the current dirty value
  once, at departure, instead of on every keystroke.
* Add a `savedAt`-based TTL check in `readDraft()`/`checkForDraft()` (or the
  triage step in `resources/js/autosave/store.js`'s `triageDraft()`) — expired drafts
  are treated the same as absent ones and removed on read.
* Wire the `data-loss-warnings` explicit-leave event/callback so the `beforeunload`
  write is skipped when that modal's "Leave anyway" was just clicked for this
  navigation.
* Replace the per-field inline restore banner (`resources/views/components/
  autosave-field.blade.php`, `draftAction`/`restoreDraft()`/`discardDraft()` in
  `field.js`) with a single page-level modal aggregating all fields with a live draft.
* Update `store.test.js`/`field.test.js` alongside — the three-way triage contract is
  exercised there today and stays load-bearing.

## Open questions

* Exact TTL value (a few hours vs. "same calendar day" vs. a fixed N hours) — needs a
  concrete number before expansion.
* Whether the page-level modal needs its own Alpine store/component or can extend the
  existing `Alpine.store('autosave')` global badge machinery.
* How the `beforeunload` write coordinates with multiple dirty fields on one page
  (e.g. `projects/edit`'s 6 fields) — one write per dirty field, or a single
  aggregated entry keyed by page.
