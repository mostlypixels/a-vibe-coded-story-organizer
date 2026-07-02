@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-flame-500 text-start text-base font-medium text-navy-900 bg-aqua-50 no-underline hover:no-underline focus:outline-none focus:text-navy-900 focus:bg-aqua-100 focus:border-flame-600 transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-aqua-100 no-underline hover:no-underline hover:text-white hover:bg-navy-800 hover:border-aqua-300 focus:outline-none focus:text-white focus:bg-navy-800 focus:border-aqua-300 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
