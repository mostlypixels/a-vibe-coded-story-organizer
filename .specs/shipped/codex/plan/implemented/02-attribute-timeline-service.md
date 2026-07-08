# Codex plan — 02 · `AttributeTimeline` service (the temporal core)

## Goal

The gap-free, event-anchored step function is implemented and exhaustively tested at the service level. This is the project's **first `app/Services` class** (anticipated by `.claude/guidelines.md` "Where logic lives"). No HTTP surface yet — endpoints arrive in task 06.

## Depends on

01 (models, factories).

## Spec references

- [`../attribute-timeline.md`](../attribute-timeline.md) — behavior, invariants, tie-break rule.
- [`../testing.md`](../testing.md) — the invariant suite.

## Files to create/modify

### `app/Services/AttributeTimeline.php`

Constructed for one (`CodexEntry`, `CodexAttribute`) pair; loads values eager-with `startEvent`, in **canonical order `(event_datetime, events.id)` ascending** — never datetime alone.

- `periods(): Collection` — ordered values, each decorated with its computed end (the next anchor, display only).
- `valueAt(CarbonInterface|Event $moment): ?CodexAttributeValue` — greatest anchor ≤ moment in canonical order. When passed an `Event`, the **anchor-identity check wins first**: if the event is itself an anchor for this pair, return that anchor's value before any datetime comparison.
- `ensureBaseline(string $value = ''): CodexAttributeValue` — idempotent; creates the Start-anchored value if none exists. Must be a plain callable method (the seeder runs `WithoutModelEvents`, so this cannot live in a `booted()` hook).
- `upsertAt(Event $startEvent, string $value): CodexAttributeValue` — `updateOrCreate` on the anchor; backs both "add period" and "edit existing period's value".
- `removeAt(Event $startEvent): void` — deletes a period; **refuses** to remove the Start baseline while other values exist (allowed only when it is the sole value).

Find the project's Start event via its `is_fixed` flag + title/earliest datetime, matching how `Project::booted()` creates it (`app/Models/Project.php:50`).

### `app/Models/CodexEntry.php`

Add `attributeValueAt(CodexAttribute $attribute, ?Event $event): ?string` — thin wrapper delegating to the service; returns null when `$event` is null (unassigned scene → "undetermined").

## Key decisions already made

Start-anchored step function (no end column); canonical `(event_datetime, events.id)` tie-break; anchor-identity priority in `valueAt(Event)`; upsert semantics; Start baseline protected.

## Tests — `tests/Feature/AttributeTimelineTest.php`

Service-level (no HTTP; those cases extend this file in task 06). Per [`../testing.md`](../testing.md):

- Baseline anchors at the project's Start event; `ensureBaseline` is idempotent (no duplicate Start rows).
- Resolution: Start=blonde / Halloween=green / Back-to-class=black → before Halloween = blonde; exactly at Halloween = green; between Back-to-class and End = black.
- Totality: any datetime ≥ Start returns exactly one value; never null when a baseline exists.
- Upsert at an existing anchor updates the row (still one row for that anchor, new value).
- Tie-break: two anchors on events sharing a datetime resolve deterministically by event id; `valueAt($event)` where `$event` is an anchor returns that anchor's value even when another anchor shares its datetime.
- Deleting a non-fixed middle anchor event keeps it gap-free — `valueAt` in the gap resolves to the previous value (confirms `cascadeOnDelete`).
- `removeAt(Start)` refuses while other values exist; allowed as sole value.
- `attributeValueAt` with a null event returns null, no crash.

## Done when

Invariant suite green, pint clean, no behavior change anywhere else in the app.
