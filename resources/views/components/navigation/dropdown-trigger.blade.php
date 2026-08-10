@props(['active' => false])

{{-- The button that opens a primary-nav dropdown: label + chevron, underlined
     in the accent colour when its section is active.

     `h-12` is the bar's height, and it is what makes the trigger match a plain
     nav-link: a link stretches to the full bar and drops its accent strip on
     the bar's bottom edge, while a button in a centred wrapper would hug its
     own text and float the strip in the middle of the bar. --}}
<button {{ $attributes->class([
    'inline-flex h-12 items-center px-1 border-b-2 text-sm font-medium leading-5 focus:outline-hidden focus:ring-2 focus:ring-focus transition duration-150 ease-in-out',
    'text-nav-content border-accent' => $active,
    'text-nav-content border-transparent hover:border-nav-content-muted' => ! $active,
]) }}>
    {{ $slot }}

    <x-tabler-chevron-down class="ms-1 h-4 w-4" />
</button>
