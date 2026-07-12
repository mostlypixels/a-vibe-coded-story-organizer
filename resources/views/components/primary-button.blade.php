@props(['icon' => false])

<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center gap-1.5 px-4 py-2 bg-navy-900 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-navy-800 focus:bg-navy-800 active:bg-navy-950 focus:outline-none focus:ring-2 focus:ring-ocean-500 focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    @if ($icon)
        <x-tabler-device-floppy class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
    @endif
    {{ $slot }}
</button>
