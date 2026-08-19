@props(['active' => false])

<button {{ $attributes->class([
    'inline-flex h-12 items-center px-1 border-b-2 text-sm font-medium leading-5 focus:outline-hidden focus:ring-2 focus:ring-focus transition duration-150 ease-in-out',
    'text-nav-content border-accent' => $active,
    'text-nav-content border-transparent hover:border-nav-content-muted' => ! $active,
]) }}>
    {{ $slot }}

    <x-tabler-chevron-down class="ms-1 h-4 w-4" />
</button>
