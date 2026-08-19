@props([
    'html' => null,
    'inline' => false,
    'kind' => null,
])


@php
    $isSource = $kind !== null && $kind !== \App\Enums\FieldKind::Rich;

    $variant = $inline
        ? 'revision-diff--inline'
        : ($isSource ? 'revision-diff--source' : 'revision-diff--visual');
@endphp

@if (filled($html))
    <div {{ $attributes->merge(['class' => "revision-diff {$variant}"]) }}>
        {!! $html !!}
    </div>
@endif
