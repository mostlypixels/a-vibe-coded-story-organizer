<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-navy-900 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-navy-800 focus:bg-navy-800 active:bg-navy-950 focus:outline-none focus:ring-2 focus:ring-ocean-500 focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
