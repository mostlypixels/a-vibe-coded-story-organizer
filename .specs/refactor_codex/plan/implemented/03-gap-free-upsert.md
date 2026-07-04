# 03 — Gap-free upsert (finding 1)

## Scope

Enforce the gap-free invariant at the service layer so the period-store endpoint (and any
future caller) cannot create a valued pair without a Start baseline.

- `app/Services/AttributeTimeline.php::upsertAt()` (`:114`): when the anchor is **not**
  the project's Start event (`$startEvent->id !== $this->entry->project->startEvent()->id`),
  call `$this->ensureBaseline()` first (default `''`). When the anchor **is** Start, skip
  it — the upsert itself is the baseline write, and pre-seeding would race the posted value
  (double write of `''` then the value).
- Wrap the two writes in `DB::transaction` inside the service (the seeder calls `upsertAt`
  too, so the transaction belongs there, not in the controller).
- Comment the invariant at the guard, per the snippet in `timeline-integrity.md`.

Does **not** change validation rules or the Blade editor (task 04). No controller changes —
that is the point of the service-level fix.

## Depends on

01 (uses `Project::startEvent()`), 02 (the invariant this enforces assumes stable bookends).

## Key decisions already made

- Service-level enforcement, not controller-level (right altitude — source spec finding 1).
- Baseline default is `''` — a first-class "recorded as blank" value (binding decision Q2);
  task 04 makes `''` storable through the endpoint, so implement 03 and 04 back to back.
- `ensureBaseline` stays idempotent `firstOrCreate`; the extra call per non-Start upsert is
  accepted cost.

## Consult

`.specs/refactor_codex/timeline-integrity.md` (§ Finding 1), `testing.md` (§ Finding 1).

## Tests

In `tests/Feature/AttributeTimelineTest.php`:

- **Amend `test_store_creates_a_period` (line 203)** — it currently codifies the hole.
  After posting the Halloween period on a never-valued pair, also assert a Start-anchored
  row with `value === ''` exists for the pair. Fails before the fix.
- New service-level test: `upsertAt` at a mid-timeline event on an unvalued pair →
  `valueAt(<moment before the anchor>)` is non-null (the `''` baseline) — no hole.
- No-double-write guard: `upsertAt` at the **Start** event on an unvalued pair → exactly
  one row for the pair, carrying the posted value (assert count 1 + value).
- Re-run the seeder locally once (`php artisan db:seed`) to confirm `MelusineSeeder`
  idempotency still holds with the new `upsertAt` behavior.
