@props(['disabled' => false])

{{--
    `bg-surface-raised` is new here — the three form controls never set a
    background before the sweep, so they rendered the browser's native white
    regardless of theme. Invisible in Daylight (surface-raised is white
    there too) but a glaring unthemed box on any dark preset; the low-glare
    dark preset is what caught it.
--}}
<input @disabled($disabled) {{ $attributes->merge(['class' => 'bg-surface-raised border-border-strong focus:border-focus focus:ring-focus rounded-md shadow-xs']) }}>
