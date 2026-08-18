@props([
    'series',              // App\Support\WordCountSeries — one day per calendar day, in order
    'dailyGoal' => null,   // int|null; the goal line only exists when the project sets one
    'variant' => 'full',   // 'full' (axes, ticks, tooltips) or 'compact' (bars and the goal line only)
])

@php
    $days = $series->days
        ->map(fn ($day) => [
            'label' => $day->date->translatedFormat('j M'),
            'written' => $day->written,
        ])
        ->values()
        ->all();

    $compact = $variant === 'compact';
@endphp

<div
    x-data="wordCountChart({
        days: @js($days),
        dailyGoal: @js($dailyGoal),
        variant: @js($variant),
        labels: {
            written: @js(__('Words written')),
            goal: @js(__('Daily goal')),
        },
    })"
    data-variant="{{ $variant }}"
    {{ $attributes->merge(['class' => $compact ? 'h-24' : 'h-64 sm:h-80']) }}
>
    <canvas
        x-ref="canvas"
        role="img"
        aria-label="{{ __('Words written per day') }}"
    ></canvas>
</div>
