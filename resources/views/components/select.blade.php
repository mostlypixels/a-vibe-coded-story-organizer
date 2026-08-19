@props(['disabled' => false])

<select @disabled($disabled) {{ $attributes->merge(['class' => 'bg-surface-raised text-content border-border-strong focus:border-focus focus:ring-focus rounded-md shadow-xs']) }}>
    {{ $slot }}
</select>
