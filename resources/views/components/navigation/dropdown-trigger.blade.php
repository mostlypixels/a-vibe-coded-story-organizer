@props(['active' => false])

{{-- The button that opens a primary-nav dropdown: label + chevron, underlined
     in the accent colour when its section is active. Mirrors nav-link's
     active look so a dropdown section and a plain link read the same in the
     bar. `focus:ring-2` was missing here (pre-existing, spec 1's
     standing-issues.md); added to match nav-link's focus affordance. --}}
<button {{ $attributes->class([
    'inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 hover:text-nav-content focus:outline-hidden focus:ring-2 focus:ring-focus transition duration-150 ease-in-out',
    'text-nav-content border-accent' => $active,
    'text-nav-content border-transparent' => ! $active,
]) }}>
    {{ $slot }}

    <svg class="ms-1 h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
    </svg>
</button>
