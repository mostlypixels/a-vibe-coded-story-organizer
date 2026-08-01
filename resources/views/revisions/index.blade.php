@php
    use Illuminate\Support\Str;
@endphp

<x-revisions-layout :project="$project" :entity="$entity" :id="$id" :field="$field">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <x-heading level="2">
                {{ __('History') }} &mdash; {{ Str::headline($entity) }} "{{ $entityName }}"
            </x-heading>
            <a href="{{ $editUrl }}" class="text-sm text-gray-500 hover:text-gray-700">
                {{ __('Back to editing') }}
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">
        {{-- Every filter is a GET parameter, so the page stays bookmarkable and
             the Back button means what it looks like it means. A native <select>
             is right here: a handful of options, no search, and it is
             keyboard-operable and screen-reader-announced for free. --}}
        <form method="GET" class="bg-white shadow-xs rounded-lg px-6 py-4 flex flex-wrap items-end gap-4">
            @if ($fieldOptions !== [])
                <div>
                    <x-input-label for="field-filter" :value="__('Field')" />
                    <x-select id="field-filter" name="field" class="mt-1 block text-sm">
                        <option value="">{{ __('All fields') }}</option>
                        @foreach ($fieldOptions as $value => $headline)
                            <option value="{{ $value }}" @selected($field === $value)>{{ $headline }}</option>
                        @endforeach
                    </x-select>
                </div>
            @endif

            <div>
                <x-input-label for="label-search" :value="__('Label')" />
                <x-text-input
                    id="label-search"
                    type="search"
                    name="label"
                    :value="$label"
                    class="mt-1 text-sm"
                    :placeholder="__('Search labels…')"
                />
            </div>

            <label for="manual-only" class="flex items-center gap-2 pb-2">
                <input
                    id="manual-only"
                    type="checkbox"
                    name="manual"
                    value="1"
                    @checked($manualOnly)
                    class="rounded-sm border-gray-300 text-ocean-600 focus:ring-ocean-500"
                >
                <span class="text-sm text-gray-700">{{ __('Manual saves only') }}</span>
            </label>

            <div class="flex items-center gap-3 pb-1">
                <x-button variant="secondary" type="submit">{{ __('Filter') }}</x-button>

                @if ($field !== null || $label !== '' || $manualOnly)
                    <a href="{{ route('revisions.index', ['entity' => $entity, 'id' => $id]) }}" class="text-sm text-gray-500 hover:text-gray-700">
                        {{ __('Clear') }}
                    </a>
                @endif
            </div>
        </form>

        @if ($savePoints->isEmpty())
            <div class="bg-white shadow-xs rounded-lg px-6 py-10 text-center text-gray-500">
                @if ($field !== null || $label !== '' || $manualOnly)
                    <p class="font-medium text-gray-600">{{ __('No saves match these filters.') }}</p>
                    <p class="mt-1 text-sm">{{ __('Try clearing them to see the whole history.') }}</p>
                @else
                    <p class="font-medium text-gray-600">{{ __('No history yet.') }}</p>
                    <p class="mt-1 text-sm">{{ __('Editing this and saving will start it.') }}</p>
                @endif
            </div>
        @else
            {{-- A <ul>, not x-table: a save point is two-level (the save, then
                 the fields it touched) and a table row cannot hold that without
                 lying about its structure to a screen reader. --}}
            <ul class="space-y-4">
                @foreach ($savePoints as $point)
                    <li>
                        @include('revisions.partials.save-point', [
                            'point' => $point,
                            'entity' => $entity,
                            'id' => $id,
                            'field' => $field,
                            'baseHashes' => $baseHashes,
                        ])
                    </li>
                @endforeach
            </ul>

            {{ $savePoints->links() }}
        @endif
    </div>
</x-revisions-layout>
