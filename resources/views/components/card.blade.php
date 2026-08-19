@props(['title' => null, 'stretch' => false, 'flushFooter' => false, 'padded' => true])

@php
    $hasHeader = isset($header) || filled($title);

    $shell = $stretch ? 'flex h-full flex-col' : '';

    $body = trim(($stretch ? 'flex-1' : '').($padded ? ' px-6 py-4' : ''));

    $footerSurface = $flushFooter ? '' : 'bg-surface-sunken';
@endphp

<div {{ $attributes->merge(['class' => trim("bg-surface-raised overflow-hidden shadow-xs sm:rounded-lg {$shell}")]) }}>
    @if ($hasHeader)
        <div class="border-b border-border px-6 py-4">
            @isset($header)
                {{ $header }}
            @else
                <x-heading level="3">{{ $title }}</x-heading>
            @endisset
        </div>
    @endif

    <div class="{{ $body }}">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="border-t border-border px-6 py-4 {{ $footerSurface }}">
            {{ $footer }}
        </div>
    @endisset
</div>
