# Codex — Attribute timeline (temporal values)

This is the crux of the feature and the part most likely to grow bugs. It defines how a mutable, event-tied attribute value is stored, resolved, and kept gap-free. Schema lives in [`data-model.md`](data-model.md); this file defines the **behavior and where the logic lives**.

## Model: start-anchored step function

Each `codex_attribute_values` row says *"from this event onward, the value is X."* A period is **implicit**: it runs from its `start_event.event_datetime` until the next value's start (or the **End** event). We deliberately do **not** store an end event.

```
Start ──"blonde"──▶ Halloween ──"green"──▶ Back to class ──"black"──▶ End
        value A                value B                    value C
```

Resolving the value **at datetime `t`**: pick the value whose `start_event.event_datetime` is the **greatest value ≤ t**. That single row is the answer.

### Tie-break: events sharing a datetime

Nothing in the schema makes `events.event_datetime` unique, so two anchor events can share the exact same datetime. Resolution must stay deterministic:

- **Canonical anchor order** is `(event_datetime, events.id)` ascending — everywhere: `periods()`, `valueAt`, and any display ordering. Never sort by datetime alone.
- `valueAt(datetime $t)` picks the **last** anchor in canonical order with `event_datetime ≤ t`.
- `valueAt(Event $e)` first checks whether `$e` **is itself an anchor** for the pair — if so, that anchor's value wins outright (a scene "during Halloween" sees the Halloween value even if another event shares Halloween's datetime). Only when `$e` is not an anchor does it fall back to the datetime lookup above.

### Why start-only (not start+end per period)

- **No holes, no overlaps by construction.** With start+end columns you must keep adjacent periods flush; any edit can open a gap or overlap. With start-only, the periods tile the timeline automatically.
- **Event deletion is safe.** Deleting a middle anchoring event just drops that value; the previous value's period extends to fill it — still gap-free. This is precisely why `start_event_id` uses `cascadeOnDelete` (data-model invariant #2).
- The start+end alternative is called out in [`open-questions.md`](open-questions.md).

## Invariants (enforced in a service, not the model)

1. **Leading anchor at Start.** For any (entry, attribute) with ≥1 value, exactly one value is anchored at the project's *Start* event. No value may be anchored *before* Start (impossible — Start is year 0000) and none may resolve to a hole at the beginning.
2. **One value per anchor.** Unique `(entry, attribute, start_event)` — enforced at the DB level as a backstop. The store endpoint is an **upsert** (`upsertAt` uses `updateOrCreate`), so posting an anchor that already has a value *updates* it rather than erroring; the Form Request therefore has **no** `Rule::unique` on `start_event_id`. This is also how existing period values (including the Start baseline) get edited — there is no separate update route.
3. **Anchors belong to the project.** `start_event_id` must be an event of the entry's project (`Rule::exists('events','id')->where('project_id', …)`, same pattern as `StoreSceneRequest`).
4. **Resolution is total.** `valueAt(t)` for any `t ≥ Start` returns exactly one value (given invariant #1). Callers never handle "no value."

> [!WARNING]
> Because `DatabaseSeeder` runs `WithoutModelEvents`, invariant #1 must **not** rely solely on a `booted()` hook — put the "ensure Start baseline" logic in a service method the seeder can call directly.

## Where the logic lives

Per guidelines ("reusable / multi-step domain workflows → a dedicated Service/Action class"; "create `app/Services` the first time an action needs non-trivial reusable logic"), introduce the first service layer here:

### `App\Services\AttributeTimeline`

Constructed for one (`CodexEntry`, `CodexAttribute`) pair; loads that pair's values eager-with `startEvent`, ordered by `event_datetime`.

- `periods(): Collection` — ordered values, each optionally decorated with its computed `endEvent` (the next value's start, for display only).
- `valueAt(CarbonInterface|Event $moment): ?CodexAttributeValue` — the "greatest start ≤ moment" lookup, using the canonical `(event_datetime, events.id)` order. Accepts an `Event` so scenes can pass their "happens during" event directly — and in that form the **anchor-identity check wins first** (see tie-break above) before falling back to the event's `event_datetime`.
- `ensureBaseline(string $value = ''): CodexAttributeValue` — creates the Start-anchored value if none exists (used on first edit and by the seeder).
- `upsertAt(Event $startEvent, string $value): CodexAttributeValue` — `updateOrCreate` on the anchor: inserts a new period or updates the existing one's value (invariant #2). This single method backs both "add period" and "edit an existing period's value".
- `removeAt(Event $startEvent): void` — delete a period; refuses to delete the Start baseline (would break invariant #1) unless it is the *only* value.

Resolution is a **pure timeline computation** — no writes — so it can also be exposed as read helpers on the model for view convenience:

- `CodexEntry::attributeValueAt(CodexAttribute $attribute, Event $event): ?string` — thin wrapper delegating to the service, for use on the scene/event "as of" views.

Keep the controller thin: resolve → authorize (via project) → delegate to `AttributeTimeline` → redirect.

## Reading a value "as of" a scene

A scene resolves its moment through its existing **"happens during"** event (`Scene::event()`, nullable). Given that event:

1. `AttributeTimeline` for (entry, attribute) → `valueAt($scene->event)`.
2. If `$scene->event` is null (scene unassigned — already flagged with a red border in the scenes index), the value is **undetermined**; show a muted "—" rather than guessing.

This lookup powers the acceptance-criteria story "open a scene during *Back to class* → hair is **black**." Eager-load to avoid N+1: when rendering a table of entries "as of" one event, load `attributeValues.startEvent` once per entry (guidelines: eager-load what a view renders).

## Editing UX contract (see [`ui.md`](ui.md))

- The sheet shows each applicable attribute as a **row of periods** in chronological (canonical) order: `Start → blonde`, `Halloween → green`, `Back to class → black`.
- Adding a period = pick an **event** (reuse the searchable event picker pattern from `x-event-picker`) + type a value. It calls `upsertAt`.
- **Editing an existing period's value** posts to the *same* store route with the row's anchor event in a hidden input — the upsert updates in place. No separate update route/action.
- The **Start** row's event is fixed/locked (can't be moved off Start); its value is editable (via the same upsert).
- Removing a period calls `removeAt`; the Start baseline row has no delete affordance (parallels how `is_fixed` events and the main plotline hide their delete buttons).
- Server always re-validates invariants — never trust the client to keep it gap-free.
