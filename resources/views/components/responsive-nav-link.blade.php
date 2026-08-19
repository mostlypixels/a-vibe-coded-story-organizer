@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-accent text-start text-base font-medium text-accent-content bg-accent-surface no-underline hover:no-underline focus:outline-hidden focus:ring-2 focus:ring-focus transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-nav-content no-underline hover:no-underline hover:bg-nav-raised hover:border-nav-content-muted focus:outline-hidden focus:bg-nav-raised focus:border-focus transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
