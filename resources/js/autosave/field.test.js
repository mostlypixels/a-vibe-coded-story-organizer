import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { clearDraft, readDraft, registerAutosaveField, shouldAutosave, storageKeyFor, writeDraft } from './field';

/**
 * Minimal Alpine stand-in for `registerAutosaveField()`'s `store()`/`data()` calls —
 * just enough of Alpine's public surface for the plain-object component methods to be
 * invoked directly in a test, without pulling in the real Alpine runtime (no
 * reactivity/DOM-diffing needed for these assertions; see badge.js/badge.test.js for
 * this codebase's precedent of testing the DOM-free half of an Alpine adapter).
 */
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

/**
 * Covers task 08's DOM-free logic: the localStorage key-building for both the
 * existing-entity and `new:` create-form shapes, the dirty-only gating function,
 * and the localStorage draft mirror itself. Everything requiring a real Alpine
 * mount (debounce timers wired to DOM events, the axios round-trip) is left to
 * the manual checklist per handoff.md §9.12 / task 08's own scope, matching
 * wysiwyg.test.js's precedent of only unit-testing the DOM-free logic.
 */
describe('storageKeyFor', () => {
    it('keys an existing entity as entity:id:field', () => {
        expect(storageKeyFor({ entity: 'scene', id: 42, field: 'contents' })).toBe('scene:42:contents');
    });

    it('keys a create form as new:entity:parentId:field, with no id required', () => {
        expect(storageKeyFor({ entity: 'scene', id: null, parentId: 7, field: 'contents' })).toBe(
            'new:scene:7:contents',
        );
    });

    it('never collides two create forms for different parents', () => {
        const first = storageKeyFor({ entity: 'scene', id: undefined, parentId: 1, field: 'contents' });
        const second = storageKeyFor({ entity: 'scene', id: undefined, parentId: 2, field: 'contents' });

        expect(first).not.toBe(second);
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

describe('draft mirror (readDraft/writeDraft/clearDraft)', () => {
    afterEach(() => {
        window.localStorage.clear();
    });

    it('round-trips a draft written to localStorage', () => {
        writeDraft('scene:1:contents', { value: 'Hello', baseHash: 'abc', savedAt: 123 });

        expect(readDraft('scene:1:contents')).toEqual({ value: 'Hello', baseHash: 'abc', savedAt: 123 });
    });

    it('returns null for a key that was never written', () => {
        expect(readDraft('scene:404:contents')).toBeNull();
    });

    it('returns null instead of throwing on corrupt JSON', () => {
        window.localStorage.setItem('scene:1:contents', '{not json');

        expect(readDraft('scene:1:contents')).toBeNull();
    });

    it('clears a draft', () => {
        writeDraft('scene:1:contents', { value: 'Hello', baseHash: 'abc', savedAt: 123 });
        clearDraft('scene:1:contents');

        expect(readDraft('scene:1:contents')).toBeNull();
    });

    // The quota-exceeded eviction path (handoff.md §9.7: "evict oldest-first on
    // QuotaExceededError") is exercised by the manual checklist (testing.md), not
    // here: jsdom's Storage implementation doesn't allow reliably stubbing
    // setItem() to simulate QuotaExceededError from a unit test, so faking it
    // would test the mock, not the browser behavior it's standing in for.
});

/**
 * Covers task 01 of the data-loss-warnings plan: the store-wide `dirty` map and
 * `isDirty()` alongside the existing per-field `state` machine. Mounts
 * `registerAutosaveField()`'s `autosaveField` component directly against a real
 * (jsdom) DOM node and a stub Alpine, bypassing the real Alpine runtime entirely —
 * matching this file's existing convention (see the top-of-file docblock) of
 * unit-testing the DOM-free/logic half of the adapter.
 */
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
