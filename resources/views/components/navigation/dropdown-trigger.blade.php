@props(['active' => false])

{{-- The button that opens a primary-nav dropdown: label + chevron, underlined
     in the accent colour when its section is active. Mirrors nav-link's
     active look so a dropdown section and a plain link read the same in the
     bar. `focus:ring-2` was missing here — a pre-existing gap, added to match
     nav-link's focus affordance. --}}
<button {{ $attributes->class([
    'inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 hover:text-nav-content focus:outline-hidden focus:ring-2 focus:ring-focus transition duration-150 ease-in-out',
    'text-nav-content border-accent' => $active,
    'text-nav-content border-transparent' => ! $active,
]) }}>
    {{ $slot }}

    <x-tabler-chevron-down class="ms-1 h-4 w-4" />
</button>
