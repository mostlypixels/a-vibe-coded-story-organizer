@props(['striped' => false])

<tr {{ $attributes->merge(['class' => $striped ? 'bg-surface' : 'bg-surface-raised']) }}>
    {{ $slot }}
</tr>
