# Birth and death — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

- Columns named `inception_event_id` / `termination_event_id`, not birth/death — "created"
  entities outnumber "born" ones, and `origin`/`end` are too generic.
- Age and the lifespan fields are gated per type via `CodexEntryType::tracksLifespan()` — a future
  type with no age concept opts out.
- Scene/event codex panel shows an entity only while it exists (`inception <= moment <=
  termination`, inclusive). Before inception and after termination it is hidden — no label, no tag.
- Time travel (termination before inception) is a legal save: age suppressed, existence filter
  skipped so the entity always shows, and the edit page warns to track age via an attribute.
- Lifespan links are set on the edit page only; the create form gets no fields.

## Deviations from the spec/plan

- Shared event picker landed as a global component, `resources/views/components/single-event-field.blade.php`
  (`<x-single-event-field>`), not a `codex/partials/` file — both the scene "Happens
  during" field and the two codex lifespan fields use it, and `x-event-picker` already
  names the unrelated multi-select chip picker used for "Mentions events".

## Issues → resolutions

- `Rule::exists(...)->where('is_fixed', false)` matched nothing on SQLite — PDO binds a PHP
  `false` in a way that never equals the stored `0`. Fixed by passing `0` instead of `false`
  in the bookend-exclusion rule for `inception_event_id`/`termination_event_id`.
