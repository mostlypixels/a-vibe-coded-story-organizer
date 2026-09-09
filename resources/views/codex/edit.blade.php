<x-app-layout>
    <x-page-heading>
        {{ __('Edit :label', ['label' => $type->label()]) }} &mdash; {{ $entry->name }}
    </x-page-heading>

    <div class="space-y-10">
        <form id="codex-entry-edit-form" method="POST" action="{{ route('codex.update', $entry) }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            @include('codex.partials.fields')
        </form>

        <form
            id="codex-entry-delete-form"
            method="POST"
            action="{{ route('codex.destroy', $entry) }}"
            onsubmit="return confirm('{{ __('Are you sure you want to delete this entry?') }}')"
        >
            @csrf
            @method('DELETE')
        </form>

        <x-duplicate-dialog
            name="duplicate-codex-entry-{{ $entry->id }}"
            :action="route('codex.duplicate', $entry)"
            :title="__('Duplicate :label', ['label' => $type->label()])"
            :suggestion="$duplicateSuggestion"
        />

        @include('codex.partials.attribute-timeline')

        <x-card :title="__('Referenced in scenes')">
            @if ($referencingScenes->isEmpty())
                <p class="text-sm text-content-muted">{{ __('No scenes reference this entry yet.') }}</p>
            @else
                <x-references.scene-table
                    :scenes="$referencingScenes->take(config('search.cap'))"
                    :show-book="$showBook"
                    :see-all-route="$referencingScenes->count() > config('search.cap') ? route('codex.scenes.index', $entry) : null"
                    :see-all-count="$referencingScenes->count()"
                />
            @endif
        </x-card>
    </div>
</x-app-layout>
