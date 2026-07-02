@props(['striped' => false])

{{--
    Body row for x-table. Pass :striped="$loop->even" for zebra striping; striped
    rows use bg-gray-100 (a step darker than the default bg-white rows).
--}}
<tr {{ $attributes->merge(['class' => $striped ? 'bg-gray-100' : 'bg-white']) }}>
    {{ $slot }}
</tr>
