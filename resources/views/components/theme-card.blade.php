@props(['slug', 'preset', 'checked' => false])

@php
    $id = 'theme-'.$slug;

    $token = fn (string $name) => preg_match(\App\Support\Oklch::CSS_VALUE_PATTERN, $preset->tokens[$name] ?? '')
        ? $preset->tokens[$name]
        : null;

    $stripes = array_filter([
        $token('primary'),
        $token('accent'),
        $token('focus'),
    ]);

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

    <span
        class="block h-full rounded-md border border-border p-2
               peer-checked:border-link peer-checked:ring-1 peer-checked:ring-link
               peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2
               peer-focus-visible:outline-focus"
    >
        <span class="relative flex h-full min-h-20 flex-col overflow-hidden rounded-sm border border-border">
            <span class="absolute inset-0 flex" aria-hidden="true">
                @foreach ($stripes as $stripe)
                    <span class="flex-1" style="background-color: {{ $stripe }};"></span>
                @endforeach
            </span>

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
