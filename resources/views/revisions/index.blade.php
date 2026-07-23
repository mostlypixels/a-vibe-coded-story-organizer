<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <x-heading level="2">
                {{ __('History') }} &mdash; {{ Illuminate\Support\Str::headline($entity) }} "{{ $entityName }}" &mdash; {{ Illuminate\Support\Str::headline($field) }}
            </x-heading>
            <a href="{{ $editUrl }}" class="text-sm text-gray-500 hover:text-gray-700">
                {{ __('Back to editing') }}
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">
        {{-- Field switcher (ui.md's "field switcher"): every other field this
             entity has registered, so moving between e.g. a Scene's description/
             notes/contents history never needs a trip back through the edit page. --}}
        @if ($fieldSwitcher->count() > 1)
            <div class="flex flex-wrap gap-2">
                @foreach ($fieldSwitcher as $switcherField)
                    <a
                        href="{{ $switcherField->url }}"
                        aria-current="{{ $switcherField->active ? 'page' : 'false' }}"
                        class="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium {{ $switcherField->active ? 'bg-navy-900 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50' }}"
                    >
                        {{ $switcherField->label }}
                    </a>
                @endforeach
            </div>
        @endif

        <div class="flex items-center justify-between gap-4">
            <form method="GET" class="flex items-center gap-2">
                <x-text-input
                    type="search"
                    name="label"
                    :value="$search"
                    class="text-sm"
                    :placeholder="__('Search labels…')"
                />
                <x-button variant="secondary" type="submit">{{ __('Search') }}</x-button>
                @if ($search !== '')
                    <a href="{{ route('revisions.index', ['entity' => $entity, 'id' => $id, 'field' => $field]) }}" class="text-sm text-gray-500 hover:text-gray-700">
                        {{ __('Clear') }}
                    </a>
                @endif
            </form>

            @if ($canCompareLatestTwo)
                <x-button variant="secondary" :href="$compareLatestTwoUrl">{{ __('Compare latest two') }}</x-button>
            @endif
        </div>

        <x-table>
            <x-slot:head>
                <x-table-heading>{{ __('Date') }}</x-table-heading>
                <x-table-heading>{{ __('Author') }}</x-table-heading>
                <x-table-heading>{{ __('Label') }}</x-table-heading>
                <x-table-heading>{{ __('Origin') }}</x-table-heading>
                <x-table-heading />
            </x-slot:head>

            @forelse ($rows as $row)
                <x-table-row :striped="$loop->even">
                    @if ($row->revision->origin === \App\Enums\RevisionOrigin::Baseline)
                        {{-- handoff.md §9.2: a baseline's `created_at` is borrowed from
                             the entity's own `updated_at` at seeding time, so it must
                             never be misread as a real edit row. --}}
                        <td colspan="4" class="px-4 py-3 text-sm text-gray-500 italic">
                            {{ __('Baseline — value before revision history') }}
                        </td>
                    @else
                        <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">
                            {{ $row->revision->created_at->format('d F Y H:i') }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            {{ $row->revision->user?->name ?? __('Unknown') }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            {{ $row->revision->label ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <x-revision-origin-badge :origin="$row->revision->origin" />
                        </td>
                    @endif
                    <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                        <div class="flex items-center justify-end gap-2">
                            @if ($row->isCurrent)
                                <x-badge variant="success">{{ __('Current') }}</x-badge>
                            @endif
                            @if ($row->compareWithPreviousUrl)
                                <a href="{{ $row->compareWithPreviousUrl }}" class="text-sm text-ocean-600 hover:text-ocean-800 hover:underline">
                                    {{ __('Compare with previous') }}
                                </a>
                            @endif
                            @unless ($row->isCurrent)
                                <x-revert-revision-button :revision="$row->revision" :base-hash="$baseHash" />
                            @endif
                        </div>
                    </td>
                </x-table-row>
            @empty
                <x-table-empty
                    :colspan="5"
                    :filtered="$search !== ''"
                    :items="__('revisions')"
                />
            @endforelse
        </x-table>
    </div>
</x-app-layout>
