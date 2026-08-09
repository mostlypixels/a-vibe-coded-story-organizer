@props(['value', 'goal', 'label'])

{{--
    One goal row: a label, "value of goal words", and a bar. Shared by the
    Progress page's status strip and the dashboard card, so the two can never
    disagree on how a goal reads.

    A null $goal renders nothing — the caller checks first (see
    expanded/ui.md, "A null goal drops its row entirely"). Both numbers go
    through x-word-count, the one place "1,234" vs "1234" is decided.

    The bar caps at 100%: a total goal already met keeps reading as "done",
    not as a bar overflowing its track.
--}}
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
