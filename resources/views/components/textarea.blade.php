@props(['disabled' => false])


<textarea @disabled($disabled) {{ $attributes->merge(['class' => 'bg-surface-raised text-content placeholder:text-content-subtle border-border-strong focus:border-focus focus:ring-focus rounded-md shadow-xs']) }}>{{ $slot }}</textarea>
