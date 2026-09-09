@props([
    'entries',
    'seeAllRoute' => null,
    'seeAllCount' => null,
])

<x-table>
    <x-slot:head>
        <x-table-heading>{{ __('Entry') }}</x-table-heading>
        <x-table-heading>{{ __('Type') }}</x-table-heading>
    </x-slot:head>

    @foreach ($entries as $entry)
        <x-references.entry-row :entry="$entry" :striped="$loop->even" />
    @endforeach

    @if ($seeAllRoute)
        <x-slot:foot>
            <tr>
                <x-table-cell colspan="2" align="right" sm>
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
