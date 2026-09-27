@props(['href', 'active' => false, 'current' => false, 'menuLabel'])

{{--
    A nav section: the label is a link to the section hub, the chevron opens its menu.
    Put it in the trigger slot of `x-dropdown hover`. The underline spans both parts.
    The pt-1 matches x-nav-link, so the labels line up with Dashboard and Search.
--}}
<div @class([
    'inline-flex h-12 items-stretch pt-1 border-b-2 transition duration-150 ease-in-out',
    'border-accent' => $active,
    'border-transparent hover:border-nav-content-muted' => ! $active,
])>
    <a href="{{ $href }}"
        @if ($current) aria-current="page" @endif
        @if ($active) data-active @endif
        class="inline-flex items-center ps-1 text-sm font-medium leading-5 text-nav-content no-underline hover:no-underline focus:outline-hidden focus:ring-2 focus:ring-focus">{{ $slot }}</a>

    {{-- WCAG 2.5.8: the hit area is at least 24px wide and the full nav height. --}}
    <x-disclosure-button
        aria-label="{{ $menuLabel }}"
        class="inline-flex min-w-6 items-center justify-center text-nav-content focus:outline-hidden focus:ring-2 focus:ring-focus">
        {{-- Points up while the menu is open. A click does what the arrow shows. --}}
        <x-tabler-chevron-down class="h-4 w-4 transition-transform duration-150" ::class="open && 'rotate-180'" aria-hidden="true" />
    </x-disclosure-button>
</div>
