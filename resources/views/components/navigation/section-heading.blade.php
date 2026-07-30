{{-- Group label in the responsive menu. The collapsed menu has no dropdowns,
     so each desktop dropdown becomes a heading followed by its flat links. --}}
<div {{ $attributes->merge(['class' => 'px-4 pt-2 pb-1 text-xs font-semibold text-aqua-200 uppercase tracking-wider']) }}>
    {{ $slot }}
</div>
