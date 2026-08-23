@props(['title' => null, 'stretch' => false, 'flushFooter' => false, 'padded' => true, 'overflow' => 'hidden'])

@php
    $hasHeader = isset($header) || filled($title);

    $shell = $stretch ? 'flex h-full flex-col' : '';

    $body = trim(($stretch ? 'flex-1' : '').($padded ? ' px-6 py-4' : ''));

    $footerSurface = $flushFooter ? '' : 'bg-surface-sunken';

    // Cards clip by default so the header border and rounded corners stay clean.
    // A card holding a pop-out (an autocomplete list) opts into `visible` so the
    // menu is not cut off. Literal classes keep Tailwind's JIT aware of both.
    $overflowClass = $overflow === 'visible' ? 'overflow-visible' : 'overflow-hidden';
@endphp

<div {{ $attributes->merge(['class' => trim("bg-surface-raised {$overflowClass} shadow-xs sm:rounded-lg {$shell}")]) }}>
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
