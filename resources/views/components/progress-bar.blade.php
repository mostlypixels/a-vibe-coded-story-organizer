@props(['value', 'goal', 'label'])

@php
    $percent = $goal > 0 ? min(100, (int) round($value / $goal * 100)) : 0;
@endphp

<div>
    <div class="flex items-baseline justify-between gap-4 text-sm">
        <span class="font-medium text-content">{{ $label }}</span>
        <span class="text-content-muted">
            <x-word-count :count="$value" variant="inline" /> {{ __('of') }} <x-word-count :count="$goal" variant="inline" />
        </span>
    </div>
    <div class="mt-1 h-2 rounded-full bg-border" role="progressbar" aria-valuenow="{{ $value }}" aria-valuemin="0" aria-valuemax="{{ $goal }}">
        <div class="h-2 rounded-full bg-accent" style="width: {{ $percent }}%"></div>
    </div>
</div>
