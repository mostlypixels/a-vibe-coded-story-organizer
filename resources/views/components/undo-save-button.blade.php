@props(['point', 'baseHashes'])

{{--
    "Undo this save" — puts every field one save point touched back to the value
    it held before that save (expanded/ui.md "History page" → Actions).

    Behind the same x-dialog confirm as x-revert-revision-button, and worded to
    say exactly what will happen: an undo is *additive* server-side (it writes a
    new save point) but it does change the writer's live text, which is worth
    one deliberate click.

    `baseHashes` carries a hash of the **current stored value of every
    registered field**, not only the fields this save touched. Two reasons: the
    history page can be filtered to one field (`?field=`), which narrows
    $point->entries but must never narrow what an undo restores; and the server
    resolves the group's real field list from the database anyway, so sending
    the full set is what guarantees it finds a hash for each one. Anything the
    undo does not need is ignored.
--}}
<div>
    <x-button
        type="button"
        variant="secondary"
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'undo-save-{{ $point->saveId }}')"
    >{{ __('Undo this save') }}</x-button>

    <x-dialog name="undo-save-{{ $point->saveId }}" :title="__('Undo this save?')">
        <p class="text-sm text-gray-600">
            {{ __('Every field it changed goes back to its previous value. Nothing is deleted — the undo is recorded as a new save.') }}
        </p>

        <x-slot name="footer">
            <x-button variant="secondary" type="button" x-on:click="$dispatch('close')">
                {{ __('Cancel') }}
            </x-button>

            <form method="POST" action="{{ route('revisions.saves.revert', $point->saveId) }}">
                @csrf
                @foreach ($baseHashes as $field => $hash)
                    <input type="hidden" name="base_hashes[{{ $field }}]" value="{{ $hash }}">
                @endforeach
                <x-button variant="danger" type="submit">{{ __('Undo this save') }}</x-button>
            </form>
        </x-slot>
    </x-dialog>
</div>
