import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { SAVE_DELAY_MS, clampSelection, createPositionTracker, readPosition, writePosition } from './position.js';

const KEY = 'editor-position:7';

function makeEditor(content = '<p>Hello world</p><p>Second paragraph</p>') {
    const element = document.createElement('div');
    document.body.appendChild(element);

    return new Editor({ element, extensions: [StarterKit], content });
}

function makeScroller() {
    const scroller = document.createElement('section');
    document.body.appendChild(scroller);

    return scroller;
}

function throwingStorage() {
    return {
        getItem: () => { throw new Error('blocked'); },
        setItem: () => { throw new Error('blocked'); },
    };
}

describe('clampSelection', () => {
    it('keeps a position inside the text', () => {
        expect(clampSelection({ from: 3, to: 5 }, 20)).toEqual({ from: 3, to: 5 });
    });

    it('clamps a position past the end to the last text position', () => {
        expect(clampSelection({ from: 400, to: 500 }, 20)).toEqual({ from: 19, to: 19 });
    });

    it('clamps below the start and orders a reversed range', () => {
        expect(clampSelection({ from: 8, to: -4 }, 20)).toEqual({ from: 1, to: 8 });
    });
});

describe('readPosition and writePosition', () => {
    beforeEach(() => localStorage.clear());

    it('round-trips a position', () => {
        writePosition(localStorage, KEY, { scrollTop: 120, from: 4, to: 9 });

        expect(readPosition(localStorage, KEY)).toEqual({ scrollTop: 120, from: 4, to: 9 });
    });

    it('treats a broken value as no value', () => {
        localStorage.setItem(KEY, '{not json');
        expect(readPosition(localStorage, KEY)).toBeNull();

        localStorage.setItem(KEY, JSON.stringify({ scrollTop: 'a', from: 1, to: 1 }));
        expect(readPosition(localStorage, KEY)).toBeNull();
    });

    it('does not throw when storage throws or is absent', () => {
        expect(readPosition(throwingStorage(), KEY)).toBeNull();
        expect(() => writePosition(throwingStorage(), KEY, { scrollTop: 0, from: 1, to: 1 })).not.toThrow();
        expect(readPosition(null, KEY)).toBeNull();
    });
});

describe('createPositionTracker', () => {
    let editor;

    beforeEach(() => {
        localStorage.clear();
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
        editor?.destroy();
        document.body.innerHTML = '';
    });

    it('saves the cursor and the scroll position after a change', () => {
        editor = makeEditor();
        const scroller = makeScroller();
        const tracker = createPositionTracker({ key: KEY, scroller, storage: localStorage });
        tracker.attach(editor);

        editor.commands.setTextSelection({ from: 3, to: 6 });
        scroller.scrollTop = 80;
        scroller.dispatchEvent(new Event('scroll'));

        expect(localStorage.getItem(KEY)).toBeNull();
        vi.advanceTimersByTime(SAVE_DELAY_MS);

        expect(readPosition(localStorage, KEY)).toEqual({ scrollTop: 80, from: 3, to: 6 });
        tracker.destroy();
    });

    it('restores the saved cursor and scroll position on attach', () => {
        writePosition(localStorage, KEY, { scrollTop: 150, from: 4, to: 8 });
        editor = makeEditor();
        const scroller = makeScroller();
        const tracker = createPositionTracker({ key: KEY, scroller, storage: localStorage });

        expect(tracker.saved).toEqual({ scrollTop: 150, from: 4, to: 8 });
        tracker.attach(editor);

        expect(editor.state.selection.from).toBe(4);
        expect(editor.state.selection.to).toBe(8);
        expect(scroller.scrollTop).toBe(150);
        tracker.destroy();
    });

    it('clamps a saved cursor past the end of shorter text', () => {
        writePosition(localStorage, KEY, { scrollTop: 0, from: 900, to: 950 });
        editor = makeEditor('<p>Short</p>');
        const tracker = createPositionTracker({ key: KEY, storage: localStorage });
        tracker.attach(editor);

        const end = editor.state.doc.content.size - 1;
        expect(editor.state.selection.from).toBe(end);
        expect(editor.state.selection.to).toBe(end);
        tracker.destroy();
    });

    it('flushes a pending save on destroy', () => {
        editor = makeEditor();
        const tracker = createPositionTracker({ key: KEY, storage: localStorage });
        tracker.attach(editor);

        editor.commands.setTextSelection(5);
        tracker.destroy();

        expect(readPosition(localStorage, KEY)).toEqual({ scrollTop: 0, from: 5, to: 5 });
    });

    it('works with storage that throws', () => {
        editor = makeEditor();
        const tracker = createPositionTracker({ key: KEY, scroller: makeScroller(), storage: throwingStorage() });

        expect(tracker.saved).toBeNull();
        expect(() => {
            tracker.attach(editor);
            editor.commands.setTextSelection(5);
            vi.advanceTimersByTime(SAVE_DELAY_MS);
            tracker.destroy();
        }).not.toThrow();
    });
});
