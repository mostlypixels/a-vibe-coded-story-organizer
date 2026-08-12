@props(['slug', 'preset', 'checked' => false])

@php
    // Same shape as x-font-card: a real radio, visually hidden, so arrow-key
    // navigation is native and the card paints the selection with `peer-checked`.
    $id = 'theme-'.$slug;

    // Values are data from config, not classes. They reach an inline style only
    // after the same pattern ThemeStyleBlock uses accepts them.
    $token = fn (string $name) => preg_match(\App\Support\Oklch::CSS_VALUE_PATTERN, $preset->tokens[$name] ?? '')
        ? $preset->tokens[$name]
        : null;

    // The three hues that tell one preset from another at tile size. The other
    // tokens are foreground partners and status families, which barely move
    // between presets.
    $stripes = array_filter([
        $token('primary'),
        $token('accent'),
        $token('focus'),
    ]);

    // The name sits on its own plate, not on the stripes: no pair of theme
    // colours is guaranteed to be legible together. The plate ramps through the
    // three surface levels, raised to sunken, which reads as a crease across
    // the flag. All three carry `content` at the text floor, so the crease
    // cannot make the name unreadable.
    $plate = $token('surface') ?? $token('surface-raised');
    $content = $token('content');

    $levels = array_filter([$token('surface-raised'), $token('surface'), $token('surface-sunken')]);

    $plateBackground = count($levels) === 3
        ? vsprintf('linear-gradient(to bottom, %s 0%%, %s 50%%, %s 100%%)', array_values($levels))
        : null;
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

    {{-- Selection paints a flush ring, focus an offset outline: a theme may give
         `link` and `focus` the same colour, so the two states differ in shape. --}}
    <span
        class="block h-full rounded-md border border-border p-2
               peer-checked:border-link peer-checked:ring-1 peer-checked:ring-link
               peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2
               peer-focus-visible:outline-focus"
    >
        {{-- The tile wears the preset it selects: full-height stripes of its own
             colours, with the name on a plate over them. --}}
        <span class="relative flex h-full min-h-20 flex-col overflow-hidden rounded-sm border border-border">
            <span class="absolute inset-0 flex" aria-hidden="true">
                @foreach ($stripes as $stripe)
                    <span class="flex-1" style="background-color: {{ $stripe }};"></span>
                @endforeach
            </span>

            {{-- Fixed spacers, not `my-auto`: a name that wraps to a second line
                 grows the band and never squeezes the bars. --}}
            <span class="h-3 shrink-0"></span>

            <span class="relative flex flex-1 items-center justify-center px-2 py-3 text-center text-sm"
                @style([
                    'background-color: '.$plate => $plate !== null,
                    'background-image: '.$plateBackground => $plateBackground !== null,
                    'color: '.$content => $content !== null,
                ])
            >{{ __($preset->name) }}</span>

            <span class="h-3 shrink-0"></span>
        </span>
    </span>
</label>
