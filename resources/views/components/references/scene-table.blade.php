@props([
    'scenes',
    'showBook' => false,
    'seeAllRoute' => null,
    'seeAllCount' => null,
])

<x-table>
    <x-slot:head>
        <x-table-heading>{{ __('Scene') }}</x-table-heading>
        <x-table-heading>{{ __('Chapter') }}</x-table-heading>
        <x-table-heading>{{ __('Act') }}</x-table-heading>
        @if ($showBook)
            <x-table-heading>{{ __('Book') }}</x-table-heading>
        @endif
        <x-table-heading>{{ __('Event') }}</x-table-heading>
    </x-slot:head>

    @foreach ($scenes as $scene)
        <x-references.scene-row :scene="$scene" :show-book="$showBook" :striped="$loop->even" />
    @endforeach

    @if ($seeAllRoute)
        <x-slot:foot>
            <tr>
                <x-table-cell :colspan="$showBook ? 5 : 4" align="right" sm>
                    <a
                        href="{{ $seeAllRoute }}"
                        class="text-link underline hover:text-link-hover"
                    >
                        {{ __('See all :count results', ['count' => $seeAllCount]) }}
                    </a>
                </x-table-cell>
            </tr>
        </x-slot:foot>
    @endif
</x-table>
