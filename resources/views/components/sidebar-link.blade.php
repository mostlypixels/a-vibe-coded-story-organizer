@props([
    'href',
    'active' => false,
    // 'sidebar' — a vertical list row with a leading accent border.
    // 'tab'     — a horizontal tab with an underline.
    'variant' => 'sidebar',
])

@php
    // Only the *state* colours live here. Geometry (border width and side,
    // padding, block vs flex) stays with the caller, passed through
    // $attributes, because the three lists that use this component are
    // deliberately different sizes — what must not drift is the active look and
    // the aria-current that goes with it.
    $stateClasses = match ([$variant, $active]) {
        ['sidebar', true] => 'border-flame-500 bg-aqua-50 text-navy-900 font-semibold',
        ['sidebar', false] => 'border-transparent text-gray-700 hover:bg-gray-100 hover:text-navy-900',
        ['tab', true] => 'border-flame-500 text-navy-900',
        ['tab', false] => 'border-transparent text-gray-500 hover:text-navy-900 hover:border-gray-300',
    };
@endphp

{{-- Active state is never colour-only: aria-current is what assistive tech and
     the tests read. --}}
<a
    href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    {{ $attributes->class([
        'no-underline hover:no-underline focus:outline-none transition duration-150 ease-in-out',
        $stateClasses,
    ]) }}
>{{ $slot }}</a>
