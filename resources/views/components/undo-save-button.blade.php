@props(['point', 'baseHashes'])

<div>
    <x-button
        type="button"
        variant="secondary"
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'undo-save-{{ $point->saveId }}')"
    >{{ __('Undo this save') }}</x-button>

    <x-dialog name="undo-save-{{ $point->saveId }}" :title="__('Undo this save?')">
        <p class="text-sm text-content-muted">
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
