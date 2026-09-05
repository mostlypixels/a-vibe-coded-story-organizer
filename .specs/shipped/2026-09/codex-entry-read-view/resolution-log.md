# Codex entry read view — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

> [!IMPORTANT]
> An **exception log, not a work journal**. A task that went to plan gets no entry — the
> diff and the task file already record what was built. Bullets under the headings below,
> root cause first, no per-task sections.

## Feedback & decisions

- The codex has no "now". The expanded spec first recommended showing a "current value" per
  attribute; that concept does not exist here. An attribute is a sequence of values along the
  timeline, and only a scene gives one of them primacy — which the as-of panel already does.
  The read page shows every period. No viewpoint picker.
- Referencing scenes are capped at 20 with a client-side "show all", not linked to a filtered
  scene list: `SceneController::index` is book-scoped, has no codex filter and does not
  paginate, so no such page exists.
- Plain "Save" on a codex entry redirects to `codex.show`. No other entity changes.
- `show` is gated on `view`. `ProjectPolicy::view` and `::update` are identical today, so the
  choice is free and correct if they ever diverge.

## Deviations from the spec/plan

_None yet._

## Issues → resolutions

- The "no inputs on the read page" invariant is not literally true, and cannot be. The
  Duplicate action reuses `x-icon-dialog-button` from the codex index, whose modal carries a
  name field, and the Delete and logout forms carry CSRF tokens. Read the invariant as: no
  field that edits **this entry in place**. The page has no autosave field, which is the part
  that made the edit form unsafe to read from.

- Aliases and tags rendered as one unbroken row of badges under the entry name, separated only
  by colour. On Mélusine that is four aliases followed by three tags, with nothing saying where
  one kind ends. Fixed on the read page: two labelled rows, "Also known as" and "Tags". The
  codex index does not share the problem — it gives each its own column.
