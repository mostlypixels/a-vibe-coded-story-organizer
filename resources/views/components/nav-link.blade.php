@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-flame-500 text-sm font-medium leading-5 text-white no-underline hover:no-underline focus:outline-none focus:border-flame-600 transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-aqua-100 no-underline hover:no-underline hover:text-white hover:border-aqua-300 focus:outline-none focus:text-white focus:border-aqua-300 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
