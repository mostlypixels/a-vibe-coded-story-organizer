@props(['slug', 'preset', 'checked' => false])

@php
    $swatch = $preset->swatch();
@endphp

<x-tooltip :text="__($preset->name)" position="bottom">
    <label class="cursor-pointer">
        <input
            type="radio"
            name="theme_slug"
            value="{{ $slug }}"
            @checked($checked)
            {{ $attributes->merge(['class' => 'peer sr-only']) }}
        >

        <span
            class="relative flex h-8 w-8 overflow-hidden rounded-md border border-border
                   peer-checked:ring-2 peer-checked:ring-link
                   peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2
                   peer-focus-visible:outline-focus"
            @style([
                'background-color: '.$swatch['plate'] => $swatch['plate'] !== null,
                'background-image: '.$swatch['plateBackground'] => $swatch['plateBackground'] !== null,
            ])
        >
            <span class="absolute inset-x-0 bottom-0 flex h-2" aria-hidden="true">
                @foreach ($swatch['stripes'] as $stripe)
                    <span class="flex-1" style="background-color: {{ $stripe }};"></span>
                @endforeach
            </span>
        </span>

        <span class="sr-only">{{ __($preset->name) }}</span>
    </label>
</x-tooltip>
