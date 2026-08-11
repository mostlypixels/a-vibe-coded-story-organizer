@props(['name', 'slug', 'family', 'checked' => false])

@php
    // The radio stays a real radio, only visually hidden: arrow-key navigation
    // between the cards, and the live preview's change event, both come from
    // native behaviour. The card paints the selection with `peer-checked`.
    $id = str_replace('_', '-', $name).'-'.$slug;
@endphp

<label for="{{ $id }}" class="cursor-pointer">
    <input
        type="radio"
        id="{{ $id }}"
        name="{{ $name }}"
        value="{{ $slug }}"
        class="peer sr-only"
        @checked($checked)
    >

    {{-- Selection paints a flush ring, focus an offset outline. A theme may
         give `link` and `focus` the same colour (low-glare-dark does), so the
         two states must differ in shape, not only in colour. --}}
    <span
        class="block h-full rounded-md border border-border p-2
               peer-checked:border-link peer-checked:ring-1 peer-checked:ring-link
               peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2
               peer-focus-visible:outline-focus"
    >
        <span class="relative flex h-full min-h-16 items-center justify-center rounded-sm bg-surface-sunken px-2 py-3">
            {{-- The name renders in its own family, from authored config (never
                 input), so each card previews the face it selects. --}}
            <span class="text-center text-base text-content" style="font-family: {{ $family['stack'] }};">
                {{ $family['name'] }}
            </span>

            @if ($family['accessible'])
                {{-- An icon alone is never a label: the note lands in both the
                     hover title and an sr-only span. --}}
                <span class="absolute top-1 right-1 inline-flex text-content-muted" title="{{ $family['note'] }}">
                    <x-tabler-eye class="h-4 w-4 shrink-0" aria-hidden="true" />
                </span>
                <span class="sr-only">{{ $family['note'] }}</span>
            @endif
        </span>
    </span>
</label>
