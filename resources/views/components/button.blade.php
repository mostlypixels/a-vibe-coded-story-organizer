@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'submit',
    'icon' => false,
])

@php
    // Keep class names complete so Tailwind can discover them.
    $base = 'inline-flex items-center justify-center gap-2 border rounded-md font-semibold uppercase tracking-widest transition ease-in-out duration-150 focus:outline-hidden focus:ring-2 focus:ring-offset-2 disabled:opacity-25 disabled:cursor-not-allowed';

    $variants = [
        'primary'   => 'bg-primary border-transparent text-primary-content hover:bg-primary-hover active:bg-primary-active focus:ring-focus',
        'secondary' => 'bg-surface-raised border-border-strong text-content-muted shadow-xs hover:bg-surface-sunken focus:ring-focus',
        'danger'    => 'bg-danger border-transparent text-danger-content hover:bg-danger/90 active:bg-danger/80 focus:ring-focus',
        'success'   => 'bg-success border-transparent text-success-content hover:bg-success/90 active:bg-success/80 focus:ring-focus',
        'warning'   => 'bg-warning border-transparent text-warning-content hover:bg-warning/90 active:bg-warning/80 focus:ring-focus',
        'ghost'     => 'bg-transparent border-transparent text-content-muted hover:bg-neutral focus:ring-focus',
        'link'      => 'bg-transparent border-transparent normal-case tracking-normal text-link hover:text-link-hover hover:underline focus:ring-focus',
    ][$variant];

    $sizes = [
        'sm' => 'px-3 py-1.5 text-xs',
        'md' => 'px-4 py-2 text-xs',
        'lg' => 'px-6 py-3 text-sm',
    ][$size];

    $classes = "$base $variants $sizes";

    $icons = [
        'primary' => 'tabler-device-floppy',
        'danger'  => 'tabler-trash',
    ];
    $iconComponent = match (true) {
        is_string($icon) => $icon,
        (bool) $icon => $icons[$variant] ?? null,
        default => null,
    };
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
