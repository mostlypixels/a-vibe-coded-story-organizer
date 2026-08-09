/**
 * The word-count chart — bars for the words a day added, a flat line for the
 * daily goal.
 *
 * Bars, not a line, because each day is its own quantity. A line implies the
 * value flows between two points, and a day the writer cut words would read as
 * a continuous quantity that goes below zero instead of one bar under the axis.
 * A rest day draws no bar at all.
 *
 * The series is server-rendered into `x-data` by
 * `components/word-count-chart.blade.php`. This module never fetches anything.
 *
 * > [!WARNING]
 * > Chart.js keeps a registry keyed by canvas and throws "Canvas is already in
 * > use" when a second chart mounts over a live one. {@see mount} destroys the
 * > previous instance first, and the Alpine component destroys it on teardown.
 */

import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';

// The pieces this chart draws, not `chart.js/auto`: `auto` registers every
// controller (pie, radar, polar, scatter, bubble) into the single app.js bundle
// for one mixed bar/line chart.
Chart.register(BarController, BarElement, LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip);

/** The theme tokens the chart paints with. `components/theme-style.blade.php`
 *  writes them onto `:root`, so a preset change repaints the chart too. */
const COLOR_TOKENS = {
    bar: '--color-primary',
    cut: '--color-danger',
    goal: '--color-accent',
    grid: '--color-border',
    tick: '--color-content-muted',
};

/**
 * Read the theme colours once, at mount. Chart.js needs literal colour strings,
 * so a `var(--color-primary)` passed straight through would paint nothing —
 * and a hard-coded hex would make the chart the one element that ignores the
 * user's theme.
 */
export function themeColors(element) {
    const styles = getComputedStyle(element);
    const colors = {};

    for (const [name, token] of Object.entries(COLOR_TOKENS)) {
        // An empty value happens where no theme block is present. Leaving the
        // key out lets Chart.js use its own default rather than paint with ''.
        const value = styles.getPropertyValue(token).trim();

        if (value !== '') colors[name] = value;
    }

    return colors;
}

/**
 * The Chart.js config for one series.
 *
 * `days` is `[{ label, written }, …]` in date order, gaps included.
 * `dailyGoal` is null when the project has no daily goal — then there is one
 * dataset, not a dataset full of nulls.
 *
 * The `compact` variant is the same data drawn small for the dashboard card:
 * no axes, no tick labels, no tooltip. One config with two variants, because a
 * second implementation would drift from this one.
 */
export function chartConfig({ days = [], dailyGoal = null, variant = 'full', colors = {}, labels = {} } = {}) {
    const compact = variant === 'compact';
    const written = days.map((day) => day.written);

    const datasets = [
        {
            type: 'bar',
            label: labels.written ?? 'Words written',
            data: written,
            // A cut day is a different event from a writing day, so it gets the
            // danger token instead of the same bar colour below the axis.
            backgroundColor: written.map((count) => (count < 0 ? colors.cut : colors.bar)),
            borderWidth: 0,
        },
    ];

    if (dailyGoal !== null && dailyGoal !== undefined) {
        datasets.push({
            type: 'line',
            label: labels.goal ?? 'Daily goal',
            data: days.map(() => dailyGoal),
            borderColor: colors.goal,
            borderDash: [4, 4],
            borderWidth: 2,
            pointRadius: 0,
            fill: false,
        });
    }

    return {
        type: 'bar',
        data: {
            labels: days.map((day) => day.label),
            datasets,
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            plugins: {
                legend: { display: false },
                tooltip: { enabled: !compact },
            },
            scales: {
                x: {
                    display: !compact,
                    grid: { display: false },
                    ticks: { color: colors.tick },
                },
                y: {
                    display: !compact,
                    grid: { color: colors.grid },
                    ticks: { color: colors.tick, precision: 0 },
                },
            },
        },
    };
}

/**
 * The Alpine component. `createChart` is injectable so the tests can drive
 * mount and teardown without a real 2D context — jsdom has none, and Chart.js
 * only logs when it cannot acquire one.
 */
export function registerWordCountChart(Alpine, { createChart = (canvas, config) => new Chart(canvas, config) } = {}) {
    Alpine.data('wordCountChart', ({ days = [], dailyGoal = null, variant = 'full', labels = {} } = {}) => ({
        chart: null,

        init() {
            this.mount();
        },

        destroy() {
            this.chart?.destroy();
            this.chart = null;
        },

        mount() {
            const canvas = this.$refs.canvas;

            if (!canvas) return;

            // Chart.js throws "Canvas is already in use" over a live instance.
            this.destroy();

            this.chart = createChart(
                canvas,
                chartConfig({ days, dailyGoal, variant, labels, colors: themeColors(canvas) }),
            );
        },
    }));
}
