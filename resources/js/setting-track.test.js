import { describe, expect, it, vi } from 'vitest';

import { registerSettingTrack, selectStep, stepAt } from './setting-track';

describe('registerSettingTrack', () => {
    it('declares its own radio list per instance', () => {
        let factory;
        registerSettingTrack({ data: (_name, callback) => { factory = callback; } });

        const one = factory();
        const two = factory();

        expect(Object.prototype.hasOwnProperty.call(one, 'radios')).toBe(true);

        one.radios = ['a'];
        expect(two.radios).toEqual([]);
    });
});

const rect = { left: 100, width: 500 };

describe('stepAt', () => {
    it('returns the step whose slice holds the pointer', () => {
        expect(stepAt(150, rect, 5)).toBe(0);
        expect(stepAt(250, rect, 5)).toBe(1);
        expect(stepAt(350, rect, 5)).toBe(2);
        expect(stepAt(450, rect, 5)).toBe(3);
        expect(stepAt(550, rect, 5)).toBe(4);
    });

    it('changes step at the boundary between two slices', () => {
        expect(stepAt(199, rect, 5)).toBe(0);
        expect(stepAt(200, rect, 5)).toBe(1);
    });

    it('clamps a pointer dragged past either end', () => {
        expect(stepAt(-9999, rect, 5)).toBe(0);
        expect(stepAt(9999, rect, 5)).toBe(4);
    });

    it('returns null for a track it cannot measure', () => {
        expect(stepAt(150, { left: 0, width: 0 }, 5)).toBeNull();
        expect(stepAt(150, undefined, 5)).toBeNull();
        expect(stepAt(150, rect, 0)).toBeNull();
    });
});

describe('selectStep', () => {
    const radios = () => [
        { checked: true, dispatchEvent: vi.fn() },
        { checked: false, dispatchEvent: vi.fn() },
    ];

    it('checks the radio and fires a bubbling change so the preview hears it', () => {
        const list = radios();

        expect(selectStep(list, 1)).toBe(true);
        expect(list[1].checked).toBe(true);
        expect(list[1].dispatchEvent).toHaveBeenCalledTimes(1);

        const event = list[1].dispatchEvent.mock.calls[0][0];
        expect(event.type).toBe('change');
        expect(event.bubbles).toBe(true);
    });

    it('does nothing when the step is already checked', () => {
        const list = radios();

        expect(selectStep(list, 0)).toBe(false);
        expect(list[0].dispatchEvent).not.toHaveBeenCalled();
    });

    it('does nothing for a step that does not exist', () => {
        const list = radios();

        expect(selectStep(list, 9)).toBe(false);
    });
});
