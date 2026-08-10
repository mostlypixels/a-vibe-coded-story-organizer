# 05 — Codex entry UI

**Depends on:** 03 (the component), 04 (the route).

## Scope

* `codex/index.blade.php`: a duplicate trigger per row, dialogs after `</x-table>`.
* `codex/partials/fields.blade.php`: the `duplicateModal` trigger on the existing `x-edit-actions`,
  with the dialog placed beside the card.
* `CodexEntryController::index` and `edit` pass the suggestion(s), built from one `pluck('name')`
  over `$project->codexEntries()->where('type', …)`.

Not in scope: any change to `x-duplicate-dialog` or `x-edit-actions` — task 03 finalised both. If
this task finds it needs to change either, that is a signal task 03's props were wrong; fix them
there and note it in `resolution-log.md`.

## Key decisions

* The candidate set is scoped to the entry's own `CodexEntryType` — a Character and a Location may
  both be "Luna" without colliding.
* Dialog labels use the type label (`__('Duplicate :label', ['label' => $type->label()])`), the way
  the delete button already does.

## Consult

`expanded/ui.md`; `expanded/data-model.md` → *Name suggestion* for the candidate set.

## Tests

Assert the codex index and edit pages render the trigger and the prefilled suggestion, and that
the suggestion ignores same-named entries of a **different** type.
