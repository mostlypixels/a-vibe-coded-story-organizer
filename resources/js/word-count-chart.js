/** Draw daily changes as bars and the daily goal as a line. */

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

// Register only the chart types that this bundle uses.
Chart.register(BarController, BarElement, LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip);

const COLOR_TOKENS = {
    bar: '--color-primary',
    cut: '--color-danger',
    goal: '--color-accent',
    grid: '--color-border',
    tick: '--color-content-muted',
};

/** Resolve theme tokens because Chart.js does not resolve CSS variables. */
export function themeColors(element) {
    const styles = getComputedStyle(element);
    const colors = {};

    for (const [name, token] of Object.entries(COLOR_TOKENS)) {
        const value = styles.getPropertyValue(token).trim();

        if (value !== '') colors[name] = value;
    }

    return colors;
}

export function chartConfig({ days = [], dailyGoal = null, variant = 'full', colors = {}, labels = {} } = {}) {
    const compact = variant === 'compact';
    const written = days.map((day) => day.written);

    const datasets = [
        {
            type: 'bar',
            label: labels.written ?? 'Words written',
            data: written,
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

            // Chart.js permits only one live chart for each canvas.
            this.destroy();

            this.chart = createChart(
                canvas,
                chartConfig({ days, dailyGoal, variant, labels, colors: themeColors(canvas) }),
            );
        },
    }));
}

/**
 * The challenge chart: what each day added, against the climbing total and par.
 *
 * Two axes because a day's delta (hundreds) and the running total (tens of
 * thousands) share no scale — one axis flattens the bars into the floor.
 * `y1` tops out at the target, so the climbing line touching the top edge is
 * the challenge being met.
 */
export function challengeChartConfig({
    days = [],
    totals = [],
    par = [],
    target = 0,
    elapsedDays = 0,
    colors = {},
    labels = {},
} = {}) {
    const written = days.map((day) => day.written);

    // The line stops at today. Drawing it to the deadline would read as a
    // forecast the app does not make.
    const soFar = totals.map((total, index) => (index < elapsedDays ? total : null));

    return {
        type: 'bar',
        data: {
            labels: days.map((day) => day.label),
            datasets: [
                {
                    type: 'bar',
                    label: labels.written ?? 'Words written',
                    data: written,
                    backgroundColor: written.map((count) => (count < 0 ? colors.cut : colors.bar)),
                    borderWidth: 0,
                    yAxisID: 'y',
                    order: 2,
                },
                {
                    type: 'line',
                    label: labels.soFar ?? 'Words so far',
                    data: soFar,
                    borderColor: colors.bar,
                    borderWidth: 2,
                    pointRadius: 0,
                    fill: false,
                    spanGaps: false,
                    yAxisID: 'y1',
                    order: 1,
                },
                {
                    type: 'line',
                    label: labels.par ?? 'Par',
                    data: par,
                    borderColor: colors.goal,
                    borderDash: [4, 4],
                    borderWidth: 2,
                    pointRadius: 0,
                    fill: false,
                    yAxisID: 'y1',
                    order: 0,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            plugins: {
                legend: { display: false },
                tooltip: { enabled: true },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: colors.tick },
                },
                y: {
                    position: 'left',
                    grid: { color: colors.grid },
                    ticks: { color: colors.tick, precision: 0 },
                },
                y1: {
                    position: 'right',
                    beginAtZero: true,
                    max: target,
                    grid: { display: false },
                    ticks: { color: colors.tick, precision: 0 },
                },
            },
        },
    };
}

export function registerChallengeChart(Alpine, { createChart = (canvas, config) => new Chart(canvas, config) } = {}) {
    Alpine.data(
        'challengeChart',
        ({ days = [], totals = [], par = [], target = 0, elapsedDays = 0, labels = {} } = {}) => ({
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

                // Chart.js permits only one live chart for each canvas.
                this.destroy();

                this.chart = createChart(
                    canvas,
                    challengeChartConfig({
                        days,
                        totals,
                        par,
                        target,
                        elapsedDays,
                        labels,
                        colors: themeColors(canvas),
                    }),
                );
            },
        }),
    );
}
