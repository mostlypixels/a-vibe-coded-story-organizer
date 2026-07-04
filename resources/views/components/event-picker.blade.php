@props(['name', 'events', 'selected' => []])

@php
    // Searchable multi-select for events, now a thin wrapper over the generic x-chip-picker.
    // Chips carry event ids (no free text — events must already exist); the chosen ids submit
    // as hidden {{ $name }}[] inputs, exactly as the scene controller/validation expect.
    $options = collect($events)->map(fn ($event) => [
        'value' => (int) $event->id,
        'label' => $event->title,
        'sublabel' => $event->event_datetime->format('M j, Y'),
        'search' => strtolower($event->title.' '.$event->event_datetime->format('M j, Y').' '.$event->event_datetime->format('Y-m-d')),
    ])->values()->all();
@endphp

<x-chip-picker
    :name="$name"
    :options="$options"
    :selected="$selected"
    :allow-free-text="false"
    :placeholder="__('Search events by name or date…')"
/>
