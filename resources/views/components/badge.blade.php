@props(['variant' => 'neutral', 'pill' => true])

@php
    // Keep class names complete so Tailwind can discover them.
    $variants = [
        'neutral' => 'bg-neutral text-neutral-content',
        'primary' => 'bg-primary text-primary-content',
        'info'    => 'bg-info-surface text-info-surface-content',
        'success' => 'bg-success-surface text-success-surface-content',
        'warning' => 'bg-warning-surface text-warning-surface-content',
        'danger'  => 'bg-danger-surface text-danger-surface-content',
        'accent'  => 'bg-accent-surface text-accent-content',
    ][$variant];

    $shape = $pill ? 'rounded-full' : 'rounded-sm';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2 py-0.5 text-xs font-medium whitespace-nowrap $shape $variants"]) }}>
    {{ $slot }}
</span>
