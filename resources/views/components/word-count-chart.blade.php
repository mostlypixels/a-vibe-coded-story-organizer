@props([
    'series',              // App\Support\WordCountSeries — one day per calendar day, in order
    'dailyGoal' => null,   // int|null; the goal line only exists when the project sets one
    'variant' => 'full',   // 'full' (axes, ticks, tooltips) or 'compact' (bars and the goal line only)
])

@php
    // The chart config lives in resources/js/word-count-chart.js; this template
    // only serialises the series. Dates are formatted server-side so the chart
    // never has to know the locale.
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
    {{-- A canvas holds no text, so it needs the label a screen reader reads
         instead. The figures themselves stay available in the page around it. --}}
    <canvas
        x-ref="canvas"
        role="img"
        aria-label="{{ __('Words written per day') }}"
    ></canvas>
</div>
