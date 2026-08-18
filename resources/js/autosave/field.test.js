import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { DEBOUNCE_MS, fieldKeyFor, registerAutosaveField, shouldAutosave } from './field';

function createAlpineStub() {
    const stores = {};
    const factories = {};

    return {
        store(name, value) {
            if (value !== undefined) {
                stores[name] = value;

                return undefined;
            }

            return stores[name];
        },
        data(name, factory) {
            factories[name] = factory;
        },
        factory(name) {
            return factories[name];
        },
    };
}

describe('fieldKeyFor', () => {
    it('keys a field as entity:id:field', () => {
        expect(fieldKeyFor({ entity: 'scene', id: 42, field: 'contents' })).toBe('scene:42:contents');
    });

    it('never collides two fields of the same entity', () => {
        const contents = fieldKeyFor({ entity: 'scene', id: 42, field: 'contents' });
        const summary = fieldKeyFor({ entity: 'scene', id: 42, field: 'summary' });

        expect(contents).not.toBe(summary);
    });
});

describe('shouldAutosave', () => {
    it('is false until the field has actually been edited', () => {
        expect(shouldAutosave(false, 42)).toBe(false);
    });

    it('is false on a create form even after an edit, since there is no id to PATCH', () => {
        expect(shouldAutosave(true, null)).toBe(false);
        expect(shouldAutosave(true, undefined)).toBe(false);
    });

    it('is true only once the field is dirty and belongs to an existing entity', () => {
        expect(shouldAutosave(true, 42)).toBe(true);
    });
});

describe('registerAutosaveField store dirty tracking', () => {
    let Alpine;

    beforeEach(() => {
        Alpine = createAlpineStub();
        registerAutosaveField(Alpine);
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
        window.localStorage.clear();
        delete window.axios;
    });

    /** Mounts an `autosaveField` instance on a real `<div><textarea /></div>`, mirroring
     *  the wrapper/inner-textarea shape `fieldValue()`'s `querySelector('textarea')`
     *  assumes (see field.js's docblock). */
    function mountField(config) {
        const root = document.createElement('div');
        const textarea = document.createElement('textarea');
        root.appendChild(textarea);
        document.body.appendChild(root);

        const field = Alpine.factory('autosaveField')(config);
        field.$root = root;
        field.$el = root;
        field.init();

        return { field, textarea };
    }

    it('isDirty() returns false and does not throw before any field has registered', () => {
        expect(Alpine.store('autosave').isDirty()).toBe(false);
    });

    it('typing in a field sets store.dirty[key] to true before the debounce timer fires', () => {
        vi.useFakeTimers();

        const { field, textarea } = mountField({ entity: 'scene', id: 42, field: 'contents', url: '/scenes/42', baseHash: 'abc' });

        textarea.value = 'hello';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));

        // The debounce timer was scheduled but not yet advanced — dirty is set
        // synchronously by onInput(), well before any PATCH fires.
        expect(Alpine.store('autosave').dirty[field.key]).toBe(true);
        expect(Alpine.store('autosave').isDirty()).toBe(true);
    });

    /**
     * The editor half of the dirty gate. A wysiwyg field fires no `input` event of its
     * own: `syncTextarea()` assigns `textarea.value`, and ProseMirror applies Delete,
     * the toolbar and undo as transactions. `wysiwyg:text-changed` is the only signal
     * that reaches this component, so an edit made that way must autosave too.
     */
    function mountEditorField(config) {
        const { field, textarea } = mountField(config);
        const editor = document.createElement('div');
        textarea.parentNode.appendChild(editor);

        /** What wysiwyg.js's `onUpdate` does: assign the value, then announce it. */
        const edit = (value) => {
            textarea.value = value;
            editor.dispatchEvent(new CustomEvent('wysiwyg:text-changed', { detail: { text: value }, bubbles: true }));
        };

        return { field, textarea, edit };
    }

    it('an edit made in the editor marks the field dirty, though it fires no input event', () => {
        vi.useFakeTimers();

        const { field, edit } = mountEditorField({ entity: 'scene', id: 42, field: 'notes', url: '/scenes/42', baseHash: 'abc' });

        edit('<p>written in the editor</p>');

        expect(Alpine.store('autosave').dirty[field.key]).toBe(true);
        expect(Alpine.store('autosave').isDirty()).toBe(true);
    });

    it('clearing the editor autosaves the empty value, rather than leaving the old text stored', async () => {
        vi.useFakeTimers();
        window.axios = {
            patch: vi.fn().mockResolvedValue({ status: 200, headers: {}, data: { hash: 'new-hash' } }),
        };

        const { edit } = mountEditorField({ entity: 'scene', id: 42, field: 'notes', url: '/scenes/42', baseHash: 'abc' });

        edit('<p>to be deleted</p>');
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);

        // Select-all then Delete. `syncTextarea()` maps an empty document to '', so
        // this is what the writer expects the server to store.
        edit('');
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);

        expect(window.axios.patch).toHaveBeenCalledTimes(2);
        expect(window.axios.patch.mock.calls[1][1]).toMatchObject({ value: '' });
    });

    it('destroy() stops the editor event autosaving into a torn-down field', () => {
        vi.useFakeTimers();
        window.axios = { patch: vi.fn() };

        const { field, edit } = mountEditorField({ entity: 'scene', id: 42, field: 'notes', url: '/scenes/42', baseHash: 'abc' });

        field.destroy();
        edit('<p>after teardown</p>');
        vi.advanceTimersByTime(DEBOUNCE_MS);

        expect(window.axios.patch).not.toHaveBeenCalled();
    });

    it('a successful save clears store.dirty[key] back to false', async () => {
        window.axios = {
            patch: vi.fn().mockResolvedValue({ status: 200, headers: {}, data: { hash: 'new-hash' } }),
        };

        const { field, textarea } = mountField({ entity: 'scene', id: 42, field: 'contents', url: '/scenes/42', baseHash: 'abc' });

        textarea.value = 'hello';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        expect(Alpine.store('autosave').dirty[field.key]).toBe(true);

        await field.save({});

        expect(Alpine.store('autosave').dirty[field.key]).toBe(false);
        expect(Alpine.store('autosave').isDirty()).toBe(false);
    });

    it('typing while a save is in flight leaves the field dirty when the response lands', async () => {
        vi.useFakeTimers();

        let respond;
        window.axios = {
            patch: vi.fn(() => new Promise((resolve) => {
                respond = () => resolve({ status: 200, headers: {}, data: { hash: 'new-hash' } });
            })),
        };

        const { field, textarea } = mountField({ entity: 'scene', id: 42, field: 'contents', url: '/scenes/42', baseHash: 'abc' });

        textarea.value = 'first';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));

        // The PATCH is in flight, describing 'first'.
        const inFlight = field.save({});

        // The writer keeps typing before the response lands.
        textarea.value = 'first and second';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));

        respond();
        await inFlight;

        // The save succeeded — but for text the field no longer holds, so the
        // page still has unsaved changes and the leave-page warning must fire.
        expect(field.dirty).toBe(true);
        expect(Alpine.store('autosave').dirty[field.key]).toBe(true);
        expect(Alpine.store('autosave').isDirty()).toBe(true);

        // The hash still advances, or the pending save would 409 against a value
        // the server has already stored.
        expect(field.baseHash).toBe('new-hash');
    });

    it('a save whose text is unchanged on arrival still clears dirty', async () => {
        window.axios = {
            patch: vi.fn().mockResolvedValue({ status: 200, headers: {}, data: { hash: 'new-hash' } }),
        };

        const { field, textarea } = mountField({ entity: 'scene', id: 42, field: 'contents', url: '/scenes/42', baseHash: 'abc' });

        textarea.value = 'hello';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));

        await field.save({});

        expect(field.dirty).toBe(false);
        expect(Alpine.store('autosave').isDirty()).toBe(false);
    });

    /**
     * `notifyWordCount()` is the field's half of the
     * counter-reconciliation channel — resources/js/word-count.js only ever
     * trusts this dispatch for the authoritative number. Covered here in
     * isolation (a hand-added `[data-word-count]` element, not the real
     * component) so this contract stays pinned independently of
     * word-count.js's own tests.
     */
    it('a successful save dispatches word-count:reconcile on this field\'s [data-word-count] element, carrying the response word_count', async () => {
        window.axios = {
            patch: vi.fn().mockResolvedValue({ status: 200, headers: {}, data: { hash: 'new-hash', word_count: 7 } }),
        };

        const { field, textarea } = mountField({ entity: 'scene', id: 42, field: 'contents', url: '/scenes/42', baseHash: 'abc' });
        const counter = document.createElement('div');
        counter.setAttribute('data-word-count', '');
        field.$root.appendChild(counter);
        const handler = vi.fn();
        counter.addEventListener('word-count:reconcile', handler);

        textarea.value = 'hello';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));

        await field.save({});

        expect(handler).toHaveBeenCalledTimes(1);
        expect(handler.mock.calls[0][0].detail.wordCount).toBe(7);
    });

    it('a save on a field with no [data-word-count] element (e.g. this test file\'s other mounts) never throws', async () => {
        window.axios = {
            patch: vi.fn().mockResolvedValue({ status: 200, headers: {}, data: { hash: 'new-hash', word_count: 7 } }),
        };

        const { field, textarea } = mountField({ entity: 'scene', id: 43, field: 'contents', url: '/scenes/43', baseHash: 'abc' });

        textarea.value = 'hello';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));

        await expect(field.save({})).resolves.toBeUndefined();
    });

    it('destroy() removes the key from store.dirty entirely, mirroring fields/elements', () => {
        const { field, textarea } = mountField({ entity: 'scene', id: 42, field: 'contents', url: '/scenes/42', baseHash: 'abc' });

        textarea.value = 'hello';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        expect(Alpine.store('autosave').dirty).toHaveProperty(field.key);

        field.destroy();

        expect(Alpine.store('autosave').dirty).not.toHaveProperty(field.key);
        expect(Alpine.store('autosave').fields).not.toHaveProperty(field.key);
        expect(Alpine.store('autosave').elements).not.toHaveProperty(field.key);
    });

    it('isDirty() is true when any registered field is dirty and false once none are', async () => {
        window.axios = {
            patch: vi.fn().mockResolvedValue({ status: 200, headers: {}, data: { hash: 'new-hash' } }),
        };

        const first = mountField({ entity: 'scene', id: 1, field: 'contents', url: '/scenes/1', baseHash: 'a' });
        const second = mountField({ entity: 'scene', id: 2, field: 'contents', url: '/scenes/2', baseHash: 'b' });

        expect(Alpine.store('autosave').isDirty()).toBe(false);

        first.textarea.value = 'hello';
        first.textarea.dispatchEvent(new Event('input', { bubbles: true }));
        expect(Alpine.store('autosave').isDirty()).toBe(true);

        second.textarea.value = 'world';
        second.textarea.dispatchEvent(new Event('input', { bubbles: true }));
        expect(Alpine.store('autosave').isDirty()).toBe(true);

        await first.field.save({});
        expect(Alpine.store('autosave').isDirty()).toBe(true); // second field is still dirty

        await second.field.save({});
        expect(Alpine.store('autosave').isDirty()).toBe(false);
    });
});

/** Confirm that autosave does not write a local draft. */
describe('no localStorage writes', () => {
    let Alpine;

    beforeEach(() => {
        Alpine = createAlpineStub();
        registerAutosaveField(Alpine);
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
        window.localStorage.clear();
        delete window.axios;
    });

    function mountField(config) {
        const root = document.createElement('div');
        const textarea = document.createElement('textarea');
        root.appendChild(textarea);
        document.body.appendChild(root);

        const field = Alpine.factory('autosaveField')(config);
        field.$root = root;
        field.$el = root;
        field.init();

        return { field, textarea };
    }

    it('leaves localStorage empty when a dirty field receives beforeunload', () => {
        const { textarea } = mountField({ entity: 'scene', id: 1, field: 'contents', url: '/scenes/1', baseHash: 'abc' });

        textarea.value = 'unsaved edit';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));

        window.dispatchEvent(new Event('beforeunload'));

        expect(window.localStorage.length).toBe(0);
    });

    it('leaves localStorage empty when a dirty field saves successfully', async () => {
        window.axios = {
            patch: vi.fn().mockResolvedValue({ status: 200, headers: {}, data: { hash: 'new-hash' } }),
        };

        const { field, textarea } = mountField({ entity: 'scene', id: 2, field: 'contents', url: '/scenes/2', baseHash: 'abc' });

        textarea.value = 'hello';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));

        await field.save({});

        expect(field.dirty).toBe(false);
        expect(window.localStorage.length).toBe(0);
    });
});
