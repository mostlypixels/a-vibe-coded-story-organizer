@props(['status'])

@php
    $colors = [
        'draft' => 'bg-neutral text-neutral-content',
        'to_proofread' => 'bg-warning-surface text-warning-surface-content',
        'to_edit' => 'bg-danger-surface text-danger-surface-content',
        'final' => 'bg-success-surface text-success-surface-content',
    ][$status->value];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap $colors"]) }}>
    {{ $status->label() }}
</span>
