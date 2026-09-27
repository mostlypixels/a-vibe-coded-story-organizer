import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { HOVER_CLOSE_DELAY, OPEN_EVENT, registerDropdown } from './dropdown';

function makeDropdown(config) {
    let factory;
    registerDropdown({ data: (_name, callback) => { factory = callback; } });

    return factory(config);
}

const mouse = { pointerType: 'mouse' };
const touch = { pointerType: 'touch' };

function clickOn(selectorMatch) {
    return { target: { closest: (selector) => (selector === selectorMatch ? {} : null) } };
}

describe('dropdown', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('announces its id when it opens, so the other dropdowns close', () => {
        const listener = vi.fn();
        window.addEventListener(OPEN_EVENT, listener);

        makeDropdown({ id: 'dropdown-a' }).show();

        window.removeEventListener(OPEN_EVENT, listener);
        expect(listener).toHaveBeenCalledTimes(1);
        expect(listener.mock.calls[0][0].detail).toBe('dropdown-a');
    });

    it('closes when another dropdown opens, but not for its own event', () => {
        const dropdown = makeDropdown({ id: 'dropdown-a' });
        dropdown.open = true;

        dropdown.otherOpened({ detail: 'dropdown-a' });
        expect(dropdown.open).toBe(true);

        dropdown.otherOpened({ detail: 'dropdown-b' });
        expect(dropdown.open).toBe(false);
    });

    it('toggles on any click in the trigger by default', () => {
        const dropdown = makeDropdown({ id: 'dropdown-a' });

        dropdown.triggerClick(clickOn('nothing'));
        expect(dropdown.open).toBe(true);
    });

    it('in hover mode, toggles only on the disclosure button', () => {
        const dropdown = makeDropdown({ id: 'dropdown-a', hover: true });

        dropdown.triggerClick(clickOn('nothing'));
        expect(dropdown.open).toBe(false);

        dropdown.triggerClick(clickOn('[aria-controls="dropdown-a"]'));
        expect(dropdown.open).toBe(true);
    });

    it('in hover mode, opens on mouse enter and closes after the delay', () => {
        const dropdown = makeDropdown({ id: 'dropdown-a', hover: true });

        dropdown.pointerEnter(mouse);
        expect(dropdown.open).toBe(true);

        dropdown.pointerLeave(mouse);
        vi.advanceTimersByTime(HOVER_CLOSE_DELAY - 1);
        expect(dropdown.open).toBe(true);

        vi.advanceTimersByTime(1);
        expect(dropdown.open).toBe(false);
    });

    it('stays open when the mouse comes back before the delay ends', () => {
        const dropdown = makeDropdown({ id: 'dropdown-a', hover: true });

        dropdown.pointerEnter(mouse);
        dropdown.pointerLeave(mouse);
        vi.advanceTimersByTime(HOVER_CLOSE_DELAY - 50);
        dropdown.pointerEnter(mouse);
        vi.advanceTimersByTime(HOVER_CLOSE_DELAY * 2);

        expect(dropdown.open).toBe(true);
    });

    it('ignores touch and pen pointers', () => {
        const dropdown = makeDropdown({ id: 'dropdown-a', hover: true });

        dropdown.pointerEnter(touch);
        expect(dropdown.open).toBe(false);

        dropdown.open = true;
        dropdown.pointerLeave(touch);
        vi.advanceTimersByTime(HOVER_CLOSE_DELAY * 2);
        expect(dropdown.open).toBe(true);
    });

    it('closes a hover-opened menu on a chevron click, as the up chevron shows', () => {
        const dropdown = makeDropdown({ id: 'dropdown-a', hover: true });

        dropdown.pointerEnter(mouse);
        dropdown.triggerClick(clickOn('[aria-controls="dropdown-a"]'));

        expect(dropdown.open).toBe(false);
    });

    it('keeps a click-opened hover menu open when the mouse leaves', () => {
        const dropdown = makeDropdown({ id: 'dropdown-a', hover: true });

        dropdown.triggerClick(clickOn('[aria-controls="dropdown-a"]'));
        dropdown.pointerLeave(mouse);
        vi.advanceTimersByTime(HOVER_CLOSE_DELAY * 2);

        expect(dropdown.open).toBe(true);
    });

    it('closes by hover again after a pinned menu closes', () => {
        const dropdown = makeDropdown({ id: 'dropdown-a', hover: true });
        const chevron = clickOn('[aria-controls="dropdown-a"]');

        dropdown.triggerClick(chevron);
        dropdown.triggerClick(chevron);
        dropdown.pointerEnter(mouse);
        dropdown.pointerLeave(mouse);
        vi.advanceTimersByTime(HOVER_CLOSE_DELAY);

        expect(dropdown.open).toBe(false);
    });

    it('ignores the pointer without hover mode', () => {
        const dropdown = makeDropdown({ id: 'dropdown-a' });

        dropdown.pointerEnter(mouse);
        expect(dropdown.open).toBe(false);
    });

    it('closes on Escape, focuses its button and stops the key', () => {
        const button = { focus: vi.fn() };
        const dropdown = makeDropdown({ id: 'dropdown-a' });
        dropdown.$root = { querySelector: (selector) => (selector === '[aria-controls="dropdown-a"]' ? button : null) };
        dropdown.open = true;
        const event = { stopPropagation: vi.fn() };

        dropdown.escape(event);

        expect(dropdown.open).toBe(false);
        expect(button.focus).toHaveBeenCalledTimes(1);
        expect(event.stopPropagation).toHaveBeenCalledTimes(1);
    });

    it('lets Escape pass when it is closed, so a surrounding dialog still closes', () => {
        const dropdown = makeDropdown({ id: 'dropdown-a' });
        const event = { stopPropagation: vi.fn() };

        dropdown.escape(event);

        expect(event.stopPropagation).not.toHaveBeenCalled();
    });
});
