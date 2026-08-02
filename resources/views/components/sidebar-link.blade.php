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
    // 'sidebar' active is tinted with `accent-surface`/`accent-content`, the same
    // "this is the one you're on" pair `dropdown-link` and `responsive-nav-link`
    // use. 'tab' active has no tint to sit on, so it stays plain `content`.
    $stateClasses = match ([$variant, $active]) {
        ['sidebar', true] => 'border-accent bg-accent-surface text-accent-content font-semibold',
        ['sidebar', false] => 'border-transparent text-content-muted hover:bg-neutral hover:text-content',
        ['tab', true] => 'border-accent text-content',
        ['tab', false] => 'border-transparent text-content-muted hover:text-content hover:border-border-strong',
    };
@endphp

{{-- Active state is never colour-only: aria-current is what assistive tech and
     the tests read. --}}
<a
    href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    {{ $attributes->class([
        'no-underline hover:no-underline focus:outline-hidden transition duration-150 ease-in-out',
        $stateClasses,
    ]) }}
>{{ $slot }}</a>
