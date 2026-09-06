# 02 — Plotline read page

## Scope

- `show` on the `projects.plotlines` resource (`routes/web.php:129`) → `plotlines.show`.
- `PlotlineController::show()`: authorize `view` on `$plotline->project`, load `events`
  ordered by `event_datetime`.
- `resources/views/plotlines/show.blade.php`: name, colour dot, main badge, description,
  and a table of its events.
- `resources/views/plotlines/index.blade.php`: name link (line 25) → `plotlines.show`;
  add `x-icon-view-link` before the edit icon (line 38).

## Depends on

Nothing.

## Key decisions

- **Events only.** A plotline has no scenes relation and gains none.
- Header actions: edit, history (`revisions.index` with slug `plotline`), delete. The main
  plotline offers no delete — mirror the `@unless ($plotline->is_main)` guard on the index.

## Consult

`expanded/architecture.md` → Routes, Controllers; `expanded/ui.md` → Page shape, Index rows.

## Tests

In `tests/Feature/PlotlineTest.php`:

- Renders name, description, and its events in date order.
- Omits the events card when the plotline has none.
- No form, input, or autosave field (copy `CodexEntryTest.php:646`).
- Non-owner gets 403.
- Index links the name to `show` and keeps the edit icon.
- The main plotline shows its badge and no delete control.
