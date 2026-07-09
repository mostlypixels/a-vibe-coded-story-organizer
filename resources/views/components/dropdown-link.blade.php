@props(['active' => false])

@php
$classes = $active
    ? 'block w-full px-4 py-2 text-start text-sm leading-5 font-semibold text-navy-900 bg-aqua-50 no-underline hover:no-underline focus:outline-none focus:bg-aqua-100 transition duration-150 ease-in-out'
    : 'block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 no-underline hover:no-underline hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if ($active) aria-current="page" @endif>{{ $slot }}</a>
