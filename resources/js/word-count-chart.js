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
