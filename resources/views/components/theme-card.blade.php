@props(['slug', 'preset', 'checked' => false])

@php
    $id = 'theme-'.$slug;
    $swatch = $preset->swatch();
@endphp

<label for="{{ $id }}" class="cursor-pointer">
    <input
        type="radio"
        id="{{ $id }}"
        name="theme_slug"
        value="{{ $slug }}"
        class="peer sr-only"
        @checked($checked)
    >

    <span
        class="block h-full rounded-md border border-border p-2
               peer-checked:border-link peer-checked:ring-1 peer-checked:ring-link
               peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2
               peer-focus-visible:outline-focus"
    >
        <span class="relative flex h-full min-h-20 flex-col overflow-hidden rounded-sm border border-border">
            <span class="absolute inset-0 flex" aria-hidden="true">
                @foreach ($swatch['stripes'] as $stripe)
                    <span class="flex-1" style="background-color: {{ $stripe }};"></span>
                @endforeach
            </span>

            <span class="h-3 shrink-0"></span>

            <span class="relative flex flex-1 items-center justify-center px-2 py-3 text-center text-sm"
                @style([
                    'background-color: '.$swatch['plate'] => $swatch['plate'] !== null,
                    'background-image: '.$swatch['plateBackground'] => $swatch['plateBackground'] !== null,
                    'color: '.$swatch['content'] => $swatch['content'] !== null,
                ])
            >{{ __($preset->name) }}</span>

            <span class="h-3 shrink-0"></span>
        </span>
    </span>
</label>
