@props(['title' => null, 'stretch' => false, 'flushFooter' => false, 'padded' => true])

@php
    // Container surface used across index/show pages. Three optional regions:
    //   - a header: either the `header` slot (full control) or a plain `title`
    //   - the default slot (the body)
    //   - a `footer` slot
    $hasHeader = isset($header) || filled($title);

    // `stretch` makes the card fill its grid cell and pushes the footer to the
    // bottom, so a row of cards with different body lengths still aligns.
    $shell = $stretch ? 'flex h-full flex-col' : '';

    // `padded` off drops the body gutter, so striped rows and their dividers
    // reach both edges of the card. The body then owns its own spacing.
    $body = trim(($stretch ? 'flex-1' : '').($padded ? ' px-6 py-4' : ''));

    // `flushFooter` keeps the card surface under the footer. Use it when the
    // footer is one quiet link; the sunken bar is for real action rows.
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
