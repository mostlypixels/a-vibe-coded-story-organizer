---
title: Data Loss Warnings — Architecture
---

# Architecture

No Livewire in this app (`composer.json` has none, `grep -rn wire:navigate
resources/views` finds nothing) — every page is a plain full-page load, so "intercept
navigation" only ever means intercepting `<a>` clicks and the two native browser exit
paths (`beforeunload`, tab-close). No SPA router to account for.

## 1. Surfacing "dirty" globally

`resources/js/autosave/field.js`'s `registerAutosaveField()` already writes each
field's **state machine value** into `Alpine.store('autosave').fields[key]` on every
`setState()` call. It does **not** currently write the raw `dirty` boolean anywhere the
store can see — `dirty` lives only as an instance property inside the Alpine component
(`autosaveField()`'s returned object, line ~151).

Add a second store map alongside `fields`:

```js
// registerAutosaveField(), inside the store-init block:
Alpine.store('autosave', {
    fields: {},
    elements: {},
    dirty: {},          // NEW — key => boolean, mirrors each field's `dirty` flag

    worstState() { /* unchanged */ },
    isDirty() {          // NEW
        return Object.values(this.dirty).some(Boolean);
    },
});
```

Set `store.dirty[this.key] = true` at the same point `onInput()` already sets
`this.dirty = true` (and `mirrorDraft()` today); clear it back to `false` wherever the
component already clears `this.dirty = false` — currently only inside `save()`'s
`STATES.SAVED` branch. `destroy()` must also delete `store.dirty[this.key]`, mirroring
the existing `delete store.fields[this.key]` / `delete store.elements[this.key]` there,
so a field removed from the DOM (e.g. Alpine re-render) can't leave a stale `true`
behind forever.

This is the one true "is there anything unsaved on this page right now" signal both the
navigation guard and the `beforeunload` fallback read — a plain `Alpine.store
('autosave').isDirty()` call, no new per-page wiring needed. A page with zero autosave
fields never registers the store at all today (`if (!Alpine.store('autosave'))` guard in
`registerAutosaveField()`), so `isDirty()` must handle "store doesn't exist yet" as
`false` at the call site, not assume it's always present.

## 2. New module: `resources/js/navigation-guard.js`

A new file, registered once from `resources/js/app.js` alongside the existing
`registerAutosaveField(Alpine)` call — not per-field, not per-page. Mirrors
`resources/js/autosave/badge.js`'s shape: a small `Alpine.data()` factory plus a couple
of pure helper functions that vitest can exercise without a DOM.

```js
export function shouldIntercept(event, anchor) {
    // Pure predicate — same event a real 'click' listener receives, plus the
    // closest <a href> ancestor (or null). Exhaustive list of reasons to let
    // the browser's default handling proceed untouched:
    if (!anchor || !anchor.href) return false;
    if (event.defaultPrevented) return false;
    if (event.button !== 0) return false;                       // not a plain left-click
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return false; // open-in-new-tab etc.
    if (anchor.target && anchor.target !== '_self') return false; // target=_blank
    if (anchor.hasAttribute('download')) return false;
    if (anchor.origin !== window.location.origin) return false;  // external link
    if (anchor.href.split('#')[0] === window.location.href.split('#')[0]) return false; // same-page anchor
    return true;
}
```

Wiring (the impure half, in the `Alpine.data()` factory registered on a wrapper element
in `layouts/app.blade.php` — see `ui.md`):

* `document.addEventListener('click', handler, true)` — capturing phase, so this runs
  before any per-component `@click` handlers that might themselves navigate.
* On a click where `shouldIntercept()` is true **and** `Alpine.store('autosave')
  ?.isDirty()`: `event.preventDefault()`, stash `anchor.href` as `pendingHref`, dispatch
  `open-modal` for the shared dialog (see `ui.md`).
* **Leave** button: `window.dispatchEvent(new CustomEvent('autosave:explicit-leave'))`
  first, then `window.location.href = pendingHref`. The event fires synchronously
  before navigation starts, so a `autosave-storage-improvements` listener registered on
  `window` is guaranteed to observe it before the page unloads.
* **Cancel** button (or Esc/backdrop, per `x-modal`'s existing behavior): just close the
  dialog, clear `pendingHref`, no event, no navigation — the writer stays exactly where
  they were.

## 3. `beforeunload` fallback

Same file, a few lines:

```js
window.addEventListener('beforeunload', (event) => {
    if (!Alpine.store('autosave')?.isDirty()) return;
    event.preventDefault();
    event.returnValue = ''; // required for Chrome; the string itself is never shown
});
```

Deliberately dumb — no custom text (browsers ignore it anyway), no attempt to
distinguish which button the user picks (§ non-goals, `overview.md`). This is the one
path that can never dispatch `autosave:explicit-leave`, by construction: the handler
above returns before the browser even shows its dialog, and there is no callback for
what happens after.

## 4. Delete confirmations

Two different shapes, decided during grilling: **Act/Chapter get a real "move or
delete" dialog** (children can be reassigned elsewhere); **Project keeps a plain
cascade-count string** (its direct children have no natural destination).

### 4a. Project — count string only, no new controller logic

Same shape as originally scoped: pass a richer string into the existing
`:delete-confirm` prop, nothing else changes.

* `ProjectController::edit()`: `$project->loadCount(['acts', 'plotlines', 'events',
  'codexEntries'])` (relations at `app/Models/Project.php:58-78`), then build the
  message from only the non-zero counts (§ `ui.md`) — a brand-new project (only its
  auto-created, un-deletable main plotline) reads as an unqualified message, not "0
  acts, 0 events…".
* No new Form Request, no new authorization path — `ProjectController::destroy()`
  (`:89-96`) and its policy check are untouched.

### 4b. Act / Chapter — "move or delete" dialog

**Counts, for the dialog body:**
* `ActController::edit()` (`:50-55`): `$act->loadCount('chapters')` +
  `Scene::whereHas('chapter', fn ($q) => $q->where('act_id', $act->id))->count()` for
  the scene total (confirm exact query shape at implementation time — a plain `whereHas`
  count is simplest and avoids relying on nested `withCount` dot-syntax).
* `ChapterController::edit()`: `$chapter->loadCount('scenes')`.
* Index pages already have what's needed for the row-level delete button:
  `ActController::index()` (`:22`) already calls `->withCount('chapters')`;
  `ChapterController::index()` (`:26-29`) already calls `->withCount('scenes')`.

**Destination list**, for the picker: every *other* act in the same project
(`$project->acts()->where('id', '!=', $act->id)->orderBy('position')->get()`) for an
Act delete; every other chapter in the same project for a Chapter delete
(`Chapter::whereHas('act', fn ($q) => $q->where('project_id', $project->id))
->where('id', '!=', $chapter->id)`, mirroring `UpdateSceneRequest`'s existing
`chapter_id` scoping rule at `app/Http/Requests/UpdateSceneRequest.php:30`). An empty
list means the dialog renders with no picker — "Delete everything" only.

**One request does both reassign and delete.** Extend the existing `destroy()` actions
to accept an optional `move_children_to` field instead of adding a new route:

```php
// ActController::destroy(DestroyActRequest $request, Act $act)
$this->authorize('update', $act->project);

if ($destinationId = $request->validated('move_children_to')) {
    $destination = $act->project->acts()->findOrFail($destinationId);
    $nextPosition = $destination->chapters()->max('position') + 1;

    $act->chapters()->orderBy('position')->get()->each(function (Chapter $chapter) use ($destination, &$nextPosition) {
        $chapter->update(['act_id' => $destination->id, 'position' => $nextPosition++]);
    });
}

$project = $act->project;
$act->delete(); // now empty if reassigned — same cascade path as before, just nothing left to cascade
```

Wrapped in a DB transaction (`CLAUDE.md`'s "use database transactions for multi-step
write operations") — reassignment and delete must not partially apply. `ChapterController
::destroy()` is the same shape one level down (`Scene::chapter_id`/`max('position')`
scoped by chapter instead of act).

**New Form Requests** — `DestroyActRequest`/`DestroyChapterRequest`, following this
codebase's existing `Update*Request` pattern (`UpdateChapterRequest`'s `act_id` rule,
`UpdateSceneRequest`'s `chapter_id` rule at `:23`/`:27-30`):

```php
'move_children_to' => [
    'nullable',
    Rule::exists('acts', 'id')->where('project_id', $act->project->id),
    Rule::notIn([$act->id]),
],
```

`authorize()` mirrors the controller: `$this->user()->can('update', $act->project)` —
same authorization boundary as every other write on this entity, no new policy.

**Position invariant, explicit:** `Chapter`/`Scene`'s `booted()` only auto-assigns
`position` on `creating`, not on update (`app/Models/Chapter.php:56-60`) — moving a
record via a plain `act_id`/`chapter_id` change does **not** recompute its position in
the new scope today. This task must set `position` explicitly on every reassigned
child (`max(position) + 1` in the destination, incrementing per child to preserve
their original relative order) rather than relying on the model's create-only hook.
