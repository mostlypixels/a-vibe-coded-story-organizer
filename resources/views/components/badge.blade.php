@props(['variant' => 'neutral', 'pill' => true])

@php
    // Small status/label pill. Each variant writes its class string in full,
    // because Tailwind only finds class names that appear complete in the source.
    // See x-scene-status-badge for a domain-specific version that maps an enum to
    // these same styles.
    //
    // Variants are named for the role they carry, never for a hue: the two that
    // used to be `gray` and `indigo` are now `neutral` and `accent`. `accent` has
    // to stay visually distinct from `info` — x-revision-origin-badge uses both,
    // for an imported revision and a manually saved one.
    //
    // A tinted variant puts its text on `<status>-surface-content`, never on
    // `<status>` itself: the fill is chosen to carry white, so reusing it as text
    // on the tint measures as low as 1.85:1.
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
