import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { registerAutosaveField } from './autosave/field';
import { countWords, DEBOUNCE_MS, formatCount, registerWordCount } from './word-count';

function createAlpineStub() {
    const factories = {};

    return {
        data(name, factory) {
            factories[name] = factory;
        },
        factory(name) {
            return factories[name];
        },
    };
}

function mountWordCount(Alpine, config) {
    const root = document.createElement('div');
    const textarea = document.createElement('textarea');
    root.appendChild(textarea);
    document.body.appendChild(root);

    const component = Alpine.factory('wordCount')(config);
    component.$root = root;
    component.init();

    return { component, root, textarea };
}

describe('countWords', () => {
    it('splits on whitespace, however many spaces separate the words', () => {
        expect(countWords('one two   three')).toBe(3);
    });

    it('is zero for an empty string', () => {
        expect(countWords('')).toBe(0);
    });

    it('is zero for a whitespace-only string', () => {
        expect(countWords('   ')).toBe(0);
    });

    it('counts across newlines and tabs, not only spaces', () => {
        expect(countWords('one\ntwo\tthree')).toBe(3);
    });

    it('treats null/undefined the same as an empty string', () => {
        expect(countWords(null)).toBe(0);
        expect(countWords(undefined)).toBe(0);
    });
});

describe('formatCount', () => {
    const templates = { zero: '%d words', one: '%d word', other: '%d words' };

    it('picks the zero branch', () => {
        expect(formatCount(0, templates)).toBe('0 words');
    });

    it('picks the one branch', () => {
        expect(formatCount(1, templates)).toBe('1 word');
    });

    it('picks the other branch and groups thousands', () => {
        expect(formatCount(1234, templates)).toBe('1,234 words');
    });
});

describe('registerWordCount', () => {
    let Alpine;

    beforeEach(() => {
        Alpine = createAlpineStub();
        registerWordCount(Alpine);
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
        document.body.innerHTML = '';
    });

    it('starts from the server-computed initial count, not zero', () => {
        const { component } = mountWordCount(Alpine, { initialCount: 42 });

        expect(component.count).toBe(42);
    });

    it('recounts (debounced) when the plain textarea receives input', () => {
        vi.useFakeTimers();
        const { component, textarea } = mountWordCount(Alpine, { initialCount: 0 });

        textarea.value = 'one two three';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        vi.advanceTimersByTime(DEBOUNCE_MS);

        expect(component.count).toBe(3);
    });

    /**
     * The debounce itself, not just the eventual number: the final number is
     * identical with or without the debounce, so it proves nothing on its own.
     * Proven here by spying on the named `recount()` method (see its
     * docblock) and asserting it fires exactly once, only after the last of
     * five rapid keystrokes. Breaking the debounce (e.g. calling `recount()`
     * synchronously from `scheduleRecount()` instead of via `setTimeout`)
     * makes `recountSpy` fire 5 times, immediately — this test would fail at
     * the very first assertion.
     */
    it('debounces N rapid inputs into a single recount', () => {
        vi.useFakeTimers();
        const { component, textarea } = mountWordCount(Alpine, { initialCount: 0 });
        const recountSpy = vi.spyOn(component, 'recount');

        ['o', 'on', 'one', 'one t', 'one two'].forEach((text) => {
            textarea.value = text;
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        });

        expect(recountSpy).not.toHaveBeenCalled();

        vi.advanceTimersByTime(DEBOUNCE_MS);

        expect(recountSpy).toHaveBeenCalledTimes(1);
        expect(component.count).toBe(2); // "one two" — the *last* keystroke's text only
    });

    it('recounts (debounced) from a wysiwyg:text-changed event bubbling up from the editor', () => {
        vi.useFakeTimers();
        const { component, root } = mountWordCount(Alpine, { initialCount: 0 });
        const editorMount = document.createElement('div');
        root.appendChild(editorMount);

        editorMount.dispatchEvent(new CustomEvent('wysiwyg:text-changed', {
            detail: { text: 'alpha beta gamma delta' },
            bubbles: true,
        }));
        vi.advanceTimersByTime(DEBOUNCE_MS);

        expect(component.count).toBe(4);
    });

    /**
     * Reconciliation — the test that makes "indicative" safe. The server number
     * must win even when it disagrees with what the typed text would estimate;
     * if it only matched, this test would prove nothing. "one two three"
     * estimates to 3; the reconciled count is a deliberately different 999.
     */
    it('reconciliation replaces the displayed number, including when it disagrees with the typed estimate', () => {
        vi.useFakeTimers();
        const { component, root, textarea } = mountWordCount(Alpine, { initialCount: 0 });

        textarea.value = 'one two three';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        vi.advanceTimersByTime(DEBOUNCE_MS);
        expect(component.count).toBe(3); // the indicative estimate, on display

        root.dispatchEvent(new CustomEvent('word-count:reconcile', { detail: { wordCount: 999 } }));

        expect(component.count).toBe(999);
    });

    it('reconciliation cancels a still-pending debounced recount, so a stale estimate cannot overwrite it afterwards', () => {
        vi.useFakeTimers();
        const { component, root, textarea } = mountWordCount(Alpine, { initialCount: 0 });

        textarea.value = 'one two three';
        textarea.dispatchEvent(new Event('input', { bubbles: true })); // schedules a recount, not yet fired

        root.dispatchEvent(new CustomEvent('word-count:reconcile', { detail: { wordCount: 42 } }));
        vi.advanceTimersByTime(DEBOUNCE_MS); // would land 3 here if the pending recount survived

        expect(component.count).toBe(42);
    });

    it('destroy() removes every listener this component added', () => {
        const { component, root } = mountWordCount(Alpine, { initialCount: 0 });
        const removeSpy = vi.spyOn(root, 'removeEventListener');

        component.destroy();

        expect(removeSpy).toHaveBeenCalledWith('input', component._onInput);
        expect(removeSpy).toHaveBeenCalledWith('wysiwyg:text-changed', component._onEditorTextChanged);
        expect(removeSpy).toHaveBeenCalledWith('word-count:reconcile', component._onReconcile);
    });
});

/**
 * End-to-end with resources/js/autosave/field.js: proves the actual wiring
 * that ships (field.js's `notifyWordCount()` finding and dispatching on this
 * component's root), not just that word-count.js reacts to a hand-fired
 * event of the right shape. Mirrors autosave-field.blade.php's real DOM
 * nesting: the autosaveField root wraps the word-count root (`data-word-count`),
 * which wraps the textarea.
 */
describe('reconciliation end-to-end through a real autosave save()', () => {
    let Alpine;

    beforeEach(() => {
        // registerAutosaveField() also needs Alpine.store() (the cross-field
        // autosave store) — the plain data()/factory() stub above is enough
        // for word-count.js alone, but not for field.js too.
        const stores = {};
        Alpine = {
            ...createAlpineStub(),
            store(name, value) {
                if (value !== undefined) {
                    stores[name] = value;

                    return undefined;
                }

                return stores[name];
            },
        };
        registerAutosaveField(Alpine);
        registerWordCount(Alpine);
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
        document.body.innerHTML = '';
        delete window.axios;
        window.localStorage.clear();
    });

    it('a successful autosave snaps the counter to the response word_count, overriding the indicative estimate it disagrees with', async () => {
        vi.useFakeTimers();

        const outer = document.createElement('div');
        const counterRoot = document.createElement('div');
        counterRoot.setAttribute('data-word-count', '');
        const textarea = document.createElement('textarea');
        counterRoot.appendChild(textarea);
        outer.appendChild(counterRoot);
        document.body.appendChild(outer);

        const field = Alpine.factory('autosaveField')({
            entity: 'scene',
            id: 1,
            field: 'contents',
            url: '/scenes/1',
            baseHash: 'abc',
        });
        field.$root = outer;
        field.$el = outer;
        field.init();

        const counter = Alpine.factory('wordCount')({ initialCount: 0 });
        counter.$root = counterRoot;
        counter.init();

        window.axios = {
            patch: vi.fn().mockResolvedValue({
                status: 200,
                headers: {},
                data: { hash: 'new-hash', word_count: 42 },
            }),
        };

        textarea.value = 'one two three';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        vi.advanceTimersByTime(DEBOUNCE_MS);
        expect(counter.count).toBe(3); // the indicative estimate, displayed before the save lands

        await field.save({});

        // The server's word_count (42) disagrees with the 3-word typed
        // estimate and wins — this is what field.save() actually does in the
        // browser, not a hand-fired stand-in event.
        expect(counter.count).toBe(42);
    });
});
