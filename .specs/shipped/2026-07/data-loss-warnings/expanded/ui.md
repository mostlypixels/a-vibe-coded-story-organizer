---
title: Data Loss Warnings — UI
---

# UI

## Navigation-guard dialog

Reuse the existing `<x-dialog>` (`resources/views/components/dialog.blade.php`, built
on `<x-modal>`) rather than a bespoke modal — it already gives header/body/footer,
focus trapping, Esc-to-close, and backdrop-click-to-close for free, and is the same
component the app's other confirmations are trending toward.

Mount **once**, in `resources/views/layouts/app.blade.php`, next to the existing
`<x-autosave-status-badge />` (`:38`) — both are page-global, both read
`Alpine.store('autosave')`, both belong beside each other rather than duplicated per
view:

```blade
<x-dialog name="unsaved-changes-guard" :title="__('Unsaved changes')" x-data="navigationGuard()">
    {{ __('You have unsaved changes. If you leave now, they may be lost.') }}
    <x-slot name="footer">
        <x-button variant="secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-button>
        <x-button variant="danger" x-on:click="confirmLeave()">{{ __('Leave anyway') }}</x-button>
    </x-slot>
</x-dialog>
```

`navigationGuard()` (from `resources/js/navigation-guard.js`, registered via
`Alpine.data('navigationGuard', ...)` the same way `Alpine.data('autosaveField', ...)`
is registered today) owns `pendingHref`, the document-level click listener, and
`confirmLeave()` — which dispatches `autosave:explicit-leave`, sets
`window.location.href = this.pendingHref`, and lets `$dispatch('close')` (already wired
by `<x-modal>`'s own `x-on:close.stop`) close the dialog on the way out.

No new visual design needed — this is exactly the shape `x-dialog`'s own docblock
example (`dialog.blade.php:10-18`) shows for a delete confirmation, just swapped to
Leave/Cancel wording and a global trigger instead of a per-button one.

## Project delete confirmation — unchanged mechanism, richer string

Still the existing native `confirm()` popup (`onsubmit="return confirm('{{ $confirm
}}')"` in `delete-button.blade.php`/`icon-delete-button.blade.php`) — only the string
`projects/edit.blade.php`'s `:delete-confirm` passes changes. `trans_choice()` per
category, joined, e.g.:

```php
// ProjectController::edit() builds the sentence from only non-zero counts:
// "This project has 2 acts and 5 codex entries, which will also be deleted."
```

If every count is zero (a brand-new project, only its un-deletable main plotline),
falls back to the original unqualified "delete this project?" text.

> [!NOTE]
> `confirm()` is a blocking synchronous browser dialog and cannot embed markup or a
> `<select>` — plain text, OK/Cancel only. That's exactly why Act/Chapter (below) move
> to a custom dialog instead of reusing this component.

## Act / Chapter delete — "move or delete" dialog

A new dialog, not the native `confirm()` — needs a destination picker, which
`confirm()` cannot render. Built on the same `<x-dialog>` component as the navigation
guard (`dialog.blade.php`), replacing `<x-delete-button>`'s plain form-with-`onsubmit`
for these two entities only (Plotline/Event/Scene/Project are unaffected).

```blade
{{-- resources/views/components/delete-with-move-dialog.blade.php (new) --}}
@props(['name', 'action', 'childLabel', 'childCount', 'destinations', 'destinationField'])

<x-dialog :name="$name" :title="__('Delete :label?', ['label' => $slot])">
    <form method="POST" action="{{ $action }}" x-data="{ mode: 'move' }">
        @csrf @method('DELETE')

        @if ($destinations->isNotEmpty())
            <label class="flex items-center gap-2">
                <input type="radio" x-model="mode" value="move">
                {{ __('Move :count to another :label, then delete', ['count' => $childCount, 'label' => $childLabel]) }}
            </label>
            <select name="move_children_to" x-show="mode === 'move'" required>
                @foreach ($destinations as $destination)
                    <option value="{{ $destination->id }}">{{ $destination->name }}</option>
                @endforeach
            </select>

            <label class="flex items-center gap-2">
                <input type="radio" x-model="mode" value="delete">
                {{ __('Delete everything (:count :label)', ['count' => $childCount, 'label' => $childLabel]) }}
            </label>
        @else
            <p>{{ __('This will also delete :count :label.', ['count' => $childCount, 'label' => $childLabel]) }}</p>
        @endif

        <x-slot name="footer">
            <x-button variant="secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-button>
            <x-button variant="danger" type="submit">{{ __('Confirm') }}</x-button>
        </x-slot>
    </form>
</x-dialog>
```

When `$destinations` is empty (no other act/chapter in the project), the radio choice
collapses to a single informational line + Confirm — same end state as today's plain
`confirm()`, just inside the dialog shell instead of browser chrome, so the component
doesn't need two different code paths for "has destinations" vs. not beyond the
`@if`/`@else` above. The `mode === 'delete'` branch submits with no `move_children_to`
field, which the Form Request's `nullable` rule (`architecture.md` §4b) already treats
as "cascade, don't reassign."

Trigger: replace `<x-delete-button>`/`<x-icon-delete-button>` on `acts/edit.blade.php`,
`acts/index.blade.php`, `chapters/edit.blade.php`, `chapters/index.blade.php` with a
button that dispatches `open-modal` for this dialog instead of relying on `onsubmit`.
`plotlines/*`, `events/*`, `scenes/*`, and `projects/edit.blade.php` are untouched.

Zero-children case (an act with no chapters yet) skips the dialog entirely — the
existing plain `confirm()` with the original unqualified text stays exactly as-is, no
reason to show a dialog with nothing to move or count.

## Accessibility

* `<x-dialog>` already handles focus trapping and Esc-to-close (`modal.blade.php`'s
  `focusables()`/keydown handlers) — the navigation-guard dialog inherits this for
  free, consistent with `CLAUDE.md`'s "ensure keyboard accessibility."
* The native `confirm()` popup used for cascade deletes is browser-chrome, already
  keyboard-accessible by definition (Enter/Esc), unchanged from today.
* The native `beforeunload` prompt is likewise browser chrome, out of this app's
  styling/accessibility control entirely.
