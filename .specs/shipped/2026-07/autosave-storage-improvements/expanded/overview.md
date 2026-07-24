---
title: Autosave Storage Improvements — Overview
---

# Overview

Grounded in `spec.md`, `autosave-with-revisions` (shipped, `.specs/shipped/2026-07/
autosave-with-revisions/`), and `data-loss-warnings` (shipped, `.specs/shipped/2026-07/
data-loss-warnings/`), plus this session's reading of the current `resources/js/
autosave/{field,store}.js`, `resources/js/navigation-guard.js`, and `resources/views/
components/autosave-field.blade.php`.

## Problem

`resources/js/autosave/field.js`'s `onInput()` calls `mirrorDraft()` on **every
keystroke**, writing `{ value, baseHash, savedAt }` to `localStorage` under
`entity:id:field`. This was meant to survive a crash/closed-laptop/expired-session, but
in practice the always-on, indefinitely-lived mirror causes more friction than it
prevents: any time the server value has moved since the draft was written — another
session's edit, the same record open in two tabs, or (found in manual testing) a dev
`migrate:fresh --seed` reissuing the same autoincrement ids — `triageDraft()`
(`resources/js/autosave/store.js`) correctly reports `offer-compare-only`, which reads
as a conflict when nothing meaningful happened. The reseed collision is a symptom, not
a one-off: an indefinitely-lived, continuously-written draft is inherently likely to
collide with *something* eventually.

Two things have since shipped that shrink what this mirror actually needs to protect
against, and change the right shape for it:

1. **Server-side autosave itself** (2-second debounce + retry) already covers the
   common case. The mirror's marginal protection is now just the last couple of
   seconds before a hard crash, and text typed during a session-expiry replay window —
   much thinner than "every keystroke" implies.
2. **`data-loss-warnings`' navigation guard** now warns before an in-app link navigates
   away from a dirty field, and dispatches `autosave:explicit-leave`
   (`resources/js/navigation-guard.js:74`) when the writer explicitly confirms leaving.
   The "walked away from a dirty field" case this mirror also existed to catch is now
   covered by a sturdier, non-fragile mechanism — an explicit yes/no dialog, not a hash
   comparison.

## Goals

* **Write once, at departure**, not on every keystroke. Each `x-autosave-field`
  instance's own `beforeunload` snapshot replaces the continuous `onInput()` mirror —
  synchronous and reliable (an actual last-chance write), not a race against the 2s
  debounce.
* **Short, hard TTL.** A draft older than the TTL is dropped silently on read, no
  prompt at all — the mechanism that actually defuses the reseed-collision class of bug
  and the "stranded laptop draft resurfaces weeks later" scenario the original
  indefinite-lifetime design had to tolerate via elaborate hash comparison.
* **Skip the write on an explicit, informed discard.** Listening for
  `autosave:explicit-leave` (already dispatched today, `data-loss-warnings` shipped)
  suppresses the `beforeunload` draft write for *that* navigation — the writer was
  asked and answered, nothing to second-guess. Native `beforeunload` (real tab-close)
  can never fire this event, so that path still always writes defensively.
* **A single page-level recovery modal**, not the current inline per-field banner
  (`autosave-field.blade.php`'s `data-autosave-draft-banner`) — offered once on page
  load if any registered field has a live (non-expired) draft, listing each affected
  field with its own Restore/Discard plus a Restore All/Discard All shortcut. Closing
  the modal without choosing must not implicitly discard.
* **Keep the existing three-way triage** (`drop-silently` / `offer-restore` /
  `offer-compare-only`, `store.js`'s `triageDraft()`) as the underlying per-field
  decision — a short TTL makes a hash mismatch rarer, not impossible (another session
  could still edit the same record inside the window).

## Non-goals

* Recovering from a true crash with no `beforeunload` opportunity at all (power loss,
  OS kill, browser crash) beyond what the 2-second server-side autosave already
  covers. Accepted trade — rare, and the exact case autosave itself handles best.
* Redesigning the server-side autosave debounce/retry/state machine
  (`mapResponse`/`retryDelayMs`/`scheduleRetry` in `store.js`) — untouched.
* The in-app "are you sure you want to leave" dialog itself — that's
  `data-loss-warnings` (shipped). This spec only listens for the event it already
  dispatches.
* A generic cross-page "any localStorage draft" viewer — scope stays to the registered
  autosave fields on the *current* page, same footprint as today.

## User stories

* A writer's laptop dies mid-edit. Reopening the same scene later shows a single
  modal: "Unsaved changes from 2:04pm — Restore or Discard?" for the one field that was
  dirty, not a per-field inline banner buried in the form.
* A writer clicks a sidebar link, the `data-loss-warnings` dialog asks "leave anyway?",
  they click Leave — no draft is written, and reopening that page later shows no
  recovery prompt for text they explicitly chose to discard.
* A writer leaves a field dirty and doesn't touch the app again for three days. On
  return, the stale draft is silently gone — no confusing "someone else's newer save"
  prompt for a three-day-old artifact nobody remembers.

## Acceptance criteria

* Typing no longer writes to `localStorage` on every keystroke; a draft is written
  exactly once, at `beforeunload`/`pagehide`, per dirty field.
* A draft older than the TTL is never surfaced — dropped silently on read.
* Explicitly confirming "Leave anyway" in the `data-loss-warnings` dialog results in no
  draft being written for that departure.
* Returning to a page with one or more live drafts shows exactly one modal (not a
  banner per field), offering Restore/Discard per field and a bulk shortcut.
* The existing `drop-silently`/`offer-restore`/`offer-compare-only` triage still
  governs what each field's entry in the modal offers.
