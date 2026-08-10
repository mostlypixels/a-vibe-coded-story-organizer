# 03 — Duplicate dialog + scene UI

**Depends on:** 02.

## Scope

* `resources/views/components/duplicate-dialog.blade.php` — the shared naming modal
  (`name`, `action`, `suggestion`, `title` props). Task 05 reuses it unchanged.
* `x-edit-actions` gains an optional `duplicateModal` prop rendering the trigger button, and its
  saved-badge condition extends to `status === 'duplicated'` → "Duplicated."
* `scenes/index.blade.php`: an `x-icon-dialog-button icon="copy"` per row, with the dialogs in a
  second `@foreach` **after** `</x-table>`.
* `scenes/edit.blade.php`: the trigger via `duplicateModal`, the dialog placed beside the card.
* `SceneController::index` and `edit` pass the suggestion(s), built from one `pluck('name')` over
  `$project->sceneQuery()`.

Not in scope: the codex views (task 05).

## Key decisions

* Modal on the current page, not a dedicated confirmation route.
* Suggestions are computed once per page load, so two rows can propose the same name — harmless,
  since a collision is accepted and the page reloads after each duplicate.
* No suggestion logic in Blade: the controller passes an `id => suggestion` map.
* The trigger is icon-only, so `label` (`__('Duplicate')`) is required by `x-icon-button`; the
  dialog's input takes a real `<x-input-label>`, not a placeholder.
* `x-icon-dialog-button` already accepts `icon`/`variant`/`label` — do not add props to it.

## Consult

`expanded/ui.md` in full.

## Tests

Assert the scenes index and scene edit pages render the trigger and the prefilled suggestion.
`BladeComponentCompilationTest` covers the new component automatically. Add a JS test only if the
open/focus behaviour needs more than Alpine's existing modal wiring.
