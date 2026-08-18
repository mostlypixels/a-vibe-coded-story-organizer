import { describe, expect, it, vi } from 'vitest';
import { chartConfig, registerWordCountChart, themeColors } from './word-count-chart';

function mountComponent(config = {}, createChart) {
    const components = {};
    const Alpine = { data: (name, factory) => (components[name] = factory) };

    registerWordCountChart(Alpine, { createChart });

    const component = components.wordCountChart(config);
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

const threeDays = [
    { label: '1 Mar', written: 800 },
    { label: '2 Mar', written: 0 },
    { label: '3 Mar', written: -120 },
];

describe('chartConfig', () => {
    it('draws the days as bars', () => {
        const config = chartConfig({ days: threeDays, colors: { bar: 'blue', cut: 'red' } });

        expect(config.data.labels).toEqual(['1 Mar', '2 Mar', '3 Mar']);
        expect(config.data.datasets[0].type).toBe('bar');
        expect(config.data.datasets[0].data).toEqual([800, 0, -120]);
        // A cut day is a different event, so it is not painted as a writing day.
        expect(config.data.datasets[0].backgroundColor).toEqual(['blue', 'blue', 'red']);
    });

    it('adds a flat goal line when a daily goal is set', () => {
        const config = chartConfig({ days: threeDays, dailyGoal: 500 });

        expect(config.data.datasets).toHaveLength(2);
        expect(config.data.datasets[1].type).toBe('line');
        expect(config.data.datasets[1].data).toEqual([500, 500, 500]);
    });

    it('yields a one-dataset config when there is no daily goal', () => {
        expect(chartConfig({ days: threeDays, dailyGoal: null }).data.datasets).toHaveLength(1);
        expect(chartConfig({ days: threeDays }).data.datasets).toHaveLength(1);
    });

    it('omits axes and tooltips in the compact variant', () => {
        const compact = chartConfig({ days: threeDays, variant: 'compact' });

        expect(compact.options.scales.x.display).toBe(false);
        expect(compact.options.scales.y.display).toBe(false);
        expect(compact.options.plugins.tooltip.enabled).toBe(false);

        const full = chartConfig({ days: threeDays });

        expect(full.options.scales.x.display).toBe(true);
        expect(full.options.scales.y.display).toBe(true);
        expect(full.options.plugins.tooltip.enabled).toBe(true);
    });
});

describe('themeColors', () => {
    it('leaves a colour out when the theme defines no value for it', () => {
        // No :root block in jsdom, so every token resolves to '' — Chart.js gets
        // its own defaults rather than a config that paints with an empty string.
        expect(themeColors(document.createElement('canvas'))).toEqual({});
    });
});

describe('the Alpine component', () => {
    it('creates one chart over its canvas on init', () => {
        const { createChart, instances } = fakeChartFactory();
        const component = mountComponent({ days: threeDays, dailyGoal: 500 }, createChart);

        component.init();

        expect(createChart).toHaveBeenCalledTimes(1);
        expect(instances[0].canvas).toBe(component.$refs.canvas);
        expect(instances[0].config.data.datasets).toHaveLength(2);
    });

    it('destroys the first instance when it mounts twice over the same canvas', () => {
        const { createChart, instances } = fakeChartFactory();
        const component = mountComponent({ days: threeDays }, createChart);

        component.init();
        component.mount();

        expect(instances).toHaveLength(2);
        expect(instances[0].destroy).toHaveBeenCalledTimes(1);
        expect(instances[1].destroy).not.toHaveBeenCalled();
        expect(component.chart).toBe(instances[1]);
    });

    it('destroys the chart on teardown', () => {
        const { createChart, instances } = fakeChartFactory();
        const component = mountComponent({ days: threeDays }, createChart);

        component.init();
        component.destroy();

        expect(instances[0].destroy).toHaveBeenCalledTimes(1);
        expect(component.chart).toBeNull();
        // A second teardown must not throw on the already-released instance.
        expect(() => component.destroy()).not.toThrow();
    });
});
