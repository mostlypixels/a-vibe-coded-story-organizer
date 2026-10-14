{{-- `project` scopes the `entity:` links of a visual diff. A source diff shows them as typed. --}}
@props([
    'html' => null,
    'inline' => false,
    'kind' => null,
    'project',
])


@php
    $isSource = $kind !== null && $kind !== \App\Enums\FieldKind::Rich;

    $variant = $inline
        ? 'revision-diff--inline'
        : ($isSource ? 'revision-diff--source' : 'revision-diff--visual');
@endphp

@if (filled($html))
    <div {{ $attributes->merge(['class' => "revision-diff {$variant}"]) }}>
        {!! $isSource ? $html : app(\App\Services\EntityLinkResolver::class)->render($html, $project) !!}
    </div>
@endif
