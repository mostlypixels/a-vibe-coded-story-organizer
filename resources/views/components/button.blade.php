@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'submit',
])

@php
    // Single flexible button. Renders an <a> when `href` is given (so links and
    // buttons share one look), otherwise a <button>. Colours are written out in
    // full so Tailwind's purge step keeps them — do not build class names by
    // interpolating the variant.
    $base = 'inline-flex items-center justify-center gap-2 border rounded-md font-semibold uppercase tracking-widest transition ease-in-out duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-25 disabled:cursor-not-allowed';

    $variants = [
        'primary'   => 'bg-gray-800 border-transparent text-white hover:bg-gray-700 active:bg-gray-900 focus:ring-indigo-500',
        'secondary' => 'bg-white border-gray-300 text-gray-700 shadow-sm hover:bg-gray-50 focus:ring-indigo-500',
        'danger'    => 'bg-red-600 border-transparent text-white hover:bg-red-500 active:bg-red-700 focus:ring-red-500',
        'success'   => 'bg-green-600 border-transparent text-white hover:bg-green-500 active:bg-green-700 focus:ring-green-500',
        'warning'   => 'bg-yellow-500 border-transparent text-white hover:bg-yellow-400 active:bg-yellow-600 focus:ring-yellow-500',
        'ghost'     => 'bg-transparent border-transparent text-gray-600 hover:bg-gray-100 focus:ring-indigo-500',
        'link'      => 'bg-transparent border-transparent normal-case tracking-normal text-indigo-600 hover:text-indigo-800 hover:underline focus:ring-indigo-500',
    ][$variant];

    $sizes = [
        'sm' => 'px-3 py-1.5 text-xs',
        'md' => 'px-4 py-2 text-xs',
        'lg' => 'px-6 py-3 text-sm',
    ][$size];

    $classes = "$base $variants $sizes";
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
