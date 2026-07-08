# Timeline integrity — findings 1, 4, 8

Three findings with one root cause: *"the project's Start event"* is a domain concept of
`Project` (its `booted()` `created` hook creates it, `app/Models/Project.php:64-70`), but it is
resolved by an ordering-based query copy-pasted in two places, and the invariant that depends
on it (`ensureBaseline` before any other period) is only enforced on one of the two write
paths. Fix order matters: **8 first** (the helper is the seam), then **4** (make the helper's
answer stable), then **1** (enforce the invariant through the helper).

## Current state

- `AttributeTimeline::startEvent()` / `endEvent()` — `app/Services/AttributeTimeline.php:193-212`:
  private, resolve "earliest / latest `is_fixed` event ordered by `(event_datetime, id)`".
- `CodexEntryController::startEvent()` — `app/Http/Controllers/CodexEntryController.php:291-298`:
  an identical private copy, used by `edit()` to split the baseline row out in `timelineSheets()`.
- `AttributeTimeline::ensureBaseline()` (`:94`) is called only from
  `CodexEntryController::seedAttributeBaselines()` (entry **create**, `:257`) and
  `MelusineSeeder`. `upsertAt()` (`:114`) — the body of the period-store endpoint
  (`CodexAttributeValueController@store:32`) — never ensures a baseline.
- `UpdateEventRequest` (`app/Http/Requests/UpdateEventRequest.php:23`) allows
  `event_datetime` edits on any event, including `is_fixed` bookends.

## Finding 8 — extract `Project::startEvent()` / `Project::endEvent()`

Add two public helpers on `app/Models/Project.php` (methods returning `Event`, not `HasOne`
relations — the ordering + `firstOrFail` semantics read better as methods, and nothing needs
to eager-load them):

```php
/**
 * The project's Start bookend event (created undeletable by booted();
 * the anchor for every attribute-timeline baseline).
 */
public function startEvent(): Event
{
    return $this->events()
        ->where('is_fixed', true)
        ->orderBy('event_datetime')
        ->orderBy('id')
        ->firstOrFail();
}

public function endEvent(): Event { /* orderByDesc twin */ }
```

Then:

- `AttributeTimeline::startEvent()` (`:193`) and `::endEvent()` (`:205`) become one-liners
  delegating to `$this->entry->project->startEvent()` / `->endEvent()` — or are deleted and
  call sites (`ensureBaseline`, `removeAt`, `periods`) call the project helper directly.
  Keeping thin private delegates is fine; the query must exist exactly once.
- `CodexEntryController::startEvent(Project $project)` (`:291-298`) is deleted; `edit()`
  (`:111`) calls `$project->startEvent()`.
- The exact ordering (`event_datetime` **then** `id`) is part of the contract — keep the
  comment explaining why datetime alone is not enough (CLAUDE.md: "never datetime alone").

This is consistent with `.claude/guidelines.md`: it is *reference/lookup* logic tied to a
model lifecycle invariant, not application workflow, so a model method is the right home
(same category as the `booted()` hook that creates the events).

## Finding 4 — make bookend resolution stable

**Recommended resolution (shallowest, per the source spec): forbid `event_datetime` edits on
`is_fixed` events.** The bookends exist purely as timeline sentinels; their dates (year 0000 /
3000) are meaningless to the writer. Implementation:

- In `UpdateEventRequest::rules()`, exclude or reject the field when the bound event is fixed:

  ```php
  'event_datetime' => $this->route('event')->is_fixed
      ? ['prohibited']
      : ['required', 'date'],
  ```

  `prohibited` gives a real validation error if the field is tampered back in; the edit form
  additionally hides/disables the datetime input for fixed events
  (`resources/views/events/edit.blade.php` — same `@unless ($event->is_fixed)` pattern the
  views already use to hide the delete affordance). Title/description/plotlines stay editable
  ("editable but not deletable" is the existing bookend contract; this narrows it to
  "datetime frozen", which `open-questions.md` flags for confirmation).
- If the form disables the input, `update()` must not null the datetime — use
  `$request->safe()->except('event_datetime')` semantics or keep the field `prohibited` +
  absent and don't include it in the update payload for fixed events. Check
  `EventController@update` for how validated data is applied.

The alternative (an `is_start` flag or lowest-id resolution) is a schema/semantic change and
only needed if freezing the datetime is rejected — see `open-questions.md` Q1.

Also update the docs: CLAUDE.md's Codex section says the anchor "can't be orphaned" because
the events are undeletable — after this fix the guarantee is stronger (can't be *re-ordered*
either); say so where the step-function invariant is documented
(`documentation/architecture.md` / glossary, wherever task 09 put it).

## Finding 1 — enforce the gap-free invariant in `upsertAt`

Service-level fix in `app/Services/AttributeTimeline.php:114`, so the controller, seeder, and
any future caller all inherit it:

```php
public function upsertAt(Event $startEvent, string $value): CodexAttributeValue
{
    // Invariant: every valued pair has a Start-anchored baseline, so valueAt()
    // is total for t >= Start. Adding a mid-timeline period to a pair that was
    // never valued must not open a hole before the new anchor.
    if ($startEvent->id !== $this->entry->project->startEvent()->id) {
        $this->ensureBaseline();
    }

    // ... existing updateOrCreate ...
}
```

Notes:

- `ensureBaseline()` is already idempotent (`firstOrCreate`), so calling it on every non-Start
  upsert is safe; the extra cost is one `firstOrCreate` per period store — acceptable.
- The baseline value defaults to `''` — this is the same semantics `seedAttributeBaselines`
  uses when the create form leaves an attribute blank. Finding 5 (in `ui-fixes.md`) makes `''`
  a first-class storable value, so the two fixes belong in the same change set.
- When the anchor **is** the Start event, `upsertAt` already *is* the baseline write —
  no extra call (and `ensureBaseline` first would race the value: `firstOrCreate` would pin
  `''` before `updateOrCreate` overwrote it; the guard above avoids the double write).
- `ensureBaseline` and `upsertAt` each reset the `$values` memo, so ordering within the method
  is safe.
- Wrap the two writes in `DB::transaction` (guidelines: multi-step writes) — either inside
  `upsertAt` or note that the only HTTP caller is a single-request action; prefer inside the
  service since the seeder calls it too.

**Test to fix, not just extend:** `tests/Feature/AttributeTimelineTest.php:203`
(`test_store_creates_a_period`) currently codifies the hole — it stores at Halloween on a
never-valued pair and asserts only the Halloween row. Extend it to also assert a
Start-anchored row exists for the pair (see `testing.md`).
