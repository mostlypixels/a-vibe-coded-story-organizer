@props(['as' => 'button', 'variant' => 'outline-solid', 'icon', 'label'])


@php
    $variants = [
        'outline-solid' => 'border border-link bg-transparent text-link hover:bg-info-surface',
        'danger' => 'border border-danger bg-transparent text-danger hover:bg-danger-surface',
        'light' => 'border border-nav-content bg-transparent text-nav-content hover:bg-nav-content/10',
        'ghost' => 'text-content-subtle hover:text-content-muted hover:bg-neutral'
            .' disabled:opacity-25 disabled:cursor-not-allowed'
            .' disabled:hover:bg-transparent disabled:hover:text-content-subtle',
    ][$variant];
@endphp

<{{ $as }}
    {{ $attributes->merge(['class' => 'inline-flex items-center justify-center p-1.5 rounded-md '.$variants]) }}
    title="{{ $label }}"
>
    <span class="sr-only">{{ $label }}</span>
    <x-dynamic-component :component="'tabler-'.$icon" class="h-4 w-4" />
</{{ $as }}>
