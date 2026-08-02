@props(['title' => null])

@php
    // Container surface used across index/show pages. Three optional regions:
    //   - a header: either the `header` slot (full control) or a plain `title`
    //   - the default slot (the body)
    //   - a `footer` slot
    $hasHeader = isset($header) || filled($title);
@endphp

<div {{ $attributes->merge(['class' => 'bg-surface-raised overflow-hidden shadow-xs sm:rounded-lg']) }}>
    @if ($hasHeader)
        <div class="border-b border-border px-6 py-4">
            @isset($header)
                {{ $header }}
            @else
                <x-heading level="3">{{ $title }}</x-heading>
            @endisset
        </div>
    @endif

    <div class="px-6 py-4">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="border-t border-border bg-surface-sunken px-6 py-4">
            {{ $footer }}
        </div>
    @endisset
</div>
