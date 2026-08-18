@props([
    'name' => 'tags',
    'tags' => [],
    'selected' => [],
])

@php
    $options = collect($tags)->map(fn ($tag) => [
        'value' => $tag->name,
        'label' => $tag->name,
        'search' => strtolower($tag->name),
    ])->values()->all();
@endphp

<x-chip-picker
    :name="$name"
    :options="$options"
    :selected="$selected"
    :allow-free-text="true"
    :placeholder="__('Search or add a tag…')"
/>
