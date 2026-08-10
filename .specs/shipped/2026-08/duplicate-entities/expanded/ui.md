# Duplicate entities — UI

## New component

`resources/views/components/duplicate-dialog.blade.php`

```
@props(['name', 'action', 'suggestion', 'title'])
```

An `<x-dialog>` wrapping a POST form with one labelled `<x-text-input name="name">` prefilled with
`$suggestion`, plus Cancel / Duplicate buttons in the `footer` slot. Modelled on
`x-delete-with-move-dialog`: a native prompt cannot be styled or validated, and this is the same
"one consequential action needs one field first" shape.

Focus and select the input when the dialog opens, so the writer can type over the suggestion
immediately.

## Index rows

Add to each row's action group, before the edit link:

```blade
<x-icon-dialog-button icon="copy" variant="outline-solid" :modal="'duplicate-scene-'.$scene->id" :label="__('Duplicate')" />
```

`x-icon-dialog-button` already takes `icon` / `variant` / `label`; it needs no change. The dialogs
go **after** `</x-table>` in a second `@foreach` — a modal `<div>` is not valid inside a `<tbody>`
(see the delete dialogs in `acts/index.blade.php`).

Views touched: `scenes/index`, `codex/index`.

Each `index` action passes a `$duplicateNames` map (`id => suggestion`) built from one
`pluck('name')` over the type's candidate set — no query per row, no logic in Blade.

> [!WARNING]
> The suggestions are computed against one snapshot of the name list, so every row on a page
> showing "Arrival" and "Arrival (2)" proposes "Arrival (3)" for both. Harmless: a collision is
> accepted anyway, and the page reloads after each duplicate.

## Edit pages

`resources/views/components/edit-actions.blade.php` gains one optional prop:

```
'duplicateModal' => null,
```

When set, it renders a `secondary` button with `icon="copy"` under Save / Save and stay / History
that dispatches `open-modal` with that name. The `<x-duplicate-dialog>` itself is placed by the
edit view beside the card, the way `chapters/edit` places its delete-with-move dialog.

Views touched: `scenes/edit`, `codex/partials/fields`.

## Feedback

The copy's edit page opens with `session('status') === 'duplicated'`. `x-edit-actions` currently
renders the "Saved." badge only for `status === 'saved'`; extend that condition to show
"Duplicated." for the new value rather than adding a second alert block.

## Accessibility

* The trigger is icon-only — `label` is required by `x-icon-button` and lands in both `title` and
  `aria-label`. Use `__('Duplicate')`.
* The dialog's input needs a real `<x-input-label>` ("Name"), not a placeholder.
* `x-modal` already traps focus (`focusable`) and closes on Escape.
