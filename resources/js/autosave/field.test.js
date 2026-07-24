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
        expect(Alpine.store('autosave').compareUrls).not.toHaveProperty(field.key);
    });

    it('init() sets store.compareUrls[key] from config.compareUrl, and destroy() removes it alongside store.elements[key]', () => {
        const { field } = mountField({
            entity: 'scene',
            id: 43,
            field: 'contents',
            url: '/scenes/43',
            baseHash: 'abc',
            compareUrl: '/revisions/compare/43',
        });

        expect(Alpine.store('autosave').compareUrls[field.key]).toBe('/revisions/compare/43');

        field.destroy();

        expect(Alpine.store('autosave').compareUrls).not.toHaveProperty(field.key);
    });

    it('init() defaults store.compareUrls[key] to null when no compareUrl is configured (the revisions.compare route may not exist yet)', () => {
        const { field } = mountField({ entity: 'scene', id: 44, field: 'contents', url: '/scenes/44', baseHash: 'abc' });

        expect(Alpine.store('autosave').compareUrls[field.key]).toBeNull();
    });

    it('destroy() removes the beforeunload listener too', () => {
        const removeSpy = vi.spyOn(window, 'removeEventListener');
        const { field } = mountField({ entity: 'scene', id: 42, field: 'contents', url: '/scenes/42', baseHash: 'abc' });

        field.destroy();

        expect(removeSpy).toHaveBeenCalledWith('beforeunload', field._onBeforeUnload);
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

/**
 * Task 01 of autosave-storage-improvements: the draft mirror moves from firing on
 * every keystroke to firing once, at `beforeunload`, and is suppressed entirely when
 * the departure was an explicit "leave anyway" via data-loss-warnings' nav guard.
 * Asserts on the actual `localStorage` contents (via `readDraft`), the same
 * observable surface `writeDraft`/`readDraft` already expose to other tests in this
 * file, rather than spying on `writeDraft` — it's called directly within field.js's
 * own module scope, not through the test file's imported binding.
 */
describe('write-once-at-beforeunload (autosave-storage-improvements task 01)', () => {
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

    // Each test below mounts its own field with a distinct `id` (and therefore a
    // distinct storage key). This matters because `beforeunload` listeners are never
    // torn down here (no `field.destroy()` call, matching this describe block's focus
    // on the listener itself) — reusing an `id` across tests would let a still-live
    // listener from an earlier test's field re-write its own draft when a later
    // test's `beforeunload` dispatch fires, since `window.dispatchEvent` reaches every
    // listener still registered on `window`, not just the field under test.

    it('typing no longer writes a draft to localStorage', () => {
        const { textarea } = mountField({ entity: 'scene', id: 1, field: 'contents', url: '/scenes/1', baseHash: 'abc' });

        textarea.value = 'hello';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));

        expect(readDraft('scene:1:contents')).toBeNull();
    });

    it('beforeunload on a dirty field writes the draft once, with the current value', () => {
        const { field, textarea } = mountField({ entity: 'scene', id: 2, field: 'contents', url: '/scenes/2', baseHash: 'abc' });

        textarea.value = 'unsaved edit';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));

        window.dispatchEvent(new Event('beforeunload'));

        expect(readDraft(field.key)).toMatchObject({ value: 'unsaved edit', baseHash: 'abc' });
    });

    it('beforeunload on a clean field writes nothing', () => {
        const { field } = mountField({ entity: 'scene', id: 3, field: 'contents', url: '/scenes/3', baseHash: 'abc' });

        window.dispatchEvent(new Event('beforeunload'));

        expect(readDraft(field.key)).toBeNull();
    });

    it('an explicit-leave suppresses the beforeunload write for every field', () => {
        const { field, textarea } = mountField({ entity: 'scene', id: 4, field: 'contents', url: '/scenes/4', baseHash: 'abc' });

        textarea.value = 'unsaved edit';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));

        window.dispatchEvent(new CustomEvent('autosave:explicit-leave'));
        window.dispatchEvent(new Event('beforeunload'));

        expect(readDraft(field.key)).toBeNull();
    });
});
