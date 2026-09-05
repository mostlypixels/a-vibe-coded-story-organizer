@props(['name', 'events', 'selected' => []])

@php
    $options = collect($events)->map(fn ($event) => [
        'value' => (int) $event->id,
        'label' => $event->title,
        'sublabel' => \App\Support\DateFormat::date($event->event_datetime, $locale),
        'search' => strtolower($event->title.' '.\App\Support\DateFormat::date($event->event_datetime, $locale).' '.$event->event_datetime->format('Y-m-d')),
    ])->values()->all();
@endphp

<x-chip-picker
    :name="$name"
    :options="$options"
    :selected="$selected"
    :allow-free-text="false"
    :placeholder="__('Search events by name or date…')"
/>
