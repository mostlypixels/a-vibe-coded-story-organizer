import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ACTIVE_CLASS, createScrollbarFader, HIDE_DELAY, registerScrollbarFader, scrollableAncestors } from './scrollbars';

/** jsdom does no layout, so the test sets the sizes by hand. */
function sized(element, { scrollHeight = 0, clientHeight = 0 } = {}) {
    Object.defineProperty(element, 'scrollHeight', { configurable: true, value: scrollHeight });
    Object.defineProperty(element, 'clientHeight', { configurable: true, value: clientHeight });

    return element;
}

describe('createScrollbarFader', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('shows the thumb, then hides it after the delay', () => {
        const panel = document.createElement('div');
        const fader = createScrollbarFader();

        fader.show(panel);
        expect(panel.classList.contains(ACTIVE_CLASS)).toBe(true);

        vi.advanceTimersByTime(HIDE_DELAY);
        expect(panel.classList.contains(ACTIVE_CLASS)).toBe(false);
    });

    it('starts the delay again on each new activity', () => {
        const panel = document.createElement('div');
        const fader = createScrollbarFader();

        fader.show(panel);
        vi.advanceTimersByTime(HIDE_DELAY - 100);
        fader.show(panel);
        vi.advanceTimersByTime(HIDE_DELAY - 100);

        expect(panel.classList.contains(ACTIVE_CLASS)).toBe(true);

        vi.advanceTimersByTime(100);
        expect(panel.classList.contains(ACTIVE_CLASS)).toBe(false);
    });
});

describe('scrollableAncestors', () => {
    it('returns only the elements whose content overflows', () => {
        const outer = sized(document.createElement('section'), { scrollHeight: 900, clientHeight: 400 });
        const middle = sized(document.createElement('div'), { scrollHeight: 100, clientHeight: 100 });
        const inner = sized(document.createElement('p'));
        outer.append(middle);
        middle.append(inner);

        expect(scrollableAncestors(inner)).toEqual([outer]);
    });
});

describe('registerScrollbarFader', () => {
    beforeEach(() => vi.useFakeTimers());

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('does nothing in a browser without ::-webkit-scrollbar', () => {
        vi.stubGlobal('CSS', { supports: () => false });
        const doc = { addEventListener: vi.fn() };

        registerScrollbarFader(doc);

        expect(doc.addEventListener).not.toHaveBeenCalled();
    });

    it('shows the thumb on the panel that scrolls', () => {
        vi.stubGlobal('CSS', { supports: () => true });
        const doc = document.implementation.createHTMLDocument();
        const panel = doc.createElement('div');
        doc.body.append(panel);

        registerScrollbarFader(doc);
        panel.dispatchEvent(new Event('scroll'));

        expect(panel.classList.contains(ACTIVE_CLASS)).toBe(true);
    });

    it('shows the page scrollbar when the document scrolls', () => {
        vi.stubGlobal('CSS', { supports: () => true });
        const doc = document.implementation.createHTMLDocument();

        registerScrollbarFader(doc);
        doc.dispatchEvent(new Event('scroll'));

        expect(doc.documentElement.classList.contains(ACTIVE_CLASS)).toBe(true);
    });
});
