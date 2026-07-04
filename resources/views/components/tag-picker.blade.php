@props([
    'name' => 'tags',
    'tags' => [],
    'selected' => [],
])

@php
    // Thin wrapper over x-chip-picker for the project's tags. Chips carry tag *names*
    // (not ids): the controller's resolveTags() firstOrCreate's each name within the
    // project, so free text is allowed and new names create fresh project-scoped tags.
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
