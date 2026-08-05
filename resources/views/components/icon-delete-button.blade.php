@props(['action', 'confirm', 'label' => null])

{{--
    Delete as a real DELETE form, gated by a native confirm(). For a delete that
    needs the richer "move or delete" dialog instead, see <x-icon-dialog-button>.

    `label` overrides the default "Delete" — e.g. the scene share panel's revoke
    button is the same delete-confirm shape under a different verb.

    The form is `flex` so its one child (the button) stretches to the form's
    full height when a flex-row ancestor stretches the form itself — e.g. the
    revoke button matching the taller "Regenerate" button beside it. A no-op
    everywhere else: with no such ancestor, the form's height is just the
    button's natural height either way.
--}}
<form class="flex" method="POST" action="{{ $action }}" onsubmit="return confirm('{{ $confirm }}')">
    @csrf
    @method('DELETE')
    <x-icon-button type="submit" icon="trash" variant="danger" :label="$label ?? __('Delete')" {{ $attributes }} />
</form>
