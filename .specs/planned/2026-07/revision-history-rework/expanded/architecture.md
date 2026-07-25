# Architecture — Revision History Rework

Where each piece of logic lives, following CLAUDE.md: controllers resolve → authorize →
delegate → respond; multi-step domain work goes to a service (`ProjectSearch` /
`ProjectRevisionsBrowser` are the template); reference data goes to `app/Support`.

## The shape of the change, in one paragraph

Storage stays **per field**: one immutable row per (entity, field, moment). Everything
above storage moves to **per entity**. The addressable unit of the UI is a *save point* —
a `save_id`, i.e. "the state of this scene right after that save". History lists save
points. Compare takes two save points and shows every field that differs between them.
Revert undoes a save point. A single field is a **filter** (`?field=`) applied to any of
those views, never a separate page with its own routes, controller and tests.

## Routes (`routes/web.php`)

Inside the existing `auth` group, replacing the current revision read routes.

| Method | URI | Name | Action |
|--------|-----|------|--------|
| GET | `/revisions/{entity}/{id}` | `revisions.index` | `RevisionController@index` — entity history, `?field=`, `?page=` |
| GET | `/revisions/{entity}/{id}/compare` | `revisions.compare` | `RevisionController@compare` — `?from=&to=` (save ids), `?field=` |
| GET | `/revisions/{entity}/{id}/{field}` | `revisions.field` | `RevisionController@field` — redirect to `revisions.index?field=` |
| GET | `/revisions/{entity}/{id}/{field}/compare` | `revisions.field-compare` | `RevisionController@fieldCompare` — redirect to `revisions.compare` (see below) |
| POST | `/revisions/{revision}/revert` | `revisions.revert` | `RevisionController@revert` — unchanged |
| POST | `/revisions/saves/{save}/revert` | `revisions.saves.revert` | `RevisionController@revertSave` — new |

* `{entity}` keeps `->whereIn('entity', AutosavableFields::slugs())`, so an unregistered
  slug still 404s at the router.
* `{save}` is constrained to the ULID alphabet (`[0-9A-HJKMNP-TV-Z]{26}`) so a malformed
  id 404s before touching the database.
* The two legacy field-scoped URLs stay as **redirects only** — they are bookmarked, they
  are what `documentation/architecture.md` currently documents, and they are what the
  revisions-browser sidebar has been emitting. `fieldCompare` translates its old
  `?from=&to=` *revision* ids into the save points those revisions belong to (a revision
  row knows its `save_id`), so an old compare link still lands on the equivalent
  comparison. The sidebar (`revisions/partials/sidebar.blade.php`, built by
  `ProjectRevisionsBrowser`) is retargeted to emit `revisions.index` + `?field=`
  directly, so only stale bookmarks pay the extra hop.

## Snapshots — the one new concept

A save point identifies a moment, not a set of values: a save that touched only `notes`
still implies a state for `description` and `contents` (whatever they held at that
moment). So:

> **Snapshot** = for each registered field of the entity, the newest revision of that
> field whose `created_at` is at or before the save point's timestamp.

This is what makes entity-level compare truthful. Diffing two snapshots yields "everything
about this scene that differs between these two moments", including fields neither save
touched directly but that changed in between.

Resolution is bounded and portable: the registry gives at most six fields per entity
(`Project`), so it is one small query per field per side —
`where field = ? and (created_at, id) <= (?, ?) order by created_at desc, id desc limit 1`
— expressed as a `created_at < ? or (created_at = ? and id <= ?)` pair so ties inside one
second resolve deterministically by id. No window functions.

## Controllers

`RevisionController` keeps every action; nothing here holds a query.

### `@index` — entity history

```
$model = $this->resolveEntity($entity, $id);          // findOrFail + authorize('view', revisionProject())
$field = $this->resolveFieldFilter($entity, $request); // null, or a registered field (404 otherwise)
$page  = app(RevisionHistory::class)->forEntity($model, $field, $request->integer('page', 1));
return view('revisions.index', [...]);
```

`resolve()` is split into `resolveEntity(slug, id)` and `resolveFieldFilter(slug, request)`;
the unknown-field 404 still goes through `AutosavableFields::resolveField()`, so that
contract stays in one place (shared with `FieldAutosaveController`).

### `@compare` — entity-level compare

```
$model    = $this->resolveEntity($entity, $id);
$field    = $this->resolveFieldFilter($entity, $request);
$points   = app(RevisionHistory::class)->savePoints($model);         // combobox options, both sides
[$from, $to] = $this->resolvePair($points, $request);                // defaults: two most recent
$comparison  = app(RevisionComparison::class)->between($model, $from, $to, $field);
return view('revisions.compare', [...]);
```

* `from`/`to` are **save ids**. Unknown ids 404. When either is missing, default to the
  two most recent save points ("what changed last" is why people land here).
* The chronological guard stays: if the URL puts them the wrong way round they are
  swapped before diffing. Direction is never the user's choice.
* `?field=` narrows the comparison to one field's section; it does not change which pair
  is being compared.

### `@revert` — one field

Behaviour unchanged (base-hash check → re-validate → assign → save → record a new
`origin: revert` row). Its body moves into `RevisionReverter::revertField()` so the
whole-save path cannot drift from it.

### `@revertSave` — one save point

```
$group  = Revision::where('save_id', $save)->get();      // abort_if empty, 404
$entity = $group->first()->revisionable;                 // one entity by construction
$this->authorize('update', $entity->revisionProject());
$validated = $request->validate(['base_hashes' => ['required','array'], 'base_hashes.*' => ['required','string']]);
$restored  = app(RevisionReverter::class)->revertSave($entity, $group, $validated['base_hashes'], $request->user());
return redirect()->route(self::EDIT_ROUTES[$slug], $entity)->with('status', 'reverted-save')->with('restored_fields', $restored);
```

Authorization walks to the project through the revisionable — the ULID in the URL is only
ever a lookup key, never a capability.

## Services

### `App\Services\RevisionHistory` (new)

Every read query the history page and the pickers make.

* `forEntity(Model $entity, ?string $field, int $page): SaveGroupPage`
* `savePoints(Model $entity, ?string $field = null): Collection<SavePoint>` — the combobox
  option list.

`forEntity()` runs a portable two-step:

```sql
-- 1. the page of save points (21: 20 rendered + 1 boundary row for the "previous" link)
SELECT save_id, MAX(created_at) AS saved_at, MAX(id) AS last_id
FROM revisions
WHERE revisionable_type = ? AND revisionable_id = ?
  [AND field = ?]
GROUP BY save_id
ORDER BY saved_at DESC, last_id DESC
LIMIT 21 OFFSET (page-1)*20

-- 2. the rows of those save points — `value` is never selected
SELECT id, save_id, field, created_at, user_id, label, origin, size_bytes,
       summary_html, change_count
FROM revisions
WHERE save_id IN (...) [AND field = ?]
```

No window functions, no engine-specific string concatenation, no `GROUP_CONCAT` — rows are
folded into value objects in PHP. Because the migration deletes the pre-existing rows
(`data-model.md`), every row carries a `save_id` and there is no null branch anywhere.

Per-page comes from `config('revisions.history.per_page')`. Pagination is a
`LengthAwarePaginator` built from a `COUNT(DISTINCT save_id)` so the existing pagination
view renders unchanged.

### `App\Services\RevisionSnapshot` (new)

`asOf(Model $entity, SavePoint $point): EntitySnapshot` — the field → `?Revision` map
described above, plus `current(Model $entity): EntitySnapshot` for "the live state". Never
selects `value` for fields the caller will not diff (the comparison service asks for the
values it needs).

### `App\Services\RevisionComparison` (new)

`between(Model $entity, SavePoint $from, SavePoint $to, ?string $field): Collection<FieldComparison>`.
Resolves both snapshots, and for each registered field (filtered by `$field` when given):

* skips the field when both sides resolve to the same revision id — unchanged fields are
  not rendered at all, with a count of them shown as "N fields unchanged";
* otherwise builds a `FieldComparison` value object: field name, `FieldKind`, the two
  `Revision`s (either may be null = "did not exist yet"), and the `RevisionDiffResult`
  from `RevisionDiffer`.

This is the class the compare view iterates. The controller does no diffing.

### `App\Services\RevisionSummarizer` (new)

`summarize(FieldKind $kind, ?string $previousValue, string $newValue): RevisionSummary`
(`{ summaryHtml: ?string, changeCount: int }`), called **only** by `RevisionRecorder` at
write time — the "a diff between two immutable revisions is a constant" rule. Truncation
is by rendered length (`config('revisions.summary.max_length')`), never by hunk count.
See `diffing.md` → *Summaries*.

### `App\Services\RevisionReverter` (new)

One implementation of "check the base hash → re-validate the old value against today's
rules → assign → save (mutators run) → record a new `revert` revision", used by both
revert paths:

* `revertField(Model $entity, Revision $revision, string $baseHash, User $user): string`
* `revertSave(Model $entity, Collection $group, array $baseHashes, User $user): array`

`revertSave()` wraps everything in `DB::transaction`, verifies **every** field's base hash
before writing anything (all-or-nothing: a half-applied undo is worse than none), calls
`$recorder->startNewSave($entity)` so its output forms one new save point, and returns the
restored field names for the flash message.

### `App\Services\RevisionRecorder` (changed)

* stamps `save_id`, `summary_html`, `change_count` on insert;
* recomputes the two summary columns on a coalescing update;
* gains `currentSaveId(Model): string` / `startNewSave(Model): void`;
* registered `scoped()` in `AppServiceProvider` so one request shares one instance
  (`data-model.md` explains why this binding is load-bearing).

### `App\Services\RevisionDiffer` (changed) + `App\Services\Diff\*` (new)

`RevisionDiffer` becomes a router: `FieldKind::Rich` → the new visual differ,
`Markdown`/`Plain` → today's `jfcherng/php-diff` source diff. Call signature unchanged.
Internals and rationale: **`diffing.md`**.

`RevisionDiffResult::formattingChangedOnly()` and its Blade branch are **removed** — with
a real visual diff, "formatting changed only" stops being an unrenderable state and
becomes a diff that shows the formatting change.

## Value objects (`app/Support`, following `RevisionDiffResult`)

```php
final class SavePoint {                 // one addressable moment in an entity's history
    public string $saveId;
    public CarbonInterface $savedAt;
    public ?string $authorName;
    public ?string $label;              // first non-null label among its rows
    public RevisionOrigin $origin;      // dominant origin, precedence below
    public bool $isCurrent;             // this point is the entity's live state
    public Collection $entries;         // SaveEntry, in registry field order
}

final class SaveEntry {                 // one field touched by that save
    public int $revisionId;
    public string $field;
    public FieldKind $kind;
    public ?string $summaryHtml;
    public int $changeCount;
    public ?string $compareWithPreviousUrl;
}

final class EntitySnapshot  { /** @var array<string, ?Revision> */ public array $fields; }
final class FieldComparison { public string $field; public FieldKind $kind;
                              public ?Revision $from, $to; public RevisionDiffResult $result; }
```

A group's `origin` when its rows disagree (possible only through coalescing) is the most
deliberate one, by the fixed precedence `manual > revert > import > automatic > baseline`
— a save point containing a manual checkpoint reads as a manual save.

## Authorization

| Action | Ability | Subject |
|--------|---------|---------|
| `index`, `compare` | `view` | `$model->revisionProject()` |
| `field`, `fieldCompare` (redirects) | none — the target authorizes | — |
| `revert` | `update` | `$revision->revisionable->revisionProject()` |
| `revertSave` | `update` | `$group->first()->revisionable->revisionProject()` |

No new policy, no new ability: `ProjectPolicy` already answers both. Every route gets a
non-owner-403 test (`testing.md`).

## Configuration (`config/revisions.php`)

```php
'history' => [
    'per_page' => 20,
],
'summary' => [
    'max_length' => 200,     // rendered characters, not hunks
],
'diff' => [
    // wikidiff2's maxWordLevelDiffComplexity idea: past this, stop highlighting
    // individual words and mark the whole block changed.
    'max_word_complexity' => 2_000_000,   // |old tokens| * |new tokens|
],
```

## Frontend

* `resources/js/revision-picker.js` — the Alpine save-point combobox, registered like the
  existing Alpine components in `resources/js/app.js`, with
  `resources/js/revision-picker.test.js` beside it (vitest, per CLAUDE.md).
* No change to the autosave JS (`resources/js/autosave/`).

## What deliberately does not change

* `AutosavableFields` (registry, caps, windows, validation rules).
* `FieldAutosaveController` and the autosave request/response contract.
* `Revision::prunable()`, `RevisionPurger`, `RevisionSetting`, the admin page.
* `ProjectRevisionsBrowser`'s tree shape — only the URLs its leaves emit.
* `x-revisions-layout` and the sidebar's collapse/filter behaviour.
