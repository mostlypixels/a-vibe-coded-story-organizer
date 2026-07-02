@props(['variant' => 'gray', 'pill' => true])

@php
    // Small status/label pill. Full class strings per variant keep Tailwind's
    // purge happy. See x-scene-status-badge for a domain-specific version that
    // maps an enum to these same styles.
    $variants = [
        'gray'    => 'bg-gray-100 text-gray-700',
        'primary' => 'bg-navy-900 text-white',
        'info'    => 'bg-blue-100 text-blue-800',
        'success' => 'bg-green-100 text-green-800',
        'warning' => 'bg-yellow-100 text-yellow-800',
        'danger'  => 'bg-red-100 text-red-800',
        'indigo'  => 'bg-ocean-100 text-ocean-800',
    ][$variant];

    $shape = $pill ? 'rounded-full' : 'rounded';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2 py-0.5 text-xs font-medium whitespace-nowrap $shape $variants"]) }}>
    {{ $slot }}
</span>
