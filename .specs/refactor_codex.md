# Codex feature — code review & refactor suggestions

Review of commit `c338ca9` (branch `codex` vs `master`), the Codex implementation produced by the
`codex-implementer` agent from `.specs/codex/plan`. Scope: full diff (~5,500 lines — app code,
migrations, views, seeder, tests, docs). Verdicts: **CONFIRMED** = traced end-to-end in the code;
**PLAUSIBLE** = depends on a realistic runtime condition the code does not exclude.

Overall: the implementation follows the spec and the repo conventions closely — thin controllers,
FormRequest validation mirroring `ProjectPolicy`, the `AttributeTimeline` service owning all
temporal logic, `x-table` indexes, solid feature tests (73 test methods across 5 files), docs and
CHANGELOG updated. The findings below are the gaps a careful pass turned up, ranked most severe
first.

---

## Findings

### 1. Gap-free invariant is not enforced on the period-store endpoint — CONFIRMED (correctness)

**Where:** `app/Http/Controllers/CodexAttributeValueController.php:32` / `app/Services/AttributeTimeline.php:114` (`upsertAt`)

The spec (`.specs/codex/attribute-timeline.md`, and CLAUDE.md: *"every valued (entry, attribute)
pair has exactly one value anchored at the project's Start event (`ensureBaseline`), so `valueAt`
is total for t ≥ Start"*) requires a Start baseline before any other period exists. But the only
places `ensureBaseline` is called are entry **create** (`seedAttributeBaselines`) and the seeder.
The value store endpoint calls `upsertAt` directly.

**Failure scenario:** create an entry, *then* create a new attribute that applies to its type
(so no baseline was seeded at entry-create time). On the entry's edit page, use "Add period at…"
with a mid-timeline event (e.g. *Halloween*). The pair is now "valued" with no Start anchor:
`valueAt(t < Halloween)` returns `null`, and every scene/event "as of" panel before Halloween
shows "—" — a hole in a timeline the spec promises is gap-free. `AttributeTimelineTest::
test_store_creates_a_period` (line 203) exercises exactly this state and codifies the hole
rather than catching it.

**Fix:** make `AttributeTimeline::upsertAt()` call `$this->ensureBaseline()` first whenever the
anchor is not the Start event (service-level fix, so the controller, seeder and any future caller
all inherit the invariant — right altitude). Extend the test to assert a baseline row exists after
storing a mid-timeline period.

---

### 2. Deleting a project (or user) leaks every codex media file on disk — CONFIRMED (correctness)

**Where:** `app/Models/Project.php:52` (`booted()` has `created` but no `deleting` hook); `app/Http/Controllers/ProjectController.php:52`

Media file cleanup lives in `CodexEntry`'s `deleting` model hook (`CodexMediaService::purge`).
That hook only fires when an entry is deleted through Eloquent. `ProjectController@destroy` calls
`$project->delete()`, and `codex_entries.project_id` is `cascadeOnDelete` — the DB cascade removes
the entry rows **without firing any model events**, so `purge` never runs. The same applies to
account deletion (Breeze profile destroy → user cascade → projects → entries).

**Failure scenario:** a project with entries carrying covers/reference media is deleted → all
its files stay on the `public` disk forever, unreferenced and unrecoverable by any UI.

**Fix:** add a `deleting` hook on `Project` that purges codex media before the cascade — e.g.
`$project->codexEntries()->with('media')->get()->each(fn ($e) => app(CodexMediaService::class)->purge($e))`,
or a bulk variant on the service (one query over `codex_media` joined through entries). Mirror it
for user deletion if profiles can be deleted. Add a feature test: delete project → `Storage::disk('public')`
no longer has the files (the existing `CodexMediaTest::test_destroying_an_entry_removes_all_its_media_files`
is the template).

---

### 3. Disk writes/deletes happen inside the DB transaction — PLAUSIBLE (correctness)

**Where:** `app/Http/Controllers/CodexEntryController.php:209` (`applyMediaChanges`, called inside `DB::transaction` at lines 85 and 130)

`applyMediaChanges` deletes files (`CodexMediaService::remove`, `storeCover`'s replace path) and
stores uploads while the transaction is still open. Disk operations are not transactional:

- **Rollback after a removal:** removals run first; if a later step throws (a failing upload in
  `storeMany`, a DB error creating the media row), the transaction rolls back and *restores the
  removed media rows* — but their files are already gone from disk. Result: media rows whose
  `url()` 404s (broken images), with no way to detect it.
- **Rollback after an upload:** files stored before the failure survive the rollback as orphans
  on disk (the comment at line 96 claims ordering prevents this, but only for failures in the
  *earlier* steps — not for a failure between two media operations).

**Fix:** split the media pass around the transaction boundary: inside the transaction, only decide
what to do (collect rows to remove); perform disk deletes **after commit** (`DB::afterCommit(...)`
or simply after the `DB::transaction()` call returns), and store uploads either after commit or
with a catch that unlinks the just-written files before rethrowing.

---

### 4. Start/End resolution silently breaks if a bookend event's datetime is edited — PLAUSIBLE (correctness)

**Where:** `app/Services/AttributeTimeline.php:193` (`startEvent()`), `:205` (`endEvent()`), duplicated at `app/Http/Controllers/CodexEntryController.php:291`

The Start event is resolved as "earliest `is_fixed` event". Bookend events are undeletable but
**editable** — `EventController@update` places no restriction on changing an `is_fixed` event's
`event_datetime`. If the writer moves "Start" later than an existing anchor (or past "End"),
every baseline stays anchored to the old Start event's id while `startEvent()` may now resolve to
the *other* bookend: `valueAt` develops holes before the moved anchor, `timelineSheets` stops
recognizing the baseline row as the baseline (it compares `start_event_id` to the freshly-resolved
Start), and `removeAt`'s baseline guard checks the wrong row.

**Fix (pick one, shallowest first):** forbid editing `event_datetime` on `is_fixed` events in
`UpdateEventRequest` (the bookends exist purely as timeline sentinels — cheap and honest), or
resolve Start by a stable marker rather than ordering (e.g. an `is_start` flag / lowest-id fixed
event). Either way, add a regression test.

---

### 5. Timeline editor swallows validation errors, and empty values can never be saved — CONFIRMED (correctness/UX)

**Where:** `resources/views/codex/partials/attribute-timeline.blade.php:29` (forms render no `value`/`start_event_id` errors); `app/Http/Requests/StoreAttributeValueRequest.php:33` (`'value' => ['required', ...]`)

Two compounding problems:

- The partial only renders `$errors->get('attribute_value')` (the destroy-guard message). A failed
  **store** (e.g. "Add period" submitted without picking an event, or with an empty value) redirects
  back with errors under `value` / `start_event_id` — none of which are displayed anywhere on the
  page. The form just resets; to the writer, Save silently did nothing.
- `value` is `required`, yet baselines are legitimately seeded as `''` (entry-create form and
  `ensureBaseline('')`). So the pre-existing empty baseline row renders an empty input whose Save
  button can never succeed until the writer types something — and a value can never be *cleared*
  back to empty. Create-form and edit-form semantics disagree about whether an empty value is valid.

**Fix:** render `<x-input-error :messages="$errors->get('value')" />` and `->get('start_event_id')`
in the card (session errors are shared across the small forms — acceptable here, or key them per
attribute), and relax `value` to `['present', 'string', 'max:255']` so empty is storable,
matching the baseline semantics.

---

### 6. DB query inside a Blade partial — CONFIRMED (conventions)

**Where:** `resources/views/codex/partials/fields.blade.php:91` — `:tags="$project->tags()->orderBy('name')->get()"`

`.claude/guidelines.md`: *"Keep presentation logic out of Blade templates"* and *"Eager-load the
relations a view renders"*. The entry form partial runs its own query for the tag picker on every
create/edit render; `CodexEntryController@index` correctly passes `$tags` from the controller, but
`create()` and `edit()` don't.

**Fix:** pass `'projectTags' => $project->tags()->orderBy('name')->get()` from `create()`/`edit()`
and consume it in the partial (with the `?? collect()` guard the partial already uses for
`$attributes`).

---

### 7. Reference-upload validation errors are only shown for the first file — CONFIRMED (UX)

**Where:** `resources/views/codex/partials/fields.blade.php` (reference images/files cards) — `$errors->get('reference_images.0')` / `$errors->get('reference_files.0')`

If the second selected file is oversized/wrong type, the error bag holds `reference_images.1`,
which nothing renders — the save fails with no visible message near the input. The codebase
already solves this: `codex-attributes/partials/fields.blade.php` uses the wildcard form
`$errors->get('applies_to.*')`.

**Fix:** replace the `.0` lookups with `$errors->get('reference_images.*')` /
`$errors->get('reference_files.*')` (drop the now-redundant second `x-input-error`).

---

### 8. Start/End event resolution is duplicated instead of living on `Project` — reuse

**Where:** `app/Services/AttributeTimeline.php:193` and `app/Http/Controllers/CodexEntryController.php:291` (identical `startEvent()` queries), `AttributeTimeline.php:205` (`endEvent()`)

"The project's Start event" is a domain concept of `Project` (its `booted()` hook creates it), yet
two classes now define it with copy-pasted queries. A third consumer (a future timeline view, the
seeder's `firstOrCreate` fallback) would make a fourth copy — and finding 4 shows the definition
is subtle enough that it must not drift between copies.

**Fix:** add `Project::startEvent(): Event` and `Project::endEvent(): Event` helpers (or `HasOne`
relations with the ordering baked in) and call them from both places. This is also the natural
seam for whatever finding 4's resolution turns out to be.

---

### 9. Codex route keys are magic strings repeated across routes and navigation — conventions/altitude

**Where:** `routes/web.php:64, 67, 70` (three identical `->whereIn('type', ['characters', 'locations', 'organizations'])`), `resources/views/layouts/navigation.blade.php` (hardcoded `'characters'`/`'locations'`/`'organizations'` links, duplicated desktop + responsive)

The spec and guidelines both say *"No magic strings — entity types … are enums"*, and
`CodexEntryType::routeKey()` exists — but the route file and nav re-list the literals. Adding a
fourth entry type (the spec's open questions mention e.g. "Items") requires touching five scattered
string lists or the new type silently 404s / never appears in the nav.

**Fix:** add `CodexEntryType::routeKeys(): array` (`array_map(fn ($c) => $c->routeKey(), self::cases())`),
use it in one shared route constraint (`Route::pattern('type', implode('|', ...))` or a grouped
`whereIn`), and render the nav links by iterating `CodexEntryType::cases()` (label from
`pluralLabel()`, key from `routeKey()`).

---

## Lower-priority refactor notes (no action required now)

- **`CodexAsOfResolver` cost on every scene/event edit page** — it loads *all* entries with *all*
  attribute values on each page view, then re-filters/re-sorts the loaded collection per
  (entry, attribute) inside `AttributeTimeline::orderedValues()`. Fine at this project's scale
  (the eager-load fast path already avoids N+1 queries); revisit only if codexes reach hundreds
  of entries — the panel is `<details>`-collapsible, so lazy-loading it via a small AJAX endpoint
  would be the natural next step.
- **Narrowing an attribute's `applies_to` strands its values** — untick "Character" on *Hair color*
  and existing character values stay in the DB but disappear from every sheet and as-of panel
  (they reappear if the type is re-ticked). Arguably a feature (non-destructive), but the
  attribute edit form should say so, the way the destroy confirm warns about data loss.
- **`RuntimeException` as control flow in `removeAt`** — the controller catches the service's
  exception and converts it to a validation error. Works, but the Blade already hides the Remove
  button for the baseline, so the guard is only reachable by hand-crafted requests; a 403
  (`abort_if`, matching the `is_main`/`is_fixed` convention) would be simpler than
  exception-to-redirect translation.
- **Orphaned tags accumulate** — removing a tag from its last entry leaves the `tags` row, and the
  index filter dropdown lists tags that match nothing. Harmless; a periodic cleanup or
  `whereHas('entries')` on the dropdown query would tidy it.
- **Timeline editor forms lose input on validation failure** — the per-period forms don't use
  `old()`, so a failed submit clears what was typed. Folded into finding 5's fix if the errors
  become visible.

## Verified positives (checked, no action)

- Authorization: every action authorizes via `ProjectPolicy` walk-up; store/update rely on
  FormRequest `authorize()` only — **consistent with the existing controllers** (Plotline/Event/
  Scene do the same). Cross-project injection is guarded for anchor events, baseline attribute
  keys, `remove_media` ids, and tags (project-scoped `firstOrCreate`).
- The `x-event-picker` → `x-chip-picker` refactor preserves the old component's behavior
  (same hidden-input contract, search semantics, Enter/Escape handling); scene form submission
  is unaffected.
- Seeder correctly sets `position` explicitly and drives `AttributeTimeline` directly
  (`WithoutModelEvents` caveats honored); idempotent on re-seed.
- Migrations: sensible indexes (`(project_id, type)`, the values unique key doubling as the
  timeline-lookup index); `nullOnDelete`/`cascadeOnDelete` choices match the spec.
- Test suite covers happy paths, 403s, validation failures, tie-break determinism, cascade
  behavior, and media file cleanup on entry delete.

---

## Appendix — findings as JSON

```json
[
  {"file": "app/Services/AttributeTimeline.php", "line": 114, "summary": "upsertAt never ensures a Start baseline, so the period-store endpoint can violate the gap-free invariant", "failure_scenario": "Attribute created after an entry exists; owner adds a period at a mid-timeline event; valueAt(t) is null for all t before that event and as-of panels show holes"},
  {"file": "app/Models/Project.php", "line": 52, "summary": "Project deletion cascades codex_entries at the DB level, bypassing the CodexEntry deleting hook, leaking all media files on disk", "failure_scenario": "Delete a project whose entries have covers/reference media; codex_media rows vanish via cascade but every file stays on the public disk forever"},
  {"file": "app/Http/Controllers/CodexEntryController.php", "line": 209, "summary": "Disk deletes/writes run inside DB::transaction, so a rollback restores media rows whose files are already deleted (and orphans partial uploads)", "failure_scenario": "Update removes media A then a later upload throws; transaction rolls back, A's row is restored but its file is gone — broken image with no recovery path"},
  {"file": "app/Services/AttributeTimeline.php", "line": 193, "summary": "Start/End are resolved by ordering is_fixed events by datetime, but bookend datetimes are editable, silently re-anchoring or breaking the baseline invariant", "failure_scenario": "Writer edits the Start event's datetime past an anchor (or past End); baselines stop being recognized as baselines, valueAt develops holes, removeAt guards the wrong row"},
  {"file": "resources/views/codex/partials/attribute-timeline.blade.php", "line": 29, "summary": "Timeline editor renders no validation errors for value/start_event_id, and value:required makes empty values unsavable despite baselines being seeded empty", "failure_scenario": "Writer submits Add-period without a value (or tries to save/clear an empty baseline): redirect back, form resets, no message anywhere — Save appears to silently do nothing"},
  {"file": "resources/views/codex/partials/fields.blade.php", "line": 91, "summary": "Tag-picker options are queried directly in the Blade partial, violating the keep-logic-out-of-Blade guideline", "failure_scenario": "Guidelines '.claude/guidelines.md' (\"Keep presentation logic out of Blade templates\", eager-load rule) broken; create/edit run a hidden query the controller should pass in like index already does"},
  {"file": "resources/views/codex/partials/fields.blade.php", "line": 138, "summary": "Reference upload errors are only rendered for index 0 (reference_images.0 / reference_files.0), hiding failures on any later file", "failure_scenario": "Second selected file is oversized; error bag holds reference_images.1, nothing renders it, save fails with no visible message"},
  {"file": "app/Http/Controllers/CodexEntryController.php", "line": 291, "summary": "startEvent()/endEvent() resolution duplicated between AttributeTimeline and CodexEntryController instead of a Project helper", "failure_scenario": "Two copies of a subtle domain query (see finding 4) can drift independently; a third consumer makes a third copy"},
  {"file": "routes/web.php", "line": 64, "summary": "Codex route keys hardcoded as string lists in three route constraints and the navigation instead of derived from CodexEntryType", "failure_scenario": "Adding a fourth entry type requires editing five scattered literal lists (routes ×3, nav ×2) or it 404s / never appears in the menu"}
]
```
