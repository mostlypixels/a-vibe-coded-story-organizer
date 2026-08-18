@props(['html' => null])

@if (filled($html))
    <div {{ $attributes->merge(['class' => 'prose prose-sm font-manuscript max-w-none text-content-muted']) }}>
        {!! $html !!}
    </div>
@endif
