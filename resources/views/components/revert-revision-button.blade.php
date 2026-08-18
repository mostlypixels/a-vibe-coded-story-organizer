@props(['revision', 'baseHash'])

<div>
    <x-button
        type="button"
        variant="secondary"
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'revert-revision-{{ $revision->id }}')"
    >{{ __('Revert to this') }}</x-button>

    <x-dialog name="revert-revision-{{ $revision->id }}" :title="__('Revert to this revision?')">
        <p class="text-sm text-content-muted">
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
