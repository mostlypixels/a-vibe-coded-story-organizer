@props(['status'])

@php
    $colors = [
        'draft' => 'bg-gray-100 text-gray-700',
        'to_proofread' => 'bg-yellow-100 text-yellow-800',
        'to_edit' => 'bg-orange-100 text-orange-800',
        'final' => 'bg-green-100 text-green-800',
    ][$status->value];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap $colors"]) }}>
    {{ $status->label() }}
</span>
