@props([
    'scene',
    'showBook' => false,
    'striped' => false,
])

<x-table-row :striped="$striped">
    <x-table-cell top>
        <a href="{{ route('scenes.show', $scene) }}" class="text-link hover:text-link-hover">{{ $scene->name }}</a>
    </x-table-cell>
    <x-table-cell top muted>{{ $scene->chapter->name }}</x-table-cell>
    <x-table-cell top muted>{{ $scene->chapter->act->name }}</x-table-cell>
    @if ($showBook)
        <x-table-cell top muted>{{ $scene->chapter->act->book->displayName() }}</x-table-cell>
    @endif
    <x-table-cell top muted>
        @if ($scene->event)
            {{ $scene->event->title }} &mdash; <x-date :value="$scene->event->event_datetime" />
        @else
            &mdash;
        @endif
    </x-table-cell>
</x-table-row>
