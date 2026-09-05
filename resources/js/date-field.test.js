import { describe, expect, it } from 'vitest';
import { clampToWindow, composeValue, daysInMonth, digitsOnly, parseValue, registerDateField, stepMinute } from './date-field';

describe('daysInMonth', () => {
    it('knows February in a leap year', () => {
        expect(daysInMonth(2024, 2)).toBe(29);
    });

    it('knows February in a normal year', () => {
        expect(daysInMonth(2023, 2)).toBe(28);
    });

    it('follows the century rule', () => {
        expect(daysInMonth(1900, 2)).toBe(28);
        expect(daysInMonth(2000, 2)).toBe(29);
    });

    it('knows a 30-day month', () => {
        expect(daysInMonth(2024, 4)).toBe(30);
    });
});

describe('parseValue', () => {
    it('splits a stored value into segments', () => {
        expect(parseValue('1247-03-15T14:30')).toEqual({
            year: '1247',
            month: '3',
            day: '15',
            hour: '14',
            minute: '30',
        });
    });

    it('gives empty segments for an empty or malformed value', () => {
        expect(parseValue('')).toEqual({ year: '', month: '', day: '', hour: '', minute: '' });
        expect(parseValue('not a date')).toEqual({ year: '', month: '', day: '', hour: '', minute: '' });
    });
});

describe('composeValue', () => {
    it('pads every segment', () => {
        expect(composeValue({ year: '847', month: '3', day: '5', hour: '9', minute: '7' })).toBe('0847-03-05T09:07');
    });

    it('defaults a missing time to midnight', () => {
        expect(composeValue({ year: '1247', month: '3', day: '15' })).toBe('1247-03-15T00:00');
    });

    it('returns empty while the date is incomplete', () => {
        expect(composeValue({ year: '1247', month: '', day: '15' })).toBe('');
    });

    it('clamps the day to the month it belongs to', () => {
        expect(composeValue({ year: '2023', month: '2', day: '31' })).toBe('2023-02-28T00:00');
        expect(composeValue({ year: '2024', month: '2', day: '31' })).toBe('2024-02-29T00:00');
    });
});

describe('digitsOnly', () => {
    it('drops non-digits', () => {
        expect(digitsOnly('1a2b3')).toBe('123');
    });

    it('keeps a leading minus for a year only when allowed', () => {
        expect(digitsOnly('-42', true)).toBe('-42');
        expect(digitsOnly('-42')).toBe('42');
    });
});

describe('clampToWindow', () => {
    it('pulls a value back inside the bookends', () => {
        expect(clampToWindow('2021-01-01T00:00', '2000-01-01T00:00', '2020-01-01T00:00')).toBe('2019-12-31T23:59');
        expect(clampToWindow('1990-01-01T00:00', '2000-01-01T00:00', '2020-01-01T00:00')).toBe('2000-01-01T00:01');
    });

    it('leaves a value inside the window and an empty value alone', () => {
        expect(clampToWindow('2010-01-01T00:00', '2000-01-01T00:00', '2020-01-01T00:00')).toBe('2010-01-01T00:00');
        expect(clampToWindow('', '2000-01-01T00:00', '')).toBe('');
    });
});

describe('clamp notice', () => {
    /** Build the Alpine component object without a real Alpine. */
    function field(config) {
        let factory;

        registerDateField({ data: (_name, callback) => (factory = callback) });

        const component = factory(config);

        component.init();

        return component;
    }

    const window = { min: '1000-01-01T00:00', max: '2000-01-01T00:00' };

    it('names the bookend the date was moved onto', () => {
        const component = field({ value: '1500-01-01T00:00', ...window });

        component.year = '3000';
        component.sync();

        expect(component.clampedTo).toBe('max');
        expect(component.value).toBe('1999-12-31T23:59');

        component.year = '0500';
        component.sync();

        expect(component.clampedTo).toBe('min');
    });

    it('stays quiet on a date inside the window', () => {
        const component = field({ value: '1500-01-01T00:00', ...window });

        component.year = '1600';
        component.sync();

        expect(component.clampedTo).toBe('');
    });

    it('waits for a whole year before it complains', () => {
        const component = field({ value: '1500-01-01T00:00', ...window });

        component.year = '3';
        component.sync();

        expect(component.clampedTo).toBe('');
    });
});

describe('the window edges', () => {
    const min = '1000-01-01T00:00';
    const max = '3000-01-01T00:00';

    it('keeps an event off the bookend instant, so the list order holds', () => {
        expect(clampToWindow(max, min, max)).toBe('2999-12-31T23:59');
        expect(clampToWindow(min, min, max)).toBe('1000-01-01T00:01');
    });
});

describe('stepMinute', () => {
    it('carries the hour, the day, the month and the year', () => {
        expect(stepMinute('1200-03-01T00:00', -1)).toBe('1200-02-29T23:59');
        expect(stepMinute('1201-01-01T00:00', -1)).toBe('1200-12-31T23:59');
        expect(stepMinute('1200-12-31T23:59', 1)).toBe('1201-01-01T00:00');
        expect(stepMinute('0001-01-01T00:00', 1)).toBe('0001-01-01T00:01');
    });

    it('leaves an unparseable value alone', () => {
        expect(stepMinute('', 1)).toBe('');
    });
});
