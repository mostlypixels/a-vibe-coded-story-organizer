@props([
    'align' => null,
    'top' => false,
    'muted' => false,
    'total' => false,
    'nowrap' => false,
    'sm' => false,
])

@php
    $classes = ['px-4 py-3'];

    if ($top) {
        $classes[] = 'align-top';
    }

    if ($align === 'right') {
        $classes[] = 'text-right';
    }

    if ($sm || $muted || $total) {
        $classes[] = 'text-sm';
    }

    if ($muted) {
        $classes[] = 'text-content-muted';
    }

    if ($total) {
        $classes[] = 'font-semibold text-table-header-content';
    }

    if ($nowrap) {
        $classes[] = 'whitespace-nowrap';
    }
@endphp

<td {{ $attributes->merge(['class' => implode(' ', $classes)]) }}>
    {{ $slot }}
</td>
