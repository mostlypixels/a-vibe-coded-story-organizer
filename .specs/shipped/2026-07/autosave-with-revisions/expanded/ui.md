---
title: Autosave With Revisions — UI
---

# UI

Grounded in `handoff.md` §3.4, §9.4–§9.7, §9.11, and this session's reading of
`resources/views/components/wysiwyg.blade.php` and `resources/js/bootstrap.js`.

## `x-autosave-field` component

New `resources/views/components/autosave-field.blade.php`, replacing the ~14 hand-rolled
`<div> → x-input-label → x-wysiwyg → x-input-error` blocks (`handoff.md` §9.4):

```blade
<x-autosave-field entity="scene" :model="$scene" field="contents" :label="__('Contents')" />
```

Renders:
1. A label row — label on the left, a history icon-link on the right (styled like the
   existing `x-icon-edit-link` family — reuse, don't invent a new icon-button style per
   CLAUDE.md's Tailwind guidance), linking to `route('revisions.index', [...])`.
2. The editor: `<x-wysiwyg>` for `rich`/`markdown` kinds (passing `:markdown="$kind ===
   FieldKind::Markdown"` exactly as `scenes/edit.blade.php` already does for `contents`);
   a **new `plain` kind** renders a raw `<textarea>` — needed because `Project.rights`
   is a raw `<textarea>` today (`resources/views/projects/edit.blade.php:73`), not an
   `x-wysiwyg` instance, and the wrapper must not force it into the rich editor.
3. `<x-input-error>` for the field (existing component, unchanged).
4. The field's inline autosave-state indicator (see below).

Kind, character cap, and window come from `AutosavableFields`, not passed as props —
"a future field is one registry line + one blade line" (§9.4).

The rendered `<textarea>`/editor root carries `data-hash="{{ hash('sha256', $model->
{$field} ?? '') }}"` so the client's `base_hash` starts correct on page load without a
round trip (§9.13's server-hash-authority rule applied to the initial render, not only
PATCH responses).

## Autosave client module

`resources/js/autosave/store.js` — the state-machine/decision logic vitest actually
exercises (§9.12): idle / saving / saved / retrying / conflict / session-expired / error,
draft triage rules (§9.7's three-way table), retry backoff, status-code → state mapping
(`architecture.md`'s table). No DOM access in this file.

`resources/js/autosave/field.js` — the thin Alpine component adapter per `<x-autosave-
field>` instance: wires debounce/blur/Ctrl-S listeners, calls into `store.js`, updates
`Alpine.store('autosave')` (the shared cross-field store for the global badge), and
mirrors pending values to `localStorage` keyed `type:id:field` (§3.4) or
`new:<type>:<parent-id>:<field>` on create forms (§9.1).

## Inline indicator (per field)

Lives in the `x-autosave-field` label row. Shows only its own field's state — precise,
per §9.5's "you always know which field". Idle renders nothing (no persistent chrome).

## Global indicator (lower-right)

A fixed-position badge (confirmed nothing else occupies that corner — `x-modal` is the
only fixed-position component today, at `z-50`, per `handoff.md` §9.5), subscribing to
`Alpine.store('autosave')`. Worst-state-wins across every field on the page, precedence
`session-expired > conflict > error > retrying > saving > saved > idle`. Invisible at
idle, fades after `saved`. Clicking scrolls to and focuses the offending field.

**Why both indicators, concretely:** `resources/views/scenes/edit.blade.php` has 3
autosaving fields; `resources/views/projects/edit.blade.php` has 6 (`description`,
`dedication`, `acknowledgements`, `preface`, `postface`, `rights`) — confirmed against
`Project::$fillable` above. A single global indicator can't say which of six fields
conflicted; a per-field-only indicator misses a session expiry on a field scrolled
off-screen.

## Session-expired recovery

Indicator escalates to *"Session expired — your work is safe. [Sign in]"*, opening
`/login` in a new tab (`target="_blank" rel="noopener"`). No token plumbing needed —
`bootstrap.js` (read above) sets `X-Requested-With: XMLHttpRequest` globally with no
explicit CSRF header, confirming the `XSRF-TOKEN` cookie path is what's live; axios
re-reads that cookie per request. The queue auto-replays on `focus`/`visibilitychange`.
The 403-after-different-user-login gap (§9.6) needs its own indicator copy — flagged in
`open-questions.md`.

## `localStorage` restore banner

Inline per-field (never a modal — `projects/edit` has 6 fields, per §9.7). On load, per
entry: drop silently if it matches the server value; offer **Restore/Discard** if the
base hash still matches the server (clean unsaved work); offer **Compare/Discard only**
(never a bare Restore) if the server has moved past the stored base hash. No age-based
eviction — only wording changes with age; storage itself is bounded by a ~4 MB budget,
oldest-evicted-first on `QuotaExceededError`.

## History page

`GET /revisions/{entity}/{id}/{field}` (`RevisionController::index`). Table: date,
author, label, origin badge, current-value marker. Label search is a portable `LIKE`
filter, matching `ProjectSearch`'s existing pattern (read above). List query selects
explicit columns and never hydrates `value` (`data-model.md`'s read rule). A field
switcher at the top lists the entity's other registered fields (from
`AutosavableFields::REGISTRY[$entity]`), so navigating between a scene's `description`,
`notes`, and `contents` history doesn't require going back to the edit page.

A `baseline` row renders as **"Baseline — value before revision history"**, not as a
normal edit row (§9.2), so its borrowed `updated_at` timestamp isn't misread as a real
edit.

## Compare view

`GET /revisions/{entity}/{id}/{field}/compare?from=&to=`. Word-level diff via
`RevisionDiffer` (`architecture.md`). Shows "formatting changed only" when the diff is
empty but the raw values differ (rich fields only, per §5.3).

## Revert

A button on the history row and on the compare view, posting to `revisions.revert`.
Confirmed via the existing `x-dialog` confirm component (matches the "behind the
existing `x-dialog` confirm" language in `handoff.md` §4.3 for the purge panel — same
component, same UX for any destructive-feeling action even though revert itself is
non-destructive server-side).

## "Revision storage" panel (project settings)

Counts + total size (`SUM(size_bytes)`, `data-model.md`) broken down by origin
(automatic/manual/labeled/imported). Bulk-delete actions per category and per age
("imported", "auto older than 1 year"), behind `x-dialog` confirm, calling
`RevisionPurger` through a controller action (`architecture.md`).

## Retention setting (admin settings)

A new field on whichever admin settings view already hosts `ImportSetting`-style
toggles (`GeneralSettingsController`'s view, or a sibling — confirm placement in
`open-questions.md`). Submitting a lower `retention_days` value returns a confirmation
step showing the exact count the next nightly prune would remove (§9.11) before
anything is saved — works without JavaScript, a plain two-step POST/confirm form, not an
AJAX live-count widget.
