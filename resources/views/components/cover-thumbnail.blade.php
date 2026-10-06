@props([
    'href',
    'src' => null,
    'alt' => '',
    'icon' => null,
    // A short text over the icon: a book or chapter number, or a character's initials.
    'label' => null,
])

{{-- The name link next to it is the one that screen readers and the keyboard use. --}}
<a href="{{ $href }}" tabindex="-1" aria-hidden="true" {{ $attributes->class('block shrink-0') }}>
    @if ($src)
        <img src="{{ $src }}" alt="{{ $alt }}" class="h-10 w-10 rounded-sm object-cover border border-border">
    @else
        {{-- Same look as card-backdrop-icon. The muted colour token keeps the contrast low on every theme. --}}
        <div class="relative isolate flex h-10 w-10 items-center justify-center overflow-hidden rounded-sm border border-border bg-surface">
            @if ($icon)
                <x-dynamic-component :component="$icon" class="absolute top-1/2 left-1/2 -z-10 h-8 w-8 -translate-x-1/2 -translate-y-1/2 -rotate-[20deg] text-content-muted opacity-15" />
            @endif
            @if ($label !== null)
                {{-- The stroke has the box colour and paints under the fill, so the letters stand clear of the icon lines.
                     The fill mixes the icon colour, a little stronger than the icon. Real opacity would also fade the stroke. --}}
                <span class="text-sm font-semibold tabular-nums text-[color-mix(in_oklab,var(--color-content-muted)_30%,var(--color-surface))] [paint-order:stroke_fill] [-webkit-text-stroke:4px_var(--color-surface)]">{{ $label }}</span>
            @endif
        </div>
    @endif
</a>
