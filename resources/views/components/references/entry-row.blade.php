@props([
    'entry',
    'striped' => false,
])

<x-table-row :striped="$striped">
    <x-table-cell top>
        <a href="{{ route('codex.show', $entry) }}" class="text-link hover:text-link-hover">{{ $entry->name }}</a>
    </x-table-cell>
    <x-table-cell top muted>{{ $entry->type->label() }}</x-table-cell>
</x-table-row>
