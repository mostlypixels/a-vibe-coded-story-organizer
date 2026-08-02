@props(['striped' => false])

{{--
    Body row for x-table. Pass :striped="$loop->even" for zebra striping; a striped
    row drops to `surface` (the page tone) while the default row stays on
    `surface-raised` (the card tone), so the stripe follows the theme's own
    elevation scale instead of a fixed grey.
--}}
<tr {{ $attributes->merge(['class' => $striped ? 'bg-surface' : 'bg-surface-raised']) }}>
    {{ $slot }}
</tr>
