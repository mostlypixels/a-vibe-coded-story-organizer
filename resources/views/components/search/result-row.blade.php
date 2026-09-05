@props([
    'row',
    'viewRoute',
    'nameField' => 'name',
    'striped' => false,
])

<x-table-row :striped="$striped">
    <x-table-cell top class="min-w-48">
        <a href="{{ route($viewRoute, $row->entity) }}" class="font-medium text-link hover:text-link-hover hover:underline">
            {{ $row->entity->{$nameField} }}
        </a>
        @if ($row->book)
            <div class="text-xs text-content-muted">{{ $row->book->displayName() }}</div>
        @endif
    </x-table-cell>
    <x-table-cell top muted nowrap>
        {{ $row->matchedFields() }}
    </x-table-cell>
    <x-table-cell top muted class="w-full">
        {!! $row->snippet !!}
    </x-table-cell>
    <x-table-cell top align="right" nowrap sm>
        <x-icon-view-link :href="route($viewRoute, $row->entity)" />
    </x-table-cell>
</x-table-row>
