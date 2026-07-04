# 04 — Timeline editor validation & UX (finding 5 + ride-alongs)

## Scope

Make every timeline-editor failure visible, make empty values storable, preserve typed
input across failed submits, and convert the baseline-removal guard to the project's 403
convention.

1. **Validation rule** — `app/Http/Requests/StoreAttributeValueRequest.php:32`:
   `'value' => ['present', 'string', 'max:255']`. Verify an empty text input posts `""`
   (passes `string`); only add `nullable` + a `(string)` cast before `upsertAt` if null can
   actually arrive.
2. **Error rendering** — `resources/views/codex/partials/attribute-timeline.blade.php`,
   next to the existing `attribute_value` error (line 16): add
   `<x-input-error :messages="$errors->get('value')" />` and
   `:messages="$errors->get('start_event_id')"`.
3. **`old()` preservation** — same partial: baseline input (line 42)
   `old('value', $sheet['baseline']?->value)`; period inputs (line 64)
   `old('value', $period->value)`; add-period row (lines 81-99) `old('start_event_id')`
   selected + `old('value')`.
4. **`removeAt` guard → 403** —
   `app/Http/Controllers/CodexAttributeValueController.php@destroy` (`:50-55`): replace the
   RuntimeException try/catch + `withErrors` with the `abort_if` → 403 convention (matches
   `is_main`/`is_fixed` guards). Preferred shape: `AttributeTimeline` exposes the check
   (e.g. `canRemoveAt(Event $startEvent): bool` or the existing count logic made queryable)
   and the controller does `abort_if(! $timeline->canRemoveAt(...), 403)`; `removeAt` can
   keep or drop its internal throw — controller no longer catches. Remove the now-dead
   `$errors->get('attribute_value')` render in the partial (line 16) **only if** nothing
   else feeds that key after this change.

Does **not** touch `upsertAt` semantics (task 03) or the entry form partial (task 08).

## Depends on

03 (shared `''`-as-value semantics; 03's baseline default and this task's `present` rule
form one coherent change).

## Key decisions already made

- Q2: `''` is first-class; no "blank displays as —" work here.
- Q3: shared session error bag and shared `old()` across the card's small forms is
  accepted — no named bags, no per-attribute keying. A failed submit echoing into sibling
  forms is known and tolerated.
- Q5: the 403 conversion is in scope (user-confirmed ride-along). The Blade already hides
  Remove on the baseline, so the guard is only reachable by hand-crafted requests — 403 is
  the honest response.

## Consult

`.specs/refactor_codex/ui-fixes.md` (§ Finding 5), `open-questions.md` Q2/Q3/Q5,
`.specs/refactor_codex.md` lower-priority notes.

## Tests

In `tests/Feature/AttributeTimelineTest.php`:

- `test_store_allows_an_empty_value`: post `value => ''` at the Start anchor → row
  persists with `''`, redirect has no errors. Fails before the fix (`required`).
- `test_store_without_an_event_shows_a_validation_error`: add-period post with
  `start_event_id => ''` → `assertSessionHasErrors('start_event_id')`, then follow the
  redirect to `codex.edit` and `assertSee` the message (this catches the missing render;
  fails before the Blade fix).
- Baseline-removal guard: deleting the Start baseline while later periods exist →
  `assertStatus(403)` (replaces/updates the existing redirect-with-errors assertion, which
  should now fail). Sole-value baseline delete still succeeds.
- Value can be cleared: upsert a non-empty period, re-post `''` at the same anchor → value
  is `''`.
