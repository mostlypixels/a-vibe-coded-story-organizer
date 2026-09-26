@props([
    'name',
    'title' => null,
    'maxWidth' => '2xl',
    // An untitled caller passes the id of its own heading.
    'labelledby' => null,
])

@php
    $labelledby = $title ? $name.'-title' : $labelledby;
@endphp

<x-modal :name="$name" :max-width="$maxWidth" :labelledby="$labelledby" focusable>
    @if ($title)
        <div class="flex items-center justify-between border-b border-border px-6 py-4">
            <x-heading level="3" :id="$labelledby">{{ $title }}</x-heading>
            <x-icon-close-button x-on:click="$dispatch('close')" />
        </div>
    @endif

    <div class="px-6 py-4">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="flex justify-end gap-2 border-t border-border bg-surface-sunken px-6 py-4">
            {{ $footer }}
        </div>
    @endisset
</x-modal>
