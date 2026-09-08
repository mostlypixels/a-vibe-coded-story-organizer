@props(['striped' => false, 'highlighted' => false])

{{-- A jumped-to group wins over the stripe: the stripe only tells rows apart.
     A tint, not the solid highlight colour — the cells inside keep their own
     text and badge colours, which a solid fill makes unreadable. --}}
<tr {{ $attributes->merge(['class' => $highlighted ? 'bg-highlight/25' : ($striped ? 'bg-surface' : 'bg-surface-raised')]) }}>
    {{ $slot }}
</tr>
