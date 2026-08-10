# The Codex — deep dive

The short version lives in [`architecture.md` → The Codex](architecture.md#the-codex-characters-locations-organizations).
This page is the reference: the temporal step function, the three services, and the traps.
Read the section you're about to change.

## One table, one controller, a type enum

All three entity kinds share `codex_entries`, with `type` cast to `App\Enums\CodexEntryType`
(`Character` / `Location` / `Organization`).

- **Why one table:** the columns are identical across types, and the type-specific data is
  exactly what the attribute system handles.
- **Type is a route segment** (`{type}` ∈ `characters|locations|organizations`), resolved via
  `CodexEntryType::fromRouteKey()`. One `CodexEntryController` serves all three.
- **Nothing hardcodes the route keys.** The route constraint, the nav links and
  `fromRouteKey()` all derive from the enum, so a fourth type needs no route edits.

```php
// One grouped constraint, from CodexEntryType::routeKeys(); an unknown {type}
// 404s before the controller runs.
Route::whereIn('type', CodexEntryType::routeKeys())->group(function () {
    Route::get('/projects/{project}/codex/{type}', [CodexEntryController::class, 'index'])
        ->name('projects.codex.index');
    // ...create, store...
});
```

- The nav dropdown (desktop and responsive) `@foreach`es `CodexEntryType::cases()` and
  highlights the **current** type, not always the first link.
- `edit` / `update` / `destroy` are flat (`/codex/{codexEntry}`) — the entry alone resolves
  them.

Hanging off each entry: **aliases** (`codex_aliases`, sync-managed from a repeatable input),
flat **tags** (`tags` + `codex_entry_tag`, `firstOrCreate`d per project then `sync`ed), and
**media** (`codex_media`).

> [!NOTE]
> There is deliberately **no `cover_media_id` column**. The cover is the `codex_media` row
> whose `collection` is `Cover`, exposed via a `CodexEntry::cover()` `hasOne`. A FK would be a
> second source of truth *and* a circular reference (`codex_entries` → `codex_media` →
> `codex_entries`).

## Attribute definitions and the step function

An **attribute definition** (`codex_attributes` — "Hair color", "Architecture style") carries an
`applies_to` JSON array of `CodexEntryType` values deciding which sheets show it.

Its **values** (`codex_attribute_values`) are temporal — a **start-anchored step function**:

- Each row says *"from this event onward, the value is X"*. There is no stored end event.
- A period runs from its `start_event`'s datetime until the next anchor, or the project's
  **End**.
- Periods therefore tile the timeline with **no holes or overlaps by construction**, and
  deleting a middle anchor simply lets the previous value extend — which is why
  `start_event_id` can safely `cascadeOnDelete`.

**Resolving at moment `t`** = the anchor whose datetime is the greatest `≤ t`.

- Ordering is always the canonical `(event_datetime, events.id)`, never datetime alone: two
  events may share a datetime.
- When resolving *at an event*, an **anchor-identity match wins first** — a scene "during
  Halloween" sees the Halloween value even if another event shares its datetime.

### Start and End are single-definition

`Project::startEvent()` / `Project::endEvent()` (earliest / latest `is_fixed` event, canonical
order) are the only definition of the sentinels. `AttributeTimeline` and the entry controller
delegate rather than re-running the query.

Start must stay the **earliest** `is_fixed` event — but its date isn't frozen. Instead the
bookends form a **containment window**:

- `App\Rules\WithinEventWindow`, applied on every event write (store/update and the Scene
  inline `new_event_datetime`), requires `Start ≤ event_datetime ≤ End` for every non-fixed
  event.
- It also forbids a bookend edit from swallowing an existing event: Start can't pass the
  earliest regular event nor reach End; End is the mirror.
- Because `startEvent()`/`endEvent()` filter on `is_fixed`, regular events never compete for
  the anchor. The baseline can be neither deleted (undeletable events) **nor re-ordered**
  (nothing sorts before Start) out from under the step function.

### `App\Services\AttributeTimeline`

Constructed for one entry+attribute pair. This logic is in a service, not a controller or a
model hook.

| Method | What it does |
|---|---|
| `valueAt(Event\|Carbon)` | The resolution above; used by scene/event "as of" panels through the thin `CodexEntry::attributeValueAt()` wrapper |
| `ensureBaseline()` | Creates the Start-anchored value |
| `upsertAt()` | Gap-free write (`updateOrCreate` on the anchor) |
| `removeAt()` | Gap-free delete |

- `upsertAt` is an **upsert**, so the store endpoint has **no update route** — editing an
  existing period posts the same route with the row's anchor.
- **`upsertAt` enforces the baseline itself**: when the anchor isn't Start it calls
  `ensureBaseline()` first, so storing a mid-timeline period for a never-valued pair can't
  open a leading hole. The invariant holds on *every* write path (period store, seeder), not
  only entry-create.
- `removeAt` refuses to delete the Start baseline while other values exist, returning a
  **`403`** (`abort_if`) rather than throwing a `RuntimeException`.

> [!IMPORTANT]
> **Invariant — leading anchor at Start.** Every (entry, attribute) with any value has
> exactly one value anchored at the project's *Start* event, so `valueAt(t)` is **total** for
> `t ≥ Start` and callers never handle "no value". Start/End are `is_fixed` and undeletable,
> and the containment window keeps Start earliest, so the anchor can be neither orphaned nor
> re-ordered. This lives in `AttributeTimeline` (callable directly by the seeder), **not** a
> `booted()` hook — hooks are suppressed under `WithoutModelEvents`.

### Empty is a real value

The store endpoint validates `value` as `['present', 'nullable', 'string', 'max:255']`.

- An **empty value is a first-class "recorded as blank"**: an empty baseline is savable and a
  value can be cleared back to blank. `required` would forbid both.
- `nullable` is there because the global `ConvertEmptyStringsToNull` middleware rewrites a
  blank input's `""` to `null`; the controller casts back with `(string)` before `upsertAt`
  (whose signature is `string $value`).
- The timeline editor renders errors under `value` / `start_event_id` and re-fills `old()`, so
  a rejected save doesn't silently do nothing.

## Media — `App\Services\CodexMediaService`

Owns the storage path and naming, the single-cover rule (replace the existing `Cover` row and
its file), position assignment, and — critically — **deleting files off disk on every removal
path**. `CodexEntry`'s `deleting` hook calls `purge()` *before* the FK cascade drops the rows,
because `cascadeOnDelete` removes DB rows but never files.

> [!WARNING]
> **A DB cascade bypasses model hooks — so it bypasses file cleanup.** Deleting a *project*
> (or a *user account*) cascades `project → codex_entries → codex_media` entirely at the
> database level, so `CodexEntry::deleting` never fires and every media file would leak. Two
> hooks close this:
> - `Project::deleting` calls `CodexMediaService::purgeProject()` — one query for the paths,
>   delete the files, let the cascade drop the rows.
> - `User::deleting` Eloquent-deletes its projects (`$user->projects->each->delete()`) so the
>   `Project` hook fires per project.
>
> That keeps `purgeProject()` the **single** purge trigger for a project's files.

**Disk I/O stays outside the `DB::transaction`.** `CodexEntryController@store`/`@update` run
DB-only work in the transaction, which *returns the paths* of the media rows it removed. Only
after commit does the controller delete those files (`deleteFiles`) and write new uploads
(`storeMediaUploads`, which unlinks a just-written file if its row insert throws).

> [!WARNING]
> **Why post-commit, and the trade-off.** Doing disk work *inside* the transaction is unsafe
> both ways: a rollback after a file delete leaves a surviving row pointing at a missing file
> (404), and files written before a later failure survive the rollback as orphans. Acting
> after commit fixes both — at the cost that a post-commit **upload** failure yields a *saved
> entry with fewer media than requested* plus a 500. Deliberately accepted: a saved entry with
> one missing image beats a rolled-back edit with corrupted disk state.

## Scene references — `App\Services\SceneReferenceMatcher`

Owns the whole-word, **case-sensitive**, Unicode-aware rule deciding which codex entries a
scene's `contents` mention, persisted in the derived `scene_codex_entry` pivot.

- A term is an entry's `name` **or** any alias. Aliases shorter than 3 characters are excluded
  as a false-positive guard; `name` has no floor.
- Matching runs on the raw Markdown `contents` — never `description`/`notes`, never rendered
  HTML.
- `syncScene(Scene)` recomputes one scene; `syncProject(Project)` recomputes every scene,
  reusing one per-project regex built once.
- **Every call is a full `sync()`** for its scope, never an incremental attach/detach. This is
  what keeps the pivot from drifting: no code path adds or removes a single row, so a stale
  row is always dropped on the next sync.
- Both sides normalize to Unicode **NFC** (`ext-intl`'s `Normalizer`) so visually-identical
  accented text from different input sources compares byte-equal. Malformed UTF-8 is caught,
  logged via `Log::warning`, and degrades that scene to "no references" — it never throws and
  never blocks a save.
- **Hyphens are part of the word**: "Jean" does not match inside "Jean-Luc". The boundary
  lookaround includes `-` alongside `\p{L}\p{N}`, and there is deliberately **no `i` flag** (a
  character named "Luck" must not match the noun "luck").

> [!IMPORTANT]
> **A service, not a `booted()` hook** — same reasoning as `AttributeTimeline`. The
> codex-entry update path only rescans when the alias set or `name` actually changed (a
> before/after comparison a hook can't express); the project-wide rescan touches records well
> beyond the model being saved; and a service can be called by a seeder or the importer
> without `WithoutModelEvents` silently suppressing it. Do **not** move this into a hook.

> [!NOTE]
> **Not the same as the index page's search.** `CodexEntryController::index` does a
> case-insensitive SQL `LIKE` substring match to help a writer *find* an entry.
> `SceneReferenceMatcher` answers a different question — does this exact term appear as a
> whole, case-sensitive word in this prose. Their semantics differ on purpose; keep them
> separate.

> [!NOTE]
> **Duplicating an entry copies its aliases verbatim** (see
> [architecture](architecture.md#duplicating-entities)), so the copy matches exactly the same
> scenes as the original until the writer edits its name/aliases apart. Two entries mentioning
> the same scene is expected, not a matcher bug.

**Manual resync** is never needed in normal editing (scene and entry saves call the syncs
themselves). Two escape hatches exist for backfilling scenes that predate the feature or
recovering from suspected drift, both calling `syncProject()`:

- `codex:sync-references {project?}` — every project, or one by id.
- The **"Resync codex references"** footer form on the project edit page
  (`ProjectController::syncCodexReferences`, `update` authorization). Its own form, separate
  from the main project-fields form, since it isn't part of that resource's data.

## Seeding caveat

Like acts/chapters and the main plotline, the Codex is subject to `WithoutModelEvents`:
`MelusineSeeder` sets `position` explicitly on `codex_attributes` and seeds temporal values by
calling `AttributeTimeline::ensureBaseline` / `upsertAt` **directly**, never relying on a hook.
It seeds the hair-color story end to end (Mélusine: raven black → silver on Saturdays after
the curse → wild once she transforms).

`scene_codex_entry` is the same story: no seeded write reaches the call sites that sync it, so
each Melusine seeder ends by calling `syncProject()` itself
(`Database\Seeders\Concerns\SyncsCodexReferences`). Without it every seeded sheet claims no
scene mentions it. **Anything that seeds scenes or entries must do the same, last** — the sync
only sees what already exists when it runs.
