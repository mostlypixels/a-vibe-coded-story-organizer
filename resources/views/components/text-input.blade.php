@props(['disabled' => false])

{{--
    `bg-surface-raised` is new here — the three form controls never set a
    background before the sweep, so they rendered the browser's native white
    regardless of theme. Invisible in Daylight (surface-raised is white
    there too) but a glaring unthemed box on any dark preset; the low-glare
    dark preset is what caught it.

    `text-content` is here for the same reason, and is just as easy to miss: a
    form control does not inherit the page's `color`, it starts from the
    browser's own near-black. Setting only the background is what turns a dark
    preset's input into dark text on a dark box.
--}}
<input @disabled($disabled) {{ $attributes->merge(['class' => 'bg-surface-raised text-content placeholder:text-content-subtle border-border-strong focus:border-focus focus:ring-focus rounded-md shadow-xs']) }}>
