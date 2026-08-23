# Birth and death — data model

## Migration

Add two nullable FK columns to `codex_entries`:

```
inception_event_id    → events.id  nullable  nullOnDelete
termination_event_id  → events.id  nullable  nullOnDelete
```

Neutral names on purpose: "created" entities outnumber "born" ones, so birth/death would name
the columns after the minority case. `origin`/`end` were rejected as too generic (likely to name
other concepts). The per-type wording (Born/Created…) lives only in the UI labels, not the schema.

- `nullOnDelete`, not cascade: a deleted event clears the link but never deletes the entry.
- No index needed beyond the FK: lookups are per-entry, never "entries by inception event".
- Reuse the existing `events` table. Birth/death are ordinary events (`is_fixed = false`), so
  `WithinEventWindow`, plotlines, and the attribute timeline all keep working unchanged.

## Why FK columns, not reserved attributes

Inception/termination is a single event link, not a start-anchored step function. It has no
"value" and no succession of periods. Two nullable FKs model it honestly; folding it into
`AttributeTimeline` would overload a value column with an event id and break the baseline
invariant. See `open-questions.md` if this is contested.

## CodexEntry

- Add `inception_event_id`, `termination_event_id` to `$fillable`.
- `inceptionEvent(): BelongsTo` and `terminationEvent(): BelongsTo` → `Event`.
- `ageAt(?Event $moment): ?Age` — the derived fact. Returns `null` when there is no inception
  event, `$moment` is null, or the lifespan is inverted; otherwise an `Age` value object (see
  `architecture.md`).
- `existsAt(?Event $moment): bool` — the existence-window test (see `architecture.md`). The
  resolver filters the scene/event panel on it.
- `hasInvertedLifespan(): bool` — both links set and termination before inception (see Invariants).

Lifespan is a per-type capability: `CodexEntryType::tracksLifespan()` gates the edit-page fields,
the age, and the existence filter. A non-tracking type has no inception/termination and always
shows.

## Event

- Add inverse relations only if a caller needs them (an event's inception/termination dependents).
  None does yet — skip until a second caller appears (project convention).

## Seeding

The Melusine seed should set at least one character's inception (and ideally a termination) so the
scene panel demonstrates age. Seeders run `WithoutModelEvents`; a plain column assign is enough
here — no service call, unlike attribute baselines.

## Invariants

- **No ordering rule.** Termination may precede inception — time travel is a common fiction trope
  and the app must not forbid it. Instead age is suppressed (see below) and the edit page warns.
- `CodexEntry::hasInvertedLifespan(): bool` — true when both events are set and termination is
  before inception. Drives both the age suppression and the UI warning; one home for the rule.
- Bookends (Start/End) are excluded from the pickers — an inception in year 0001 or year 3000 is
  never intended. Server mirrors this (see `architecture.md`).
- The link is independent of attribute periods: a value may be anchored before inception or after
  termination. Not our problem to police.
