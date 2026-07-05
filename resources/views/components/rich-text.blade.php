@props(['html' => null])

{{--
    Renders sanitized rich HTML. This is the ONLY place rich user HTML is echoed
    with {!! !!}: the value has already passed through App\Services\HtmlSanitizer
    on write (per-field set-mutators, task 02), so this is "intentionally rendering
    trusted HTML" per the guidelines. Never point {!! !!} at a rich field anywhere
    else — index/list cells use x-rich-text-excerpt (escaped text) instead.

    The prose classes mirror the Story overview's Markdown rendering so rich HTML
    reads consistently across the app.
--}}
@if (filled($html))
    <div {{ $attributes->merge(['class' => 'prose prose-sm max-w-none text-gray-700']) }}>
        {!! $html !!}
    </div>
@endif
