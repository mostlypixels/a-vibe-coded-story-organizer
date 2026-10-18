import { afterEach, describe, expect, it } from 'vitest';
import { goalPercent, liveToday, registerEditorToday } from './today.js';

const templates = { zero: '%d words', one: '%d word', other: '%d words' };

function mountToday(config) {
    let factory = null;
    registerEditorToday({ data: (name, make) => { factory = make; } });

    const component = factory(config);
    component.init();

    return component;
}

function reconcile(wordCount) {
    // The autosave field sends it from the counter. It bubbles to the window.
    const counter = document.createElement('div');
    document.body.appendChild(counter);
    counter.dispatchEvent(new CustomEvent('word-count:reconcile', { detail: { wordCount }, bubbles: true }));
}

describe('liveToday', () => {
    it('adds the change of the scene count to the figure at load', () => {
        expect(liveToday(300, 1000, 1150)).toBe(450);
    });

    it('goes down when the writer cuts text', () => {
        expect(liveToday(300, 1000, 900)).toBe(200);
    });
});

describe('goalPercent', () => {
    it('rounds and stays between 0 and 100', () => {
        expect(goalPercent(250, 500)).toBe(50);
        expect(goalPercent(900, 500)).toBe(100);
        expect(goalPercent(-20, 500)).toBe(0);
        expect(goalPercent(10, null)).toBe(0);
    });
});

describe('editorToday', () => {
    let component;

    afterEach(() => {
        component?.destroy();
        document.body.innerHTML = '';
    });

    it('shows the load value plus the delta of each autosave', () => {
        component = mountToday({ today: 300, sceneCount: 1000, goal: 500, templates });
        expect(component.writtenText()).toBe('300 words');

        reconcile(1100);
        expect(component.written).toBe(400);
        expect(component.writtenText()).toBe('400 words');
        expect(component.percent()).toBe(80);

        // Each save carries the full scene count, so deltas do not add up twice.
        reconcile(1150);
        expect(component.written).toBe(450);
    });

    it('stops listening after destroy', () => {
        component = mountToday({ today: 10, sceneCount: 10, templates });
        component.destroy();

        reconcile(50);
        expect(component.written).toBe(10);
        component = null;
    });
});
