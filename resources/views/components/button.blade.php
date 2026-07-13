@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'submit',
    'icon' => false,
])

@php
    // Single flexible button. Renders an <a> when `href` is given (so links and
    // buttons share one look), otherwise a <button>. Colours are written out in
    // full so Tailwind's purge step keeps them — do not build class names by
    // interpolating the variant.
    $base = 'inline-flex items-center justify-center gap-2 border rounded-md font-semibold uppercase tracking-widest transition ease-in-out duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-25 disabled:cursor-not-allowed';

    $variants = [
        'primary'   => 'bg-navy-900 border-transparent text-white hover:bg-navy-800 active:bg-navy-950 focus:ring-ocean-500',
        'secondary' => 'bg-white border-gray-300 text-gray-700 shadow-sm hover:bg-gray-50 focus:ring-ocean-500',
        'danger'    => 'bg-red-600 border-transparent text-white hover:bg-red-500 active:bg-red-700 focus:ring-red-500',
        'success'   => 'bg-green-600 border-transparent text-white hover:bg-green-500 active:bg-green-700 focus:ring-green-500',
        'warning'   => 'bg-yellow-500 border-transparent text-white hover:bg-yellow-400 active:bg-yellow-600 focus:ring-yellow-500',
        'ghost'     => 'bg-transparent border-transparent text-gray-600 hover:bg-gray-100 focus:ring-ocean-500',
        'link'      => 'bg-transparent border-transparent normal-case tracking-normal text-ocean-600 hover:text-ocean-800 hover:underline focus:ring-ocean-500',
    ][$variant];

    $sizes = [
        'sm' => 'px-3 py-1.5 text-xs',
        'md' => 'px-4 py-2 text-xs',
        'lg' => 'px-6 py-3 text-sm',
    ][$size];

    $classes = "$base $variants $sizes";

    // `icon` only has a defined glyph for the two variants that historically shipped one
    // (Save / Delete). Other variants silently render without an icon rather than erroring,
    // since a decorative leading icon is optional everywhere else.
    $icons = [
        'primary' => 'tabler-device-floppy',
        'danger'  => 'tabler-trash',
    ];
    $iconComponent = $icon ? ($icons[$variant] ?? null) : null;
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($iconComponent)
            <x-dynamic-component :component="$iconComponent" class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($iconComponent)
            <x-dynamic-component :component="$iconComponent" class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
        @endif
        {{ $slot }}
    </button>
@endif
