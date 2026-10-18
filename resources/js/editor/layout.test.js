import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { clampWidth, maxWidthFor, registerEditorLayout, widthAfterKey } from './layout.js';

const SAVE_URL = '/preferences/editor-layout';

const layout = () => ({
    left: { width: 280, collapsed: false },
    right: { width: 360, collapsed: false },
    sections: { codex: false, notes: false },
});

function stubMedia(matches) {
    window.matchMedia = vi.fn().mockReturnValue({
        matches,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
    });
}

function mount({ large = true } = {}) {
    stubMedia(large);

    let factory = null;
    registerEditorLayout({ data: (name, make) => { factory = make; } });

    const component = factory({
        layout: layout(),
        defaults: layout(),
        minWidth: 200,
        maxWidth: 640,
        saveUrl: SAVE_URL,
    });
    component.init();

    return component;
}

const key = (name) => ({ key: name, preventDefault: vi.fn() });

describe('width helpers', () => {
    it('clamps a width to the limits', () => {
        expect(clampWidth(150, 200, 640)).toBe(200);
        expect(clampWidth(900, 200, 640)).toBe(640);
        expect(clampWidth(300.6, 200, 640)).toBe(301);
    });

    it('caps the max at 40% of the window, never below the min', () => {
        expect(maxWidthFor(200, 640, 2000)).toBe(640);
        expect(maxWidthFor(200, 640, 1200)).toBe(480);
        expect(maxWidthFor(200, 640, 300)).toBe(200);
    });

    it('steps 16 px towards the prose for each arrow, and jumps with Home and End', () => {
        expect(widthAfterKey('ArrowRight', 'left', 280, 200, 640)).toBe(296);
        expect(widthAfterKey('ArrowLeft', 'left', 280, 200, 640)).toBe(264);
        expect(widthAfterKey('ArrowLeft', 'right', 360, 200, 640)).toBe(376);
        expect(widthAfterKey('ArrowRight', 'right', 360, 200, 640)).toBe(344);
        expect(widthAfterKey('ArrowLeft', 'left', 205, 200, 640)).toBe(200);
        expect(widthAfterKey('Home', 'left', 280, 200, 640)).toBe(200);
        expect(widthAfterKey('End', 'left', 280, 200, 640)).toBe(640);
        expect(widthAfterKey('a', 'left', 280, 200, 640)).toBeNull();
    });
});

describe('editorLayout', () => {
    let component;

    beforeEach(() => {
        vi.useFakeTimers();
        window.innerWidth = 2000;
        window.axios = { patch: vi.fn().mockResolvedValue({ status: 204 }) };
    });

    afterEach(() => {
        component?.destroy();
        vi.useRealTimers();
        delete window.axios;
    });

    it('clamps a drag to the min and the max', () => {
        component = mount();
        const handle = { setPointerCapture: vi.fn(), releasePointerCapture: vi.fn() };

        component.startDrag('left', { button: 0, clientX: 280, pointerId: 1, currentTarget: handle, preventDefault: vi.fn() });
        expect(handle.setPointerCapture).toHaveBeenCalledWith(1);

        component.moveDrag({ clientX: 10 });
        expect(component.left.width).toBe(200);

        component.moveDrag({ clientX: 5000 });
        expect(component.left.width).toBe(640);

        component.endDrag({ pointerId: 1, currentTarget: handle });
        expect(component.drag).toBeNull();
    });

    it('widens the right panel when the pointer moves left', () => {
        component = mount();

        component.startDrag('right', { button: 0, clientX: 1000, pointerId: 1, currentTarget: {}, preventDefault: vi.fn() });
        component.moveDrag({ clientX: 960 });

        expect(component.right.width).toBe(400);
    });

    it('resizes with the keys, and Enter toggles the collapse', () => {
        component = mount();

        const right = key('ArrowRight');
        component.onHandleKey('left', right);
        expect(right.preventDefault).toHaveBeenCalled();
        expect(component.left.width).toBe(296);

        component.onHandleKey('left', key('Home'));
        expect(component.left.width).toBe(200);

        component.onHandleKey('left', key('End'));
        expect(component.left.width).toBe(640);

        component.onHandleKey('left', key('Enter'));
        expect(component.left.collapsed).toBe(true);
        expect(component.isOpen('left')).toBe(false);
    });

    it('resets to the default width', () => {
        component = mount();
        component.setWidth('right', 500);

        component.resetWidth('right');

        expect(component.right.width).toBe(360);
    });

    it('toggles the collapse of a panel and keeps its width', () => {
        component = mount();

        component.toggle('right');
        expect(component.right.collapsed).toBe(true);
        expect(component.columns()).toBe('--editor-left: 280px; --editor-right: 2.5rem;');

        component.toggle('right');
        expect(component.right.collapsed).toBe(false);
        expect(component.columns()).toBe('--editor-left: 280px; --editor-right: 360px;');
    });

    it('records a section toggle and ignores the toggle event of the load', () => {
        component = mount();

        // Browsers send `toggle` at load for a `<details open>`.
        component.setSection('codex', true);
        vi.advanceTimersByTime(1000);
        expect(window.axios.patch).not.toHaveBeenCalled();

        component.setSection('notes', false);
        expect(component.sections.notes).toBe(true);
    });

    it('sends one PATCH with the full shape 500 ms after many changes', () => {
        component = mount();

        component.onHandleKey('left', key('ArrowRight'));
        component.onHandleKey('left', key('ArrowRight'));
        component.toggle('right');
        component.setSection('notes', false);

        vi.advanceTimersByTime(499);
        expect(window.axios.patch).not.toHaveBeenCalled();

        vi.advanceTimersByTime(1);
        expect(window.axios.patch).toHaveBeenCalledTimes(1);
        expect(window.axios.patch).toHaveBeenCalledWith(SAVE_URL, {
            left: { width: 312, collapsed: false },
            right: { width: 360, collapsed: true },
            sections: { codex: false, notes: true },
        });
    });

    it('keeps the change on screen when the save fails, and does not retry', async () => {
        window.axios.patch.mockRejectedValue(new Error('offline'));
        component = mount();

        component.toggle('left');
        await vi.advanceTimersByTimeAsync(5000);

        expect(window.axios.patch).toHaveBeenCalledTimes(1);
        expect(component.left.collapsed).toBe(true);
    });

    it('below lg, starts both panels closed, opens one drawer at a time and saves nothing', async () => {
        component = mount({ large: false });

        expect(component.isOpen('left')).toBe(false);
        expect(component.isOpen('right')).toBe(false);

        component.toggle('left');
        expect(component.isOpen('left')).toBe(true);

        component.toggle('right');
        expect(component.isOpen('left')).toBe(false);
        expect(component.isOpen('right')).toBe(true);
        // The stored state does not change.
        expect(component.right.collapsed).toBe(false);

        component.setSection('codex', false);
        await vi.advanceTimersByTimeAsync(5000);
        expect(window.axios.patch).not.toHaveBeenCalled();
    });

    it('closes the drawer with Escape and reports when nothing was open', () => {
        component = mount({ large: false });

        expect(component.closeDrawer()).toBe(false);

        component.toggle('right');
        expect(component.closeDrawer()).toBe(true);
        expect(component.drawer).toBeNull();
    });
});
