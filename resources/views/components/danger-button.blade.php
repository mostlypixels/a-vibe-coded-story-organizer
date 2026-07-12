@props(['icon' => false])

<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center gap-1.5 px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 active:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    @if ($icon)
        <x-tabler-trash class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
    @endif
    {{ $slot }}
</button>
