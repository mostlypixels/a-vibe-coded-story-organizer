# Task 9 — Wire `x-autosave-field` into existing views + global badge

## Scope

* Replace each hand-rolled `<div> → x-input-label → x-wysiwyg/textarea → x-input-error`
  block for a registered field, across every existing edit view (`projects/edit`,
  `acts/edit`, `chapters/edit`, `plotlines/edit`, `events/edit`, `scenes/edit`,
  `codex/edit` — locate the exact Blade files via the existing routes/controllers read
  in earlier tasks) with `<x-autosave-field entity="..." :model="$x" field="..."
  :label="__('...')" />`. This is the one task explicitly agreed to stay as a single
  task rather than being split per-view.
* The global lower-right indicator badge: a fixed-position Blade partial/component
  subscribing to `Alpine.store('autosave')`, rendered once (e.g. in the authenticated
  layout), showing the worst-state-wins badge per task 7's precedence order, invisible
  at idle, fading after `saved`, clicking scrolls to and focuses the offending field.
* The `session-expired` and `forbidden-after-replay` indicator copy/UX: "Session
  expired — your work is safe. [Sign in]" (opens `/login` in a new tab,
  `target="_blank" rel="noopener"`) and the dedicated forbidden-after-replay copy from
  `open-questions.md` #5 ("You're signed in as a different account — copy your text
  before switching back"), surfacing the pending value inline since it's already in
  `localStorage`.
* Queue auto-replay wiring on `focus`/`visibilitychange`.

Does **not** include: any change to `FieldAutosaveController`, `store.js`, or
`field.js`'s core logic (tasks 6–8) — this task is presentation/wiring only, plus the
new indicator copy strings.

## Depends on

Task 8 (`x-autosave-field` component + shared store must exist first).

## Key decisions already made

* **Both indicators, always** (`handoff.md` §9.5) — the per-field inline indicator
  (already part of `x-autosave-field` from task 8) stays; this task adds the global
  badge *in addition*, not instead.
* **Nothing else occupies the lower-right fixed-position corner** — confirmed this
  session: `x-modal` is the only existing fixed-position component, at `z-50`. The new
  badge must not collide with it (pick a compatible or higher z-index and a corner that
  doesn't overlap an open modal's typical footprint).
* **`resources/views/projects/edit.blade.php` has 6 autosaving fields**
  (`description`, `dedication`, `acknowledgements`, `preface`, `postface`, `rights`) —
  confirmed against `Project::$fillable` this session. This is the concrete view that
  proves why a global-only indicator would fail (can't say which of six fields
  conflicted) — use it as the primary manual-test case.
* **No token plumbing needed for the session-expired recovery** — confirmed
  `bootstrap.js` already relies on the `XSRF-TOKEN` cookie path (no `<meta
  name="csrf-token">` freeze-at-load pattern in use), so logging in on the second tab
  heals the original tab automatically once axios re-reads the rotated cookie.

## Consult

* `expanded/ui.md` — "Global indicator", "Session-expired recovery" sections.
* `handoff.md` §9.5, §9.6.
* The actual current Blade views for each of the 7 entity types — read each one before
  editing, to see the exact block being replaced (don't assume they're all
  byte-identical in structure).

## Tests

* `tests/Feature/*Test.php` (whichever existing feature test covers each edit view's
  page render — `ProjectTest`, `ActTest`, `ChapterTest`, `SceneTest`, per CLAUDE.md's
  "these controllers have dedicated feature tests, keep them in step") — assert the
  edit page still renders 200 and still contains the expected field values, proving the
  swap to `x-autosave-field` didn't break the existing no-JS form-submit path (the
  wrapper's real `<textarea>` fallback must still submit correctly).
* At least one view's full-form Save (existing `PUT`/`PATCH` update route, unchanged)
  still round-trips correctly end to end — regression coverage that swapping the field
  markup didn't disturb the existing Form Request flow for short fields on the same
  page.
* Manual checklist item (not automated): visually confirm the badge's position doesn't
  collide with an open `x-dialog`/`x-modal`.
