@props([
    'standing',   // App\Support\ChallengeStanding — one day per day of the window
])

@php
    $days = $standing->days
        ->map(fn ($day) => [
            'label' => $day->date->translatedFormat('j M'),
            'written' => $day->written,
        ])
        ->values()
        ->all();
@endphp

<div
    x-data="challengeChart({
        days: @js($days),
        totals: @js($standing->dailyTotals->values()->all()),
        par: @js($standing->parTotals->values()->all()),
        target: @js($standing->target),
        elapsedDays: @js($standing->elapsedDays),
        labels: {
            written: @js(__('Words written')),
            soFar: @js(__('Words so far')),
            par: @js(__('Par')),
        },
    })"
    {{ $attributes->merge(['class' => 'h-48 sm:h-64']) }}
>
    <canvas
        x-ref="canvas"
        role="img"
        aria-label="{{ __('Words written per day against the challenge target') }}"
    ></canvas>
</div>
