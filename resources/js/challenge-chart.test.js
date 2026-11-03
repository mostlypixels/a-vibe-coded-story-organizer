import { describe, expect, it, vi } from 'vitest';
import { challengeChartConfig, registerChallengeChart } from './word-count-chart';

const threeDays = [
    { label: '1 Mar', written: 800 },
    { label: '2 Mar', written: 0 },
    { label: '3 Mar', written: -120 },
];

// Three days of a five-day window, so two days are still ahead of today.
const runningWindow = {
    days: [...threeDays, { label: '4 Mar', written: 0 }, { label: '5 Mar', written: 0 }],
    totals: [800, 800, 680, 680, 680],
    par: [200, 400, 600, 800, 1000],
    target: 1000,
    elapsedDays: 3,
};

function datasets(config) {
    return config.data.datasets;
}

describe('challengeChartConfig', () => {
    it('draws the running total and par on one axis', () => {
        const [soFar, par] = datasets(challengeChartConfig(runningWindow));

        expect(datasets(challengeChartConfig(runningWindow))).toHaveLength(2);
        expect(soFar.type).toBe('line');
        expect(par.type).toBe('line');
        expect(par.data).toEqual([200, 400, 600, 800, 1000]);
        expect(par.borderDash).toEqual([4, 4]);
        expect(soFar.yAxisID).toBeUndefined();
        expect(par.yAxisID).toBeUndefined();
    });

    it('stops the climbing line at today rather than predicting the rest', () => {
        const [soFar] = datasets(challengeChartConfig(runningWindow));

        expect(soFar.data).toEqual([800, 800, 680, null, null]);
        // A gap must stay a gap, not be bridged to the deadline.
        expect(soFar.spanGaps).toBe(false);
    });

    it('runs the climbing line to the end once the window is over', () => {
        const [soFar] = datasets(challengeChartConfig({ ...runningWindow, elapsedDays: 5 }));

        expect(soFar.data).toEqual([800, 800, 680, 680, 680]);
    });

    it('tops the axis at the target, so the line meeting it is the target met', () => {
        const { options } = challengeChartConfig(runningWindow);

                expect(options.scales.y.max).toBe(1000);
        expect(options.scales.y.beginAtZero).toBe(true);
    });

    it('yields a valid config for a window with no days at all', () => {
        const config = challengeChartConfig();

        expect(config.data.labels).toEqual([]);
        expect(datasets(config)).toHaveLength(2);
        expect(datasets(config).every((dataset) => dataset.data.length === 0)).toBe(true);
        expect(config.options.scales.y.max).toBe(0);
    });
});

describe('the Alpine component', () => {
    function mountComponent(config, createChart) {
        const components = {};
        const Alpine = { data: (name, factory) => (components[name] = factory) };

        registerChallengeChart(Alpine, { createChart });

        const component = components.challengeChart(config);
        component.$refs = { canvas: document.createElement('canvas') };

        return component;
    }

    function fakeChartFactory() {
        const instances = [];
        const createChart = vi.fn((canvas, chartConfiguration) => {
            const instance = { canvas, config: chartConfiguration, destroy: vi.fn() };
            instances.push(instance);

            return instance;
        });

        return { createChart, instances };
    }

    it('creates one chart over its canvas on init', () => {
        const { createChart, instances } = fakeChartFactory();
        const component = mountComponent(runningWindow, createChart);

        component.init();

        expect(createChart).toHaveBeenCalledTimes(1);
        expect(instances[0].canvas).toBe(component.$refs.canvas);
        expect(instances[0].config.data.datasets).toHaveLength(2);
    });

    it('destroys the chart on teardown', () => {
        const { createChart, instances } = fakeChartFactory();
        const component = mountComponent(runningWindow, createChart);

        component.init();
        component.destroy();

        expect(instances[0].destroy).toHaveBeenCalledTimes(1);
        expect(component.chart).toBeNull();
        // A second teardown must not throw on the already-released instance.
        expect(() => component.destroy()).not.toThrow();
    });
});
