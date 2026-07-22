---
title: Autosave With Revisions — Overview
---

# Overview

Source: `.specs/draft/autosave-with-revisions/spec.md` (hand-written intent) and
`handoff.md` (grilling decisions, 2026-07-21/22 — where the two disagree, `handoff.md`
wins). This file restates the design as goals/non-goals/stories/acceptance criteria; it
does not re-litigate decisions already made in `handoff.md` — see that file for the "why"
behind each one.

## Problem

Long-text fields across the project tree (`Scene.contents` above all) are only saved on
an explicit form submit today. A writer who loses their tab — crash, closed laptop,
expired session — loses everything typed since the last click of Save. There is also no
way to see or recover an earlier version of a field once it has been overwritten.

## Goals

* Every registered long-text field (§ registry below) autosaves via AJAX, without the
  writer clicking Save, using the triggers in `handoff.md` §2.4 (debounce / blur / Ctrl-S
  / form submit).
* Every field-level save that matters is recoverable: a per-field revision history page,
  compare, and non-destructive revert (`handoff.md` §5).
* Data is never silently lost to a stale-tab overwrite (§3.3 conflict detection), a
  crash (§3.4 `localStorage` mirror), or an expired session (§9.6).
* The revision table stays bounded over time via an automatic daily prune with
  non-negotiable safety rules, and an explicit purge for the exemptions that never age
  out on their own (§4).
* Future long-text fields added to the app get this behavior for the cost of one
  registry entry + one Blade line (§9.4's `x-autosave-field`), not a new
  controller/route/test per field.

## Non-goals (v1)

* **No draft-vs-published split.** Autosave writes the live column directly; there is
  one source of truth, matching what exports, search, share links and
  `SceneReferenceMatcher` already read (`handoff.md` §2.1).
* **No autosave for short fields or relations** (`name`, `chapter_id`, `status`,
  `event_id`, `mentioned_events`) — these keep the existing Form Request flow because
  they carry cross-field rules that don't survive field-level saves (§2.3). Closing this
  gap is `.specs/draft/data-loss-warnings`' job, not this spec's.
* **No time-travel on `CodexAttributeValue.value`** — it already has story-time
  versioning via `AttributeTimeline`; stacking edit-time history on top is a genuine
  design conflict, not scope creep (§7).
* **No revision package dependency** (`laravel-auditing`, `laravel-versionable`,
  `revisionable` were all considered and rejected — §6).
* **No server-side collaborative locking** — this is a single-owner app
  (`ProjectPolicy` has no collaborator concept); the conflict story is a two-tab,
  single-user problem (§3.3).
* **EPUB/PDF exports never include revisions** (unchanged from `spec.md`).

## User stories

1. As a writer, I type in a scene's contents for twenty minutes without touching Save,
   close the laptop, and reopen it later to find my text intact.
2. As a writer, I accidentally overwrite three paragraphs, open the field's history, and
   revert to the version from ten minutes ago without losing anything — the bad version
   is still in history too.
3. As a writer, I leave a tab open overnight; when I return, the app tells me plainly
   that my session expired and my work is safe, rather than failing silently.
4. As a writer, I open the same scene in two tabs, edit both, and the second tab that
   tries to save tells me "changed elsewhere" instead of clobbering the first tab's work.
5. As a writer, I name a particularly good draft ("Before the ending rewrite") so it
   never gets swept by the daily prune, and I can find it again by searching that name.
6. As an admin (the app's only user role with settings access), I can see how much
   storage revisions are consuming and bulk-delete a category I no longer need
   (imported revisions, or automatic revisions older than a year).

## Acceptance criteria (high level; see `testing.md` for the concrete test list)

* Typing in any of the 14 registered fields (§7 in `handoff.md`) triggers a debounced
  PATCH that updates the live column and, once outside its coalescing window, a new
  revision row.
* Ctrl-S and blur flush the pending save, run `SceneReferenceMatcher` (scene contents
  only), and close the current coalescing window.
* The manual form Save button still works exactly as today, and additionally tags its
  revision `origin: manual`.
* A 409 response is possible, is triggered by a genuinely stale base hash, and offers
  Reload / Keep mine / Compare.
* A crashed/closed tab's unsaved text survives in `localStorage` and is offered back on
  next load, with the three-way rule in `handoff.md` §9.7 (never a bare Restore over
  newer server data).
* `php artisan model:prune --model=App\Models\Revision` removes only prunable rows and
  never removes: a labeled revision, a non-`automatic`-origin revision, or the newest
  revision of any field.
* The revision-storage panel and `revisions:purge` command produce identical results
  because both call `RevisionPurger`.
* No new endpoint exists per model — one `FieldAutosaveController` route serves every
  registered field.
