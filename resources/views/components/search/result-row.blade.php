@props([
    'row',
    'editRoute',
    'nameField' => 'name',
    'striped' => false,
])

<x-table-row :striped="$striped">
    <td class="px-4 py-3 align-top min-w-48">
        <a href="{{ route($editRoute, $row->entity) }}" class="font-medium text-link hover:text-link-hover hover:underline">
            {{ $row->entity->{$nameField} }}
        </a>
        @if ($row->book)
            <div class="text-xs text-content-muted">{{ $row->book->displayName() }}</div>
        @endif
    </td>
    <td class="px-4 py-3 align-top text-sm text-content-muted whitespace-nowrap">
        {{ $row->matchedFields() }}
    </td>
    <td class="px-4 py-3 align-top text-sm text-content-muted w-full">
        {!! $row->snippet !!}
    </td>
    <td class="px-4 py-3 align-top text-right text-sm whitespace-nowrap">
        <x-icon-view-link :href="route($editRoute, $row->entity)" />
    </td>
</x-table-row>
