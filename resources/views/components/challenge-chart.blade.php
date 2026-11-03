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

{{-- Two charts, because a day's words and the running total share no scale. --}}
<div {{ $attributes->merge(['class' => 'space-y-4']) }}>
    <div
        x-data="challengeChart({
            days: @js($days),
            totals: @js($standing->dailyTotals->values()->all()),
            par: @js($standing->parTotals->values()->all()),
            target: @js($standing->target),
            elapsedDays: @js($standing->elapsedDays),
            labels: {
                soFar: @js(__('Words so far')),
                par: @js(__('Par')),
            },
        })"
        class="h-48 sm:h-64"
    >
        <canvas
            x-ref="canvas"
            role="img"
            aria-label="{{ __('Words so far against the challenge par') }}"
        ></canvas>
    </div>

    <div
        x-data="wordCountChart({
            days: @js($days),
            labels: { written: @js(__('Words written')) },
        })"
        class="h-32 sm:h-40"
    >
        <canvas
            x-ref="canvas"
            role="img"
            aria-label="{{ __('Words written per day') }}"
        ></canvas>
    </div>
</div>
