@props(['status'])

@php
    // Same four status tokens x-badge uses (see its own comment for why the
    // tinted text lands on `<status>-surface-content`, never `<status>` itself).
    // `to_edit` was `orange`, which has no token of its own — mapped to `danger`,
    // the more urgent of the two remaining statuses, since `to_proofread` already
    // claims `warning`.
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
