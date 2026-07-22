@props(['revision', 'baseHash'])

{{--
    Revert-with-confirm, behind the existing x-dialog confirm component
    (expanded/ui.md "Revert", handoff.md §5.2/§9.3) — same UX pattern as any
    other destructive-feeling action in this app, even though revert itself
    is non-destructive server-side (it only ever adds a new revision).

    `baseHash` is the hash of the field's *current* stored value, computed by
    RevisionController::index()/compare() at page-render time — the same
    base-hash conflict check FieldAutosaveController's autosave PATCH uses, so
    reverting against a field someone else already changed since the page
    loaded 409s instead of silently overwriting their work.

    Reused on both the history row (resources/views/revisions/index.blade.php)
    and the compare view (resources/views/revisions/compare.blade.php) — one
    dialog name per revision id, since a page can render several of these
    buttons at once.
--}}
<div>
    <x-button
        type="button"
        variant="secondary"
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'revert-revision-{{ $revision->id }}')"
    >{{ __('Revert to this') }}</x-button>

    <x-dialog name="revert-revision-{{ $revision->id }}" :title="__('Revert to this revision?')">
        <p class="text-sm text-gray-600">
            {{ __('This will make it the new current value and add a new entry to the history — nothing already in the history is removed or changed.') }}
        </p>

        <x-slot name="footer">
            <x-button variant="secondary" type="button" x-on:click="$dispatch('close')">
                {{ __('Cancel') }}
            </x-button>

            <form method="POST" action="{{ route('revisions.revert', $revision) }}">
                @csrf
                <input type="hidden" name="base_hash" value="{{ $baseHash }}">
                <x-button variant="danger" type="submit">{{ __('Revert') }}</x-button>
            </form>
        </x-slot>
    </x-dialog>
</div>
